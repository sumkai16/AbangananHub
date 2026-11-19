<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The Lease strip on the rent ledger: a lease of 12 months or fewer fits on one row from md up, so a lease that
 * crosses a year (Jul 2026 to Jun 2027) shows both years side by side instead of hiding the second behind
 * "See more". Longer leases keep the stacked years. Phones keep six bars a row either way.
 */
class LeaseStripOneRowTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function ledgerHtml(string $moveIn, string $moveOut): string
    {
        Carbon::setTestNow('2026-11-07 10:00:00');

        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $tenancy = Reservation::create([
            'tenant_id' => $this->makeTenant()->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => $moveIn,
            'target_move_in_date' => $moveIn, 'target_move_out_date' => $moveOut,
            'agreed_monthly_rent' => 7000, 'rent_due_day' => 1,
        ]);

        return $this->actingAs($landlord)->get(route('landlord.payments.ledger', $tenancy))->assertOk()->getContent();
    }

    public function test_a_twelve_month_lease_across_two_years_shows_both_years_on_one_row(): void
    {
        $html = $this->ledgerHtml('2026-07-01', '2027-06-30');

        $this->assertStringContainsString('Rent by month, 2026', $html);
        $this->assertStringContainsString('Rent by month, 2027', $html);
        // Row width follows the months in the lease; each year group follows its own months.
        $this->assertStringContainsString('style="--n: 12"', $html);
        $this->assertSame(2, substr_count($html, 'style="--m: 6"'));
        $this->assertStringContainsString('md:[grid-template-columns:repeat(var(--m),minmax(0,1fr))]', $html);
        // The second year is still folded away on a phone, and "See more" is only for phones.
        $this->assertMatchesRegularExpression('/<div\s+x-show="moreYears"\s+x-cloak\s+class="[^"]*md:!block[^"]*"[^>]*>\s*<h3[^>]*>2027<\/h3>/', $html);
        $this->assertMatchesRegularExpression('/class="md:hidden mt-3 self-start/', $html);
    }

    public function test_a_short_lease_keeps_the_width_of_a_full_row_per_month(): void
    {
        $html = $this->ledgerHtml('2026-07-01', '2026-12-31');

        $this->assertStringContainsString('style="--n: 6"', $html);
        $this->assertStringNotContainsString('See more', $html);
    }

    public function test_a_long_lease_keeps_the_stacked_years_and_see_more(): void
    {
        $html = $this->ledgerHtml('2024-07-01', '2027-06-30');

        $this->assertStringNotContainsString('--n:', $html);
        $this->assertStringContainsString('See more', $html);
        $this->assertStringContainsString('sm:grid-cols-12', $html);
    }
}
