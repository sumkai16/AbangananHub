<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMoveInDeadlines extends Command
{
    protected $signature = 'reservations:process-move-in-deadlines';

    protected $description = 'Backfill turnover deadlines, send move-in reminders, and process expired escrow clocks';

    public function handle(): int
    {
        $this->backfillTurnoverDeadlines();
        $this->sendConfirmationReminders();
        $this->escalateOverdueTurnovers();
        $this->releaseExpiredConfirmations();

        return self::SUCCESS;
    }

    /**
     * Clock 1 starts when the deposit is held, but nothing in the payment path
     * writes the deadline — deriving it here keeps the PayMongo webhook out of
     * this feature entirely.
     */
    protected function backfillTurnoverDeadlines(): void
    {
        $candidateIds = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNull('move_in_deadline_at')
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereHas('payments', fn ($q) => $q->where('status', 'Held'))
            ->pluck('reservation_id');

        $count = 0;
        $failures = 0;

        foreach ($candidateIds as $id) {
            try {
                $backfilled = DB::transaction(function () use ($id) {
                    $locked = Reservation::whereKey($id)->lockForUpdate()->first();

                    // Re-check every condition the outer query filtered on: a
                    // landlord's markKeysTurnedOver() may have started Clock 2
                    // between the SELECT above and this lock, and writing the
                    // stale Clock 1 value here would clobber it.
                    if (! $locked
                        || $locked->rental_status !== 'Rental Agreement Signed'
                        || $locked->move_in_deadline_at !== null
                        || $locked->keys_turned_over_at !== null
                        || $locked->move_in_disputed_at !== null
                    ) {
                        return false;
                    }

                    $heldPayment = $locked->payments()->where('status', 'Held')->first();

                    if (! $heldPayment) {
                        return false;
                    }

                    $deadline = $locked->computeTurnoverDeadline();

                    if (! $deadline) {
                        return false;
                    }

                    $locked->update(['move_in_deadline_at' => $deadline]);

                    return true;
                });

                if ($backfilled) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::error("ProcessMoveInDeadlines: failed to backfill turnover deadline for reservation {$id}", ['exception' => $e]);
            }
        }

        $this->info("Backfilled {$count} turnover deadline(s), {$failures} failure(s).");
    }

    /**
     * Clock 2 reminders. Guarded by move_in_last_reminder_on so a same-day
     * re-run cannot re-notify — days-remaining alone is not idempotent.
     */
    protected function sendConfirmationReminders(): void
    {
        $thresholds = config('rentals.reminder_days_remaining');

        $reservations = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNotNull('keys_turned_over_at')
            ->whereNotNull('move_in_deadline_at')
            ->whereNull('move_in_disputed_at')
            ->where(function ($q) {
                $q->whereNull('move_in_last_reminder_on')
                    ->orWhereDate('move_in_last_reminder_on', '<', today());
            })
            ->with(['tenant', 'property'])
            ->get();

        $count = 0;
        $failures = 0;

        foreach ($reservations as $reservation) {
            try {
                $daysLeft = $reservation->daysUntilMoveInDeadline();

                if (! in_array($daysLeft, $thresholds, true)) {
                    continue;
                }

                $message = $daysLeft === 0
                    ? 'Today is the last day to confirm your move-in. After today your deposit is released to the landlord automatically.'
                    : "You have {$daysLeft} day(s) left to confirm your move-in. After that your deposit is released to the landlord automatically.";

                Notification::notify(
                    $reservation->tenant_id,
                    'move_in_confirmation_reminder',
                    'Confirm your move-in',
                    $message,
                    route('agreements.show', $reservation),
                    $reservation->conversation_id,
                );

                $reservation->update(['move_in_last_reminder_on' => today()]);
                $count++;
            } catch (\Throwable $e) {
                $failures++;
                Log::error("ProcessMoveInDeadlines: failed to send confirmation reminder for reservation {$reservation->reservation_id}", ['exception' => $e]);
            }
        }

        $this->info("Sent {$count} confirmation reminder(s), {$failures} failure(s).");
    }

    /**
     * Clock 1 expiry — phase 1. Nobody has claimed the keys changed hands, so
     * this is escalated to an admin rather than refunded automatically.
     *
     * Reuses the dispute queue: one admin screen, one set of resolution
     * actions. A system-written reason distinguishes this from a tenant report.
     */
    protected function escalateOverdueTurnovers(): void
    {
        $overdue = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereNotNull('move_in_deadline_at')
            ->where('move_in_deadline_at', '<=', now())
            ->whereHas('payments', fn ($q) => $q->where('status', 'Held'))
            ->pluck('reservation_id');

        $count = 0;
        $failures = 0;

        foreach ($overdue as $id) {
            try {
                $escalated = DB::transaction(function () use ($id) {
                    $locked = Reservation::whereKey($id)->lockForUpdate()->first();

                    if (! $locked
                        || $locked->rental_status !== 'Rental Agreement Signed'
                        || $locked->move_in_disputed_at
                        || $locked->keys_turned_over_at
                    ) {
                        return false;
                    }

                    // Both clocks only exist while money is held; without a held
                    // payment this reservation has nothing to escalate.
                    if (! $locked->payments()->where('status', 'Held')->exists()) {
                        return false;
                    }

                    $locked->update([
                        'move_in_disputed_at' => now(),
                        'move_in_dispute_reason' => 'Landlord did not turn over the keys by the deadline.',
                        'move_in_deadline_at' => null,
                    ]);

                    $locked->postSystemMessage(
                        'The key turnover deadline passed without confirmation. The deposit is on hold pending review by an administrator.'
                    );

                    Notification::notify(
                        $locked->tenant_id,
                        'turnover_overdue',
                        'Move-in deadline passed',
                        'Your landlord did not confirm the key turnover in time. Your deposit is still held and an administrator will review this.',
                        route('agreements.show', $locked),
                        $locked->conversation_id,
                    );

                    Notification::notify(
                        $locked->property?->landlord_id,
                        'turnover_overdue',
                        'Key turnover deadline passed',
                        'The turnover deadline for this reservation has passed. An administrator will review the held deposit.',
                        route('landlord.reservations.index'),
                        $locked->conversation_id,
                    );

                    return true;
                });

                if ($escalated) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::error("ProcessMoveInDeadlines: failed to escalate overdue turnover for reservation {$id}", ['exception' => $e]);
            }
        }

        $this->info("Escalated {$count} overdue turnover(s) to admin review, {$failures} failure(s).");
    }

    /**
     * Clock 2 expiry — releases the deposit to the landlord.
     *
     * The only place in the app where money moves with no human present, so the
     * held payment is locked inside the same transaction as the status flip and
     * the broadcast is deferred until after commit.
     */
    protected function releaseExpiredConfirmations(): void
    {
        // The reminder promises the tenant the whole calendar day named in the
        // countdown ("today is the last day"), so the deadline instant is not
        // enough to release on — only once that day has fully elapsed.
        $expired = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNotNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereNotNull('move_in_deadline_at')
            ->where('move_in_deadline_at', '<', now()->startOfDay())
            ->pluck('reservation_id');

        $released = [];
        $failures = 0;

        foreach ($expired as $id) {
            try {
                $payment = DB::transaction(function () use ($id) {
                    $locked = Reservation::whereKey($id)->lockForUpdate()->first();

                    if (! $locked
                        || $locked->rental_status !== 'Rental Agreement Signed'
                        || $locked->move_in_disputed_at
                        || ! $locked->keys_turned_over_at
                        || ! $locked->move_in_deadline_at
                        || ! $locked->move_in_deadline_at->lt(now()->startOfDay())
                    ) {
                        return null;
                    }

                    $heldPayment = $locked->payments()
                        ->where('status', 'Held')
                        ->lockForUpdate()
                        ->first();

                    if (! $heldPayment) {
                        return null;
                    }

                    // tenant_confirmed_move_in_at stays null on purpose. It means
                    // what it says; conflating "confirmed" with "timed out" would
                    // poison occupancy reporting.
                    $locked->update([
                        'rental_status' => 'Occupied',
                        'move_in_deadline_at' => null,
                    ]);

                    $heldPayment->update([
                        'status' => 'Released',
                        'released_at' => now(),
                        'released_by' => null,
                        'release_reason' => 'auto_expiry',
                    ]);

                    if ($locked->unit) {
                        $locked->unit->update(['availability_status' => 'Occupied']);
                    }

                    $locked->postSystemMessage(
                        'The move-in confirmation window closed. The unit is marked occupied and the deposit has been released to the landlord.'
                    );

                    Notification::notify(
                        $locked->property?->landlord_id,
                        'payment_released',
                        'Deposit released',
                        'The tenant did not confirm within the confirmation window, so the deposit has been released to you automatically.',
                        route('landlord.reservations.index'),
                        $locked->conversation_id,
                    );

                    Notification::notify(
                        $locked->tenant_id,
                        'payment_released',
                        'Deposit released to your landlord',
                        'Your move-in confirmation window has closed, so your deposit was released to the landlord automatically.',
                        route('agreements.show', $locked),
                        $locked->conversation_id,
                    );

                    return $heldPayment;
                });

                if ($payment) {
                    $released[] = $payment;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::error("ProcessMoveInDeadlines: failed to release expired confirmation for reservation {$id}", ['exception' => $e]);
            }
        }

        // PaymentObserver broadcasts each Held -> Released transition, and its
        // $afterCommit keeps those announcements outside the transactions above.
        $this->info('Released '.count($released)." expired confirmation(s), {$failures} failure(s).");
    }
}
