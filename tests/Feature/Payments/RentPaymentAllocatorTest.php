<?php

namespace Tests\Feature\Payments;

use App\Support\RentPaymentAllocator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * RentPaymentAllocator turns one lump of rent money into per-month rows, and it
 * does it with floats. The two things that must never happen are a centavo
 * being lost or invented, and money landing on the wrong month. Pure function,
 * so no database: the periods collection is built by hand in the shape
 * RentLedger::periods() returns (a `period` and a `balance` per month).
 */
class RentPaymentAllocatorTest extends TestCase
{
    private const RENT = 5000.0;

    /** @param array<string, float> $balances 'Y-m' => balance still owed */
    private function unsettled(array $balances): Collection
    {
        return collect($balances)->map(fn (float $balance, string $month) => [
            'period'  => Carbon::createFromFormat('!Y-m', $month),
            'balance' => $balance,
        ])->values();
    }

    private function month(string $ym): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $ym);
    }

    /** Whole centavos, so a float-sum comparison can never hide a lost cent. */
    private function cents(array $rows): int
    {
        return (int) array_sum(array_map(fn (array $r) => (int) round($r['amount'] * 100), $rows));
    }

    /** @return array<int, array{0: string, 1: float}> */
    private function plain(array $rows): array
    {
        return array_map(fn (array $r) => [$r['billing_period']->format('Y-m-d'), $r['amount']], $rows);
    }

    public function test_an_amount_equal_to_one_month_clears_exactly_one_month(): void
    {
        $rows = RentPaymentAllocator::allocate($this->unsettled(['2026-08' => 5000.0]), $this->month('2026-08'), self::RENT, 5000.0);

        $this->assertSame([['2026-08-01', 5000.0]], $this->plain($rows));
    }

    public function test_a_larger_amount_spills_into_the_following_months_in_order(): void
    {
        $unsettled = $this->unsettled(['2026-08' => 5000.0, '2026-09' => 5000.0, '2026-10' => 5000.0]);

        $rows = RentPaymentAllocator::allocate($unsettled, $this->month('2026-08'), self::RENT, 7500.50);

        $this->assertSame([['2026-08-01', 5000.0], ['2026-09-01', 2500.5]], $this->plain($rows));
        $this->assertSame(750050, $this->cents($rows));
    }

    public function test_a_part_payment_stays_inside_its_month(): void
    {
        $rows = RentPaymentAllocator::allocate($this->unsettled(['2026-08' => 5000.0]), $this->month('2026-08'), self::RENT, 1234.56);

        $this->assertSame([['2026-08-01', 1234.56]], $this->plain($rows));
    }

    public function test_the_start_month_is_only_charged_its_remaining_balance(): void
    {
        // August already had 3,000 paid into it, so only 2,000 is still owed.
        $rows = RentPaymentAllocator::allocate($this->unsettled(['2026-08' => 2000.0]), $this->month('2026-08'), self::RENT, 5000.0);

        $this->assertSame([['2026-08-01', 2000.0], ['2026-09-01', 3000.0]], $this->plain($rows));
    }

    public function test_every_month_takes_only_what_the_ledger_says_it_still_owes(): void
    {
        // A later month can be part-paid too (prepaid, or paid offline earlier).
        // It must take its own remaining balance, not a fresh full rent.
        $periods = $this->unsettled(['2026-08' => 1000.0, '2026-09' => 1000.0, '2026-10' => 5000.0]);

        $rows = RentPaymentAllocator::allocate($periods, $this->month('2026-08'), self::RENT, 11000.0);

        // November is not in the ledger yet, so it owes a full rent.
        $this->assertSame([['2026-08-01', 1000.0], ['2026-09-01', 1000.0], ['2026-10-01', 5000.0], ['2026-11-01', 4000.0]], $this->plain($rows));
    }

    public function test_a_settled_month_is_skipped_not_billed_a_second_time(): void
    {
        $periods = $this->unsettled(['2026-08' => 0.0, '2026-09' => 5000.0]);

        $rows = RentPaymentAllocator::allocate($periods, $this->month('2026-08'), self::RENT, 5000.0);

        $this->assertSame([['2026-09-01', 5000.0]], $this->plain($rows));
    }

    public function test_an_overpaid_month_with_a_negative_balance_counts_as_settled(): void
    {
        $periods = $this->unsettled(['2026-08' => -2000.0, '2026-09' => 5000.0]);

        $rows = RentPaymentAllocator::allocate($periods, $this->month('2026-08'), self::RENT, 3000.0);

        $this->assertSame([['2026-09-01', 3000.0]], $this->plain($rows));
    }

    public function test_a_start_month_missing_from_the_ledger_is_billed_at_full_rent(): void
    {
        $rows = RentPaymentAllocator::allocate(collect(), $this->month('2026-11'), self::RENT, 6000.0);

        $this->assertSame([['2026-11-01', 5000.0], ['2026-12-01', 1000.0]], $this->plain($rows));
    }

    public function test_the_split_always_sums_to_exactly_the_amount_entered(): void
    {
        $unsettled = $this->unsettled(['2026-08' => 3333.33]);

        // Awkward centavo rents and amounts, including the classic float traps.
        foreach ([[3333.33, 10000.00], [3333.33, 9999.99], [1111.11, 3333.33], [5000.0, 0.01], [5000.0, 0.1 + 0.2], [4999.99, 24999.95], [2750.25, 8250.75]] as [$rent, $amount]) {
            $rows = RentPaymentAllocator::allocate($unsettled, $this->month('2026-08'), $rent, $amount);

            $this->assertSame((int) round($amount * 100), $this->cents($rows), "Rent {$rent}, amount {$amount}: a centavo was lost or invented.");
        }
    }

    public function test_a_large_amount_fills_each_month_to_its_due_and_the_last_month_takes_the_remainder(): void
    {
        $rows = RentPaymentAllocator::allocate($this->unsettled(['2026-08' => 2000.0]), $this->month('2026-08'), self::RENT, 17500.0);

        $this->assertSame(
            [['2026-08-01', 2000.0], ['2026-09-01', 5000.0], ['2026-10-01', 5000.0], ['2026-11-01', 5000.0], ['2026-12-01', 500.0]],
            $this->plain($rows)
        );
    }

    public function test_billing_periods_roll_over_the_year_end(): void
    {
        $rows = RentPaymentAllocator::allocate(collect(), $this->month('2026-12'), self::RENT, 10000.0);

        $this->assertSame([['2026-12-01', 5000.0], ['2027-01-01', 5000.0]], $this->plain($rows));
    }

    public function test_nothing_or_a_negative_amount_allocates_nothing(): void
    {
        $unsettled = $this->unsettled(['2026-08' => 5000.0]);

        $this->assertSame([], RentPaymentAllocator::allocate($unsettled, $this->month('2026-08'), self::RENT, 0.0));
        $this->assertSame([], RentPaymentAllocator::allocate($unsettled, $this->month('2026-08'), self::RENT, -500.0));
        $this->assertSame([], RentPaymentAllocator::allocate($unsettled, $this->month('2026-08'), self::RENT, 0.004));
    }

    public function test_past_the_sixty_month_cap_the_rest_lands_on_the_last_month_instead_of_vanishing(): void
    {
        // 400,000 against 5,000 rent is 80 months of money; only 60 months are minted.
        $rows = RentPaymentAllocator::allocate(collect(), $this->month('2026-08'), self::RENT, 400000.0);

        $this->assertCount(60, $rows);
        $this->assertSame(5000.0, $rows[58]['amount']);
        $this->assertSame(105000.0, $rows[59]['amount'], 'The 20 months over the cap are credited to the final month.');
        $this->assertSame(40000000, $this->cents($rows));
    }

    public function test_a_zero_rent_cannot_loop_or_drop_money(): void
    {
        // A tenancy with no rent configured: nothing is billable, so nothing can be
        // placed. The amount must not be silently half-allocated.
        $rows = RentPaymentAllocator::allocate(collect(), $this->month('2026-08'), 0.0, 1000.0);

        $this->assertSame([], $rows);
    }
}
