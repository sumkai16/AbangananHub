<?php

namespace App\Console\Commands;

use App\Models\RentReminder;
use App\Models\Reservation;
use App\Services\RentLedger;
use App\Services\RentReminderNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Nightly rent-due reminders, read straight off the ledger.
 *
 * The ledger already knows each period's due date and whether it's paid, so
 * this command only decides which milestone (if any) is due to fire today and
 * for whom. It focuses on the OLDEST unpaid period per tenancy — the debt that
 * defines how far behind the tenant is — so a tenancy three months behind gets
 * one reminder a run, not three.
 *
 * Recipients:
 *   - Landlord always. It is the only channel that reaches a walk-in tenancy,
 *     since a walk-in tenant is inactive and cannot log in.
 *   - Platform tenant additionally, when they can actually receive it.
 *
 * Idempotent via rent_reminders (one row per fired milestone) and catch-up
 * safe: a missed night fires only the current milestone, never a backlog.
 * Per-row try/catch/log/continue so one bad row can't abort the batch — the
 * same discipline the escrow batch uses.
 */
class ProcessRentReminders extends Command
{
    protected $signature = 'reservations:process-rent-reminders
                            {--date= : Treat this date as "today" (YYYY-MM-DD) for testing milestones}';

    protected $description = 'Notify landlords (and platform tenants) about upcoming and overdue rent';

    public function __construct(private RentReminderNotifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $sent = 0;
        $failures = 0;

        // Occupied only: a Completed tenancy's ledger is frozen, so there is
        // nothing left to chase.
        $reservations = Reservation::where('rental_status', 'Occupied')
            ->with(['tenant', 'property', 'unit', 'payments'])
            ->whereHas('property')
            ->get();

        foreach ($reservations as $reservation) {
            try {
                $sent += $this->remindFor($reservation, $today) ? 1 : 0;
            } catch (\Throwable $e) {
                $failures++;
                Log::error('Rent reminder failed for reservation ' . $reservation->reservation_id, [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Rent reminders: {$sent} sent, {$failures} failed, {$reservations->count()} tenancies scanned.");

        return self::SUCCESS;
    }

    /**
     * Fire at most one reminder for this tenancy: the current milestone on its
     * oldest unpaid period. Returns true when a reminder was actually sent.
     */
    private function remindFor(Reservation $reservation, Carbon $today): bool
    {
        // periods() is oldest-first, so the first unpaid one is the oldest debt.
        $target = RentLedger::for($reservation)
            ->periods()
            ->firstWhere(fn ($p) => $p['status'] !== 'paid');

        if (! $target) {
            return false;
        }

        $milestone = $this->currentMilestone($target['due_on'], $today);

        if (! $milestone) {
            return false; // the due-soon window hasn't opened yet
        }

        // The guard: firstOrCreate + wasRecentlyCreated means a re-run — or a
        // second nightly invocation — never double-notifies. Record first: a
        // duplicate reminder is worse than a missed one, and notify() writes a
        // durable row regardless of whether the live broadcast lands.
        $reminder = RentReminder::firstOrCreate([
            'reservation_id' => $reservation->reservation_id,
            'billing_period' => $target['period']->toDateString(),
            'milestone'      => $milestone,
        ]);

        if (! $reminder->wasRecentlyCreated) {
            return false;
        }

        $this->notifier->sendToLandlord($reservation, $target, $today);
        $this->notifier->sendToTenant($reservation, $target, $today);

        return true;
    }

    /**
     * The milestone whose scheduled date has most recently passed. Ordered
     * ascending, so the last one that is due wins — which is what makes a
     * missed run fire only the current milestone instead of every skipped one.
     * Null until the due-soon window opens.
     */
    private function currentMilestone(Carbon $dueOn, Carbon $today): ?string
    {
        $lead = (int) config('rentals.rent_reminder_lead_days');
        $interval = (int) config('rentals.rent_overdue_reminder_interval_days');
        $maxWeeks = (int) config('rentals.rent_reminder_max_overdue_weeks');

        $schedule = [
            ['due_soon', $dueOn->copy()->subDays($lead)],
            ['due_today', $dueOn->copy()],
        ];

        for ($k = 1; $k <= $maxWeeks; $k++) {
            $schedule[] = ['overdue_w' . $k, $dueOn->copy()->addDays($k * $interval)];
        }

        $current = null;
        foreach ($schedule as [$key, $date]) {
            if ($date->lessThanOrEqualTo($today)) {
                $current = $key;
            }
        }

        return $current;
    }
}
