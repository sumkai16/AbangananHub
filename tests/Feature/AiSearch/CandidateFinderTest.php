<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\CandidateFinder;
use App\Services\AiSearch\IntentValidator;

class CandidateFinderTest extends AiSearchTestCase
{
    private function find(array $raw): array
    {
        return app(CandidateFinder::class)->find(app(IntentValidator::class)->validate($raw));
    }

    public function test_an_exact_search_returns_only_what_fits_with_hit_chips(): void
    {
        $this->listing('Fits', [], 4500, ['Wi-Fi']);
        $this->listing('No wifi', [], 4000);

        $out = $this->find(['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'price_max' => 5000, 'amenities' => ['Wi-Fi']]);

        // Only 'Fits' is exact, so the finder relaxes and also returns 'No wifi' as a close match.
        $this->assertSame(1, $out['exact_count']);
        $first = $out['rows']->first();
        $this->assertSame('Fits', $first['property']->title);
        $this->assertEmpty($first['misses']);
        $this->assertContains('Wi-Fi', array_column($first['hits'], 'text'));
        $this->assertSame('No Wi-Fi', $out['rows']->last()['misses'][0]['text']);
    }

    public function test_a_budget_just_too_low_still_finds_the_closest_with_the_gap_named(): void
    {
        $this->listing('Slightly over', [], 4500);

        $out = $this->find(['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'price_max' => 4000]);

        $this->assertSame(0, $out['exact_count']);
        $this->assertSame(1, $out['rows']->count());
        $this->assertSame('₱500 over budget', $out['rows']->first()['misses'][0]['text']);
        $this->assertContains('budget +15%', $out['relaxed']);
    }

    public function test_relaxation_stops_once_enough_results_are_found(): void
    {
        foreach (['A', 'B', 'C'] as $t) {
            $this->listing($t, [], 4500);
        }

        $out = $this->find(['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'price_max' => 5000]);

        $this->assertSame([], $out['relaxed']);
        $this->assertSame(3, $out['exact_count']);
    }

    public function test_landmark_search_uses_distance_and_widens_the_radius(): void
    {
        $this->listing('Near USC', ['latitude' => 10.3540, 'longitude' => 123.9120]);       // ~0.1 km
        $this->listing('Three km away', ['latitude' => 10.3800, 'longitude' => 123.9120]);  // ~3 km

        $out = $this->find(['location' => ['kind' => 'landmark', 'value' => 'usc_talamban'], 'radius_km' => 2]);

        $this->assertSame('Near USC', $out['rows']->first()['property']->title);
        $this->assertStringContainsString('km from USC Talamban', $out['rows']->first()['hits'][0]['text']);
        // Fewer than 3 results, so the radius widened and the far one appears as a close match.
        $this->assertContains('wider area', $out['relaxed']);
        $far = $out['rows']->firstWhere(fn ($r) => $r['property']->title === 'Three km away');
        $this->assertNotNull($far);
        $this->assertSame('location', $far['misses'][0]['facet']);
    }

    public function test_house_rules_and_unit_level_amenities_are_judged_from_real_data(): void
    {
        $this->listing('Pets ok', ['house_rules' => ['Quiet Hours']], 4500, ['Parking Space']);
        $this->listing('No pets', ['house_rules' => ['No Pets']], 4500, ['Parking Space']);

        $out = $this->find(['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'rules' => ['pets'], 'amenities' => ['Parking Space']]);

        $this->assertSame('Pets ok', $out['rows']->first()['property']->title);
        $this->assertSame(1, $out['exact_count']);
        $this->assertSame('No pets', $out['rows']->last()['misses'][0]['text']);
    }

    public function test_unpublished_or_full_listings_never_appear(): void
    {
        $hidden = $this->listing('Hidden', ['publication_status' => 'Unpublished']);
        $full = $this->listing('Full');
        $full->units()->update(['availability_status' => 'Occupied']);

        $out = $this->find(['location' => ['kind' => 'city', 'value' => 'Cebu City']]);

        $this->assertSame(0, $out['rows']->count());
        $this->assertNotNull($hidden);
    }
}
