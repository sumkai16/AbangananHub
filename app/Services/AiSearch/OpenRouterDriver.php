<?php

namespace App\Services\AiSearch;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenRouter (https://openrouter.ai): one OpenAI-style API in front of many models, including
 * free ones (model ids ending in ":free"). Changing OPENROUTER_MODEL is the whole upgrade path
 * from a free model to a paid one (Claude Haiku, Gemini Flash-Lite, ...).
 *
 * Two ways to get JSON back: strict schema (OPENROUTER_SCHEMA=true, for models that support it, which removes
 * "no usable JSON" and off-list values) or "JSON mode" with the shape spelled out in the prompt. IntentValidator
 * catches anything off either way. The long fixed part of the prompt is marked cacheable for Anthropic models.
 * Every failure becomes AiSearchUnavailable so the keyword fallback answers.
 *
 * Besides the two AiSearchDriver calls, this driver also does the optional features (follow-ups, planner,
 * reasons, screenshots): see AiSearchAdvanced.
 */
class OpenRouterDriver implements AiSearchAdvanced, AiSearchDriver
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    public function interpret(string $query, array $vocab): array
    {
        $schema = config('services.openrouter.schema');

        return $this->chat(
            Prompts::interpret($vocab) . ($schema ? '' : Prompts::interpretShape()),
            Prompts::interpretUser($query),
            $schema ? ['schema' => ['name' => 'search_intent', 'schema' => Schemas::interpret($vocab)]] : [],
        );
    }

    public function rank(array $intent, array $candidates, string $language, bool $exact): array
    {
        $payload = json_encode([
            'language' => $language,
            'all_candidates_fit_fully' => $exact,
            'search' => array_filter([
                'location' => $intent['location']['name'] ?? null,
                'type' => $intent['property_type'],
                'budget_max' => $intent['price_max'],
                'budget_min' => $intent['price_min'],
                'amenities' => $intent['amenities'],
                'house_rules_must_allow' => $intent['rules'],
                'unmapped_wishes' => $intent['unmapped'],
            ], fn ($v) => $v !== null && $v !== []),
            'candidates' => $candidates,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $out = $this->chat(Prompts::rank() . Prompts::rankShape(), $payload);

        return [
            'order' => array_values(array_map('intval', (array) ($out['order'] ?? []))),
            'summary' => trim((string) ($out['summary'] ?? '')),
            'summary_en' => trim((string) ($out['summary_en'] ?? '')),
        ];
    }

    public function refine(array $previous, string $message, array $vocab): array
    {
        $schema = config('services.openrouter.schema');
        $user = '<previous>' . json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</previous>\n" . Prompts::interpretUser($message);

        $out = $this->chat(
            Prompts::interpret($vocab) . ($schema ? '' : Prompts::interpretShape()),
            $user,
            ['system_extra' => Prompts::refineRules() . ($schema ? '' : Prompts::refineShape())]
                + ($schema ? ['schema' => ['name' => 'search_followup', 'schema' => Schemas::refine($vocab)]] : []),
        );

        if (! is_array($out['intent'] ?? null)) {
            throw new AiSearchUnavailable('OpenRouter returned no search for the follow-up.');
        }

        return [
            'mode' => ($out['mode'] ?? 'refine') === 'new' ? 'new' : 'refine',
            'ops' => array_values(array_intersect((array) ($out['ops'] ?? []), RefinePatch::OPS)),
            'intent' => $out['intent'],
        ];
    }

    public function reasons(array $intent, array $candidates, string $language): array
    {
        $schema = config('services.openrouter.schema');
        $payload = json_encode([
            'language' => $language,
            'search' => array_filter([
                'location' => $intent['location']['name'] ?? null,
                'type' => $intent['property_type'],
                'budget_max' => $intent['price_max'],
                'budget_min' => $intent['price_min'],
                'amenities' => $intent['amenities'],
                'house_rules_must_allow' => $intent['rules'],
                'unmapped_wishes' => $intent['unmapped'],
            ], fn ($v) => $v !== null && $v !== []),
            'candidates' => $candidates,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $out = $this->chat(
            Prompts::reasons() . ($schema ? '' : Prompts::reasonsShape()),
            $payload,
            $schema ? ['schema' => ['name' => 'listing_reasons', 'schema' => Schemas::reasons()]] : [],
        );

        return [
            'reasons' => array_values(array_filter((array) ($out['reasons'] ?? []), 'is_array')),
            'summary' => trim((string) ($out['summary'] ?? '')),
            'summary_en' => trim((string) ($out['summary_en'] ?? '')),
        ];
    }

    public function interpretImage(string $dataUri, array $vocab): array
    {
        $schema = config('services.openrouter.schema');

        return $this->chat(
            Prompts::interpret($vocab) . Prompts::imageRules() . ($schema ? '' : Prompts::interpretShape()),
            Prompts::interpretUser('(the tenant attached an image: read the search from it)'),
            ['images' => [$dataUri]] + ($schema ? ['schema' => ['name' => 'search_intent', 'schema' => Schemas::interpret($vocab)]] : []),
        );
    }

    /**
     * One call that answers with a JSON object. `$options`: `schema` => ['name', 'schema'] for strict output,
     * `images` => list of data URIs sent with the user message, `max_tokens`.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chat(string $system, string $user, array $options = []): array
    {
        $payload = [
            'model' => config('services.openrouter.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemContent($system, $options['system_extra'] ?? null)],
                ['role' => 'user', 'content' => $this->userContent($user, (array) ($options['images'] ?? []))],
            ],
            // Free models are often "reasoning" models: they think first, and a small cap cuts the answer off mid-thought.
            'max_tokens' => (int) ($options['max_tokens'] ?? 4000),
            'response_format' => isset($options['schema'])
                ? ['type' => 'json_schema', 'json_schema' => ['name' => $options['schema']['name'], 'strict' => true, 'schema' => $options['schema']['schema']]]
                : ['type' => 'json_object'],
            // Only route to models that really support the format; the free router otherwise sometimes lands on one that answers in prose.
            'provider' => ['require_parameters' => true],
        ] + $this->temperature();

        $data = $this->decode((string) $this->send($payload)->json('choices.0.message.content'));
        if ($data === null) {
            throw new AiSearchUnavailable('OpenRouter returned no usable JSON.');
        }

        return $data;
    }

    /**
     * Posts a chat payload and returns the response, or throws AiSearchUnavailable. Records the usage
     * (calls, cost, cached tokens) so the search log can show what a search cost.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload, ?int $timeout = null): Response
    {
        try {
            $response = $this->http($timeout)->post(self::URL, $payload);
        } catch (Throwable $e) {
            throw new AiSearchUnavailable('OpenRouter request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            // error.message is often just "Provider returned error"; the upstream provider's own words are in metadata.
            $detail = $response->json('error.metadata.raw') ?? $response->json('error.message') ?? $response->body();
            $provider = $response->json('error.metadata.provider_name');
            throw new AiSearchUnavailable('OpenRouter returned HTTP ' . $response->status() . ($provider ? " ({$provider})" : '') . ': ' . mb_substr(is_string($detail) ? $detail : json_encode($detail), 0, 300));
        }

        app(AiUsage::class)->record((array) $response->json('usage', []));

        return $response;
    }

    /**
     * The system prompt, as one cacheable block when the model supports prompt caching (Anthropic). The fixed
     * part (rules, vocabulary, examples) is identical for every search, so a cache hit skips re-reading it.
     *
     * @return string|list<array<string, mixed>>
     */
    private function systemContent(string $system, ?string $extra = null): string|array
    {
        if (config('services.openrouter.cache_prompt') && str_starts_with((string) config('services.openrouter.model'), 'anthropic/')) {
            // The cached block ends before the part that differs per call, so every call shares the same prefix.
            $parts = [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]];
            $extra !== null && $parts[] = ['type' => 'text', 'text' => $extra];

            return $parts;
        }

        return $system . ($extra ?? '');
    }

    /**
     * @param  list<string>  $images  data URIs (screenshots), sent after the text
     * @return string|list<array<string, mixed>>
     */
    private function userContent(string $user, array $images): string|array
    {
        if ($images === []) {
            return $user;
        }

        return array_merge(
            [['type' => 'text', 'text' => $user]],
            array_map(fn ($uri) => ['type' => 'image_url', 'image_url' => ['url' => $uri]], $images),
        );
    }

    /**
     * Sent only when OPENROUTER_TEMPERATURE is set. Newer models (Claude Haiku 5.5) reject the parameter, and with
     * require_parameters on that leaves OpenRouter no endpoint to route to (HTTP 404), so the default is to omit it.
     *
     * @return array<string, float>
     */
    private function temperature(): array
    {
        $t = config('services.openrouter.temperature');

        return is_numeric($t) ? ['temperature' => (float) $t] : [];
    }

    /** Models sometimes wrap JSON in ```json fences or add a sentence around it. */
    private function decode(string $text): ?array
    {
        $text = trim($text);
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $data : null;
    }

    private function http(?int $timeout = null): PendingRequest
    {
        return Http::timeout($timeout ?? (int) config('ai_search.timeout', 8))
            ->withToken((string) config('services.openrouter.key'))
            ->withHeaders(['HTTP-Referer' => (string) config('app.url'), 'X-Title' => 'AbangananHub'])
            ->retry(1, 400, function ($e) {
                $status = $e instanceof RequestException ? $e->response->status() : 500;

                return $status === 429 || $status >= 500;
            }, throw: false);
    }
}
