<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Shared-room listings pick a bed on a seat box (mockup option 5): one tile per unit, taken ones disabled, and
 * no unit cards underneath. Other property types keep the card list.
 */
class BedSeatPickerTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function listing(string $type, array $statuses): string
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['property_type' => $type]);
        foreach ($statuses as $i => $status) {
            $this->makeUnit($property, ['unit_label' => 'Bed '.chr(65 + $i), 'availability_status' => $status]);
        }

        return route('properties.show', $property);
    }

    public function test_a_bedspace_with_several_beds_shows_the_seat_box_and_names_them_beds(): void
    {
        $url = $this->listing('Bedspace', ['Available', 'Reserved', 'Occupied']);

        $this->get($url)->assertOk()
            ->assertSee('data-seat-picker', false)
            ->assertSee('Beds in this property')
            ->assertSee('Number of beds')
            ->assertSee('3 beds')
            ->assertSee('Bed A, available', false)
            ->assertSee('Bed B, taken', false)
            ->assertDontSee('View all units');
    }

    public function test_a_boarding_house_names_them_rooms(): void
    {
        $url = $this->listing('Boarding House', ['Available', 'Available']);

        $this->get($url)->assertOk()
            ->assertSee('data-seat-picker', false)
            ->assertSee('Number of rooms')
            ->assertSee('Rooms in this property');
    }

    public function test_an_apartment_keeps_the_unit_cards_and_has_no_seat_box(): void
    {
        $url = $this->listing('Apartment', ['Available', 'Available']);

        $this->get($url)->assertOk()
            ->assertDontSee('data-seat-picker', false)
            ->assertSee('Units in this property')
            ->assertSee('Number of units');
    }

    public function test_a_single_bed_has_no_seat_box(): void
    {
        $url = $this->listing('Bedspace', ['Available']);

        $this->get($url)->assertOk()->assertDontSee('data-seat-picker', false);
    }
}
