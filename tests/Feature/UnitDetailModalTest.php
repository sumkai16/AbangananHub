<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The unit details modal is shared by the Units page and a property's own Units tab, fed by one payload
 * (PropertyUnit::detailPayload) that must work with no lazy loading.
 */
class UnitDetailModalTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function propertyWithUnits(): array
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Sunrise House']);
        $a = $this->makeUnit($property, ['unit_label' => 'Bed A', 'rental_fee' => 4500]);
        $b = $this->makeUnit($property, ['unit_label' => 'Bed B', 'rental_fee' => 5200, 'availability_status' => 'Occupied']);

        return [$landlord, $property, $a, $b];
    }

    public function test_the_payload_never_lazy_loads_when_the_page_eager_loads_its_units(): void
    {
        [, $property] = $this->propertyWithUnits();

        // Several units loaded together: lazy loading is switched off for them outside production.
        $loaded = Property::with([
            'media', 'units.media', 'units.amenities',
            'units.reservations' => fn ($q) => $q->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)->with('tenant:user_id,first_name,last_name'),
        ])->findOrFail($property->property_id);
        $loaded->units->each->setRelation('property', $loaded);

        $payloads = $loaded->units->map(fn ($unit) => $unit->detailPayload($loaded, 'property'));

        $this->assertCount(2, $payloads);
        $this->assertSame('Bed A', $payloads[0]['label']);
        $this->assertSame('Sunrise House', $payloads[0]['property']);
        $this->assertSame(4500.0, $payloads[0]['rent']);
    }

    public function test_the_edit_link_remembers_where_to_come_back_to(): void
    {
        [, $property, $unit] = $this->propertyWithUnits();
        $unit->load('media', 'amenities', 'property.media');

        $this->assertStringContainsString('return_to=property', $unit->detailPayload($property, 'property')['edit_url']);
        $this->assertStringContainsString('return_to=units', $unit->detailPayload($property)['edit_url']);
    }

    public function test_the_payload_names_the_current_tenant_but_not_a_past_one(): void
    {
        config(['broadcasting.default' => 'null']);
        [, $property, , $occupied] = $this->propertyWithUnits();
        $current = $this->makeTenant();
        $past = $this->makeTenant();
        foreach ([[$past, 'Completed'], [$current, 'Occupied']] as [$tenant, $status]) {
            Reservation::create([
                'tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'unit_id' => $occupied->unit_id,
                'rental_status' => $status, 'reservation_date' => '2026-08-01', 'target_move_in_date' => '2026-08-01',
                'agreed_monthly_rent' => 5200, 'rent_due_day' => 5,
            ]);
        }

        $unit = $occupied->fresh()->load(['media', 'amenities', 'property.media', 'reservations.tenant']);
        $payload = $unit->detailPayload($property);

        $this->assertSame($current->first_name.' '.$current->last_name, $payload['tenant']);
        $this->assertContains(['Tenant', $current->first_name.' '.$current->last_name], $payload['facts']);
    }

    public function test_the_property_page_wires_the_modal_to_every_unit_and_hides_its_own_property_link(): void
    {
        [$landlord, $property, $a, $b] = $this->propertyWithUnits();

        $html = $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()
            ->assertSee('open-unit-detail', false)
            ->assertSee('openUnit('.$a->unit_id.')', false)
            ->assertSee('openUnit('.$b->unit_id.')', false)
            ->assertSee('View details')
            ->getContent();

        // A property page does not offer "View property" on its own units.
        $modal = substr($html, strpos($html, 'x-on:open-unit-detail.window'));
        $this->assertStringNotContainsString('modal.property_url', $modal);
        // The payload rides along once, keyed by unit, so the Activity Log can reuse it.
        $this->assertSame(1, substr_count($html, 'unitPayloads: '));
    }

    public function test_the_modal_works_on_a_property_with_one_unit(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()->assertSee('openUnit('.$unit->unit_id.')', false);
    }

    public function test_the_units_page_uses_the_shared_modal_and_a_wider_grid(): void
    {
        [$landlord, $property] = $this->propertyWithUnits();

        $this->actingAs($landlord)->get(route('landlord.units.index'))
            ->assertOk()
            ->assertSee('open-unit-detail', false)
            ->assertSee('modal.property_url', false)          // here the modal offers "View property"
            ->assertSee('return_to=units', false)
            ->assertDontSee('openModal(', false)
            ->assertSee('gap-3 sm:gap-5', false);
    }

    public function test_every_export_button_says_csv(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeUnit($this->makeProperty($landlord));

        foreach (['landlord.units.index', 'landlord.reservations.index', 'landlord.tenants.index', 'landlord.analytics.index'] as $route) {
            $this->actingAs($landlord)->get(route($route))->assertOk()->assertSee('Export CSV')->assertDontSee('Export Report');
        }
    }

    public function test_the_property_page_units_tab_has_room_and_a_navy_rent(): void
    {
        [$landlord, $property] = $this->propertyWithUnits();

        $html = $this->actingAs($landlord)->get(route('landlord.properties.show', $property))->assertOk()->getContent();

        // Smaller cards (Nov 7 2026): up to four across and a 16:10 photo.
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4', $html);
        $this->assertStringContainsString('aspect-[16/10]', $html);
        // Red on a price reads as money owed (DESIGN §6n).
        $this->assertDoesNotMatchRegularExpression('/text-\[#DC2626\][^>]*>\s*₱4,500/', $html);
    }
}
