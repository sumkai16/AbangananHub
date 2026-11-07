<?php

namespace Tests\Feature;

use App\Models\DepositCharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A security deposit is the tenant's money, owed back at move-out, so Analytics keeps it out of Revenue and
 * shows it on its own "Deposits Held" card. Only the part the landlord keeps (a deposit charge) is revenue.
 */
class LandlordAnalyticsDepositTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function seedTenancy(): array
    {
        $t = $this->makeTenancy();
        $this->makePayment($t['reservation'], ['amount' => 5000]);
        $this->makePayment($t['reservation'], ['payment_type' => 'Deposit', 'billing_period' => null, 'amount' => 3000]);

        DepositCharge::create([
            'reservation_id' => $t['reservation']->reservation_id,
            'amount'         => 500,
            'category'       => 'Cleaning',
            'description'    => 'Deep clean',
            'charged_at'     => now()->toDateString(),
        ]);
        // A voided charge is neither revenue nor taken off the held deposit.
        DepositCharge::create([
            'reservation_id' => $t['reservation']->reservation_id,
            'amount'         => 900,
            'category'       => 'Damage',
            'description'    => 'Entered by mistake',
            'charged_at'     => now()->toDateString(),
            'voided_at'      => now(),
            'void_reason'    => 'duplicate',
        ]);

        return $t;
    }

    public function test_revenue_excludes_the_deposit_but_counts_what_was_kept_from_it(): void
    {
        $t = $this->seedTenancy();

        $this->actingAs($t['landlord'])->get(route('landlord.analytics.index'))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['revenue'] === 5500.0 && $s['depositsHeld'] === 2500.0)
            ->assertViewHas('revenueTrend', fn ($trend) => $trend->last()['value'] === 5500.0)
            ->assertViewHas('perProperty', fn ($rows) => $rows->first()['revenue'] === 5500.0)
            ->assertSee('Deposits Held')
            ->assertSee('₱2,500.00');
    }

    public function test_a_completed_tenancy_no_longer_holds_its_deposit(): void
    {
        $t = $this->seedTenancy();
        $t['reservation']->update(['rental_status' => 'Completed']);

        $this->actingAs($t['landlord'])->get(route('landlord.analytics.index'))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['depositsHeld'] === 0.0 && $s['revenue'] === 5500.0);
    }

    public function test_the_csv_export_uses_the_same_revenue(): void
    {
        $t = $this->seedTenancy();

        $csv = $this->actingAs($t['landlord'])->get(route('landlord.analytics.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('5500.00', $csv);
        $this->assertStringNotContainsString('8500.00', $csv);
    }
}
