<?php

namespace App\Services\AiSearch;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini over plain HTTP (same style as OcrService), JSON-only output via
 * responseSchema. Built for the free tier: short timeout, one retry on 429/5xx,
 * and every failure becomes AiSearchUnavailable so the keyword fallback answers.
 *
 * The model comes from GEMINI_MODEL; Google renames models often, so check
 * AI Studio for the current free Flash model.
 */
class GeminiDriver implements AiSearchDriver
{
    public function interpret(string $query, array $vocab): array
    {
        return $this->generate(Prompts::interpret($vocab), Prompts::interpretUser($query), $this->interpretSchema());
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

        $out = $this->generate(Prompts::rank(), $payload, $this->rankSchema());

        return [
            'order' => array_values(array_map('intval', (array) ($out['order'] ?? []))),
            'summary' => trim((string) ($out['summary'] ?? '')),
            'summary_en' => trim((string) ($out['summary_en'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function generate(string $system, string $user, array $schema): array
    {
        $model = (string) config('services.gemini.model');

        $config = [
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
            'temperature' => 0.1,
            'maxOutputTokens' => 2048,
        ];
        // Reading a sentence needs no long reasoning, and Gemini 3 models think first by default (slow on the free tier).
        if (filled($level = config('services.gemini.thinking'))) {
            $config['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        try {
            $response = $this->http()->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => $config,
            ]);
        } catch (Throwable $e) {
            throw new AiSearchUnavailable('Gemini request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            // Google's own message ("models/x is not found...", "quota exceeded") is what tells you what to fix.
            throw new AiSearchUnavailable('Gemini returned HTTP ' . $response->status() . ': ' . ($response->json('error.message') ?? mb_substr($response->body(), 0, 200)));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($data)) {
            throw new AiSearchUnavailable('Gemini returned no usable JSON.');
        }

        return $data;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('ai_search.timeout', 8))
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->retry(1, 400, function ($e) {
                // Retry once on a network error, a rate limit or a server error; never on a bad request.
                $status = $e instanceof RequestException ? $e->response->status() : 500;

                return $status === 429 || $status >= 500;
            }, throw: false);
    }

    private function interpretSchema(): array
    {
        $strings = ['type' => 'ARRAY', 'items' => ['type' => 'STRING']];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'location' => ['type' => 'OBJECT', 'nullable' => true, 'properties' => [
                    'kind' => ['type' => 'STRING', 'enum' => ['landmark', 'city', 'barangay', 'text']],
                    'value' => ['type' => 'STRING'],
                ], 'required' => ['kind', 'value']],
                'radius_km' => ['type' => 'NUMBER', 'nullable' => true],
                'property_type' => ['type' => 'STRING', 'nullable' => true, 'enum' => Vocabulary::PROPERTY_TYPES],
                'price_min' => ['type' => 'INTEGER', 'nullable' => true],
                'price_max' => ['type' => 'INTEGER', 'nullable' => true],
                'amenities' => $strings,
                'rules' => $strings,
                'living' => ['type' => 'STRING', 'nullable' => true],
                'for' => ['type' => 'STRING', 'nullable' => true],
                'furnishing' => ['type' => 'STRING', 'nullable' => true],
                'occupants' => ['type' => 'INTEGER', 'nullable' => true],
                'language' => ['type' => 'STRING', 'enum' => ['en', 'tl', 'ceb', 'mixed']],
                'unmapped' => $strings,
            ],
            'required' => ['language'],
        ];
    }

    private function rankSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'order' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                'summary' => ['type' => 'STRING'],
                'summary_en' => ['type' => 'STRING'],
            ],
            'required' => ['order', 'summary', 'summary_en'],
        ];
    }
}
