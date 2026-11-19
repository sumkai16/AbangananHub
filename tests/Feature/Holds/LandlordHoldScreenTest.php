<?php

namespace Tests\Feature\Holds;

use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The landlord's side of an unpaid-rent hold: report from the contract modal, see the status, lift their own hold. */
class LandlordHoldScreenTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Cache::flush();
        $this->makeUserWithRole('Admin');
    }

    private function modal($landlord, $reservation)
    {
        return $this->actingAs($landlord)->get(route('landlord.contracts.show', $reservation), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function test_the_modal_offers_a_report_button_only_while_rent_is_overdue(): void
    {
        $t = $this->makeTenancy();

        $this->modal($t['landlord'], $t['reservation'])
            ->assertOk()
            ->assertSee('Report unpaid rent')
            ->assertSee('At least 10 characters');

        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makePayment($t['reservation'], ['billing_period' => $month, 'amount' => 5000]);
        }

        $this->modal($t['landlord'], $t['reservation'])->assertOk()->assertDontSee('Report unpaid rent');
    }

    public function test_the_owner_can_report_and_the_hold_waits_for_an_admin(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.holds.store', $t['reservation']), ['reason' => 'Three months unpaid, not answering.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $hold = TenantHold::firstOrFail();
        $this->assertSame(TenantHold::PENDING, $hold->status);
        $this->assertSame($t['landlord']->user_id, $hold->landlord_id);
        $this->assertFalse($t['tenant']->isOnHold(), 'nothing bites until an admin confirms');
    }

    public function test_a_short_reason_is_refused_and_nothing_is_saved(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.holds.store', $t['reservation']), ['reason' => 'late'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, TenantHold::count());
    }

    public function test_a_tenant_with_no_overdue_rent_cannot_be_reported(): void
    {
        $t = $this->makeTenancy();
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makePayment($t['reservation'], ['billing_period' => $month, 'amount' => 5000]);
        }

        $this->actingAs($t['landlord'])
            ->post(route('landlord.holds.store', $t['reservation']), ['reason' => 'Three months unpaid, not answering.'])
            ->assertSessionHas('error');

        $this->assertSame(0, TenantHold::count());
    }

    public function test_someone_elses_tenancy_is_forbidden(): void
    {
        $t = $this->makeTenancy();
        $stranger = $this->makeLandlord();

        $this->actingAs($stranger)
            ->post(route('landlord.holds.store', $t['reservation']), ['reason' => 'Three months unpaid, not answering.'])
            ->assertForbidden();

        $this->assertSame(0, TenantHold::count());
    }

    public function test_a_second_report_for_the_same_tenancy_is_blocked(): void
    {
        $t = $this->makeTenancy();
        $url = route('landlord.holds.store', $t['reservation']);

        $this->actingAs($t['landlord'])->post($url, ['reason' => 'Three months unpaid, not answering.']);
        $this->actingAs($t['landlord'])->post($url, ['reason' => 'Still nothing from the tenant.'])->assertSessionHas('error');

        $this->assertSame(1, TenantHold::count());
    }

    public function test_the_modal_shows_the_status_chip_instead_of_the_button(): void
    {
        $t = $this->makeTenancy();
        $hold = app(HoldService::class)->flag($t['landlord'], $t['reservation'], 'Three months unpaid, not answering.');

        $this->modal($t['landlord'], $t['reservation'])
            ->assertSee('Pending review')
            ->assertSee('Three months unpaid, not answering.')
            ->assertSee('Lift hold')
            ->assertDontSee('Report unpaid rent');

        app(HoldService::class)->confirm($hold, $this->makeUserWithRole('Admin'));

        $this->modal($t['landlord'], $t['reservation'])->assertSee('On hold');
    }

    public function test_a_landlord_can_lift_their_own_hold_but_not_someone_elses(): void
    {
        $t = $this->makeTenancy();
        $hold = app(HoldService::class)->flag($t['landlord'], $t['reservation'], 'Three months unpaid, not answering.');
        $stranger = $this->makeLandlord();

        $this->actingAs($stranger)->post(route('landlord.holds.release', $hold))->assertForbidden();
        $this->assertSame(TenantHold::PENDING, $hold->fresh()->status);

        $this->actingAs($t['landlord'])->post(route('landlord.holds.release', $hold))->assertRedirect()->assertSessionHas('success');

        $fresh = $hold->fresh();
        $this->assertSame(TenantHold::RELEASED, $fresh->status);
        $this->assertSame('landlord', $fresh->release_reason);
    }
}
