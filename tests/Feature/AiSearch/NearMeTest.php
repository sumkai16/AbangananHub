<?php

namespace Tests\Feature\AiSearch;

use App\Models\AiSearchLog;
use App\Models\Property;
use App\Models\RentalBusiness;
use App\Models\User;
use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\NearestArea;
use App\Services\AiSearch\NearMe;

/**
 * "Near me" and "places by <business> near me" (plans/near-me-search.md). The position comes from the browser and
 * is for one search: clamped to Cebu, named from our own listings, never logged and never sent to the AI.
 */
class NearMeTest extends AiSearchTestCase
{
    // The tenant standing in Tunghaan, Minglanilla.
    private const TUNGHAAN = ['kind' => 'point', 'lat' => 10.2455, 'lng' => 123.7965];

    private User $tudtud;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tudtud = $this->business('Gerardo', 'Tudtud', 'Tudtud Boarding Houses');
        $lozada = $this->business('Wilfredo', 'Lozada', 'Lozada Urban Living');

        // Nearest first from Tunghaan: itself, then Talamban (~17 km), then Carcar (~23 km).
        $this->place($this->tudtud, 'Tunghaan Rooms', 'Minglanilla', 'Tunghaan', 10.2450, 123.7960);
        $this->place($this->tudtud, 'Talamban Rooms', 'Cebu City', 'Talamban', 10.3532, 123.9120);
        $this->place($this->tudtud, 'Poblacion Rooms', 'Carcar City', 'Poblacion', 10.1060, 123.6400);
        $this->place($lozada, 'Lahug Studio', 'Cebu City', 'Lahug', 10.3290, 123.9060);
    }

    private function business(string $first, string $last, string $name): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole('Landlord');
        RentalBusiness::create(['landlord_id' => $user->user_id, 'business_name' => $name]);

        return $user;
    }

    private function place(User $landlord, string $title, string $city, string $barangay, float $lat, float $lng): Property
    {
        $property = $this->makeProperty($landlord, [
            'title' => $title, 'property_type' => 'Boarding House', 'city_municipality' => $city, 'barangay' => $barangay,
            'address' => "{$barangay}, {$city}, Cebu", 'latitude' => $lat, 'longitude' => $lng,
        ]);
        $this->makeUnit($property, ['rental_fee' => 3000]);

        return $property;
    }

    /** interpret (keyword reading), then results with the position the page would add. */
    private function search(string $query, ?array $point = self::TUNGHAAN): array
    {
        $service = app(AiSearchService::class);
        $read = $service->interpret($query, 'fast');
        $intent = $read['intent'];
        if ($point !== null) {
            $intent['location'] = $point;
        }

        return [$read['intent'], $service->results($query, $intent, false)];
    }

    private function titles(array $result): array
    {
        return $result['rows']->pluck('property.title')->all();
    }

    // ─── reading the sentence ───────────────────────────────────

    public function test_near_me_is_spotted_in_english_tagalog_and_bisaya_and_taken_out(): void
    {
        $this->assertSame(['boarding house under 4k', true], NearMe::split('boarding house near me under 4k'));
        $this->assertSame(['kwarto', true], NearMe::split('kwarto malapit sa akin'));
        $this->assertSame(['Kwarto', true], NearMe::split('Kwarto duol nako'));
        $this->assertSame(['apartment', true], NearMe::split('apartment nearby'));
        $this->assertSame(['places by tudtud boarding houses', true], NearMe::split('places by tudtud boarding houses NEAR ME'));
    }

    public function test_it_does_not_fire_inside_other_words_or_other_phrases(): void
    {
        foreach (['near mercury apartments', 'malapit sa akong trabaho', 'nearest school', 'room in Talamban', 'unnearby'] as $sentence) {
            $this->assertSame([$sentence, false], NearMe::split($sentence), $sentence);
        }
    }

    public function test_the_ai_never_sees_the_phrase(): void
    {
        $driver = new class implements AiSearchDriver
        {
            public array $queries = [];

            public function interpret(string $query, array $vocab): array
            {
                $this->queries[] = $query;

                return [];
            }

            public function rank(array $intent, array $candidates, string $language, bool $exact): array
            {
                return ['order' => array_column($candidates, 'id'), 'summary' => '', 'summary_en' => ''];
            }
        };
        config(['ai_search.driver' => 'gemini']);
        $this->app->instance(AiSearchDriver::class, $driver);

        $read = app(AiSearchService::class)->interpret('boarding house near me under 4k');

        $this->assertSame(['boarding house under 4k'], $driver->queries);
        $this->assertTrue($read['intent']['near_me']);
    }

    public function test_a_named_business_and_near_me_are_both_read(): void
    {
        $read = app(AiSearchService::class)->interpret('places listed by tudtud boarding houses near me', 'fast');

        $this->assertTrue($read['intent']['near_me']);
        $this->assertSame([$this->tudtud->user_id], $read['intent']['host']['landlord_ids']);
        // The words of the business name are not also filters, and "me" is not a place.
        $this->assertNull($read['intent']['property_type']);
        $this->assertNull($read['intent']['location']);
    }

    // ─── the position ───────────────────────────────────────────

    public function test_a_position_in_cebu_is_rounded_and_named_from_our_listings(): void
    {
        $intent = app(IntentValidator::class)->validate(['near_me' => true, 'location' => ['kind' => 'point', 'lat' => 10.245512345, 'lng' => 123.796598765]]);

        $this->assertSame('point', $intent['location']['kind']);
        $this->assertSame(10.246, $intent['location']['lat']);
        $this->assertSame(123.797, $intent['location']['lng']);
        $this->assertSame('Tunghaan, Minglanilla', $intent['location']['name']);
        $this->assertFalse($intent['geo_outside']);
    }

    public function test_a_name_sent_with_the_position_is_ignored(): void
    {
        $intent = app(IntentValidator::class)->validate(['location' => ['kind' => 'point', 'lat' => 10.2455, 'lng' => 123.7965, 'name' => '<b>Hacked</b>', 'city' => 'Nowhere']]);

        $this->assertSame('Tunghaan, Minglanilla', $intent['location']['name']);
        $this->assertSame('Minglanilla', $intent['location']['city']);
    }

    public function test_a_position_outside_cebu_or_not_a_number_is_dropped_and_flagged(): void
    {
        $validator = app(IntentValidator::class);

        $manila = $validator->validate(['near_me' => true, 'location' => ['kind' => 'point', 'lat' => 14.5995, 'lng' => 120.9842]]);
        $this->assertNull($manila['location']);
        $this->assertTrue($manila['geo_outside']);

        $junk = $validator->validate(['near_me' => true, 'location' => ['kind' => 'point', 'lat' => 'north', 'lng' => null]]);
        $this->assertNull($junk['location']);
        $this->assertTrue($junk['geo_outside']);
    }

    public function test_the_nearest_area_is_the_closest_one_with_listings(): void
    {
        $areas = app(NearestArea::class);

        $this->assertSame('Poblacion, Carcar City', $areas->nearest(10.11, 123.65)['name']);
        // Standing where we have no listings names the nearest neighbour.
        $this->assertSame('Tunghaan, Minglanilla', $areas->nearest(10.20, 123.75)['name']);
    }

    // ─── results ────────────────────────────────────────────────

    public function test_a_named_business_near_me_shows_all_of_its_places_nearest_first(): void
    {
        [, $result] = $this->search('places listed by tudtud boarding houses near me');

        // Carcar is ~23 km away and still shows: a named business is never cut at a radius.
        $this->assertSame(['Tunghaan Rooms', 'Talamban Rooms', 'Poblacion Rooms'], $this->titles($result));
        $this->assertStringContainsString('km from you', json_encode($result['rows']->first()['hits']));
        $this->assertStringContainsString('Nearest to you first.', $result['summary']);
        $this->assertContains('Near you · Tunghaan, Minglanilla', array_column($result['chips'], 'label'));
        $this->assertSame(['kind' => 'using', 'area' => 'Tunghaan, Minglanilla'], $result['location_notice']);
        // No other landlord's place leaks in.
        $this->assertNotContains('Lahug Studio', $this->titles($result));
    }

    public function test_near_me_without_a_name_uses_a_radius_and_orders_by_distance(): void
    {
        [, $result] = $this->search('boarding house near me');

        $this->assertSame('Tunghaan Rooms', $this->titles($result)[0]);
        $this->assertNotContains('Poblacion Rooms', $this->titles($result), 'beyond the widest radius (8 km)');
        $this->assertSame('using', $result['location_notice']['kind']);
    }

    public function test_the_businesss_other_places_are_listed_nearest_first_with_their_distance(): void
    {
        $service = app(AiSearchService::class);
        $read = $service->interpret('places listed by tudtud boarding houses near me', 'fast');
        // A budget nothing meets: the results are empty, but every place they list still shows underneath.
        $intent = array_merge($read['intent'], ['location' => self::TUNGHAAN, 'price_max' => 1000]);

        $result = $service->results('x', $intent, false);

        $this->assertTrue($result['rows']->isEmpty());
        $this->assertSame(['Tunghaan Rooms', 'Talamban Rooms', 'Poblacion Rooms'], $result['more_from_host']['properties']->pluck('title')->all());

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('More from Tudtud Boarding Houses', $html);
        $this->assertStringContainsString('nearest to you first', $html);
        $this->assertMatchesRegularExpression('/\d+\.\d km from you/', $html);
    }

    public function test_the_log_keeps_the_area_name_and_never_the_coordinates(): void
    {
        $this->search('places listed by tudtud boarding houses near me');

        $logged = AiSearchLog::latest('id')->first()->intent;

        $this->assertSame(['kind' => 'point', 'name' => 'Tunghaan, Minglanilla'], $logged['location']);
        $this->assertStringNotContainsString('10.24', json_encode($logged));
        $this->assertStringNotContainsString('123.79', json_encode($logged));
    }

    public function test_without_a_position_the_page_is_told_why(): void
    {
        [$intent, $result] = $this->search('boarding house near me', null);

        $this->assertTrue($intent['near_me']);
        $this->assertSame(['kind' => 'unavailable'], $result['location_notice']);

        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('We couldn&rsquo;t get your location', $html);
        $this->assertStringContainsString('Use my location', $html);
        $this->assertStringContainsString('Search here', $html);
    }

    public function test_a_position_outside_cebu_gets_its_own_notice(): void
    {
        [, $result] = $this->search('boarding house near me', ['kind' => 'point', 'lat' => 14.5995, 'lng' => 120.9842]);

        $this->assertSame(['kind' => 'outside'], $result['location_notice']);
        $html = view('properties.partials.ai-results', $result + ['favoritedIds' => [], 'filtersUrl' => '/properties'])->render();
        $this->assertStringContainsString('outside Cebu', $html);
        $this->assertStringNotContainsString('Use my location', $html);
    }

    public function test_typing_an_area_replaces_the_position_and_keeps_the_business(): void
    {
        $read = app(AiSearchService::class)->interpret('places listed by tudtud boarding houses near me', 'fast');
        // What the page's changeArea() posts: near_me off, a typed place.
        $intent = array_merge($read['intent'], ['near_me' => false, 'location' => ['kind' => 'text', 'value' => 'Talamban']]);

        $result = app(AiSearchService::class)->results('x', $intent, false);

        $this->assertSame(['Talamban Rooms'], $this->titles($result));
        $this->assertNull($result['location_notice']);
        $this->assertNotNull($result['intent']['host']);
    }

    public function test_an_ordinary_search_is_untouched(): void
    {
        [$intent, $result] = $this->search('boarding house in Talamban', null);

        $this->assertFalse($intent['near_me']);
        $this->assertNull($result['location_notice']);
        // Same as before this feature: the exact match first (the search then widens to the city, as it always has).
        $this->assertSame('Talamban Rooms', $this->titles($result)[0]);
    }

    public function test_the_http_endpoints_round_trip_and_draw_you_without_a_radius_for_a_named_business(): void
    {
        $read = $this->postJson('/search/interpret', ['q' => 'places listed by tudtud boarding houses near me'])
            ->assertOk()->assertJsonPath('intent.near_me', true)->json();

        $out = $this->postJson('/search/results', [
            'q' => 'places listed by tudtud boarding houses near me',
            'intent' => array_merge($read['intent'], ['location' => self::TUNGHAAN]),
            'used_ai' => false,
        ])->assertOk()
            ->assertJsonPath('map_origin.kind', 'point')
            ->assertJsonPath('map_origin.name', 'You')
            ->assertJsonPath('map_origin.radius_km', 0)
            ->assertJsonPath('intent.location.name', 'Tunghaan, Minglanilla')
            ->json();

        $this->assertStringContainsString('Using your location, near', $out['html']);
        $this->assertStringContainsString('Tunghaan, Minglanilla', $out['html']);
        $this->assertStringContainsString('Not here? Type your area', $out['html']);
    }

    public function test_the_privacy_page_says_the_position_is_not_stored(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('near me', false)->assertSee('is not saved', false);
    }
}
