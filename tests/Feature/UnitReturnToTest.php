<?php

namespace Tests\Feature;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Saving or removing a unit brings the landlord back to where they started: the property's Units tab or the
 * Units page. Only those two words are honoured.
 */
class UnitReturnToTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private array $fields = ['unit_label' => 'Bed A', 'rental_fee' => 5000, 'occupancy_limit' => 2, 'availability_status' => 'Available'];

    private function fixtures(): array
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        return [$landlord, $property, $this->makeUnit($property, ['unit_label' => 'Bed A'])];
    }

    public function test_saving_from_the_property_page_returns_to_its_units_tab(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();

        $this->actingAs($landlord)
            ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields + ['return_to' => 'property'])
            ->assertRedirect(route('landlord.properties.show', $property).'#units')
            ->assertSessionHas('success');
    }

    public function test_saving_with_no_return_to_still_goes_to_the_units_page(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();

        $this->actingAs($landlord)
            ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields)
            ->assertRedirect(route('landlord.properties.units.index', $property));
    }

    public function test_anything_else_is_ignored(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();

        foreach (['https://evil.example/phish', '//evil.example', 'PROPERTY', '../', ''] as $bad) {
            $this->actingAs($landlord)
                ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields + ['return_to' => $bad])
                ->assertRedirect(route('landlord.properties.units.index', $property));
        }

        $this->actingAs($landlord)
            ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields + ['return_to' => ['property']])
            ->assertRedirect(route('landlord.properties.units.index', $property));
    }

    public function test_the_wizard_still_wins(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();

        $this->actingAs($landlord)
            ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields + ['from' => 'wizard', 'return_to' => 'property'])
            ->assertRedirect(route('properties.wizard.units', $property));
    }

    public function test_removing_a_unit_from_the_property_page_returns_there(): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('destroy')->andReturn([]);
        $client = Mockery::mock(Cloudinary::class);
        $client->shouldReceive('uploadApi')->andReturn($api);
        $this->app->instance(Cloudinary::class, $client);

        [$landlord, $property, $unit] = $this->fixtures();

        $this->actingAs($landlord)
            ->delete(route('landlord.properties.units.destroy', [$property, $unit]), ['return_to' => 'property'])
            ->assertRedirect(route('landlord.properties.show', $property).'#units')
            ->assertSessionHas('success', 'Unit removed.');

        $this->assertDatabaseMissing('property_units', ['unit_id' => $unit->unit_id]);
    }

    public function test_the_edit_and_create_pages_carry_the_choice_and_cancel_goes_back(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();
        $back = route('landlord.properties.show', $property).'#units';

        $this->actingAs($landlord)->get(route('landlord.properties.units.edit', [$property, $unit, 'return_to' => 'property']))
            ->assertOk()
            ->assertSee('name="return_to" value="property"', false)
            ->assertSee('href="'.$back.'"', false);

        $this->actingAs($landlord)->get(route('landlord.properties.units.create', [$property, 'return_to' => 'property']))
            ->assertOk()
            ->assertSee('name="return_to" value="property"', false)
            ->assertSee('href="'.$back.'"', false);

        $this->actingAs($landlord)->get(route('landlord.properties.units.edit', [$property, $unit]))
            ->assertOk()
            ->assertDontSee('name="return_to"', false)
            ->assertSee('href="'.route('landlord.properties.units.index', $property).'"', false);
    }

    public function test_the_property_page_links_edit_add_and_remove_with_the_choice(): void
    {
        [$landlord, $property, $unit] = $this->fixtures();

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()
            ->assertSee(route('landlord.properties.units.edit', [$property, $unit, 'return_to' => 'property']), false)
            ->assertSee(route('landlord.properties.units.create', [$property, 'return_to' => 'property']), false)
            ->assertSee('name="return_to" value="property"', false)
            // The rail's Manage link and the attention list switch the tab in place instead of opening the filtered Units page.
            ->assertSee('href="#units"', false);
    }

    public function test_another_landlord_cannot_use_it_to_reach_a_property(): void
    {
        [, $property, $unit] = $this->fixtures();

        $this->actingAs($this->makeLandlord())
            ->put(route('landlord.properties.units.update', [$property, $unit]), $this->fields + ['return_to' => 'property'])
            ->assertForbidden();
    }
}
