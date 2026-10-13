<?php

namespace App\Services\AiSearch;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenRouter (https://openrouter.ai): one OpenAI-style API in front of many models, including
 * free ones (model ids ending in ":free"). Changing OPENROUTER_MODEL is the whole upgrade path
 * from a free model to a paid one (Claude Haiku, Gemini Flash-Lite, ...).
 *
 * Asks for "JSON mode" (response_format json_object, widely supported) and spells the shape out
 * in the prompt rather than relying on schema enforcement; IntentValidator catches anything off.
 * Every failure becomes AiSearchUnavailable so the keyword fallback answers.
 */
class OpenRouterDriver implements AiSearchDriver
{
    public function interpret(string $query, array $vocab): array
    {
        return $this->chat(Prompts::interpret($vocab) . Prompts::interpretShape(), Prompts::interpretUser($query));
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

    /** @return array<string, mixed> */
    private function chat(string $system, string $user): array
    {
        try {
            $response = $this->http()->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('services.openrouter.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.1,
                // Free models are often "reasoning" models: they think first, and a small cap cuts the answer off mid-thought.
                'max_tokens' => 4000,
                'response_format' => ['type' => 'json_object'],
                // Only route to models that really support JSON mode; the free router otherwise sometimes lands on one that answers in prose.
                'provider' => ['require_parameters' => true],
            ]);
        } catch (Throwable $e) {
            throw new AiSearchUnavailable('OpenRouter request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            // error.message is often just "Provider returned error"; the upstream provider's own words are in metadata.
            $detail = $response->json('error.metadata.raw') ?? $response->json('error.message') ?? $response->body();
            $provider = $response->json('error.metadata.provider_name');
            throw new AiSearchUnavailable('OpenRouter returned HTTP ' . $response->status() . ($provider ? " ({$provider})" : '') . ': ' . mb_substr(is_string($detail) ? $detail : json_encode($detail), 0, 300));
        }

        $data = $this->decode((string) $response->json('choices.0.message.content'));
        if ($data === null) {
            throw new AiSearchUnavailable('OpenRouter returned no usable JSON.');
        }

        return $data;
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

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('ai_search.timeout', 8))
            ->withToken((string) config('services.openrouter.key'))
            ->withHeaders(['HTTP-Referer' => (string) config('app.url'), 'X-Title' => 'AbangananHub'])
            ->retry(1, 400, function ($e) {
                $status = $e instanceof RequestException ? $e->response->status() : 500;

                return $status === 429 || $status >= 500;
            }, throw: false);
    }
}
