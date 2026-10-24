<?php

namespace Tests\Feature\Payments;

use App\Models\LateFeeWaiver;
use App\Services\RentLedger;
use App\Support\LeaseTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Grace days and the one-time late fee. Tenancy: moved in 1 Aug 2026, rent 5,000 due on the 5th, "today" is
 * 20 Oct 2026. The rules are read from the lease snapshot, never from the live property.
 */
class RentLedgerLateFeeTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.rent_overdue_grace_days' => 0]);
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    }

    /** A tenancy whose lease froze the given rules. */
    private function tenancy(?array $lateFee, int $grace = 2): array
    {
        $t = $this->makeTenancy();
        $t['reservation']->forceFill(['lease_snapshot' => ['grace_days' => $grace, 'late_fee' => $lateFee]])->save();

        return $t;
    }

    private function period($reservation, string $month): array
    {
        return RentLedger::for($reservation->fresh())->periods()
            ->first(fn ($p) => $p['period']->format('Y-m') === $month);
    }

    public function test_rent_paid_inside_the_grace_window_has_no_fee(): void
    {
        $t = $this->tenancy(['type' => 'percent', 'value' => 10]);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 5000, 'paid_at' => '2026-10-07 10:00:00']);

        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(0.0, $oct['late_fee']);
        $this->assertSame('paid', $oct['status']);
    }

    public function test_rent_paid_after_the_window_still_owes_the_fee(): void
    {
        $t = $this->tenancy(['type' => 'percent', 'value' => 10]);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 5000, 'paid_at' => '2026-10-12 10:00:00']);

        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(500.0, $oct['late_fee']);
        $this->assertSame(5500.0, $oct['expected']);
        $this->assertSame(500.0, $oct['balance']);
        $this->assertSame('overdue', $oct['status']);
    }

    public function test_a_part_payment_before_the_deadline_is_still_late(): void
    {
        $t = $this->tenancy(['type' => 'fixed', 'value' => 250]);
        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 2000, 'paid_at' => '2026-10-06 10:00:00']);

        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(250.0, $oct['late_fee']);
        $this->assertSame(3250.0, $oct['balance']);
    }

    public function test_no_fee_before_the_grace_deadline_passes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay()); // due 5th, grace 2 -> deadline end of the 7th
        $t = $this->tenancy(['type' => 'percent', 'value' => 10]);

        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(0.0, $oct['late_fee']);
        $this->assertSame(5000.0, $oct['expected']);
    }

    public function test_the_fee_is_one_time_not_daily(): void
    {
        $t = $this->tenancy(['type' => 'fixed', 'value' => 100]);
        $first = $this->period($t['reservation'], '2026-09')['late_fee'];

        $this->travelTo(now()->addDays(20));

        $this->assertSame(100.0, $first);
        $this->assertSame(100.0, $this->period($t['reservation'], '2026-09')['late_fee']);
    }

    public function test_a_waiver_zeroes_the_fee_for_that_month_only(): void
    {
        $t = $this->tenancy(['type' => 'fixed', 'value' => 100]);
        LateFeeWaiver::create(['reservation_id' => $t['reservation']->reservation_id, 'billing_period' => '2026-09-01', 'waived_by' => $t['landlord']->user_id]);

        $sep = $this->period($t['reservation'], '2026-09');
        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(0.0, $sep['late_fee']);
        $this->assertTrue($sep['late_fee_waived']);
        $this->assertSame(5000.0, $sep['expected']);
        $this->assertSame(100.0, $oct['late_fee']);
    }

    public function test_a_lease_without_a_snapshot_never_gets_a_fee_even_if_the_property_has_one(): void
    {
        $t = $this->makeTenancy();
        $t['reservation']->property->forceFill(['late_fee_type' => 'percent', 'late_fee_value' => 10, 'rent_grace_days' => 3])->save();

        $oct = $this->period($t['reservation'], '2026-10');

        $this->assertSame(0.0, $oct['late_fee']);
        $this->assertSame(5000.0, $oct['expected']);
    }

    public function test_editing_the_property_does_not_change_an_issued_lease(): void
    {
        $t = $this->tenancy(['type' => 'fixed', 'value' => 100]);
        $t['reservation']->property->forceFill(['late_fee_type' => 'percent', 'late_fee_value' => 50])->save();

        $this->assertSame(100.0, $this->period($t['reservation'], '2026-09')['late_fee']);
    }

    public function test_issuing_a_lease_freezes_the_property_rules(): void
    {
        $t = $this->makeTenancy();
        $t['reservation']->property->forceFill(['rent_grace_days' => 4, 'late_fee_type' => 'percent', 'late_fee_value' => 8])->save();

        $snapshot = LeaseTerms::snapshot($t['reservation']->fresh());

        $this->assertSame(4, $snapshot['grace_days']);
        $this->assertSame(['type' => 'percent', 'value' => 8.0], $snapshot['late_fee']);
    }

    public function test_the_summary_counts_the_fee_in_what_is_outstanding(): void
    {
        $t = $this->tenancy(['type' => 'fixed', 'value' => 100]);

        $this->assertSame(15300.0, RentLedger::for($t['reservation']->fresh())->summary()['outstanding']); // 3 x (5,000 + 100)
    }
}
