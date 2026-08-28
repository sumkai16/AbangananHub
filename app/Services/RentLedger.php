<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The rent owed on a tenancy, month by month, against what was actually paid.
 *
 * **Periods are derived, never stored.** A billing period is just a month
 * between move-in and move-out, and a `payments` row carrying
 * `payment_type = 'Monthly'` with a `billing_period` inside that month settles
 * it. There is deliberately no schedule table: editing the rent, the due day or
 * the move-out date would leave stale rows behind it, and the one fact worth
 * storing — that money changed hands — already has a home in `payments`.
 *
 * `billing_period` and the 'Monthly' payment type were both in the schema from
 * the original payments migration and had never been written by any code; this
 * is the reader they were carved out for.
 *
 * Serves walk-in and platform tenancies identically. The escrow only ever
 * covers the initial payment, so month two onward has always been unrecorded
 * for everyone.
 */
class RentLedger
{
    /** Charges that sit outside the monthly cycle. */
    private const NON_MONTHLY_TYPES = ['Initial', 'Deposit', 'Utility', 'Other'];

    /**
     * Money that has actually reached the landlord.
     *
     * 'Paid' is a landlord-recorded offline payment, 'Held' is in escrow and
     * 'Released' has been paid out. 'Pending' is an unfinished checkout session
     * that may never complete and is deliberately not counted — the same rule
     * Landlord\AnalyticsController applies to revenue.
     */
    private const SETTLED_STATUSES = ['Paid', 'Held', 'Released'];

    private Collection $payments;

    public function __construct(private Reservation $reservation)
    {
        // Loaded once and filtered in memory: a tenancy has a handful of
        // payments and the ledger touches them once per rendered period, so
        // one query beats a query per month.
        $this->payments = $reservation->relationLoaded('payments')
            ? $reservation->payments
            : $reservation->payments()->get();
    }

    public static function for(Reservation $reservation): self
    {
        return new self($reservation);
    }

    /**
     * One row per billing month, oldest first.
     *
     * Runs from move-in to move-out, or to the current month for an open-ended
     * tenancy — never past it, since rent that hasn't come due yet isn't
     * something a landlord is chasing. A tenancy that hasn't started yet
     * produces no periods rather than an empty-looking table of future debt.
     */
    public function periods(): Collection
    {
        $start = $this->reservation->tenancyStartDate();

        if (! $start) {
            return collect();
        }

        $cursor = $start->copy()->startOfMonth();
        $last = $this->lastBillablePeriod($start);

        if ($cursor->greaterThan($last)) {
            return collect();
        }

        $expected = $this->reservation->monthlyRent();
        $dueDay = $this->reservation->rentDueDay();
        $grace = (int) config('rentals.rent_overdue_grace_days');
        $thisMonth = now()->startOfMonth();
        $periods = collect();

        while ($cursor->lessThanOrEqualTo($last)) {
            $paidPayments = $this->monthlyPaymentsFor($cursor);
            $paid = (float) $paidPayments->sum(fn (Payment $p) => (float) $p->amount);
            // Safe without overflow handling because rentDueDay() clamps to 28.
            $dueOn = $cursor->copy()->day($dueDay);

            $periods->push([
                'period'   => $cursor->copy(),
                'label'    => $cursor->format('M Y'),
                'due_on'   => $dueOn,
                'expected' => $expected,
                'paid'     => $paid,
                'balance'  => round($expected - $paid, 2),
                'status'   => $this->periodStatus($expected, $paid, $dueOn, $grace),
                // A month that hasn't arrived yet, reached only because rent was
                // paid into it. Nothing may treat it as money owed — see
                // summary() and unsettledPeriods().
                'is_future' => $cursor->greaterThan($thisMonth),
                'payments' => $paidPayments,
            ]);

            $cursor->addMonthNoOverflow();
        }

        return $periods;
    }

    /**
     * Deposits, the initial payment, utilities and one-offs — money that
     * belongs to the tenancy but not to any single month, so it would silently
     * inflate a period's "paid" column if it were folded in.
     */
    public function otherCharges(): Collection
    {
        return $this->payments
            ->filter(fn (Payment $p) => in_array($p->payment_type, self::NON_MONTHLY_TYPES, true)
                && in_array($p->status, self::SETTLED_STATUSES, true))
            ->sortByDesc(fn (Payment $p) => $p->paid_at ?? $p->created_at)
            ->values();
    }

    /**
     * Headline numbers for the tenancy.
     *
     * `collected` counts everything settled including deposits, because that is
     * the question a landlord is asking ("what has this tenant given me?").
     * `outstanding` counts only unpaid monthly rent — a deposit is not a debt.
     */
    public function summary(): array
    {
        $periods = $this->periods();

        // Everything a landlord could be chasing. A month that hasn't arrived
        // yet is in the ledger only because it was paid into, so counting its
        // balance would report a prepaying tenant as being in arrears — in
        // August, for November. `collected` deliberately still counts every
        // period, prepaid ones included: that money genuinely has been received.
        $billed = $periods->reject(fn ($p) => $p['is_future']);
        $overdue = $billed->where('status', 'overdue');
        // Only *fully* covered future months count as paid in advance. A month
        // carrying the remainder of an overpayment is part-covered, and saying
        // a tenant is paid through it would overstate what they have settled.
        $prepaid = $periods->filter(fn ($p) => $p['is_future'] && $p['status'] === 'paid');

        $monthlyCollected = (float) $periods->sum('paid');
        $otherCollected = (float) $this->otherCharges()->sum(fn (Payment $p) => (float) $p->amount);

        return [
            'monthlyRent'      => $this->reservation->monthlyRent(),
            'dueDay'           => $this->reservation->rentDueDay(),
            'periodCount'      => $periods->count(),
            'collected'        => round($monthlyCollected + $otherCollected, 2),
            'monthlyCollected' => round($monthlyCollected, 2),
            'otherCollected'   => round($otherCollected, 2),
            'outstanding'      => round((float) $billed->sum(fn ($p) => max(0, $p['balance'])), 2),
            'overdueCount'     => $overdue->count(),
            'overdueAmount'    => round((float) $overdue->sum(fn ($p) => max(0, $p['balance'])), 2),
            'nextDue'          => $billed->firstWhere(fn ($p) => in_array($p['status'], ['due', 'partial'], true)),
            'oldestOverdue'    => $overdue->first(),
            // Months already covered before they arrived, for the "paid in
            // advance through …" line on the tenancy pages.
            'prepaidCount'     => $prepaid->count(),
            'prepaidThrough'   => $prepaid->last()['period'] ?? null,
        ];
    }

    /**
     * Billing months this tenancy has left to settle, for the "record a
     * payment" form. Unpaid first so the obvious choice is the top one.
     *
     * Future months are excluded even when only partly covered: this feeds the
     * tenant's online payment (Tenant\PaymentController takes ->first()), and
     * "what do I owe" must not start with a month nobody has reached yet. A
     * landlord who genuinely wants to record next month's rent early can still
     * pick any month in the record-payment modal's date field.
     */
    public function unsettledPeriods(): Collection
    {
        return $this->periods()
            ->filter(fn ($p) => $p['status'] !== 'paid' && ! $p['is_future'])
            ->values();
    }

    // ─── Internals ───────────────────────────────────────────

    /**
     * The last month worth billing: move-out where the tenancy has ended,
     * otherwise the current month. A future move-out doesn't bill early.
     */
    private function lastBillablePeriod(Carbon $start): Carbon
    {
        $thisMonth = now()->startOfMonth();
        $moveOut = $this->reservation->target_move_out_date;

        $last = $moveOut && $moveOut->copy()->startOfMonth()->lessThan($thisMonth)
            ? $moveOut->copy()->startOfMonth()
            : $thisMonth;

        // A tenancy that starts next month still owes its first month, so the
        // window can never close before it opens.
        if ($last->lessThan($start->copy()->startOfMonth())) {
            $last = $start->copy()->startOfMonth();
        }

        // Rent already paid into future months has to be visible, or a tenant
        // who prepaid six months sees nothing for it and a landlord cannot tell
        // a prepaid tenancy from an unpaid one. The window only ever reaches
        // forward over months that actually carry a payment — speculative
        // future debt stays out, which is what keeps `outstanding` honest.
        $prepaidThrough = $this->latestSettledMonthlyPeriod();

        return $prepaidThrough && $prepaidThrough->greaterThan($last)
            ? $prepaidThrough
            : $last;
    }

    /**
     * The furthest-out month any settled monthly payment is dated into, or null.
     */
    private function latestSettledMonthlyPeriod(): ?Carbon
    {
        return $this->payments
            ->filter(fn (Payment $p) => $p->payment_type === 'Monthly'
                && $p->billing_period
                && in_array($p->status, self::SETTLED_STATUSES, true))
            ->map(fn (Payment $p) => $p->billing_period->copy()->startOfMonth())
            ->sort()
            ->last();
    }

    /**
     * Settled monthly payments whose billing_period falls in this month.
     *
     * Matched on the month rather than the exact date so a payment recorded
     * against the 5th and one against the 1st of the same month both land on
     * the same period.
     */
    private function monthlyPaymentsFor(Carbon $period): Collection
    {
        return $this->payments
            ->filter(function (Payment $payment) use ($period) {
                if ($payment->payment_type !== 'Monthly') {
                    return false;
                }

                if (! in_array($payment->status, self::SETTLED_STATUSES, true)) {
                    return false;
                }

                return $payment->billing_period
                    && $payment->billing_period->isSameMonth($period);
            })
            ->sortBy(fn (Payment $p) => $p->paid_at ?? $p->created_at)
            ->values();
    }

    /**
     * Order matters: fully paid wins over late, so a period settled after its
     * due date reads Paid rather than staying Overdue forever.
     */
    private function periodStatus(float $expected, float $paid, Carbon $dueOn, int $grace): string
    {
        if ($expected <= 0 || $paid >= $expected) {
            return 'paid';
        }

        if ($dueOn->copy()->addDays($grace)->endOfDay()->isPast()) {
            return 'overdue';
        }

        return $paid > 0 ? 'partial' : 'due';
    }
}
