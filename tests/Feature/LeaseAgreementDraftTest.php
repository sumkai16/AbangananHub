<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Generate a rental agreement from a typed name, a property and a unit, with nothing saved. */
class LeaseAgreementDraftTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_a_typed_name_a_property_and_a_unit_make_a_printable_agreement_and_save_nothing(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property, ['rental_fee' => 3500]);
        $before = Reservation::count();

        $this->actingAs($landlord)
            ->get(route('landlord.leases.agreement', [
                'tenant_name' => 'Juan Dela Cruz', 'property_id' => $property->property_id, 'unit_id' => $unit->unit_id,
                'move_in_date' => today()->addWeek()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Print / Save PDF');

        $this->assertSame($before, Reservation::count());
    }

    public function test_the_form_opens_without_an_agreement(): void
    {
        $this->actingAs($this->makeLandlord())->get(route('landlord.leases.agreement'))
            ->assertOk()->assertDontSee('Print / Save PDF');
    }

    public function test_another_landlords_property_is_refused(): void
    {
        $other = $this->makeProperty($this->makeLandlord());
        $unit = $this->makeUnit($other);

        $this->actingAs($this->makeLandlord())
            ->get(route('landlord.leases.agreement', [
                'tenant_name' => 'Juan', 'property_id' => $other->property_id, 'unit_id' => $unit->unit_id,
                'move_in_date' => today()->toDateString(),
            ]))
            ->assertSessionHasErrors('property_id');
    }
}
