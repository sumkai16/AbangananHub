<?php

namespace Tests\Feature;

use App\Models\PropertyActivity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The admin's property page shows the same history the landlord sees (PropertyActivity, written by the observers),
 * read-only and without links into landlord-only pages.
 */
class AdminPropertyActivityTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    /** A property with a clean history: the observers log its own creation, which these tests do not want to count. */
    private function quietProperty()
    {
        $property = $this->makeProperty($this->makeLandlord());
        PropertyActivity::where('property_id', $property->property_id)->delete();

        return $property;
    }

    private function line($property, string $role, string $summary, string $action = 'unit.updated'): PropertyActivity
    {
        return PropertyActivity::create([
            'property_id' => $property->property_id, 'actor_role' => $role, 'actor_name' => 'Someone',
            'action' => $action, 'summary' => $summary, 'created_at' => now(),
        ]);
    }

    public function test_the_admin_sees_the_properties_recent_activity(): void
    {
        $property = $this->quietProperty();
        $this->line($property, 'landlord', 'Raised the rent of Room 1 to ₱4,800');
        $this->line($property, 'tenant', 'Ana left a 5-star review', 'review.created');

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.catalogue.properties.show', $property))->assertOk()
            ->assertSee('Recent activity')
            ->assertSee('Raised the rent of Room 1 to ₱4,800')
            ->assertSee('Ana left a 5-star review');
    }

    public function test_a_landlord_line_says_landlord_not_you_for_the_admin(): void
    {
        $property = $this->quietProperty();
        $this->line($property, 'landlord', 'Edited the description');

        $html = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.catalogue.properties.show', $property))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>\s*Landlord\s*<\/span>\s*<span[^>]*>\s*<span[^>]*>Edited the description/u', $html);
    }

    public function test_only_the_latest_ten_lines_show_and_another_propertys_history_never_does(): void
    {
        $property = $this->quietProperty();
        $other = $this->quietProperty();
        foreach (range(1, 12) as $i) {
            $this->line($property, 'system', "Line number {$i}");
        }
        $this->line($other, 'system', 'Belongs to another property');

        $r = $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.catalogue.properties.show', $property))->assertOk();

        $r->assertSee('Line number 12')->assertSee('Line number 3')
            ->assertDontSee('Line number 2<', false)->assertDontSee('Line number 1<', false)
            ->assertDontSee('Belongs to another property')
            ->assertSee('Latest 10 of 12');
    }

    public function test_a_property_with_no_history_says_so(): void
    {
        $property = $this->quietProperty();

        $this->actingAs($this->makeUserWithRole('Admin'))->get(route('admin.catalogue.properties.show', $property))->assertOk()
            ->assertSee('Recent activity')->assertSee('Nothing has happened on this listing yet');
    }
}
