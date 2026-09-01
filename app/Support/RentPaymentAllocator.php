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
     * @param  Collection  $unsettledPeriods  RentLedger::unsettledPeriods() — each entry carries
     *                                        `period` (Carbon, start-of-month) and `balance`.
     * @param  Carbon  $startMonth  The month the landlord selected in the form, start-of-month.
     * @param  float  $monthlyRent  Full rent for every month after the start month.
     * @param  float  $amount  The amount being recorded.
     * @return array<int, array{billing_period: Carbon, amount: float}>
     */
    public static function allocate(
        Collection $unsettledPeriods,
        Carbon $startMonth,
        float $monthlyRent,
        float $amount,
    ): array {
        $remaining = round(max(0.0, $amount), 2);

        if ($remaining <= 0.0) {
            return [];
        }

        // Only the starting month can carry a partial balance from an
        // earlier payment — everything after it is a month nobody has paid
        // into yet, so it is billed at the full rent.
        $startBalance = $unsettledPeriods->first(
            fn (array $p) => $p['period']->isSameMonth($startMonth)
        )['balance'] ?? $monthlyRent;
        $startBalance = round(max(0.0, $startBalance), 2);

        $rows = [];
        $cursor = $startMonth->copy();

        for ($month = 0; $month < self::MAX_ADVANCE_MONTHS && $remaining > 0.0; $month++) {
            $due = $month === 0 ? $startBalance : $monthlyRent;
            $slice = round(min($remaining, $due), 2);

            if ($slice > 0.0) {
                $rows[] = ['billing_period' => $cursor->copy(), 'amount' => $slice];
                $remaining = round($remaining - $slice, 2);
            }
            // $slice can be 0 only when the selected start month is already
            // fully settled — that month is skipped rather than billed for
            // nothing, and the loop moves straight to the next one.

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
