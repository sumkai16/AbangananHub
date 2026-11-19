<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The Properties list opens a property by its card or row; View Units and Edit are gone. */
class PropertyListActionsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_a_published_property_has_no_view_units_or_edit_buttons(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Sunrise House']);
        $this->makeUnit($property);

        $this->actingAs($landlord)->get(route('landlord.properties.index'))
            ->assertOk()
            ->assertSee('Sunrise House')
            ->assertDontSee('View Units')
            ->assertDontSee('Edit property')
            ->assertDontSee(route('properties.edit', $property), false)
            // The way in: the card and the table's View details both go to the property page.
            ->assertSee(route('landlord.properties.show', $property), false)
            ->assertSee('View details');
    }

    public function test_the_whole_card_is_one_link_on_every_screen_width(): void
    {
        $landlord = $this->makeLandlord();
        $this->makeProperty($landlord);

        // The title's stretched link covers the card: it was phone-only (max-sm:) before.
        $this->actingAs($landlord)->get(route('landlord.properties.index'))
            ->assertOk()
            ->assertSee('after:absolute after:inset-0', false)
            ->assertDontSee('max-sm:after:absolute', false);
    }

    public function test_a_draft_keeps_continue_setup_and_delete(): void
    {
        $landlord = $this->makeLandlord();
        $draft = $this->makeProperty($landlord, ['publication_status' => 'Draft', 'verification_status' => 'Pending', 'title' => 'Half done']);

        $this->actingAs($landlord)->get(route('landlord.properties.index'))
            ->assertOk()
            ->assertSee('Half done')
            ->assertSee('Continue Setup')
            ->assertSee(route('properties.wizard.resume', $draft), false)
            ->assertSee('Delete draft');
    }
}
