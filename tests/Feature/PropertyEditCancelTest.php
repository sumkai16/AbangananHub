<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The Edit property page's Cancel goes back to the property's own page, not to the My Properties list: the
 * landlord got to this form from that page, and its "changes send the listing back for review" note is about it.
 */
class PropertyEditCancelTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $landlord;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        $this->landlord = $this->makeLandlord();
        $this->property = $this->makeProperty($this->landlord);
    }

    /** The href of the footer Cancel link, read out of the rendered page. */
    private function cancelHref(string $html): ?string
    {
        preg_match('/<a\s+href="([^"]+)"[^>]*>\s*Cancel\s*<\/a>/s', $html, $m);

        return isset($m[1]) ? html_entity_decode($m[1]) : null;
    }

    public function test_cancel_returns_to_the_property_page_not_the_list(): void
    {
        $html = $this->actingAs($this->landlord)
            ->get(route('properties.edit', $this->property))
            ->assertOk()
            ->getContent();

        $this->assertSame(route('landlord.properties.show', $this->property), $this->cancelHref($html));
        $this->assertNotSame(route('landlord.properties.index'), $this->cancelHref($html));
    }

    public function test_the_properties_breadcrumb_still_goes_to_the_list(): void
    {
        // The breadcrumb is the way up a level; only the Cancel button moved.
        $this->actingAs($this->landlord)
            ->get(route('properties.edit', $this->property))
            ->assertOk()
            ->assertSee('href="'.route('landlord.properties.index').'"', false)
            ->assertSee('href="'.route('landlord.properties.show', $this->property).'"', false);
    }

    public function test_another_landlord_cannot_open_the_edit_page(): void
    {
        $this->actingAs($this->makeLandlord())
            ->get(route('properties.edit', $this->property))
            ->assertForbidden();
    }
}
