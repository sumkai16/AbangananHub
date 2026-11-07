<?php

namespace Tests\Feature;

use App\Models\PropertyActivity;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The Activity Log tab and the Overview's recent activity on a landlord's property page. */
class PropertyActivityPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function landlordWithProperty(): array
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Sunrise House']);
        // The fixtures wrote their own lines; start each test from a known, empty history.
        PropertyActivity::where('property_id', $property->property_id)->delete();

        return [$landlord, $property];
    }

    /** Just the Activity Log tab (the Overview's recent activity shows the latest lines whatever the chip says). */
    private function logTab(string $html): string
    {
        $from = strpos($html, 'id="activity"');
        $to = strpos($html, 'History before November 2026');

        return substr($html, $from, $to - $from);
    }

    private function say(int $propertyId, string $role, string $summary, array $extra = []): PropertyActivity
    {
        return PropertyActivity::create(array_merge([
            'property_id' => $propertyId, 'actor_role' => $role, 'actor_name' => ucfirst($role),
            'action' => 'test.line', 'summary' => $summary, 'created_at' => now(),
        ], $extra));
    }

    public function test_the_owner_sees_the_history_newest_first_with_a_chip_per_actor(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        $this->say($property->property_id, 'landlord', 'Edited the listing (title)', ['created_at' => now()->subHours(3)]);
        $this->say($property->property_id, 'tenant', 'Ana Reyes left a 5-star review', ['created_at' => now()->subHour()]);
        $this->say($property->property_id, 'admin', 'Listing approved', ['created_at' => now()->subMinutes(5)]);

        $html = $this->actingAs($landlord)
            ->get(route('landlord.properties.show', $property))
            ->assertOk()
            ->assertSee('Edited the listing (title)')
            ->assertSee('Ana Reyes left a 5-star review')
            ->assertSee('Listing approved')
            ->assertSee('You')->assertSee('Tenant')->assertSee('Admin')
            ->assertSee('History before November 2026 is partial')
            ->getContent();

        $this->assertLessThan(strpos($html, 'Ana Reyes left'), strpos($html, 'Listing approved'), 'newest first');
    }

    public function test_the_chips_narrow_the_list_and_count_each_group(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        $this->say($property->property_id, 'landlord', 'Mine one');
        $this->say($property->property_id, 'landlord', 'Mine two');
        $this->say($property->property_id, 'tenant', 'Tenant thing');
        $this->say($property->property_id, 'admin', 'Admin thing');
        $this->say($property->property_id, 'system', 'Job thing');

        $url = route('landlord.properties.show', $property);

        $tab = fn (string $query) => $this->logTab($this->actingAs($landlord)->get($url.$query)->assertOk()->getContent());

        $you = $tab('?activity=you');
        $this->assertStringContainsString('Mine one', $you);
        $this->assertStringContainsString('Mine two', $you);
        foreach (['Tenant thing', 'Admin thing', 'Job thing'] as $other) {
            $this->assertStringNotContainsString($other, $you);
        }

        $tenants = $tab('?activity=tenants');
        $this->assertStringContainsString('Tenant thing', $tenants);
        $this->assertStringNotContainsString('Mine one', $tenants);

        $admin = $tab('?activity=admin');
        $this->assertStringContainsString('Admin thing', $admin);
        $this->assertStringNotContainsString('Tenant thing', $admin);

        // All includes the system's own lines; the chip counts read 5 / 2 / 1 / 1.
        $all = $tab('');
        $this->assertStringContainsString('Job thing', $all);
        $this->assertMatchesRegularExpression('/All\s*<span[^>]*>5<\/span>.*You\s*<span[^>]*>2<\/span>.*Tenants\s*<span[^>]*>1<\/span>.*Admin\s*<span[^>]*>1<\/span>/s', $all);
    }

    public function test_a_hostile_filter_falls_back_to_all(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        $this->say($property->property_id, 'tenant', 'Visible anyway');
        $url = route('landlord.properties.show', $property);

        $this->actingAs($landlord)->get($url.'?activity[]=you')->assertOk()->assertSee('Visible anyway');
        $this->actingAs($landlord)->get($url.'?activity=%27%20OR%201%3D1')->assertOk()->assertSee('Visible anyway');
        $this->actingAs($landlord)->get($url.'?activity_page=abc')->assertOk();
    }

    public function test_the_log_is_paged_and_a_page_link_reopens_the_tab(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        foreach (range(1, 25) as $n) {
            $this->say($property->property_id, 'landlord', "Line number {$n}", ['created_at' => now()->subMinutes(30 - $n)]);
        }
        $url = route('landlord.properties.show', $property);

        $first = $this->actingAs($landlord)->get($url)->assertOk();
        $first->assertSee('Line number 25')->assertDontSee('Line number 5<')->assertSee('activity_page=2', false);

        $second = $this->actingAs($landlord)->get($url.'?activity_page=2')->assertOk();
        $second->assertSee('Line number 1<', false)->assertSee("tab: 'activity_log'", false);
    }

    public function test_the_overview_shows_the_latest_three(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        foreach (['Oldest', 'Older', 'Newer', 'Newest'] as $i => $text) {
            $this->say($property->property_id, 'landlord', "{$text} thing", ['created_at' => now()->subHours(10 - $i)]);
        }

        $html = $this->actingAs($landlord)->get(route('landlord.properties.show', $property))->assertOk()->getContent();

        // The Overview card holds the latest 3; the Activity Log tab below holds all 4 (so "Oldest" appears once).
        $this->assertSame(2, substr_count($html, 'Newest thing'));
        $this->assertSame(1, substr_count($html, 'Oldest thing'));
        $this->assertStringContainsString('View all', $html);
    }

    public function test_the_empty_history_says_what_will_appear(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()->assertSee('Nothing recorded yet');
    }

    public function test_another_landlord_is_refused(): void
    {
        [, $property] = $this->landlordWithProperty();
        $this->say($property->property_id, 'tenant', 'Private to the owner');

        $this->actingAs($this->makeLandlord())
            ->get(route('landlord.properties.show', $property))
            ->assertForbidden()->assertDontSee('Private to the owner');
    }

    public function test_a_line_that_names_a_unit_opens_the_unit_and_text_is_escaped(): void
    {
        [$landlord, $property] = $this->landlordWithProperty();
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);
        PropertyActivity::where('property_id', $property->property_id)->delete();
        $this->say($property->property_id, 'landlord', 'Edited unit "Bed A" (rent)', ['unit_id' => $unit->unit_id]);
        $this->say($property->property_id, 'tenant', '<script>alert(1)</script> left a 5-star review');

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()
            ->assertSee('openUnit('.$unit->unit_id.')', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_tenancy_line_links_to_the_lease_and_shows_no_contact_details(): void
    {
        config(['broadcasting.default' => 'null']);
        [$landlord, $property] = $this->landlordWithProperty();
        $tenant = $this->makeTenant();
        $tenant->update(['contact_number' => '09175550142']);
        $unit = $this->makeUnit($property, ['unit_label' => 'Bed A']);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $property->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Occupied', 'reservation_date' => '2026-11-01', 'target_move_in_date' => '2026-11-01',
            'agreed_monthly_rent' => 5000, 'rent_due_day' => 5,
        ]);

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))
            ->assertOk()
            ->assertSee($tenant->first_name.' '.$tenant->last_name.' moved in to', false)
            ->assertSee(route('landlord.contracts.index', ['open' => $reservation->reservation_id]), false)
            ->assertDontSee((string) $tenant->email)
            ->assertDontSee('09175550142');
    }
}
