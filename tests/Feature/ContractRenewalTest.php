<?php

namespace Tests\Feature;

use App\Models\LeaseRenewal;
use App\Models\Notification;
use App\Models\Reservation;
use App\Services\RentLedger;
use App\Support\RentPaymentAllocator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Renewing a contract: a later end date and the rent from a chosen month on, without touching months already
 * billed. plans/landlord-tenants-contracts-direct-payments.md, phase 4.
 */
class ContractRenewalTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private $tenant;

    private Reservation $contract;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');

        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->landlord));
        $this->contract = Reservation::create([
            'tenant_id' => $this->tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-06-01',
            'target_move_in_date' => '2026-06-01', 'target_move_out_date' => '2026-11-30',
            'agreed_monthly_rent' => 5000, 'rent_due_day' => 1,
        ]);
    }

    private function renew(array $overrides = [])
    {
        return $this->actingAs($this->landlord)->post(route('landlord.contracts.renew', $this->contract), $overrides + [
            'new_move_out_date' => '2027-05-31',
            'new_monthly_rent' => 5500,
            'rent_effective_from' => '2026-12',
        ]);
    }

    public function test_renewing_extends_the_end_date_and_tells_the_tenant(): void
    {
        $this->renew()->assertRedirect(route('landlord.contracts.index', ['open' => $this->contract->reservation_id]))
            ->assertSessionHas('success');

        $this->assertSame('2027-05-31', $this->contract->fresh()->target_move_out_date->toDateString());

        $renewal = LeaseRenewal::where('reservation_id', $this->contract->reservation_id)->sole();
        $this->assertSame('5500.00', $renewal->new_monthly_rent);
        $this->assertSame('2026-12-01', $renewal->rent_effective_from->toDateString());
        $this->assertSame($this->landlord->user_id, $renewal->renewed_by);

        $this->assertTrue(Notification::where('user_id', $this->tenant->user_id)->where('title', 'Your lease was renewed')->exists());
    }

    public function test_the_new_rent_applies_from_its_month_and_earlier_months_keep_the_old_rent(): void
    {
        $this->renew()->assertSessionHas('success');

        Carbon::setTestNow('2027-01-15 10:00:00');
        $periods = RentLedger::for($this->contract->fresh())->periods()->keyBy(fn ($p) => $p['period']->format('Y-m'));

        $this->assertSame(5000.0, $periods['2026-10']['rent']);
        $this->assertSame(5000.0, $periods['2026-11']['rent']);
        $this->assertSame(5500.0, $periods['2026-12']['rent']);
        $this->assertSame(5500.0, $periods['2027-01']['rent']);
        $this->assertSame(5500.0, $this->contract->fresh()->currentRent());
        $this->assertSame(5000.0, $this->contract->fresh()->monthlyRent(), 'The original agreed rent is unchanged.');
    }

    public function test_the_new_rent_cannot_start_in_a_past_month(): void
    {
        $this->renew(['rent_effective_from' => '2026-09'])
            ->assertRedirect(route('landlord.contracts.index', ['open' => $this->contract->reservation_id]))
            ->assertSessionHas('error');

        $this->assertSame(0, LeaseRenewal::count());
        $this->assertSame('2026-11-30', $this->contract->fresh()->target_move_out_date->toDateString());
    }

    public function test_the_new_end_date_must_be_later_than_the_current_one(): void
    {
        $this->renew(['new_move_out_date' => '2026-11-15'])->assertSessionHas('error');

        $this->assertSame(0, LeaseRenewal::count());
    }

    public function test_another_landlord_cannot_renew(): void
    {
        $this->actingAs($this->makeLandlord())->post(route('landlord.contracts.renew', $this->contract), [
            'new_move_out_date' => '2027-05-31', 'new_monthly_rent' => 1, 'rent_effective_from' => '2026-12',
        ])->assertForbidden();

        $this->assertSame(0, LeaseRenewal::count());
    }

    public function test_an_ended_contract_cannot_be_renewed(): void
    {
        $this->contract->update(['rental_status' => 'Completed']);

        $this->renew()->assertSessionHas('error');

        $this->assertSame(0, LeaseRenewal::count());
    }

    public function test_renew_is_offered_only_near_or_past_the_end_date(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.contracts.show', $this->contract))
            ->assertOk()->assertSee('Save renewal');

        $this->contract->update(['target_move_out_date' => '2027-10-31']);

        $this->actingAs($this->landlord)->get(route('landlord.contracts.show', $this->contract))
            ->assertOk()->assertDontSee('Save renewal');
    }

    public function test_a_lump_payment_into_renewed_months_is_split_at_each_months_rent(): void
    {
        $rows = RentPaymentAllocator::allocate(
            collect(),
            Carbon::parse('2026-11-01'),
            fn (Carbon $month) => $month->gte(Carbon::parse('2026-12-01')) ? 5500.0 : 5000.0,
            10500,
        );

        $this->assertSame([5000.0, 5500.0], array_column($rows, 'amount'));
        $this->assertSame('2026-12-01', $rows[1]['billing_period']->toDateString());
    }
}
