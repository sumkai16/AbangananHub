<?php

namespace Tests\Feature\AiSearch;

use App\Models\GeoPlace;
use App\Services\AiSearch\CandidateFinder;
use App\Services\AiSearch\ChipBuilder;
use App\Services\AiSearch\GeoLookup;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\KeywordFallback;
use App\Services\AiSearch\Travel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Phase 2 of plans/ai-search-maps-and-haiku.md: minutes, places to avoid, any place, "Search this area". */
class MapGeometryTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.geo_lookup' => true, 'ai_search.features.area' => true, 'ai_search.features.sort' => true]);
        Cache::flush();
    }

    private function validate(array $raw): array
    {
        return app(IntentValidator::class)->validate($raw);
    }

    private function itPark(): array
    {
        return ['kind' => 'landmark', 'value' => 'it_park'];
    }

    // ─── travel minutes ─────────────────────────────────────────

    public function test_minutes_become_a_radius_and_back(): void
    {
        $this->assertEqualsWithDelta(0.58, Travel::radiusKm(10, 'walk'), 0.02);
        $this->assertGreaterThan(Travel::radiusKm(10, 'walk'), Travel::radiusKm(10, 'drive'));
        $this->assertSame(10, Travel::minutes(0.58, 'walk'));
        // The jeepney's waiting time is not travel time.
        $this->assertSame(0.3, Travel::radiusKm(6, 'jeep'));
        $this->assertSame('about 8 min walk', Travel::label(8, 'walk'));
    }

    public function test_minutes_need_a_place_and_a_sane_range(): void
    {
        $this->assertNull($this->validate(['max_minutes' => ['minutes' => 10, 'mode' => 'walk']])['max_minutes'], 'no place, no minutes');

        $i = $this->validate(['location' => $this->itPark(), 'max_minutes' => ['minutes' => 500, 'mode' => 'teleport']]);
        $this->assertSame(['minutes' => 60, 'mode' => 'walk'], $i['max_minutes']);
    }

    public function test_the_search_circle_follows_the_minutes_and_widens_as_a_radius(): void
    {
        $near = $this->listing('Next to IT Park', ['latitude' => 10.3300, 'longitude' => 123.9060]);
        $far = $this->listing('A Bit Far', ['latitude' => 10.3400, 'longitude' => 123.9060]); // about 1.2 km away
        $finder = app(CandidateFinder::class);

        $found = $finder->find($this->validate(['location' => $this->itPark(), 'max_minutes' => ['minutes' => 10, 'mode' => 'walk']]));

        $this->assertSame('Next to IT Park', $found['rows']->first()['property']->title);
        $this->assertSame(1, $found['exact_count']);
        $this->assertStringContainsString('min walk to Cebu IT Park', $found['rows']->first()['hits'][0]['text']);
        $this->assertContains('wider area', $found['relaxed']);
        $this->assertSame($near->property_id, $found['rows']->first()['property']->property_id);
        $this->assertContains($far->property_id, $found['rows']->pluck('property.property_id')->all());
    }

    // ─── places to avoid ────────────────────────────────────────

    public function test_a_landmark_to_avoid_gets_its_coordinates_and_an_unknown_one_waits_for_the_lookup(): void
    {
        $i = $this->validate(['avoid' => [['place' => 'Colon'], ['place' => 'Some Unknown Mall', 'radius_km' => 99]]]);

        $this->assertSame('Colon Street', $i['avoid'][0]['name']);
        $this->assertEqualsWithDelta(10.2954, $i['avoid'][0]['lat'], 0.001);
        $this->assertNull($i['avoid'][1]['lat']);
        $this->assertSame(5.0, $i['avoid'][1]['radius_km']);
        $this->assertCount(2, $this->validate(['avoid' => [['place' => 'a'], ['place' => 'b'], ['place' => 'c']]])['avoid'] + [0, 0], 'at most two');
    }

    public function test_nothing_inside_the_avoided_circle_is_an_exact_match(): void
    {
        $this->listing('Right By Colon', ['latitude' => 10.2960, 'longitude' => 123.9010]);
        $this->listing('Far From Colon', ['latitude' => 10.3532, 'longitude' => 123.9120]);
        $this->listing('Also Far', ['latitude' => 10.3550, 'longitude' => 123.9100]);
        $this->listing('Far Again', ['latitude' => 10.3500, 'longitude' => 123.9140]);

        $found = app(CandidateFinder::class)->find($this->validate(['avoid' => [['place' => 'Colon']]]));

        $titles = $found['rows']->pluck('property.title')->all();
        $this->assertNotContains('Right By Colon', $titles);
        $this->assertSame(3, $found['exact_count']);
        $this->assertStringContainsString('km from Colon Street', $found['rows']->first()['hits'][0]['text']);
    }

    public function test_the_avoid_circle_is_the_last_thing_to_relax_and_a_miss_says_how_close(): void
    {
        $this->listing('Right By Colon', ['latitude' => 10.2960, 'longitude' => 123.9010]);

        $found = app(CandidateFinder::class)->find($this->validate(['avoid' => [['place' => 'Colon']]]));

        $this->assertContains('places to avoid', $found['relaxed']);
        $this->assertStringStartsWith('Only 0.', $found['rows']->first()['misses'][0]['text']);
    }

    public function test_the_chips_say_away_from_and_how_many_minutes(): void
    {
        $chips = app(ChipBuilder::class)->build($this->validate([
            'location' => $this->itPark(), 'max_minutes' => ['minutes' => 10, 'mode' => 'walk'], 'avoid' => [['place' => 'Colon']],
        ]));
        $labels = array_column($chips, 'label');

        $this->assertContains('≤ 10 min walk to Cebu IT Park', $labels);
        $this->assertContains('Away from Colon Street', $labels);

        $intent = ChipBuilder::remove($this->validate(['avoid' => [['place' => 'Colon']]]), 'avoid', 'Colon Street');
        $this->assertSame([], $intent['avoid']);
    }

    // ─── any place (GeoLookup) ──────────────────────────────────

    private function fakeNominatim(array $rows = null): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($rows ?? [[
            'name' => 'Skyrise 4', 'display_name' => 'Skyrise 4, Apas, Cebu City', 'lat' => '10.33011', 'lon' => '123.90627',
            'osm_type' => 'way', 'osm_id' => 99, 'address' => ['city' => 'Cebu City'],
        ]])]);
    }

    public function test_an_unknown_place_is_found_once_and_remembered(): void
    {
        $this->fakeNominatim();
        $geo = app(GeoLookup::class);

        $first = $geo->find('Skyrise');
        $again = $geo->find('skyrise');

        $this->assertSame('Skyrise 4', $first['name']);
        $this->assertSame($first, $again);
        Http::assertSentCount(1); // the second ask came from geo_places
        Http::assertSent(fn ($r) => str_contains($r->url(), 'countrycodes=ph') && str_contains($r->url(), 'bounded=1')
            && str_contains($r->header('User-Agent')[0], 'AbangananHub'));
        $this->assertSame(1, GeoPlace::where('query_key', 'skyrise')->count());
    }

    public function test_only_the_typed_name_is_sent_nothing_about_the_tenant(): void
    {
        $this->fakeNominatim();

        app(GeoLookup::class)->find('Skyrise');

        Http::assertSent(function ($r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $q['q'] === 'Skyrise' && ! array_key_exists('lat', $q) && ! array_key_exists('lon', $q);
        });
    }

    public function test_a_result_outside_cebu_or_a_failure_leaves_the_place_as_text(): void
    {
        $this->fakeNominatim([['name' => 'Skyrise', 'lat' => '14.5995', 'lon' => '120.9842', 'osm_type' => 'node', 'osm_id' => 1]]); // Manila
        $this->assertNull(app(GeoLookup::class)->find('Skyrise Manila'));
        $this->assertNull(GeoPlace::where('query_key', 'skyrise manila')->first()->lat, 'the miss is remembered too');

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);
        $this->assertNull(app(GeoLookup::class)->find('Anything Down'));
    }

    public function test_apply_turns_a_text_place_into_a_map_point_and_resolves_avoid_names(): void
    {
        $this->fakeNominatim();
        $intent = $this->validate(['location' => ['kind' => 'text', 'value' => 'Skyrise'], 'avoid' => [['place' => 'Skyrise Cafe']]]);

        $out = app(GeoLookup::class)->apply($intent);

        $this->assertSame('landmark', $out['location']['kind']);
        $this->assertSame('geo', $out['location']['source']);
        $this->assertStringStartsWith('geo:', $out['location']['value']);
        $this->assertEqualsWithDelta(10.33011, $out['location']['lat'], 0.0001);
        $this->assertEqualsWithDelta(10.33011, $out['avoid'][0]['lat'], 0.0001);
    }

    public function test_a_known_landmark_never_calls_openstreetmap(): void
    {
        Http::fake();

        $intent = $this->validate(['location' => $this->itPark()]);
        app(GeoLookup::class)->apply($intent);

        Http::assertNothingSent();
    }

    public function test_with_the_feature_off_nothing_is_looked_up(): void
    {
        config(['ai_search.features.geo_lookup' => false]);
        Http::fake();

        $out = app(GeoLookup::class)->apply($this->validate(['location' => ['kind' => 'text', 'value' => 'Skyrise']]));

        $this->assertSame('text', $out['location']['kind']);
        Http::assertNothingSent();
    }

    public function test_a_found_place_posted_back_by_the_page_is_clamped_to_cebu(): void
    {
        $ok = $this->validate(['location' => ['kind' => 'landmark', 'value' => 'geo:abc', 'name' => 'Skyrise 4', 'lat' => 10.33, 'lng' => 123.906, 'source' => 'geo']]);
        $this->assertSame('Skyrise 4', $ok['location']['name']);

        $manila = $this->validate(['location' => ['kind' => 'landmark', 'value' => 'geo:abc', 'name' => 'x', 'lat' => 14.6, 'lng' => 120.98, 'source' => 'geo']]);
        $this->assertNotSame('geo', $manila['location']['source'] ?? null);
    }

    // ─── "Search this area" ─────────────────────────────────────

    public function test_a_map_area_is_a_box_in_cebu_at_most_25_km_across(): void
    {
        $i = $this->validate(['location' => ['kind' => 'area', 'south' => 10.30, 'west' => 123.88, 'north' => 10.34, 'east' => 123.92]]);
        $this->assertSame('area', $i['location']['kind']);

        $wide = $this->validate(['location' => ['kind' => 'area', 'south' => 9.5, 'west' => 123.3, 'north' => 11.0, 'east' => 124.4]]);
        $this->assertLessThanOrEqual(25 / 111 + 0.001, $wide['location']['north'] - $wide['location']['south']);

        $this->assertNull($this->validate(['location' => ['kind' => 'area', 'south' => 14, 'west' => 120, 'north' => 15, 'east' => 121]])['location']);
        $this->assertNull($this->validate(['location' => ['kind' => 'area', 'south' => 10.34, 'west' => 123.88, 'north' => 10.30, 'east' => 123.92]])['location'], 'upside down');
        config(['ai_search.features.area' => false]);
        $this->assertNull($this->validate(['location' => ['kind' => 'area', 'south' => 10.30, 'west' => 123.88, 'north' => 10.34, 'east' => 123.92]])['location']);
    }

    public function test_only_listings_inside_the_area_are_exact_and_the_page_gets_the_box_to_draw(): void
    {
        $this->listing('Inside The Box', ['latitude' => 10.32, 'longitude' => 123.90]);
        $this->listing('Outside The Box', ['latitude' => 10.40, 'longitude' => 124.00]);
        $area = ['kind' => 'area', 'south' => 10.30, 'west' => 123.88, 'north' => 10.34, 'east' => 123.92];

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => ['location' => $area, 'language' => 'en'], 'used_ai' => false])->assertOk()->json();

        $this->assertStringContainsString('Inside The Box', $out['html']);
        $this->assertSame('area', $out['map_origin']['kind']);
        $this->assertSame([10.3, 123.88, 10.34, 123.92], $out['map_origin']['bounds']);
        $this->assertContains('In the map area', array_column($out['chips'], 'label'));
    }

    public function test_the_results_map_gets_the_circles_to_avoid(): void
    {
        $this->listing('Far From Colon', ['latitude' => 10.3532, 'longitude' => 123.9120]);

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => ['avoid' => [['place' => 'Colon']], 'language' => 'en'], 'used_ai' => false])->assertOk()->json();

        $this->assertSame('Colon Street', $out['map_avoid'][0]['name']);
        $this->assertSame(1.5, $out['map_avoid'][0]['radius_km']);
    }

    // ─── keyword fallback ───────────────────────────────────────

    /** @dataProvider fallbackSentences */
    public function test_the_fallback_reads_far_from_and_minutes(string $sentence, array $expected, ?string $location = null): void
    {
        $intent = $this->validate(app(KeywordFallback::class)->parse($sentence));

        foreach ($expected as $field => $value) {
            $this->assertEquals($value, $intent[$field], "$sentence -> $field");
        }
        $this->assertSame($location, $intent['location']['value'] ?? null, "$sentence -> location");
    }

    public static function fallbackSentences(): array
    {
        return [
            'far from' => ['bedspace far from Colon, under 3k', ['property_type' => 'Bedspace', 'price_max' => 3000, 'avoid' => [['name' => 'Colon Street', 'lat' => 10.2954, 'lng' => 123.9004, 'radius_km' => 1.5]]], null],
            'layo sa' => ['apartment layo sa Colon', ['avoid' => [['name' => 'Colon Street', 'lat' => 10.2954, 'lng' => 123.9004, 'radius_km' => 1.5]]], null],
            'minutes walk' => ['room 10 minutes walk to IT Park', ['max_minutes' => ['minutes' => 10, 'mode' => 'walk']], 'it_park'],
            'minutes drive' => ['apartment 15 min drive from Ayala', ['max_minutes' => ['minutes' => 15, 'mode' => 'drive']], 'ayala_center'],
            'walking distance' => ['bedspace walking distance to CIT-U', ['max_minutes' => ['minutes' => 10, 'mode' => 'walk']], 'cit_u'],
            'near is no minutes' => ['room near IT Park', ['max_minutes' => null], 'it_park'],
        ];
    }
}
