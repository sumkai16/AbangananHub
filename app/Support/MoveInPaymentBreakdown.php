<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Splits one lump of move-in money into the things it was actually for.
 *
 * A walk-in landlord collects a single amount at the door and types one number.
 * That number is really up to three obligations — the security deposit, the
 * first month's rent, and whatever is left over as rent paid in advance — and
 * recording it as one opaque row is why a walk-in tenant's move-in month used to
 * read Overdue the day after they paid for it: RentLedger only settles a period
 * from a 'Monthly' row carrying a billing_period, and the old code wrote a
 * single 'Initial' row carrying neither.
 *
 * **Advance rent is not a payment type.** It is 'Monthly' rows dated into future
 * months — the exact shape RentLedger already reads — so this needs no schema
 * change and no new enum member. A single 'Advance' row would land back in
 * otherCharges() and settle nothing, which is the bug this class exists to fix.
 *
 * Pure value object: no database, no request state. The web controller, the API
 * controller and the dev fixtures all allocate through this one implementation
 * so they cannot drift apart, and the Blade form mirrors it in Alpine purely to
 * show the landlord the split before they commit to it.
 */
class MoveInPaymentBreakdown
{
    /**
     * Ceiling on how many months an overpayment may be spread across.
     *
     * Without it a large amount against a small rent generates a row per month
     * until the arithmetic runs out — 1,000,000 (the validation maximum) at
     * ₱500/month is 2,000 payment rows from one form submission. Five years is
     * far past any real advance; anything beyond it lands on the final month as
     * a credit rather than being silently dropped.
     */
    private const MAX_ADVANCE_MONTHS = 60;

    private float $depositPortion = 0.0;

    /** @var array<int, array{month: Carbon, amount: float}> */
    private array $rentSlices = [];

    private function __construct(
        private readonly float $amountReceived,
        private readonly float $rent,
        private readonly float $deposit,
        private readonly Carbon $moveInMonth,
    ) {
        $this->allocate();
    }

    public static function make(
        float $amountReceived,
        float $rent,
        float $deposit,
        Carbon|string $moveInDate,
    ): self {
        return new self(
            max(0.0, round($amountReceived, 2)),
            max(0.0, round($rent, 2)),
            max(0.0, round($deposit, 2)),
            Carbon::parse($moveInDate)->startOfMonth(),
        );
    }

    /**
     * What the tenant owes to move in at all: one month's rent plus the deposit.
     *
     * The deposit is required at the application layer on every unit (and was
     * backfilled to one month's rent in Aug 2026), so in practice this is never
     * just the rent — but the arithmetic tolerates a zero rather than assuming.
     */
    public function requiredMoveIn(): float
    {
        return round($this->rent + $this->deposit, 2);
    }

    /**
     * How far short of the required amount this payment falls, or 0.0.
     *
     * Deliberately not an error. A walk-in records a tenancy that already
     * happened offline — the landlord may genuinely have taken ₱3,000, or
     * collected the deposit last week. The form warns on this; it does not
     * refuse the record, because a landlord blocked from entering the truth
     * enters a convenient fiction instead.
     */
    public function shortfall(): float
    {
        return round(max(0.0, $this->requiredMoveIn() - $this->amountReceived), 2);
    }

    public function isShort(): bool
    {
        return $this->shortfall() > 0;
    }

    public function amountReceived(): float
    {
        return $this->amountReceived;
    }

    public function depositPortion(): float
    {
        return $this->depositPortion;
    }

    /** The slice settling the move-in month itself. */
    public function rentPortion(): float
    {
        return $this->rentSlices[0]['amount'] ?? 0.0;
    }

    /** Everything paid toward months after the move-in month. */
    public function advancePortion(): float
    {
        return round(
            collect($this->rentSlices)->skip(1)->sum(fn (array $slice) => $slice['amount']),
            2
        );
    }

    public function hasAdvance(): bool
    {
        return $this->advancePortion() > 0;
    }

    /**
     * The future months this payment reaches into, move-in month excluded.
     *
     * @return Collection<int, Carbon>
     */
    public function advanceMonths(): Collection
    {
        return collect($this->rentSlices)
            ->skip(1)
            ->map(fn (array $slice) => $slice['month']->copy())
            ->values();
    }

    /**
     * The payment rows to write, in the order they should be created.
     *
     * Each is a partial Payment attribute set — the caller supplies the shared
     * facts (reservation, method, paid_at, reference, recorded_by), because all
     * of these rows describe one collection event split by purpose, not several
     * separate payments.
     *
     * @return array<int, array{payment_type: string, billing_period: ?Carbon, amount: float}>
     */
    public function rows(): array
    {
        $rows = [];

        if ($this->depositPortion > 0) {
            $rows[] = [
                'payment_type'   => 'Deposit',
                'billing_period' => null,
                'amount'         => $this->depositPortion,
            ];
        }

        foreach ($this->rentSlices as $slice) {
            $rows[] = [
                'payment_type'   => 'Monthly',
                'billing_period' => $slice['month']->copy(),
                'amount'         => $slice['amount'],
            ];
        }

        return $rows;
    }

    // ─── Internals ───────────────────────────────────────────

    /**
     * Deposit first, then rent month by month from the move-in month forward.
     *
     * **Order matters and is the one judgement call here.** Satisfying the
     * deposit first means an underpayment surfaces as an unpaid *rent* balance,
     * which the ledger keeps raising every month until it is settled. The
     * alternative — renting first — leaves a half-paid Deposit row sitting in
     * "Deposits & other payments" looking like a completed deposit unless
     * somebody compares it against the unit's own figure.
     */
    private function allocate(): void
    {
        $remaining = $this->amountReceived;

        if ($remaining <= 0) {
            return;
        }

        $this->depositPortion = round(min($remaining, $this->deposit), 2);
        $remaining = round($remaining - $this->depositPortion, 2);

        if ($remaining <= 0) {
            return;
        }

        // A unit with no rent recorded cannot have its months apportioned —
        // dividing by it would spin forever. The whole remainder settles the
        // move-in month, where a zero expectation reads as paid.
        if ($this->rent <= 0) {
            $this->rentSlices[] = ['month' => $this->moveInMonth->copy(), 'amount' => $remaining];

            return;
        }

        $month = $this->moveInMonth->copy();

        while ($remaining > 0 && count($this->rentSlices) < self::MAX_ADVANCE_MONTHS) {
            $slice = round(min($remaining, $this->rent), 2);

            $this->rentSlices[] = ['month' => $month->copy(), 'amount' => $slice];

            $remaining = round($remaining - $slice, 2);
            $month->addMonthNoOverflow();
        }

        // Past the ceiling: fold the residue into the last month rather than
        // losing money that demonstrably changed hands.
        if ($remaining > 0 && $this->rentSlices !== []) {
            $last = count($this->rentSlices) - 1;
            $this->rentSlices[$last]['amount'] = round($this->rentSlices[$last]['amount'] + $remaining, 2);
        }
    }
}
