<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Splits one lump of rent money — recorded against an already-occupied
 * tenancy — across as many billing months as it actually covers.
 *
 * Before this existed, the record-payment modal wrote whatever amount the
 * landlord typed as a single row against a single chosen month. A tenant
 * handing over ₱30,000 against ₱10,000 rent landed the whole amount on one
 * month — that month's balance went to −₱20,000, and September/October sat
 * unpaid until the landlord noticed and entered two more payments by hand.
 *
 * **Rows out, not database writes.** Same shape as MoveInPaymentBreakdown's
 * `rows()`: this returns billing_period/amount pairs for the caller to
 * persist inside its own transaction. It is deliberately a sibling of that
 * class rather than a shared base — MoveInPaymentBreakdown also carries a
 * deposit split that has nothing to do with this, and the two are triggered
 * from different forms with different validation.
 */
class RentPaymentAllocator
{
    /**
     * Same ceiling as MoveInPaymentBreakdown, same reason: without it, a
     * large amount against a small rent keeps minting monthly rows until the
     * arithmetic runs out of money to place.
     */
    private const MAX_ADVANCE_MONTHS = 60;

    /**
     * @param  Collection  $periods  RentLedger::periods() — every month the ledger knows about, each with
     *                               `period` (Carbon, start-of-month) and `balance`. Settled months have a
     *                               balance of 0 and are skipped; a part-paid month, past or future,
     *                               takes only what it still owes.
     * @param  Carbon  $startMonth  The month the landlord selected in the form, start-of-month.
     * @param  float  $monthlyRent  Full rent for any month the ledger has no row for yet.
     * @param  float  $amount  The amount being recorded.
     * @return array<int, array{billing_period: Carbon, amount: float}>
     */
    public static function allocate(
        Collection $periods,
        Carbon $startMonth,
        float $monthlyRent,
        float $amount,
    ): array {
        $remaining = round(max(0.0, $amount), 2);

        if ($remaining <= 0.0) {
            return [];
        }

        // Each month owes what the ledger says it still owes. A month the ledger
        // has no row for yet is untouched, so it owes the full rent. Keyed once
        // so the loop is a lookup, not a scan per month.
        $balances = $periods->mapWithKeys(
            fn (array $p) => [$p['period']->format('Y-m') => round(max(0.0, (float) $p['balance']), 2)]
        );

        $rows = [];
        $cursor = $startMonth->copy();

        for ($month = 0; $month < self::MAX_ADVANCE_MONTHS && $remaining > 0.0; $month++) {
            $due = $balances->get($cursor->format('Y-m'), $monthlyRent);
            $slice = round(min($remaining, $due), 2);

            if ($slice > 0.0) {
                $rows[] = ['billing_period' => $cursor->copy(), 'amount' => $slice];
                $remaining = round($remaining - $slice, 2);
            }
            // $slice is 0 for a month that is already fully settled: it is
            // skipped rather than billed a second time, and the loop moves on.

            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        // Past the cap, whatever is left lands on the final month as a
        // credit rather than being silently dropped — same rule as
        // MoveInPaymentBreakdown for the same reason.
        if ($remaining > 0.0 && $rows !== []) {
            $rows[count($rows) - 1]['amount'] = round($rows[count($rows) - 1]['amount'] + $remaining, 2);
        }

        return $rows;
    }
}
