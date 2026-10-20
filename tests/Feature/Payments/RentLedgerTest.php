<?php

namespace Tests\Feature\Payments;

use App\Models\DepositCharge;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The ledger decides what a tenant owes and what a landlord is owed, so its
 * arithmetic is pinned here against a fixed "today". Tenancy: moved in
 * 1 Aug 2026, rent 5,000 due on the 5th, no grace days.
 */
class RentLedgerTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.rent_overdue_grace_days' => 0]);
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    }

    private function summary($reservation): array
    {
        return RentLedger::for($reservation->fresh())->summary();
    }

    public function test_an_unpaid_tenancy_owes_every_month_since_move_in(): void
    {
        $t = $this->makeTenancy();

        $summary = $this->summary($t['reservation']);

        $this->assertSame(3, $summary['periodCount']);       // Aug, Sep, Oct
        $this->assertSame(15000.0, $summary['outstanding']);
        $this->assertSame(3, $summary['overdueCount']);
        $this->assertSame('overdue', $summary['paymentStatus']);
    }

    public function test_a_full_payment_clears_its_month_only(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01', 'amount' => 5000]);

        $summary = $this->summary($t['reservation']);

        $this->assertSame(10000.0, $summary['outstanding']);
        $this->assertSame(5000.0, $summary['monthlyCollected']);
        $this->assertSame(2, $summary['overdueCount']);
    }

    public function test_a_part_payment_leaves_the_balance_owing(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01', 'amount' => 5000]);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-09-01', 'amount' => 2000]);

        $this->assertSame(8000.0, $this->summary($t['reservation'])['outstanding']); // 3,000 Sep + 5,000 Oct
    }

    public function test_a_voided_payment_counts_for_nothing(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01', 'status' => 'Voided']);

        $summary = $this->summary($t['reservation']);

        $this->assertSame(15000.0, $summary['outstanding']);
        $this->assertSame(0.0, $summary['monthlyCollected']);
    }

    public function test_a_pending_checkout_counts_for_nothing_until_it_settles(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01', 'status' => 'Pending', 'paid_at' => null]);

        $this->assertSame(15000.0, $this->summary($t['reservation'])['outstanding']);
    }

    public function test_rent_paid_through_paymongo_counts_the_same_as_cash(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['billing_period' => '2026-08-01', 'status' => 'Paid', 'payment_method' => 'GCash', 'payout_status' => 'Pending Payout']);

        $this->assertSame(10000.0, $this->summary($t['reservation'])['outstanding']);
    }

    public function test_paying_a_future_month_is_prepaid_not_arrears(): void
    {
        $t = $this->makeTenancy();
        foreach (['2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01'] as $month) {
            $this->makePayment($t['reservation'], ['billing_period' => $month]);
        }

        $summary = $this->summary($t['reservation']);

        $this->assertSame(0.0, $summary['outstanding']);
        $this->assertSame(1, $summary['prepaidCount']);
        $this->assertSame(20000.0, $summary['monthlyCollected']);
    }

    public function test_a_month_before_its_due_day_is_due_not_overdue(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3)->startOfDay());
        $t = $this->makeTenancy();
        foreach (['2026-08-01', '2026-09-01'] as $month) {
            $this->makePayment($t['reservation'], ['billing_period' => $month]);
        }

        $summary = $this->summary($t['reservation']);

        $this->assertSame(0, $summary['overdueCount']);
        $this->assertSame('due', $summary['nextDue']['status']);
    }

    public function test_a_tenancy_that_has_not_started_owes_nothing_yet(): void
    {
        $t = $this->makeTenancy(['target_move_in_date' => '2026-12-01', 'reservation_date' => '2026-10-01']);

        $summary = $this->summary($t['reservation']);

        $this->assertSame(1, $summary['periodCount']);        // its first month is still billable
        $this->assertSame(0, $summary['overdueCount']);
    }

    public function test_the_deposit_is_held_money_not_rent_income(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'billing_period' => null, 'amount' => 5000, 'status' => 'Held', 'payment_method' => 'GCash']);

        $summary = $this->summary($t['reservation']);

        $this->assertSame(5000.0, $summary['depositCollected']);
        $this->assertSame(5000.0, $summary['depositRemaining']);
        $this->assertSame(0.0, $summary['monthlyCollected']);
        $this->assertSame(15000.0, $summary['outstanding'], 'A deposit must not pay off rent.');
    }

    public function test_deposit_charges_reduce_the_remaining_deposit_unless_voided(): void
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'billing_period' => null, 'amount' => 5000, 'status' => 'Held', 'payment_method' => 'GCash']);
        $live = DepositCharge::create(['reservation_id' => $t['reservation']->reservation_id, 'amount' => 1500, 'category' => 'Damage', 'description' => 'Door', 'charged_at' => today(), 'charged_by' => $t['landlord']->user_id]);
        DepositCharge::create(['reservation_id' => $t['reservation']->reservation_id, 'amount' => 800, 'category' => 'Cleaning', 'description' => 'Wrong tenant', 'charged_at' => today(), 'charged_by' => $t['landlord']->user_id, 'voided_at' => now(), 'void_reason' => 'wrong_tenancy']);

        $summary = $this->summary($t['reservation']);

        $this->assertSame(1500.0, $summary['depositCharged']);
        $this->assertSame(3500.0, $summary['depositRemaining']);
        $this->assertNotNull($live);
    }
}
