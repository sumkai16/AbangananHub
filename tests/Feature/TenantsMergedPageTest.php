<?php

namespace Tests\Feature;

use App\Models\LandlordTenant;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Tenants, Contracts and the Sent & signed list became one page: one row per tenant with their current contract,
 * stage chips instead of separate pages. plans/merge-tenants-contracts-policies.md.
 */
class TenantsMergedPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    /** A tenant on the landlord's list, optionally with one contract in $status. */
    private function tenantWith(User $landlord, ?string $status, array $extra = [], ?string $unitLabel = null): LandlordTenant
    {
        $tenant = $this->makeTenant();
        $row = LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);

        if ($status !== null) {
            $unit = $this->makeUnit($this->makeProperty($landlord, ['title' => 'Place '.$tenant->user_id]), $unitLabel ? ['unit_label' => $unitLabel] : []);
            Reservation::create($extra + [
                'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
                'rental_status' => $status, 'reservation_date' => '2026-06-01',
                'target_move_in_date' => '2026-06-01', 'target_move_out_date' => '2027-05-31',
                'agreed_monthly_rent' => 5000, 'rent_due_day' => 1,
            ]);
        }

        return $row;
    }

    private function names(LandlordTenant $row): string
    {
        return $row->tenant->first_name;
    }

    public function test_every_stage_chip_shows_only_its_tenants_and_the_counts_match(): void
    {
        $landlord = $this->makeLandlord();
        $living = $this->tenantWith($landlord, 'Occupied');
        $moving = $this->tenantWith($landlord, 'Reserved', ['target_move_in_date' => '2026-11-15']);
        $ending = $this->tenantWith($landlord, 'Occupied', ['target_move_out_date' => '2026-11-13']);
        $none = $this->tenantWith($landlord, null);
        $former = $this->tenantWith($landlord, 'Completed');

        // The Add contract modal lists every tenant by name, so read the rows the page was given, not its HTML.
        $listed = fn (array $query) => $this->actingAs($landlord)->get(route('landlord.tenants.index', $query))->assertOk()
            ->viewData('rows')->pluck('tenant.user_id')->all();
        $ids = fn (LandlordTenant ...$rows) => array_map(fn ($r) => $r->tenant_id, $rows);

        $this->assertEqualsCanonicalizing($ids($living, $ending), $listed(['status' => 'living']));
        $this->assertSame($ids($moving), $listed(['status' => 'moving']));
        $this->assertSame($ids($ending), $listed(['status' => 'ending']));
        $this->assertSame($ids($none), $listed(['status' => 'none']));
        $this->assertSame($ids($former), $listed(['status' => 'former']));
        $this->assertCount(5, $listed([]));
        $this->actingAs($landlord)->get(route('landlord.tenants.index', ['status' => 'ending']))->assertSee('Renew');

        $page = $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk();
        $counts = $page->viewData('counts');
        $this->assertSame(['all' => 5, 'living' => 2, 'moving' => 1, 'ending' => 1, 'no-agreement' => 3, 'none' => 1, 'former' => 1], $counts);
    }

    public function test_a_tenant_with_an_ended_and_a_running_contract_shows_the_running_one(): void
    {
        $landlord = $this->makeLandlord();
        $row = $this->tenantWith($landlord, 'Completed');
        $unit = $this->makeUnit($this->makeProperty($landlord, ['title' => 'Newer Place']));
        Reservation::create([
            'tenant_id' => $row->tenant_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-08-01',
            'target_move_in_date' => '2026-08-01', 'target_move_out_date' => '2027-07-31', 'agreed_monthly_rent' => 7000,
        ]);

        $page = $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk();

        $this->assertSame('living', $page->viewData('rows')->first()['stage']);
        $page->assertSee('Newer Place');
        $this->assertSame(1, $page->viewData('counts')['living']);
        $this->assertSame(0, $page->viewData('counts')['former']);
    }

    public function test_the_waiting_box_lists_tenants_with_no_contract_and_hides_when_filtering(): void
    {
        $landlord = $this->makeLandlord();
        $waiting = $this->tenantWith($landlord, null);
        $this->tenantWith($landlord, 'Occupied');

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))
            ->assertOk()->assertSee('Waiting for a contract')->assertSee($this->names($waiting));
        $this->actingAs($landlord)->get(route('landlord.tenants.index', ['status' => 'living']))
            ->assertOk()->assertDontSee('Waiting for a contract');
        $this->actingAs($landlord)->get(route('landlord.tenants.index', ['search' => 'zzznobody']))
            ->assertOk()->assertDontSee('Waiting for a contract')->assertSee('No tenants match');
    }

    public function test_search_finds_a_tenant_by_unit_label(): void
    {
        $landlord = $this->makeLandlord();
        $room = $this->tenantWith($landlord, 'Occupied', [], 'Room 7Q');
        $other = $this->tenantWith($landlord, 'Occupied', [], 'Room 2A');

        $this->actingAs($landlord)->get(route('landlord.tenants.index', ['search' => '7Q']))
            ->assertOk()->assertSee($this->names($room))->assertDontSee($this->names($other));
    }

    public function test_another_landlords_tenants_never_show(): void
    {
        $landlord = $this->makeLandlord();
        $stranger = $this->tenantWith($this->makeLandlord(), 'Occupied');

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))
            ->assertOk()->assertDontSee($this->names($stranger));
    }

    public function test_unsigned_agreements_are_a_link_to_reservations_not_rows(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $asking = $this->makeTenant();
        Reservation::create([
            'tenant_id' => $asking->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Pending Rental Agreement', 'reservation_date' => '2026-10-10',
        ]);

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))
            ->assertOk()
            ->assertSee('1 agreement sent and waiting for a signature')
            ->assertSee(route('landlord.reservations.index', ['stage' => 'agreement']), false)
            ->assertDontSee($asking->first_name);
    }

    public function test_one_modal_opens_on_the_current_contract_and_lists_the_others(): void
    {
        $landlord = $this->makeLandlord();
        $row = $this->tenantWith($landlord, 'Completed');
        $oldUnit = $this->makeUnit($this->makeProperty($landlord, ['title' => 'First Place']));
        $newUnit = $this->makeUnit($this->makeProperty($landlord, ['title' => 'Second Place']));
        $old = Reservation::where('tenant_id', $row->tenant_id)->firstOrFail();
        $current = Reservation::create([
            'tenant_id' => $row->tenant_id, 'property_id' => $newUnit->property_id, 'unit_id' => $newUnit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-08-01',
            'target_move_in_date' => '2026-08-01', 'target_move_out_date' => '2027-07-31', 'agreed_monthly_rent' => 7000,
        ]);

        $this->actingAs($landlord)->get(route('landlord.tenants.show', $row))
            ->assertOk()
            ->assertSee('Second Place')
            ->assertSee('Other contracts with you')
            ->assertSee('contracts\\/'.$old->reservation_id, false)
            ->assertSee('End contract');

        $this->actingAs($this->makeLandlord())->get(route('landlord.tenants.show', $row))->assertForbidden();
        $this->assertNotNull($current->reservation_id);
    }

    public function test_a_tenant_without_a_contract_opens_their_details_with_add_contract(): void
    {
        $landlord = $this->makeLandlord();
        $row = $this->tenantWith($landlord, null);

        $this->actingAs($landlord)->get(route('landlord.tenants.show', $row))
            ->assertOk()->assertSee('No contracts yet')->assertSee('Add contract')
            ->assertSee(route('landlord.tenants.index', ['new' => 1, 'tenant' => $row->landlord_tenant_id]));
    }

    public function test_the_renew_flag_opens_the_modal_on_the_renew_form(): void
    {
        $landlord = $this->makeLandlord();
        $row = $this->tenantWith($landlord, 'Occupied', ['target_move_out_date' => '2026-11-13']);

        $this->actingAs($landlord)->get(route('landlord.tenants.show', ['landlordTenant' => $row, 'renew' => 1]))
            ->assertOk()->assertSee('renewing: true', false);
        $this->actingAs($landlord)->get(route('landlord.tenants.show', $row))
            ->assertOk()->assertSee('renewing: false', false);
    }

    public function test_old_contracts_links_redirect_with_their_filters_and_open_modal(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->get('/landlord/contracts')->assertRedirect(route('landlord.tenants.index'));
        $this->actingAs($landlord)->get('/landlord/contracts?status=active&open=5')
            ->assertRedirect(route('landlord.tenants.index', ['open' => 5, 'status' => 'living']));
        $this->actingAs($landlord)->get('/landlord/contracts?status=awaiting')->assertRedirect(route('landlord.tenants.index', ['status' => 'moving']));
        $this->actingAs($landlord)->get('/landlord/contracts?status=ended')->assertRedirect(route('landlord.tenants.index', ['status' => 'former']));
        $this->actingAs($landlord)->get('/landlord/contracts?lease_missing=1')->assertRedirect(route('landlord.tenants.index', ['status' => 'no-agreement']));
        $this->actingAs($landlord)->get('/landlord/contracts?new=1&tenant=3')->assertRedirect(route('landlord.tenants.index', ['new' => 1, 'tenant' => 3]));
        $this->actingAs($landlord)->get('/landlord/tenants/walk-in/create')->assertRedirect('/landlord/tenants?new=1');
    }

    public function test_the_sidebar_has_tenants_but_no_contracts_or_policies_item(): void
    {
        $landlord = $this->makeLandlord();
        $html = $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('landlord.leases.index').'" ', explode('</aside>', $html)[0]);
        $this->assertStringNotContainsString('>Contracts<', explode('</aside>', $html)[0]);
        $this->actingAs($landlord)->get(route('landlord.leases.index'))->assertOk()->assertSee('Lease policies');
    }

    public function test_the_list_stays_flat_in_queries_as_tenants_grow(): void
    {
        $landlord = $this->makeLandlord();
        $this->tenantWith($landlord, 'Occupied');
        $this->tenantWith($landlord, 'Rental Agreement Signed');
        $count = function () use ($landlord) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($landlord)->get(route('landlord.tenants.index'))->assertOk();

            return count(DB::getQueryLog());
        };
        $count(); // warm up: first-request caches (roles, permissions) are not per-tenant cost
        $small = $count();

        foreach (range(1, 6) as $i) {
            $this->tenantWith($landlord, $i % 2 ? 'Occupied' : 'Rental Agreement Signed');
        }

        $this->assertSame($small, $count(), 'Query count must not grow with the number of tenants.');
    }
}
