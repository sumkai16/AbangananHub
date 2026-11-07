<?php

namespace Tests\Feature;

use App\Http\Middleware\LandlordPropertyScope;
use App\Models\LandlordTenant;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The sidebar's Property filter: remembered in the session, applied to the landlord's list pages, the
 * dashboard and analytics, and overridden by a page's own Property dropdown.
 */
class LandlordPropertyScopeTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;

    private Property $north;

    private Property $south;

    protected function setUp(): void
    {
        parent::setUp();

        if (! LandlordPropertyScope::ENABLED) {
            $this->markTestSkipped('The sidebar property filter is switched off (LandlordPropertyScope::ENABLED).');
        }

        Mail::fake();

        $this->landlord = $this->makeLandlord();
        $this->north = $this->makeProperty($this->landlord, ['title' => 'North House']);
        $this->south = $this->makeProperty($this->landlord, ['title' => 'South House']);
        // The sidebar offers approved properties only.
        Property::whereIn('property_id', [$this->north->property_id, $this->south->property_id])->update(['verification_status' => 'Approved']);

        // North has 2 units, South has 1.
        $this->makeUnit($this->north);
        $this->makeUnit($this->north);
        $this->makeUnit($this->south);
    }

    /** A tenant on this landlord's list with a running lease in the given property. */
    private function tenantIn(Property $property, string $first): LandlordTenant
    {
        $tenant = $this->makeTenant();
        $tenant->update(['first_name' => $first]);
        $row = LandlordTenant::ensureFor($this->landlord->user_id, $tenant->user_id);
        $unit = $this->makeUnit($property);
        Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-01-01',
            'target_move_in_date' => '2026-01-01', 'target_move_out_date' => '2027-01-01', 'agreed_monthly_rent' => 3000,
        ]);

        return $row;
    }

    private function listedTenantIds(array $query = []): array
    {
        return $this->actingAs($this->landlord)->get(route('landlord.tenants.index', $query))
            ->assertOk()->viewData('rows')->pluck('row.landlord_tenant_id')->all();
    }

    public function test_the_sidebar_choice_is_remembered_and_filters_the_tenants_page(): void
    {
        $inNorth = $this->tenantIn($this->north, 'Nora');
        $inSouth = $this->tenantIn($this->south, 'Sam');

        // All properties to begin with.
        $this->assertEqualsCanonicalizing([$inNorth->landlord_tenant_id, $inSouth->landlord_tenant_id], $this->listedTenantIds());

        $this->actingAs($this->landlord)->from(route('landlord.tenants.index', ['status' => 'living', 'page' => 2]))
            ->post(route('landlord.property-scope.update'), ['property' => $this->north->property_id])
            ->assertRedirect(route('landlord.tenants.index', ['status' => 'living']));

        $this->assertSame([$inNorth->landlord_tenant_id], $this->listedTenantIds());
        // Pages with no ?property of their own pick the choice up too.
        $this->actingAs($this->landlord)->get(route('landlord.dashboard'))->assertOk()->assertViewHas('totalUnits', 3);
    }

    public function test_a_page_dropdown_changes_the_shared_choice_and_all_clears_it(): void
    {
        $inNorth = $this->tenantIn($this->north, 'Nora');
        $inSouth = $this->tenantIn($this->south, 'Sam');

        $this->assertSame([$inSouth->landlord_tenant_id], $this->listedTenantIds(['property' => $this->south->property_id]));
        $this->assertSame($this->south->property_id, LandlordPropertyScope::current($this->landlord->user_id));
        // Still south on the next page, with no ?property.
        $this->assertSame([$inSouth->landlord_tenant_id], $this->listedTenantIds());

        // "All Properties" on the page sends an empty value.
        $this->assertEqualsCanonicalizing([$inNorth->landlord_tenant_id, $inSouth->landlord_tenant_id], $this->listedTenantIds(['property' => '']));
        $this->assertNull(LandlordPropertyScope::current($this->landlord->user_id));
    }

    public function test_dashboard_and_analytics_follow_the_filter(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.dashboard'))->assertOk()->assertViewHas('totalUnits', 3);

        $this->actingAs($this->landlord)->post(route('landlord.property-scope.update'), ['property' => $this->south->property_id]);

        $this->actingAs($this->landlord)->get(route('landlord.dashboard'))->assertOk()
            ->assertViewHas('totalUnits', 1)
            ->assertViewHas('properties', fn ($p) => $p->pluck('property_id')->all() === [$this->south->property_id]);

        $this->actingAs($this->landlord)->get(route('landlord.analytics.index'))->assertOk()
            ->assertViewHas('perProperty', fn ($rows) => collect($rows)->pluck('property_id')->all() === [$this->south->property_id]);
    }

    public function test_someone_elses_property_is_never_applied(): void
    {
        $other = $this->makeProperty($this->makeLandlord());

        $this->actingAs($this->landlord)->post(route('landlord.property-scope.update'), ['property' => $other->property_id]);
        $this->assertNull(LandlordPropertyScope::current($this->landlord->user_id));

        $this->actingAs($this->landlord)->get(route('landlord.dashboard', ['property' => $other->property_id]))
            ->assertOk()->assertViewHas('totalUnits', 3);
    }

    public function test_the_sidebar_lists_the_properties_and_hides_with_only_one(): void
    {
        $this->actingAs($this->landlord)->get(route('landlord.dashboard'))
            ->assertOk()->assertSee('All properties')->assertSee('North House')->assertSee('South House');

        $solo = $this->makeLandlord();
        $this->makeProperty($solo, ['title' => 'Only House']);
        Property::where('landlord_id', $solo->user_id)->update(['verification_status' => 'Approved']);

        $this->actingAs($solo)->get(route('landlord.dashboard'))->assertOk()->assertDontSee('All properties');
    }
}
