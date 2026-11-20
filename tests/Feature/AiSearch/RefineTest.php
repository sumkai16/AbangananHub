<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\AiSearchAdvanced;
use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiSearchUnavailable;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\RefinePatch;
use Illuminate\Support\Facades\Cache;

/** Phase 3 of plans/ai-search-maps-and-haiku.md: follow-up messages on the search the tenant already has. */
class RefineTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.refine' => true, 'ai_search.features.sort' => true, 'ai_search.features.geo_lookup' => true]);
        Cache::flush();
    }

    private function intent(array $raw): array
    {
        return app(IntentValidator::class)->validate($raw + ['language' => 'en']);
    }

    private function base(): array
    {
        return $this->intent(['location' => ['kind' => 'landmark', 'value' => 'it_park'], 'property_type' => 'Bedspace', 'price_max' => 4000]);
    }

    /** A driver that answers a follow-up with whatever the test hands it, and counts calls. */
    private function useRefiner(?array $answer, bool $fail = false): object
    {
        $driver = new class($answer, $fail) implements AiSearchAdvanced, AiSearchDriver
        {
            public int $calls = 0;

            public ?array $sawPrevious = null;

            public function __construct(private ?array $answer, private bool $fail) {}

            public function interpretImage(string $dataUri, array $vocab): array
            {
                return [];
            }

            public function interpret(string $query, array $vocab): array
            {
                return [];
            }

            public function rank(array $intent, array $candidates, string $language, bool $exact): array
            {
                return ['order' => [], 'summary' => '', 'summary_en' => ''];
            }

            public function reasons(array $intent, array $candidates, string $language): array
            {
                return ['reasons' => [], 'summary' => '', 'summary_en' => ''];
            }

            public function refine(array $previous, string $message, array $vocab): array
            {
                $this->calls++;
                $this->sawPrevious = $previous;
                $this->fail && throw new AiSearchUnavailable('down');

                return $this->answer;
            }
        };
        config(['ai_search.driver' => 'gemini']);
        $this->app->instance(AiSearchDriver::class, $driver);

        return $driver;
    }

    // ─── the patch (no AI) ──────────────────────────────────────

    public function test_cheaper_takes_15_percent_off_the_budget_or_off_the_cheapest_shown(): void
    {
        $with = RefinePatch::apply($this->base(), ['ops' => ['cheaper']]);
        $this->assertSame(3400, $with['price_max']);

        $none = $this->intent(['property_type' => 'Bedspace']);
        $this->assertSame(2500, RefinePatch::apply($none, ['ops' => ['cheaper']], ['cheapest_fee' => 3000])['price_max']);
        $this->assertNull(RefinePatch::apply($none, ['ops' => ['cheaper']])['price_max'], 'nothing to base it on');
        $this->assertSame(5000, RefinePatch::apply($this->base(), ['ops' => ['pricier']])['price_max']);
    }

    public function test_closer_and_wider_move_the_radius_and_turn_minutes_into_a_radius(): void
    {
        $this->assertSame(1.0, RefinePatch::apply($this->base(), ['ops' => ['closer']])['radius_km']);
        $this->assertSame(4.0, RefinePatch::apply($this->base(), ['ops' => ['wider']])['radius_km']);

        $minutes = $this->intent(['location' => ['kind' => 'landmark', 'value' => 'it_park'], 'max_minutes' => ['minutes' => 10, 'mode' => 'walk']]);
        $closer = RefinePatch::apply($minutes, ['ops' => ['closer']]);
        $this->assertNull($closer['max_minutes']);
        $this->assertSame(0.5, $closer['radius_km']);

        $city = $this->intent(['location' => ['kind' => 'city', 'value' => 'Mandaue']]);
        $this->assertSame($city['radius_km'], RefinePatch::apply($city, ['ops' => ['closer']])['radius_km'], 'a city has no radius');
    }

    public function test_set_add_and_remove(): void
    {
        $out = RefinePatch::apply($this->base(), [
            'set' => ['property_type' => 'Dormitory', 'bogus' => 'x'],
            'add' => ['amenities' => ['Wi-Fi'], 'rules' => ['pets']],
            'remove' => ['price_max'],
        ]);

        $this->assertSame('Dormitory', $out['property_type']);
        $this->assertArrayNotHasKey('bogus', $out);
        $this->assertSame(['Wi-Fi'], $out['amenities']);
        $this->assertSame(['pets'], $out['rules']);
        $this->assertNull($out['price_max']);
    }

    public function test_the_prompt_never_gets_the_tenants_position_or_map_area(): void
    {
        $point = $this->intent(['location' => ['kind' => 'point', 'lat' => 10.3157, 'lng' => 123.8854]]);
        $shown = json_encode(RefinePatch::forPrompt($point));

        $this->assertStringNotContainsString('10.31', $shown);
        $this->assertStringNotContainsString('123.88', $shown);
        $this->assertStringContainsString('location_note', $shown);

        $area = $this->intent(['location' => ['kind' => 'area', 'south' => 10.30, 'west' => 123.88, 'north' => 10.34, 'east' => 123.92]]);
        $this->assertStringNotContainsString('10.3', json_encode(RefinePatch::forPrompt($area)));
    }

    public function test_quick_chips_depend_on_the_search_and_never_on_an_empty_result(): void
    {
        $labels = array_column(RefinePatch::quick($this->base(), 3), 'label');
        $this->assertSame(['Cheaper', 'Closer', 'Add aircon', 'Add Wi-Fi'], $labels);

        $city = $this->intent(['location' => ['kind' => 'city', 'value' => 'Mandaue'], 'amenities' => ['Air Conditioning']]);
        $this->assertNotContains('Closer', array_column(RefinePatch::quick($city, 3), 'label'));
        $this->assertNotContains('Add aircon', array_column(RefinePatch::quick($city, 3), 'label'));

        $this->assertSame([], RefinePatch::quick($this->base(), 0));
    }

    // ─── the service ────────────────────────────────────────────

    public function test_a_quick_chip_changes_the_search_with_no_ai_call(): void
    {
        $driver = $this->useRefiner(null);

        $out = app(AiSearchService::class)->refine('', $this->base(), ['ops' => ['cheaper']]);

        $this->assertSame(3400, $out['intent']['price_max']);
        $this->assertContains('Up to ₱3,400', array_column($out['chips'], 'label'));
        $this->assertSame(0, $driver->calls);
    }

    public function test_the_model_updates_the_search_and_relative_words_become_ops(): void
    {
        $driver = $this->useRefiner(['mode' => 'refine', 'ops' => ['cheaper'], 'intent' => ['location' => ['kind' => 'landmark', 'value' => 'it_park'], 'property_type' => 'Bedspace', 'price_max' => 4000, 'amenities' => ['Air Conditioning'], 'language' => 'tl']]);

        $out = app(AiSearchService::class)->refine('mas mura pero may aircon', $this->base());

        $this->assertSame('refine', $out['mode']);
        $this->assertTrue($out['used_ai']);
        $this->assertSame(3400, $out['intent']['price_max']);
        $this->assertSame(['Air Conditioning'], $out['intent']['amenities']);
        $this->assertSame('it_park', $out['intent']['location']['value']);
        $this->assertSame(1, $driver->calls);
        $this->assertSame('Bedspace', $driver->sawPrevious['property_type']);
    }

    public function test_a_field_the_model_forgot_to_copy_stays_unless_the_tenant_released_it(): void
    {
        // The model returns no place and no type: it forgot them.
        $this->useRefiner(['mode' => 'refine', 'ops' => [], 'intent' => ['price_max' => 4000, 'rules' => ['pets'], 'language' => 'en']]);
        $kept = app(AiSearchService::class)->refine('pets ok', $this->base());
        $this->assertSame('it_park', $kept['intent']['location']['value']);
        $this->assertSame('Bedspace', $kept['intent']['property_type']);

        $this->useRefiner(['mode' => 'refine', 'ops' => [], 'intent' => ['price_max' => 4000, 'language' => 'en']]);
        Cache::flush();
        $released = app(AiSearchService::class)->refine('kahit saan basta bedspace', $this->base());
        $this->assertNull($released['intent']['location'], 'anywhere releases the place');
    }

    public function test_a_different_search_is_read_on_its_own(): void
    {
        $this->useRefiner(['mode' => 'new', 'ops' => [], 'intent' => ['location' => ['kind' => 'landmark', 'value' => 'usc_talamban'], 'property_type' => 'Dormitory', 'price_max' => 6000, 'language' => 'en']]);

        $out = app(AiSearchService::class)->refine('dorm near USC for my sister', $this->base());

        $this->assertSame('new', $out['mode']);
        $this->assertSame('usc_talamban', $out['intent']['location']['value']);
        $this->assertSame('Dormitory', $out['intent']['property_type']);
    }

    public function test_the_tenants_position_survives_a_follow_up(): void
    {
        $this->useRefiner(['mode' => 'refine', 'ops' => [], 'intent' => ['property_type' => 'Bedspace', 'rules' => ['pets'], 'language' => 'en']]);
        $near = $this->intent(['location' => ['kind' => 'point', 'lat' => 10.3157, 'lng' => 123.8854], 'property_type' => 'Bedspace', 'near_me' => true]);

        $out = app(AiSearchService::class)->refine('pets ok', $near);

        $this->assertSame('point', $out['intent']['location']['kind']);
        $this->assertTrue($out['intent']['near_me']);
    }

    public function test_when_the_ai_is_down_the_keyword_reader_adds_to_the_search(): void
    {
        $this->useRefiner(null, fail: true);

        $out = app(AiSearchService::class)->refine('mas mura, may aircon at wifi', $this->base());

        $this->assertFalse($out['used_ai']);
        $this->assertSame('unavailable', $out['fallback_reason']);
        $this->assertSame(3400, $out['intent']['price_max']);
        $this->assertEqualsCanonicalizing(['Air Conditioning', 'Wi-Fi'], $out['intent']['amenities']);
        $this->assertSame('it_park', $out['intent']['location']['value']);
    }

    public function test_the_feature_off_uses_the_keyword_reader_without_asking_the_ai(): void
    {
        config(['ai_search.features.refine' => false]);
        $driver = $this->useRefiner(['mode' => 'refine', 'ops' => [], 'intent' => []]);

        $out = app(AiSearchService::class)->refine('closer please', $this->base());

        $this->assertSame(0, $driver->calls);
        $this->assertSame(1.0, $out['intent']['radius_km']);
    }

    public function test_the_same_follow_up_is_answered_from_the_cache(): void
    {
        $driver = $this->useRefiner(['mode' => 'refine', 'ops' => [], 'intent' => ['property_type' => 'Bedspace', 'language' => 'en']]);
        $svc = app(AiSearchService::class);

        $svc->refine('may aircon', $this->base());
        $svc->refine('may aircon', $this->base());

        $this->assertSame(1, $driver->calls);
    }

    // ─── the endpoint ───────────────────────────────────────────

    public function test_the_endpoint_returns_the_new_search_and_asks_for_something_to_change(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $previous = $this->base();

        $this->postJson('/search/refine', ['previous' => $previous, 'patch' => ['ops' => ['cheaper']]])
            ->assertOk()
            ->assertJsonPath('intent.price_max', 3400)
            ->assertJsonPath('mode', 'refine')
            ->assertJsonFragment(['label' => 'Up to ₱3,400']);

        $this->postJson('/search/refine', ['previous' => $previous])->assertStatus(422);
    }

    public function test_results_carry_the_quick_chips_and_the_cheapest_rent_shown(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $this->listing('Cheaper Rooms', [], 3200);

        $out = $this->postJson('/search/results', ['q' => 'x', 'intent' => ['property_type' => 'Boarding House', 'language' => 'en'], 'used_ai' => false])->assertOk()->json();

        $this->assertSame(3200.0, (float) $out['context']['cheapest_fee']);
        $this->assertSame('Cheaper', $out['quick'][0]['label']);
    }
}
