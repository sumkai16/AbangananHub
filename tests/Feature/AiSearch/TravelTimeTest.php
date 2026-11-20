<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\TravelTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Phase 4 of plans/ai-search-maps-and-haiku.md: real minutes from OpenRouteService, estimate when it is not there. */
class TravelTimeTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.routing' => true, 'ai_search.features.geo_lookup' => true, 'services.openrouteservice.key' => 'ors-key']);
        Cache::flush();
    }

    private function intent(array $extra = []): array
    {
        return app(IntentValidator::class)->validate($extra + ['location' => ['kind' => 'landmark', 'value' => 'it_park'], 'language' => 'en']);
    }

    private function fakeRouter(array $seconds): void
    {
        Http::fake(['api.openrouteservice.org/*' => Http::response(['durations' => [$seconds]])]);
    }

    public function test_it_is_off_without_a_key_or_the_feature(): void
    {
        $this->assertTrue(TravelTime::available());

        config(['services.openrouteservice.key' => '']);
        $this->assertFalse(TravelTime::available());

        config(['services.openrouteservice.key' => 'k', 'ai_search.features.routing' => false]);
        $this->assertFalse(TravelTime::available());
    }

    public function test_walking_seconds_become_minutes_and_each_pair_is_cached(): void
    {
        $this->fakeRouter([600, 1500]);
        $from = ['lat' => 10.3292, 'lng' => 123.9055];
        $to = [1 => ['lat' => 10.331, 'lng' => 123.906], 2 => ['lat' => 10.34, 'lng' => 123.91]];

        $first = app(TravelTime::class)->minutes($from, $to, 'walk');
        $again = app(TravelTime::class)->minutes($from, $to, 'walk');

        $this->assertSame([1 => 10, 2 => 25], $first);
        $this->assertSame($first, $again);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/matrix/foot-walking') && $r->header('Authorization')[0] === 'ors-key'
            && $r['locations'][0] === [123.9055, 10.3292] && $r['sources'] === [0] && $r['metrics'] === ['duration']);
    }

    public function test_driving_uses_the_car_profile_and_a_jeep_is_slower_plus_a_wait(): void
    {
        $this->fakeRouter([600]);
        $from = ['lat' => 10.3292, 'lng' => 123.9055];
        $to = [1 => ['lat' => 10.331, 'lng' => 123.906]];

        $drive = app(TravelTime::class)->minutes($from, $to, 'drive');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/matrix/driving-car'));

        Cache::flush();
        $this->fakeRouter([600]);
        $jeep = app(TravelTime::class)->minutes($from, $to, 'jeep');

        $this->assertSame(10, $drive[1]);
        $this->assertGreaterThan($drive[1], $jeep[1]); // 10 min x (22 / 14) + 6
    }

    public function test_a_failure_or_timeout_leaves_the_estimate_alone(): void
    {
        Http::fake(['api.openrouteservice.org/*' => Http::response('', 503)]);
        $this->assertSame([], app(TravelTime::class)->minutes(['lat' => 10.33, 'lng' => 123.9], [1 => ['lat' => 10.331, 'lng' => 123.906]], 'walk'));

        Http::fake(['api.openrouteservice.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertSame([], app(TravelTime::class)->minutes(['lat' => 10.33, 'lng' => 123.9], [2 => ['lat' => 10.332, 'lng' => 123.907]], 'walk'));
    }

    public function test_real_minutes_replace_the_estimate_and_over_the_limit_is_a_miss(): void
    {
        $near = $this->listing('Close By', ['latitude' => 10.3300, 'longitude' => 123.9060]);
        $far = $this->listing('Winding Road', ['latitude' => 10.3305, 'longitude' => 123.9062]); // close as the crow flies, a long walk
        $this->fakeRouter([480, 1500]); // order follows the rows: whichever the finder puts first
        $svc = app(AiSearchService::class);

        $result = $svc->results('x', $this->intent(['max_minutes' => ['minutes' => 10, 'mode' => 'walk']]), false);

        $first = $result['rows']->first();
        $this->assertSame(1, $result['exact_count']);
        $this->assertSame(8, $first['route_minutes']);
        $this->assertSame('8 min walk to Cebu IT Park', $first['hits'][0]['text']); // no "about": it is a real time
        $last = $result['rows']->last();
        $this->assertSame('25 min walk to Cebu IT Park', $last['misses'][0]['text']);
        $this->assertNotEmpty($last['misses']);
        $this->assertContains($near->property_id, $result['rows']->pluck('property.property_id')->all());
        $this->assertContains($far->property_id, $result['rows']->pluck('property.property_id')->all());
    }

    public function test_the_tenants_own_position_and_a_map_area_never_go_to_the_router(): void
    {
        $this->listing('Close By', ['latitude' => 10.3300, 'longitude' => 123.9060]);
        Http::fake();
        config(['ai_search.features.area' => true]);
        $svc = app(AiSearchService::class);

        $svc->results('x', app(IntentValidator::class)->validate(['location' => ['kind' => 'point', 'lat' => 10.3292, 'lng' => 123.9055], 'language' => 'en']), false);
        $svc->results('x', app(IntentValidator::class)->validate(['location' => ['kind' => 'area', 'south' => 10.30, 'west' => 123.88, 'north' => 10.34, 'east' => 123.92], 'language' => 'en']), false);

        Http::assertNothingSent();
    }

    public function test_without_a_key_results_keep_the_straight_line_estimate(): void
    {
        config(['services.openrouteservice.key' => '']);
        $this->listing('Close By', ['latitude' => 10.3300, 'longitude' => 123.9060]);
        Http::fake();

        $result = app(AiSearchService::class)->results('x', $this->intent(['max_minutes' => ['minutes' => 10, 'mode' => 'walk']]), false);

        Http::assertNothingSent();
        $this->assertStringStartsWith('about ', $result['rows']->first()['hits'][0]['text']);
    }
}
