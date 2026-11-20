<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The map pin picker warns "outside Cebu" unless the reverse geocoder says the province is Cebu. Cebu City, Mandaue and
 * Lapu-Lapu are independent of the province, so the geocoder gives them no province and the warning showed for the
 * three biggest rental cities. The picker (resources/js/maps/location-picker.js) reads this list from the page.
 */
class LocationPickerCebuCitiesTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_the_independent_cities_are_listed_and_are_real_allowed_cities(): void
    {
        $this->assertEqualsCanonicalizing(['Cebu City', 'Lapu-Lapu City', 'Mandaue City'], config('cebu.independent_cities'));

        // A name that is not an allowed city could never be matched by the picker, so the warning would still show.
        foreach (config('cebu.independent_cities') as $city) {
            $this->assertContains($city, config('cebu.lgus'));
        }
    }

    public function test_the_property_edit_page_hands_the_list_to_the_picker(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)
            ->get(route('properties.edit', $property))
            ->assertOk()
            ->assertSee('id="city-municipality-picker"', false)
            ->assertSee('data-hucs=', false)
            ->assertSee('Mandaue City', false);
    }
}
