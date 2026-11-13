<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily: nudge, then close, reservations that sit waiting on a person (config rentals.stale_reservations).
 *
 *   Inquiry                  the landlord has not answered     -> remind landlord, then cancel
 *   Under Negotiation        the chat went quiet               -> remind both, then cancel
 *   Pending Rental Agreement the tenant has not signed         -> remind tenant (landlord told), then cancel
 *   Signed, not paid         the tenant has not paid move-in   -> remind tenant (landlord told), then cancel
 *   Signed, paid             nobody confirmed the move-in      -> remind both; never cancelled (the money is paid)
 *
 * Idempotent: a reminder goes out once per quiet spell (reservations.stale_reminder_sent_at is compared with the
 * moment the spell began, so new activity re-arms it). The cancel goes through Reservation::cancel(), so anything
 * holding money or with an open checkout is left alone. Walk-in tenants are skipped: they cannot log in and the
 * landlord runs those leases by hand. One bad row never stops the batch.
 */
class ProcessStaleReservations extends Command
{
    protected $signature = 'reservations:process-stale';

    protected $description = 'Remind, then cancel, inquiries, negotiations and unsigned agreements that went quiet';

    /** key under config rentals.stale_reservations => status. 'signed' and 'paid' are the two halves of Rental Agreement Signed. */
    private const STAGES = [
        'inquiry' => 'Inquiry',
        'negotiation' => 'Under Negotiation',
        'agreement' => 'Pending Rental Agreement',
        'signed' => 'Rental Agreement Signed',
        'paid' => 'Rental Agreement Signed',
    ];

    public function handle(): int
    {
        $reminded = $cancelled = $failures = 0;

        foreach (self::STAGES as $key => $status) {
            $rules = config("rentals.stale_reservations.$key");

            $rows = $this->rows($key, $status);

            $stageRules = $rules;

            foreach ($rows as $reservation) {
                try {
                    // A landlord's own payment deadline for the unit replaces the platform default for this stage.
                    $rules = $stageRules;
                    $days = $reservation->paymentDeadlineDays($key);
                    if ($days !== null && $days !== ($stageRules['cancel_days'] ?? null)) {
                        $rules['cancel_days'] = $days;
                        $rules['remind_days'] = max(1, min($stageRules['remind_days'], $days - 1));
                    }

                    // Part of the move-in money (the reservation fee) is already with the landlord: never cancel the
                    // tenant and lose it. Remind both sides once the balance is past due; the landlord decides.
                    if ($key === 'signed' && $reservation->moveInPaid() > 0) {
                        $rules['cancel_days'] = null;
                        $rules['remind_days'] = $reservation->paymentDeadlineDays('signed') ?? $rules['remind_days'];
                    }

                    $quietSince = $reservation->quietSince();

                    // The tenant uploaded what the lease asked for and the landlord has not reviewed it: the landlord is
                    // the one holding this up, so the tenant is never cancelled for it. Nudge the landlord instead.
                    if ($key === 'agreement' && $reservation->documents()->where('status', 'Pending')->exists()) {
                        if ($this->shouldRemind($reservation, $quietSince, $rules)) {
                            $this->remindReview($reservation);
                            $reminded++;
                        }

                        continue;
                    }

                    if ($this->shouldCancel($quietSince, $rules) && $this->cancel($reservation, $key, $rules)) {
                        $cancelled++;
                    } elseif ($this->shouldRemind($reservation, $quietSince, $rules)) {
                        $this->remind($reservation, $key, $rules);
                        $reminded++;
                    }
                } catch (\Throwable $e) {
                    $failures++;
                    Log::error('Stale reservation check failed for '.$reservation->reservation_id, ['exception' => $e->getMessage()]);
                }
            }
        }

        $this->info("Stale reservations: {$reminded} reminded, {$cancelled} cancelled, {$failures} failed.");

        return self::SUCCESS;
    }

    /** The reservations of one stage that a person is holding up. */
    private function rows(string $key, string $status)
    {
        $query = Reservation::where('rental_status', $status)
            ->whereHas('property')
            ->whereHas('tenant', fn ($q) => $q->where('is_walk_in', false))
            ->with(['property', 'tenant', 'unit']);

        if ($key === 'signed') {
            // Unpaid, and not waiting on the landlord to answer a payment report (that is the landlord's turn).
            $query->whereNot(fn ($q) => $q->moveInSettled())
                ->whereDoesntHave('paymentClaims', fn ($c) => $c->pending()->where('claim_type', 'MoveIn'));
        } elseif ($key === 'paid') {
            // Paid straight to the landlord. Money held by AbangananHub has its own escrow clocks (ProcessMoveInDeadlines).
            $query->moveInSettled()
                ->whereNull('move_in_disputed_at')
                ->whereDoesntHave('payments', fn ($p) => $p->where('status', 'Held'));
        }

        return $query->get();
    }

    private function shouldRemind(Reservation $reservation, Carbon $quietSince, array $rules): bool
    {
        if ($quietSince->gt(now()->subDays($rules['remind_days']))) {
            return false;
        }

        // Not yet reminded during this quiet spell.
        return $reservation->stale_reminder_sent_at === null || Carbon::parse($reservation->stale_reminder_sent_at)->lt($quietSince);
    }

    private function shouldCancel(Carbon $quietSince, array $rules): bool
    {
        // A null cancel_days means this stage only ever reminds (e.g. paid, waiting for move-in).
        return (bool) config('rentals.stale_reservations.auto_cancel')
            && ($rules['cancel_days'] ?? null) !== null
            && $quietSince->lte(now()->subDays($rules['cancel_days']));
    }

    private function remind(Reservation $reservation, string $key, array $rules): void
    {
        $cancels = config('rentals.stale_reservations.auto_cancel') && ($rules['cancel_days'] ?? null) !== null;
        $left = $cancels ? max(1, $rules['cancel_days'] - $rules['remind_days']) : 0;
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $name = trim($reservation->tenant->first_name.' '.$reservation->tenant->last_name);
        $landlordId = $reservation->property->landlord_id;
        $tenantId = $reservation->tenant_id;
        $thread = route('conversations.index', ['active' => $reservation->conversation_id]);
        $closing = $cancels
            ? " It will be cancelled in about {$left} ".($left === 1 ? 'day' : 'days').' if nothing happens.'
            : '';

        match ($key) {
            'inquiry' => Notification::notify($landlordId, 'reservation', 'Inquiry waiting for you',
                "{$name} is waiting for your reply about {$unit}. Accept or decline it.".$closing, $thread, $reservation->conversation_id),
            'negotiation' => $this->both($reservation, $landlordId, $tenantId, 'Talks have gone quiet',
                "The conversation about {$unit} has had no new message for a while. Pick it up before the reservation is closed.".$closing, $thread),
            'agreement' => $this->agreementReminder($reservation, $landlordId, $tenantId, $unit, $name, $closing, $thread),
            'signed' => $reservation->moveInPaid() > 0
                ? $this->balanceReminder($reservation, $landlordId, $tenantId, $unit, $name, $thread)
                : $this->unpaidReminder($reservation, $landlordId, $tenantId, $unit, $name, $closing, $thread),
            'paid' => $this->both($reservation, $landlordId, $tenantId, 'Move-in still to confirm',
                "The move-in payment for {$unit} is confirmed, but the move-in has not been. Once the keys are handed over, tap Confirm move-in to start the tenancy.", $thread),
        };

        // Query builder on purpose: no model events, and updated_at (the quiet-spell clock) stays put.
        DB::table('reservations')->where('reservation_id', $reservation->reservation_id)
            ->update(['stale_reminder_sent_at' => now()]);
    }

    private function both(Reservation $reservation, int $landlordId, int $tenantId, string $title, string $text, string $thread): void
    {
        Notification::notify($landlordId, 'reservation', $title, $text, $thread, $reservation->conversation_id);
        Notification::notify($tenantId, 'reservation', $title, $text, $thread, $reservation->conversation_id);
    }

    private function agreementReminder(Reservation $reservation, int $landlordId, int $tenantId, string $unit, string $name, string $closing, string $thread): void
    {
        Notification::notify($tenantId, 'agreement', 'Please sign your rental agreement',
            "Your rental agreement for {$unit} is still waiting for your signature. The unit is held for you until then.".$closing,
            route('agreements.show', $reservation), $reservation->conversation_id);
        Notification::notify($landlordId, 'agreement', 'Agreement still unsigned',
            "{$name} has not signed the rental agreement for {$unit} yet. We reminded them.".$closing, $thread, $reservation->conversation_id);
    }

    /** The tenant's documents are in; the landlord has not approved or rejected them, so the tenant cannot sign yet. */
    private function remindReview(Reservation $reservation): void
    {
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $name = trim($reservation->tenant->first_name.' '.$reservation->tenant->last_name);

        Notification::notify($reservation->property->landlord_id, 'agreement', 'Documents waiting for your review',
            "{$name} uploaded the documents for {$unit}. They cannot sign the rental agreement until you approve or reject them.",
            route('conversations.index', ['active' => $reservation->conversation_id]), $reservation->conversation_id);

        DB::table('reservations')->where('reservation_id', $reservation->reservation_id)
            ->update(['stale_reminder_sent_at' => now()]);
    }

    private function unpaidReminder(Reservation $reservation, int $landlordId, int $tenantId, string $unit, string $name, string $closing, string $thread): void
    {
        $due = number_format($reservation->moveInDue(), 2);

        Notification::notify($tenantId, 'agreement', 'Please pay to keep your unit',
            "You signed the rental agreement for {$unit}, but the move-in payment (₱{$due}) has not been made. Pay your landlord, then tap I've paid.".$closing,
            route('agreements.show', $reservation), $reservation->conversation_id);
        Notification::notify($landlordId, 'agreement', 'Move-in payment not made yet',
            "{$name} signed the rental agreement for {$unit} but has not paid yet. We reminded them.".$closing, $thread, $reservation->conversation_id);
    }

    /** Fee paid, balance past due: nothing is cancelled. The tenant is asked to pay, the landlord to decide. */
    private function balanceReminder(Reservation $reservation, int $landlordId, int $tenantId, string $unit, string $name, string $thread): void
    {
        $left = number_format($reservation->moveInShortfall(), 2);
        $paid = number_format($reservation->moveInPaid(), 2);

        Notification::notify($tenantId, 'agreement', 'Balance is due',
            "You paid ₱{$paid} for {$unit}, but the balance of ₱{$left} was due. Pay your landlord to keep the unit.",
            route('agreements.show', $reservation), $reservation->conversation_id);
        Notification::notify($landlordId, 'agreement', 'Balance overdue',
            "{$name} paid ₱{$paid} for {$unit} but has not paid the balance of ₱{$left}. The reservation stays open. Wait, or cancel it and record whether you return the ₱{$paid}.",
            $thread, $reservation->conversation_id);
    }

    /** True when the reservation was actually cancelled (false when money is held or a checkout is open). */
    private function cancel(Reservation $reservation, string $key, array $rules): bool
    {
        $days = $rules['cancel_days'];
        $reservation->systemCancelReason = match ($key) {
            'inquiry' => "No reply for {$days} days, so the inquiry was closed.",
            'negotiation' => "No new message for {$days} days, so the reservation was closed.",
            'agreement' => "The agreement was not signed within {$days} days, so the reservation was closed and the unit is free again.",
            'signed' => "No move-in payment was made within {$days} days of signing, so the reservation was closed and the unit is free again.",
        };

        if (! $reservation->cancel()) {
            $reservation->systemCancelReason = null;

            return false;
        }

        $reason = $reservation->systemCancelReason;
        $unit = $reservation->unit?->unit_label ?? 'the unit';
        $name = trim($reservation->tenant->first_name.' '.$reservation->tenant->last_name);
        $thread = route('conversations.index', ['active' => $reservation->conversation_id]);

        // Sent here, not by the observer, which stays quiet for these cancels and skips reservations with no chat.
        Notification::notify($reservation->tenant_id, 'reservation', 'Reservation closed', "{$unit}: {$reason}", $thread, $reservation->conversation_id);
        Notification::notify($reservation->property->landlord_id, 'reservation', 'Reservation closed', "{$name}, {$unit}: {$reason}", $thread, $reservation->conversation_id);
        $reservation->postSystemMessage($reason);

        return true;
    }
}
