<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A landlord's standard extra terms and the no-deposit lease wording
 * (plans/stakeholder-feedback-pass.md, phase 1).
 */
class AgreementTermsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;

    private User $tenant;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        // Values the edit form posts back unchanged, so a save that only touches the terms is not "dirty".
        $this->property = $this->makeProperty($this->landlord, [
            'house_rules'                  => [],
            'water_included'               => false,
            'electricity_included'         => false,
            'internet_included'            => false,
            'association_fees_included'    => false,
            'utilities_separately_metered' => false,
        ]);
    }

    private function editPayload(array $overrides = []): array
    {
        $property = $this->property->fresh();

        return array_merge([
            'title'                => $property->title,
            'description'          => $property->description,
            'property_type'        => $property->property_type,
            'living_arrangement'   => $property->living_arrangement,
            'occupancy_preference' => $property->occupancy_preference,
            'address'              => $property->address,
            'city_municipality'    => $property->city_municipality,
            'barangay'             => $property->barangay,
            'latitude'             => (string) $property->latitude,
            'longitude'            => (string) $property->longitude,
        ], $overrides);
    }

    private function negotiatingReservation(array $unitAttributes = []): Reservation
    {
        $unit = $this->makeUnit($this->property, $unitAttributes);

        return Reservation::create([
            'tenant_id'           => $this->tenant->user_id,
            'property_id'         => $this->property->property_id,
            'unit_id'             => $unit->unit_id,
            'rental_status'       => 'Under Negotiation',
            'reservation_date'    => today(),
            'target_move_in_date' => today()->addWeek(),
        ]);
    }

    public function test_saving_standard_terms_does_not_send_an_approved_listing_back_to_review(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('properties.update', $this->property), $this->editPayload([
                'default_lease_terms' => 'Quiet hours start at 10pm.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Property updated.');

        $property = $this->property->fresh();
        $this->assertSame('Quiet hours start at 10pm.', $property->default_lease_terms);
        $this->assertSame('Approved', $property->verification_status);
    }

    public function test_changing_listing_details_still_requeues_the_review(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('properties.update', $this->property), $this->editPayload([
                'title' => 'A Brand New Listing Title',
            ]))
            ->assertSessionHas('success', 'Property updated. It\'s back in the approval queue.');

        $this->assertSame('Pending', $this->property->fresh()->verification_status);
    }

    public function test_standard_terms_are_capped_and_owner_only(): void
    {
        $this->actingAs($this->landlord)
            ->put(route('properties.update', $this->property), $this->editPayload([
                'default_lease_terms' => str_repeat('a', 2001),
            ]))
            ->assertSessionHasErrors('default_lease_terms');

        $this->actingAs($this->makeLandlord())
            ->put(route('properties.update', $this->property), $this->editPayload([
                'default_lease_terms' => 'Not yours.',
            ]))
            ->assertForbidden();

        $this->assertNull($this->property->fresh()->default_lease_terms);
    }

    public function test_the_send_agreement_form_is_prefilled_with_the_property_terms(): void
    {
        $this->property->update(['default_lease_terms' => 'No visitors after nine in the evening.']);
        $this->negotiatingReservation();

        $this->actingAs($this->landlord)
            ->get(route('landlord.reservations.index'))
            ->assertOk()
            ->assertSee('name="agreement_terms_notes"', false)
            ->assertSee('No visitors after nine in the evening.');
    }

    public function test_the_prefilled_terms_land_on_the_lease_the_tenant_signs(): void
    {
        $this->property->update(['default_lease_terms' => 'Keep the hallway clear.']);
        $reservation = $this->negotiatingReservation();

        $this->actingAs($this->landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc'             => '1',
            'agreement_terms_notes' => $this->property->default_lease_terms,
        ])->assertSessionHas('success');

        $this->actingAs($this->tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Keep the hallway clear.');
    }

    public function test_a_unit_without_a_deposit_reads_none_on_the_lease(): void
    {
        $reservation = $this->negotiatingReservation(['security_deposit' => 0]);

        $this->actingAs($this->landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc' => '1',
        ])->assertSessionHas('success');

        $this->actingAs($this->tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSeeInOrder(['Security deposit', 'None'])
            ->assertDontSee('The Tenant pays a security deposit');
    }

    public function test_a_unit_with_a_deposit_still_shows_the_amount(): void
    {
        $reservation = $this->negotiatingReservation(['security_deposit' => 3500]);

        $this->actingAs($this->landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc' => '1',
        ])->assertSessionHas('success');

        $this->actingAs($this->tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('The Tenant pays a security deposit of')
            ->assertSee('3,500');
    }
}
