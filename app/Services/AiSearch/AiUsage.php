<?php

namespace App\Services\AiSearch;

/**
 * What the AI calls of ONE request used: how many, what they cost and how much of the prompt came from the cache.
 * Bound as a scoped singleton (AppServiceProvider), so each web request and each queue job starts from zero.
 * The drivers record into it; AiSearchService::log reads it.
 */
class AiUsage
{
    public int $calls = 0;

    public float $costUsd = 0.0;

    public int $promptTokens = 0;

    public int $cachedTokens = 0;

    public int $completionTokens = 0;

    /** @var list<string> the optional features that ran in this request (sort, geo_lookup, refine, planner ...) */
    public array $features = [];

    /** @param  array<string, mixed>  $usage  the `usage` object of an OpenRouter response */
    public function record(array $usage): void
    {
        $this->calls++;
        $this->costUsd += (float) ($usage['cost'] ?? 0);
        $this->promptTokens += (int) ($usage['prompt_tokens'] ?? 0);
        $this->completionTokens += (int) ($usage['completion_tokens'] ?? 0);
        $this->cachedTokens += (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
    }

    public function feature(string $name): void
    {
        if (! in_array($name, $this->features, true)) {
            $this->features[] = $name;
        }
    }

    /** @return array{calls: int, cost: float, features: list<string>} */
    public function snapshot(): array
    {
        return ['calls' => $this->calls, 'cost' => $this->costUsd, 'features' => $this->features];
    }

    /** Adds what an earlier request of the same search used (the interpret call, before the results call). */
    public function absorb(?array $earlier): void
    {
        if (! is_array($earlier)) {
            return;
        }
        $this->calls += (int) ($earlier['calls'] ?? 0);
        $this->costUsd += (float) ($earlier['cost'] ?? 0);
        foreach ((array) ($earlier['features'] ?? []) as $feature) {
            is_string($feature) && $this->feature($feature);
        }
    }

    public function reset(): void
    {
        $this->calls = $this->promptTokens = $this->cachedTokens = $this->completionTokens = 0;
        $this->costUsd = 0.0;
        $this->features = [];
    }
}
