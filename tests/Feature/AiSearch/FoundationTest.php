<?php

namespace Tests\Feature\AiSearch;

use App\Models\AiSearchLog;
use App\Services\AiSearch\AiSearchService;
use App\Services\AiSearch\AiUsage;
use App\Services\AiSearch\OpenRouterDriver;
use App\Services\AiSearch\Schemas;
use App\Services\AiSearch\Vocabulary;
use Illuminate\Support\Facades\Http;

/** Phase 0 of plans/ai-search-maps-and-haiku.md: strict schema, prompt caching, usage and cost in the log. */
class FoundationTest extends AiSearchTestCase
{
    private function fakeReply(array $usage = []): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => '{"price_max":4000,"language":"en"}']]],
            'usage' => $usage ?: ['prompt_tokens' => 5000, 'completion_tokens' => 80, 'cost' => 0.00061, 'prompt_tokens_details' => ['cached_tokens' => 4800]],
        ])]);
    }

    public function test_haiku_gets_a_strict_schema_and_a_cacheable_system_prompt(): void
    {
        config(['services.openrouter.key' => 'k', 'services.openrouter.model' => 'anthropic/claude-haiku-5.5', 'services.openrouter.schema' => true, 'services.openrouter.cache_prompt' => true]);
        $this->fakeReply();

        (new OpenRouterDriver)->interpret('ubos sa 4k', Vocabulary::forPrompt());

        Http::assertSent(function ($r) {
            $format = $r['response_format'];
            $system = $r['messages'][0]['content'];

            return $format['type'] === 'json_schema'
                && $format['json_schema']['strict'] === true
                && $format['json_schema']['schema']['additionalProperties'] === false
                && isset($format['json_schema']['schema']['properties']['sort'], $format['json_schema']['schema']['properties']['avoid'])
                && is_array($system) && $system[0]['cache_control'] === ['type' => 'ephemeral']
                && ! str_contains($system[0]['text'], 'OUTPUT FORMAT'); // the schema says the shape, the prompt need not
        });
    }

    public function test_a_free_model_keeps_json_mode_and_a_plain_system_prompt(): void
    {
        config(['services.openrouter.key' => 'k', 'services.openrouter.model' => 'google/gemma-4-31b-it:free', 'services.openrouter.schema' => false]);
        $this->fakeReply();

        (new OpenRouterDriver)->interpret('x', Vocabulary::forPrompt());

        Http::assertSent(fn ($r) => $r['response_format'] === ['type' => 'json_object']
            && is_string($r['messages'][0]['content'])
            && str_contains($r['messages'][0]['content'], 'OUTPUT FORMAT'));
    }

    public function test_every_call_is_counted_with_its_cost_and_cached_tokens(): void
    {
        config(['services.openrouter.key' => 'k', 'services.openrouter.model' => 'm']);
        $this->fakeReply();
        $usage = app(AiUsage::class);
        $usage->reset();

        $driver = new OpenRouterDriver;
        $driver->interpret('a', Vocabulary::forPrompt());
        $driver->interpret('b', Vocabulary::forPrompt());

        $this->assertSame(2, $usage->calls);
        $this->assertEqualsWithDelta(0.00122, $usage->costUsd, 0.000001);
        $this->assertSame(9600, $usage->cachedTokens);
    }

    public function test_the_schema_lists_every_field_as_required_and_only_known_values(): void
    {
        $schema = Schemas::interpret(Vocabulary::forPrompt());

        $this->assertSame(array_keys($schema['properties']), $schema['required']);
        $this->assertContains('Bedspace', $schema['properties']['property_type']['anyOf'][0]['enum']);
        $this->assertSame(['pets', 'smoking', 'overnight', 'curfew'], $schema['properties']['rules']['items']['enum']);
        $this->assertContains('price_asc', $schema['properties']['sort']['anyOf'][0]['enum']);
    }

    public function test_the_log_records_calls_cost_and_features_of_the_whole_search(): void
    {
        $this->listing('Talamban Rooms', [], 4500);
        $svc = app(AiSearchService::class);

        // The interpret call spent 1 call; the results call adds its own. The log shows both.
        $usage = app(AiUsage::class);
        $usage->reset();
        $usage->absorb(['calls' => 1, 'cost' => 0.0006, 'features' => ['sort']]);
        $usage->record(['cost' => 0.0004]);
        $usage->feature('reasons');

        $interp = $svc->interpret('boarding house in Talamban under 5k');
        $svc->results($interp['query'], $interp['intent'], false);

        $log = AiSearchLog::latest('id')->first();
        $this->assertSame(2, $log->ai_calls);
        $this->assertEqualsWithDelta(0.001, $log->cost_usd, 0.000001);
        $this->assertSame(['sort', 'reasons'], $log->features);
    }

    public function test_the_interpret_answer_carries_a_token_for_what_it_spent(): void
    {
        $usage = app(AiUsage::class);
        $usage->reset();
        $usage->record(['cost' => 0.0005]);

        $interp = app(AiSearchService::class)->interpret('boarding house in Talamban');

        $this->assertNotNull($interp['usage_token']);
        $this->assertSame(1, cache()->get('ai_usage:' . $interp['usage_token'])['calls']);
    }
}
