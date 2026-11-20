<?php

namespace Tests\Feature\AiSearch;

use App\Services\AiSearch\AiSearchAdvanced;
use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiSearchUnavailable;
use App\Services\AiSearch\IntentValidator;
use App\Services\AiSearch\ReasonGuard;
use Illuminate\Support\Facades\Cache;

/** Phase 6 of plans/ai-search-maps-and-haiku.md: one checked "Why this fits you" line per card. */
class ReasonsTest extends AiSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_search.features.reasons' => true]);
        Cache::flush();
    }

    private function facts(): array
    {
        return [
            10 => ['id' => 10, 'title' => 'Lahug Bedspace', 'price_from' => 2600, 'under_budget_by' => 400, 'rating' => 4.5, 'reviews' => 12,
                'area' => 'Lahug, Cebu City', 'amenities' => ['Wi-Fi', 'Air Conditioning'], 'meets' => ['0.2 km from Lahug', 'Under budget'], 'misses' => []],
            11 => ['id' => 11, 'title' => 'Other', 'price_from' => 3200, 'over_budget_by' => 200, 'amenities' => ['Electric Fan'], 'meets' => [], 'misses' => ['₱200 over budget']],
        ];
    }

    // ─── the guard ──────────────────────────────────────────────

    public function test_a_line_built_only_from_the_listings_facts_is_kept(): void
    {
        $kept = ReasonGuard::clean([
            ['id' => 10, 'text' => 'Aircon bedspace 0.2 km from Lahug, ₱400 under your budget'],
            ['id' => 11, 'text' => 'Only ₱200 over budget, with a fan'],
        ], $this->facts(), [3000]);

        $this->assertSame([10, 11], array_keys($kept));
    }

    public function test_a_number_the_listing_does_not_have_drops_the_line(): void
    {
        $dropped = ReasonGuard::clean([
            ['id' => 10, 'text' => 'Only 5 minutes walk to Lahug'],      // minutes not in the facts
            ['id' => 10, 'text' => 'Costs ₱2,800 a month'],               // wrong price
            ['id' => 10, 'text' => 'Rated 4.9 by tenants'],               // wrong rating
        ], $this->facts());

        $this->assertSame([], $dropped);
    }

    public function test_a_price_written_with_a_comma_matches_the_same_number_in_the_facts(): void
    {
        $kept = ReasonGuard::clean([['id' => 10, 'text' => 'From ₱2,600 a month']], $this->facts());

        $this->assertSame([10], array_keys($kept));
    }

    public function test_an_amenity_the_listing_does_not_have_drops_the_line(): void
    {
        $out = ReasonGuard::clean([
            ['id' => 11, 'text' => 'Has aircon and Wi-Fi'],          // listing 11 only has a fan
            ['id' => 11, 'text' => 'Comes with parking'],
            ['id' => 10, 'text' => 'Aircon and wifi included'],      // spelled differently, but it does have both
        ], $this->facts());

        $this->assertSame([10], array_keys($out));
    }

    public function test_unknown_ids_duplicates_empty_and_long_lines_and_markup_are_handled(): void
    {
        $out = ReasonGuard::clean([
            ['id' => 99, 'text' => 'Not one of ours'],
            ['id' => 10, 'text' => 'First line wins'],
            ['id' => 10, 'text' => 'Second line is ignored'],
            ['id' => 11, 'text' => ''],
            ['id' => 11, 'text' => str_repeat('a', 121)],
            ['id' => 11, 'text' => '<b>Fan</b> room'],
            'garbage',
        ], $this->facts());

        $this->assertSame([10 => 'First line wins', 11 => 'Fan room'], $out);
    }

    public function test_a_summary_may_use_the_budget_and_any_listing_fact_but_nothing_else(): void
    {
        $this->assertTrue(ReasonGuard::summaryIsSafe('Both are near your ₱3,000 budget; one has aircon.', $this->facts(), [3000]));
        $this->assertFalse(ReasonGuard::summaryIsSafe('Prices average ₱2,900.', $this->facts(), [3000]));
        $this->assertFalse(ReasonGuard::summaryIsSafe('All have parking.', $this->facts(), [3000]));
        $this->assertFalse(ReasonGuard::summaryIsSafe('', $this->facts()));
    }

    // ─── the service ────────────────────────────────────────────

    /** A driver whose reasons() answers whatever the test hands it. */
    private function useReasoner(?array $answer, bool $fail = false): object
    {
        $driver = new class($answer, $fail) implements AiSearchAdvanced, AiSearchDriver
        {
            public int $calls = 0;

            public array $sawCandidates = [];

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

            public function refine(array $previous, string $message, array $vocab): array
            {
                return ['mode' => 'refine', 'ops' => [], 'intent' => []];
            }

            public function reasons(array $intent, array $candidates, string $language): array
            {
                $this->calls++;
                $this->sawCandidates = $candidates;
                $this->fail && throw new AiSearchUnavailable('down');

                return $this->answer;
            }
        };
        config(['ai_search.driver' => 'gemini']);
        $this->app->instance(AiSearchDriver::class, $driver);

        return $driver;
    }

    private function intent(): array
    {
        return app(IntentValidator::class)->validate(['property_type' => 'Boarding House', 'price_max' => 5000, 'language' => 'en']);
    }

    public function test_the_service_finds_the_listings_itself_and_keeps_only_safe_lines(): void
    {
        $a = $this->listing('Talamban Rooms', [], 4500, ['Wi-Fi']);
        $b = $this->listing('Cheaper Rooms', [], 3200);
        $driver = $this->useReasoner([
            'reasons' => [
                ['id' => $a->property_id, 'text' => 'Wi-Fi included, ₱500 under your budget'],
                ['id' => $b->property_id, 'text' => 'Has aircon'],               // it does not: dropped
                ['id' => 987654, 'text' => 'Not a listing of the search'],       // not sent: dropped
            ],
            'summary' => 'Two rooms under ₱5,000.', 'summary_en' => 'Two rooms under ₱5,000.',
        ]);

        $out = app(AiSearchService::class)->reasons($this->intent());

        $this->assertSame([$a->property_id => 'Wi-Fi included, ₱500 under your budget'], $out['reasons']);
        $this->assertSame('Two rooms under ₱5,000.', $out['summary']);
        $this->assertSame(1, $driver->calls);
        // The model was given the precomputed budget difference, so it never has to calculate one.
        $sent = collect($driver->sawCandidates)->keyBy('id');
        $this->assertSame(500, $sent[$a->property_id]['under_budget_by']);
    }

    public function test_an_unsafe_summary_is_dropped_but_the_lines_stay(): void
    {
        $a = $this->listing('Talamban Rooms', [], 4500);
        $this->useReasoner([
            'reasons' => [['id' => $a->property_id, 'text' => '₱500 under your budget']],
            'summary' => 'Prices start at ₱1,000.', 'summary_en' => 'Prices start at ₱1,000.',
        ]);

        $out = app(AiSearchService::class)->reasons($this->intent());

        $this->assertCount(1, $out['reasons']);
        $this->assertSame('', $out['summary']);
    }

    public function test_nothing_happens_when_off_empty_down_or_with_no_results(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $driver = $this->useReasoner(['reasons' => [], 'summary' => '', 'summary_en' => '']);
        $svc = app(AiSearchService::class);

        config(['ai_search.features.reasons' => false]);
        $this->assertSame([], $svc->reasons($this->intent())['reasons']);
        $this->assertSame(0, $driver->calls);

        config(['ai_search.features.reasons' => true]);
        $this->assertSame([], $svc->reasons(app(IntentValidator::class)->validate([]))['reasons'], 'an empty search has nothing to explain');
        $nothing = app(IntentValidator::class)->validate(['property_type' => 'Dormitory', 'price_max' => 600]);
        $this->assertSame(0, $driver->calls);
        $svc->reasons($nothing);
        $this->assertSame(0, $driver->calls, 'no listings, no call');

        $this->useReasoner(null, fail: true);
        $this->assertSame([], app(AiSearchService::class)->reasons($this->intent())['reasons'], 'a failing provider just means no lines');
    }

    public function test_the_same_results_are_explained_once_then_served_from_the_cache(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $driver = $this->useReasoner(['reasons' => [], 'summary' => '', 'summary_en' => '']);
        $svc = app(AiSearchService::class);

        $svc->reasons($this->intent());
        $svc->reasons($this->intent());

        $this->assertSame(1, $driver->calls);
    }

    // ─── the endpoint and the card ──────────────────────────────

    public function test_the_endpoint_always_answers_200_with_a_map_of_ids_to_lines(): void
    {
        $a = $this->listing('Talamban Rooms', [], 4500);
        $this->useReasoner(['reasons' => [['id' => $a->property_id, 'text' => '₱500 under your budget']], 'summary' => '', 'summary_en' => '']);

        $this->postJson('/search/reasons', ['intent' => ['property_type' => 'Boarding House', 'price_max' => 5000, 'language' => 'en']])
            ->assertOk()
            ->assertJsonPath('reasons.' . $a->property_id, '₱500 under your budget');

        // Feature off: still 200, and an empty JSON object (not a list) so the page can read it.
        config(['ai_search.features.reasons' => false]);
        $this->postJson('/search/reasons', ['intent' => ['property_type' => 'Boarding House', 'language' => 'en']])
            ->assertOk()->assertExactJson(['reasons' => [], 'summary' => '', 'summary_en' => '']);

        $this->postJson('/search/reasons', [])->assertStatus(422);
    }

    public function test_the_result_cards_have_an_empty_slot_for_the_line_only_when_the_feature_is_on(): void
    {
        $a = $this->listing('Talamban Rooms', [], 4500);
        $payload = ['q' => 'x', 'intent' => ['property_type' => 'Boarding House', 'language' => 'en'], 'used_ai' => false];

        $on = $this->postJson('/search/results', $payload)->assertOk()->json('html');
        $this->assertStringContainsString('data-reason="' . $a->property_id . '"', $on);

        config(['ai_search.features.reasons' => false]);
        $off = $this->postJson('/search/results', $payload)->assertOk()->json('html');
        $this->assertStringNotContainsString('data-reason=', $off);
    }
}
