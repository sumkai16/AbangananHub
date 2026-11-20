<?php

namespace Tests\Feature\AiSearch;

use App\Models\AiSearchLog;
use App\Services\AiSearch\AiSearchDriver;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiSearchUnavailable;
use App\Services\AiSearch\GeminiDriver;
use App\Services\AiSearch\OpenRouterDriver;
use App\Services\AiSearch\Vocabulary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiSearchServiceTest extends AiSearchTestCase
{
    /** A scripted driver: returns canned intent / ranking, or throws when told to. */
    private function useDriver(?array $intent, ?array $rank = null, bool $fail = false): object
    {
        $driver = new class($intent, $rank, $fail) implements AiSearchDriver
        {
            public int $calls = 0;

            public function __construct(private ?array $intent, private ?array $rank, private bool $fail) {}

            public function interpret(string $query, array $vocab): array
            {
                $this->calls++;
                $this->fail && throw new AiSearchUnavailable('down');

                return $this->intent;
            }

            public function rank(array $intent, array $candidates, string $language, bool $exact): array
            {
                $this->fail && throw new AiSearchUnavailable('down');

                return $this->rank ?? ['order' => array_column($candidates, 'id'), 'summary' => '', 'summary_en' => ''];
            }
        };
        config(['ai_search.driver' => 'gemini']);
        $this->app->instance(AiSearchDriver::class, $driver);

        return $driver;
    }

    public function test_without_a_driver_the_keyword_fallback_answers(): void
    {
        $this->listing('Talamban Rooms', [], 4500);

        $svc = app(AiSearchService::class);
        $interp = $svc->interpret('boarding house in Talamban under 5k');

        $this->assertFalse($interp['used_ai']);
        $this->assertSame('not_configured', $interp['fallback_reason']);
        $this->assertSame(5000, $interp['intent']['price_max']);

        $result = $svc->results($interp['query'], $interp['intent'], false, null, $interp['fallback_reason']);
        $this->assertSame(1, $result['rows']->count());
        $this->assertStringContainsString('Talamban Rooms', $result['summary']); // template summary
        $this->assertSame(1, AiSearchLog::where('query', $interp['query'])->count());
    }

    public function test_wishes_no_filter_can_check_are_said_out_loud(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $this->useDriver(['language' => 'en', 'property_type' => 'Boarding House', 'unmapped' => ['no deposit', 'near the beach']]);

        $svc = app(AiSearchService::class);
        $interp = $svc->interpret('boarding house with no deposit near the beach');
        $result = $svc->results($interp['query'], $interp['intent'], true);

        // "fits everything" is only true for what a filter can check; the rest is named, never silently dropped.
        $this->assertStringContainsString('fits everything', $result['summary']);
        $this->assertStringContainsString('“no deposit” and “near the beach”', $result['summary']);
        $this->assertStringContainsString('check each listing', $result['summary_en']);

        // No results, or nothing unmapped: no note.
        $none = $svc->results('x', array_merge($interp['intent'], ['property_type' => 'Dormitory']), true);
        $this->assertStringNotContainsString('check each listing', $none['summary']);
        $plain = $svc->results('x', array_merge($interp['intent'], ['unmapped' => []]), true);
        $this->assertStringNotContainsString('check each listing', $plain['summary']);
    }

    public function test_the_unmapped_note_follows_the_search_language(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $this->useDriver(['language' => 'tl', 'property_type' => 'Boarding House', 'unmapped' => ['walang deposito']]);

        $svc = app(AiSearchService::class);
        $interp = $svc->interpret('kwarto na walang deposito');
        $result = $svc->results($interp['query'], $interp['intent'], true);

        $this->assertStringContainsString('tingnan sa bawat listing', $result['summary']);
        $this->assertStringContainsString('check each listing', $result['summary_en']);
    }

    public function test_a_neighbourhood_is_a_place_with_a_distance_not_plain_text(): void
    {
        // "bedspace near Lahug" used to be an address text match that could never widen to nearby areas.
        foreach (['Lahug', 'lahug', 'Banilad', 'Mabolo'] as $name) {
            $loc = app(\App\Services\AiSearch\IntentValidator::class)->validate(['location' => ['kind' => 'text', 'value' => $name]])['location'];
            $this->assertSame('landmark', $loc['kind'], $name);
            $this->assertSame('Cebu City', $loc['city']);
        }
    }

    public function test_a_search_never_ends_on_an_empty_page_when_something_is_listed(): void
    {
        $this->listing('Talamban Flats', ['property_type' => 'Apartment'], 4500);
        $finder = app(\App\Services\AiSearch\CandidateFinder::class);
        $validator = app(\App\Services\AiSearch\IntentValidator::class);

        // No dormitory exists: an apartment that fits everything else is suggested, with the right article.
        $dorm = $finder->find($validator->validate(['property_type' => 'Dormitory', 'price_max' => 18000]));
        $this->assertSame(0, $dorm['rows']->count());
        $this->assertSame(1, $dorm['alternatives']->count());
        $this->assertFalse($dorm['alternatives_closest']);

        // Not even another type fits exactly (a little over budget): the closest places still show, with what differs.
        $tight = $finder->find($validator->validate(['property_type' => 'House', 'price_max' => 4000]));
        $this->assertSame(0, $tight['rows']->count());
        $this->assertTrue($tight['alternatives_closest']);
        $this->assertNotEmpty($tight['alternatives']->first()['misses']);

        // A sentence with nothing searchable in it stays empty: there is nothing to be close to.
        $none = $finder->find($validator->validate([]));
        $this->assertSame(0, $none['alternatives']->count());
    }

    public function test_the_suggestion_sentence_uses_an_before_a_vowel(): void
    {
        $this->listing('Talamban Flats', ['property_type' => 'Apartment'], 4500);
        $this->useDriver(['language' => 'en', 'property_type' => 'Dormitory']);
        $svc = app(AiSearchService::class);
        $i = $svc->interpret('dorm please');
        $r = $svc->results($i['query'], $i['intent'], true);

        $this->assertStringContainsString('an Apartment', $r['summary']);
    }

    public function test_studio_is_an_apartment_and_walang_is_tagalog(): void
    {
        // Studio units are listed as Apartment here; the keyword fallback must agree with the prompt.
        $intent = (new \App\Services\AiSearch\KeywordFallback)->parse('furnished studio near Ayala');
        $this->assertSame('Apartment', $intent['property_type']);

        $this->assertSame('tl', \App\Services\AiSearch\LanguageDetector::detect('walang curfew, pwede magluto, Talisay'));
        $this->assertSame('ceb', \App\Services\AiSearch\LanguageDetector::detect('walay curfew, pwede magluto, Talisay'));
    }

    public function test_ai_intent_is_validated_and_cached(): void
    {
        $driver = $this->useDriver(['location' => ['kind' => 'city', 'value' => 'Mandaue'], 'price_max' => 4000, 'property_type' => 'Castle', 'language' => 'ceb']);
        $svc = app(AiSearchService::class);

        $a = $svc->interpret('Naa bay apartment sa Mandaue ubos sa 4k');
        $b = $svc->interpret('naa bay  apartment sa mandaue ubos sa 4K'); // same after normalizing

        $this->assertTrue($a['used_ai']);
        $this->assertNull($a['intent']['property_type']);          // 'Castle' dropped
        $this->assertSame('Mandaue City', $a['intent']['location']['value']);
        $this->assertSame('ceb', $a['intent']['language']);
        $this->assertSame(1, $driver->calls);                       // second one came from cache
        $this->assertSame($a['intent'], $b['intent']);
    }

    public function test_a_driver_failure_falls_back_instead_of_erroring(): void
    {
        $this->useDriver(null, null, fail: true);
        $this->listing('Talamban Rooms', [], 4500);

        $interp = app(AiSearchService::class)->interpret('room in Talamban');

        $this->assertFalse($interp['used_ai']);
        $this->assertSame('unavailable', $interp['fallback_reason']);
        $this->assertNotNull($interp['intent']['location']);
    }

    public function test_the_daily_cap_sends_everyone_to_the_fallback(): void
    {
        config(['ai_search.daily_cap' => 1]);
        $this->useDriver(['language' => 'en', 'property_type' => 'Apartment']);
        $svc = app(AiSearchService::class);

        $first = $svc->interpret('apartment one');
        $second = $svc->interpret('apartment two');

        $this->assertTrue($first['used_ai']);
        $this->assertFalse($second['used_ai']);
        $this->assertSame('busy', $second['fallback_reason']);
    }

    public function test_ai_order_applies_within_groups_but_never_puts_a_miss_above_an_exact(): void
    {
        $exactA = $this->listing('Exact A', [], 4000);
        $exactB = $this->listing('Exact B', [], 4200);
        $close = $this->listing('Close', [], 5200);
        // The AI "wants" Close first, then B, then A.
        $this->useDriver(['language' => 'en'], ['order' => [$close->property_id, $exactB->property_id, $exactA->property_id], 'summary' => 'AI wrote this.', 'summary_en' => 'AI wrote this.']);
        config(['ai_search.rank_with_ai' => true]);
        $svc = app(AiSearchService::class);
        $intent = ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'price_max' => 4500, 'language' => 'en'];

        $result = $svc->results('x', $intent, true);

        $this->assertSame(['Exact B', 'Exact A', 'Close'], $result['rows']->map(fn ($r) => $r['property']->title)->all());
        $this->assertSame('AI wrote this.', $result['summary']);
        $this->assertTrue($result['ai_summary']);
    }

    public function test_rank_failure_keeps_the_results_and_uses_the_template_summary(): void
    {
        $this->listing('Only one', [], 4500);
        $this->useDriver(null, null, fail: true);
        config(['ai_search.rank_with_ai' => true]);

        $result = app(AiSearchService::class)->results('x', ['location' => ['kind' => 'city', 'value' => 'Cebu City'], 'language' => 'en'], true);

        $this->assertSame(1, $result['rows']->count());
        $this->assertFalse($result['ai_summary']);
        $this->assertSame('unavailable', $result['fallback_reason']);
        $this->assertNotSame('', $result['summary']);
    }

    public function test_over_the_concurrency_limit_the_ai_is_skipped_and_the_slot_is_released_after_use(): void
    {
        $driver = $this->useDriver(['language' => 'en', 'property_type' => 'Apartment']);
        config(['ai_search.max_concurrent' => 2]);
        $svc = app(AiSearchService::class);

        // Two searches already running (their slots are held in the shared counter).
        Cache::put('ai_search:running', 2, 60);
        $crowded = $svc->interpret('apartment please');
        $this->assertFalse($crowded['used_ai']);
        $this->assertSame('crowded', $crowded['fallback_reason']);
        $this->assertSame(0, $driver->calls);
        $this->assertSame(2, Cache::get('ai_search:running')); // refusing didn't leak a slot

        // One finishes: now a search fits, and it gives its slot back when done.
        Cache::put('ai_search:running', 1, 60);
        $ok = $svc->interpret('apartment please');
        $this->assertTrue($ok['used_ai']);
        $this->assertSame(1, Cache::get('ai_search:running'));
    }

    public function test_a_crowded_moment_does_not_use_up_the_daily_cap(): void
    {
        $this->useDriver(['language' => 'en']);
        config(['ai_search.max_concurrent' => 1, 'ai_search.daily_cap' => 1]);
        $svc = app(AiSearchService::class);

        Cache::put('ai_search:running', 1, 60);
        $svc->interpret('one');
        Cache::put('ai_search:running', 0, 60);

        $this->assertTrue($svc->interpret('two')['used_ai']); // cap of 1 still unspent
    }

    public function test_by_default_only_one_ai_call_is_made_per_search_and_php_writes_the_summary(): void
    {
        $this->listing('Only one', [], 4500);
        $driver = $this->useDriver(['language' => 'en', 'location' => ['kind' => 'city', 'value' => 'Cebu City']], ['order' => [], 'summary' => 'SHOULD NOT BE USED', 'summary_en' => 'x']);
        $svc = app(AiSearchService::class);

        $interp = $svc->interpret('room in cebu');
        $result = $svc->results('room in cebu', $interp['intent'], true);

        $this->assertSame(1, $driver->calls);
        $this->assertFalse($result['ai_summary']);
        $this->assertStringContainsString('Only one', $result['summary']);
    }

    public function test_openrouter_driver_sends_json_mode_and_reads_fenced_json(): void
    {
        config(['services.openrouter.key' => 'or-key', 'services.openrouter.model' => 'google/gemma-4-31b-it:free']);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => "Sure!\n```json\n{\"language\":\"ceb\",\"price_max\":4000}\n```"]]]])]);

        $out = (new OpenRouterDriver)->interpret('ubos sa 4k', Vocabulary::forPrompt());

        $this->assertSame(4000, $out['price_max']);
        Http::assertSent(fn ($r) => $r->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer or-key')
            && $r['model'] === 'google/gemma-4-31b-it:free'
            && $r['response_format'] === ['type' => 'json_object']
            && str_contains($r['messages'][1]['content'], '<query>ubos sa 4k</query>')
            && str_contains($r['messages'][0]['content'], 'OUTPUT FORMAT'));
    }

    public function test_openrouter_driver_omits_temperature_unless_configured(): void
    {
        // Claude Haiku 5.5 rejects `temperature`; sent with require_parameters it makes OpenRouter answer 404 (no endpoint).
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"price_max":4000}']]]])]);
        config(['services.openrouter.key' => 'k', 'services.openrouter.model' => 'anthropic/claude-haiku-5.5', 'services.openrouter.temperature' => null]);

        (new OpenRouterDriver)->interpret('x', Vocabulary::forPrompt());
        Http::assertSent(fn ($r) => ! isset($r->data()['temperature']) && $r['provider'] === ['require_parameters' => true]);

        config(['services.openrouter.temperature' => '0.1']);
        (new OpenRouterDriver)->interpret('x', Vocabulary::forPrompt());
        Http::assertSent(fn ($r) => ($r->data()['temperature'] ?? null) === 0.1);
    }

    public function test_openrouter_quota_and_garbage_are_unavailable(): void
    {
        config(['services.openrouter.key' => 'k', 'services.openrouter.model' => 'm']);
        Http::fakeSequence('openrouter.ai/*')->push(['error' => ['message' => 'Rate limit exceeded: free-models-per-day']], 429)->push(['choices' => [['message' => ['content' => 'no json here']]]]);
        $driver = new OpenRouterDriver;

        try {
            $driver->interpret('x', Vocabulary::forPrompt());
            $this->fail('429 should be unavailable');
        } catch (AiSearchUnavailable $e) {
            $this->assertStringContainsString('429', $e->getMessage());
            $this->assertStringContainsString('free-models-per-day', $e->getMessage());
        }

        $this->expectException(AiSearchUnavailable::class);
        $driver->interpret('x', Vocabulary::forPrompt());
    }

    public function test_gemini_driver_parses_the_json_it_is_given(): void
    {
        config(['services.gemini.key' => 'k', 'services.gemini.model' => 'm']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"language":"tl","price_max":5000}']]]]]])]);

        $out = (new GeminiDriver)->interpret('hanap ako', Vocabulary::forPrompt());

        $this->assertSame(5000, $out['price_max']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'models/m:generateContent') && $r->header('x-goog-api-key')[0] === 'k'
            && str_contains($r['contents'][0]['parts'][0]['text'], '<query>hanap ako</query>'));
    }

    public function test_gemini_bad_json_is_unavailable(): void
    {
        config(['services.gemini.key' => 'k', 'services.gemini.model' => 'm']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not json']]]]]])]);

        $this->expectException(AiSearchUnavailable::class);
        (new GeminiDriver)->interpret('x', Vocabulary::forPrompt());
    }

    public function test_gemini_quota_error_is_unavailable(): void
    {
        config(['services.gemini.key' => 'k', 'services.gemini.model' => 'm']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('quota', 429)]);

        $this->expectException(AiSearchUnavailable::class);
        (new GeminiDriver)->interpret('x', Vocabulary::forPrompt());
    }
}
