<?php

namespace App\Services\AiSearch;

use App\Models\AiSearchLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates one AI search (plans/ai-search.md):
 *   interpret(): sentence -> validated intent (AI, or keyword fallback)
 *   results():   intent -> candidates (exact, then relaxed) -> ordered + summarized
 *
 * Any AI failure (no key, quota, timeout, bad JSON) lands on the keyword
 * fallback and a template summary, so a search always returns something.
 */
class AiSearchService
{
    public function __construct(
        private AiSearchDriver $driver,
        private IntentValidator $validator,
        private CandidateFinder $finder,
        private KeywordFallback $fallback,
        private SummaryComposer $composer,
        private ChipBuilder $chips,
    ) {
    }

    /** @return array{query: string, intent: array<string, mixed>, chips: list<array<string, mixed>>, used_ai: bool, fallback_reason: ?string} */
    public function interpret(string $query, ?string $skipAi = null): array
    {
        $query = $this->clean($query);
        $reason = $skipAi; // e.g. 'rate_limited': keyword search only
        $usedAi = false;
        $intent = null;

        if ($query === '') {
            $intent = $this->validator->validate([]);
        } elseif ($skipAi !== null) {
            // keyword fallback below
        } elseif ($this->driver instanceof NullDriver) {
            $reason = 'not_configured';
        } else {
            $cacheKey = 'ai_search:interpret:' . $this->driverName() . ':' . md5(Landmarks::normalize($query));
            if ($cached = Cache::get($cacheKey)) {
                $intent = $cached;
                $usedAi = true;
            } else {
                try {
                    // A slot first (cheap to refuse), then the daily cap, so a crowded moment doesn't spend the day's allowance.
                    $raw = $this->inSlot(function () use ($query) {
                        $this->withinDailyCap() || throw new AiSearchBusy('busy');

                        return $this->driver->interpret($query, Vocabulary::forPrompt());
                    });
                    $intent = $this->validator->validate($raw);
                    $usedAi = true;
                    Cache::put($cacheKey, $intent, (int) config('ai_search.cache.interpret_ttl'));
                } catch (AiSearchBusy $e) {
                    $reason = $e->reason;
                } catch (Throwable $e) {
                    Log::warning('AI search interpret failed', ['error' => $e->getMessage()]);
                    $reason = 'unavailable';
                }
            }
        }

        if ($intent === null) {
            $intent = $this->validator->validate($this->fallback->parse($query));
        }

        // The model often answers "mixed" for plain Tagalog or Bisaya; the words typed tell us better.
        $intent['language'] = LanguageDetector::detect($query) ?? $intent['language'];

        return [
            'query' => $query,
            'intent' => $intent,
            'chips' => $this->chips->build($intent),
            'used_ai' => $usedAi,
            'fallback_reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent  validated again here: the page posts it back after chips are removed
     * @return array<string, mixed>
     */
    public function results(string $query, array $intent, bool $usedAi, ?int $userId = null, ?string $fallbackReason = null): array
    {
        $started = microtime(true);
        $query = $this->clean($query);
        $intent = $this->validator->validate($intent);

        $found = IntentValidator::isEmpty($intent)
            ? ['rows' => collect(), 'exact_count' => 0, 'relaxed' => []]
            : $this->finder->find($intent);
        $rows = $found['rows'];
        $exact = $found['exact_count'];
        $language = $intent['language'];

        $summary = $this->composer->compose($rows, $exact, $language);
        $aiSummary = false;

        if ($usedAi && $rows->isNotEmpty() && config('ai_search.rank_with_ai')) {
            try {
                $ranked = $this->rank($intent, $rows, $language, $exact > 0 && $exact === $rows->count());
                $rows = $this->applyOrder($rows, $ranked['order']);
                if (filled($ranked['summary'])) {
                    $summary = ['summary' => $ranked['summary'], 'summary_en' => $ranked['summary_en'] ?: $ranked['summary']];
                    $aiSummary = true;
                }
            } catch (Throwable $e) {
                Log::warning('AI search rank failed', ['error' => $e->getMessage()]);
                $fallbackReason ??= 'unavailable';
            }
        }

        $result = [
            'query' => $query,
            'intent' => $intent,
            'chips' => $this->chips->build($intent, $rows),
            'rows' => $rows,
            'exact_count' => $exact,
            'relaxed' => $found['relaxed'],
            'summary' => $summary['summary'],
            'summary_en' => $summary['summary_en'],
            'language' => $language,
            'used_ai' => $usedAi,
            'ai_summary' => $aiSummary,
            'fallback_reason' => $fallbackReason,
        ];

        $this->log($result, $userId, (int) round((microtime(true) - $started) * 1000));

        return $result;
    }

    /** Ask the AI to order + summarize, cached for a few minutes per (intent, candidate set). */
    private function rank(array $intent, Collection $rows, string $language, bool $exactOnly): array
    {
        $candidates = $rows->map(fn ($r) => [
            'id' => $r['property']->property_id,
            'title' => $r['property']->title,
            'type' => $r['property']->property_type,
            'area' => trim(($r['property']->barangay ? $r['property']->barangay . ', ' : '') . $r['property']->city_municipality),
            'price_from' => (float) $r['property']->min_rental_fee,
            'rating' => $r['property']->avg_rating !== null ? round((float) $r['property']->avg_rating, 1) : null,
            'reviews' => (int) $r['property']->review_count,
            'meets' => array_column($r['hits'], 'text'),
            'misses' => array_column($r['misses'], 'text'),
        ])->all();

        $key = 'ai_search:rank:' . $this->driverName() . ':' . md5(json_encode([$intent, $candidates, $language]));

        return Cache::remember($key, (int) config('ai_search.cache.rank_ttl'), fn () => $this->inSlot(fn () => $this->driver->rank($intent, $candidates, $language, $exactOnly)));
    }

    /** The AI may reorder within the exact group and within the close group, never between them. */
    private function applyOrder(Collection $rows, array $order): Collection
    {
        $position = array_flip(array_map('intval', $order));
        $sorted = $rows->sortBy(fn ($r) => [empty($r['misses']) ? 0 : 1, $position[$r['property']->property_id] ?? PHP_INT_MAX])->values();

        return $sorted;
    }

    private function clean(string $query): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($query)) ?? ''), 0, (int) config('ai_search.max_query_length', 300));
    }

    private function driverName(): string
    {
        return (string) config('ai_search.driver', 'null');
    }

    /**
     * Runs an AI call only if fewer than `ai_search.max_concurrent` are already running. Each call holds
     * a PHP worker for as long as the model takes (10-15 s on free models), so without a cap a burst of
     * searches could leave no worker for ordinary pages. Over the cap -> AiSearchBusy('crowded') and the
     * caller answers with keyword results. The counter expires on its own so a crashed request can't
     * hold a slot forever.
     */
    private function inSlot(callable $call): mixed
    {
        $key = 'ai_search:running';
        Cache::add($key, 0, 60);

        if (Cache::increment($key) > (int) config('ai_search.max_concurrent', 5)) {
            Cache::decrement($key);

            throw new AiSearchBusy('crowded');
        }

        try {
            return $call();
        } finally {
            Cache::decrement($key);
        }
    }

    /** Counts one search against today's cap (resets at midnight Manila time); false once it is spent. */
    private function withinDailyCap(): bool
    {
        $key = 'ai_search:daily:' . now('Asia/Manila')->format('Ymd');
        Cache::add($key, 0, now('Asia/Manila')->endOfDay()->addMinutes(5));

        return Cache::increment($key) <= (int) config('ai_search.daily_cap', 600);
    }

    private function log(array $result, ?int $userId, int $latencyMs): void
    {
        try {
            AiSearchLog::create([
                'user_id' => $userId,
                'query' => $result['query'],
                'language' => $result['language'],
                'intent' => $result['intent'],
                'result_count' => $result['rows']->count(),
                'exact_count' => $result['exact_count'],
                'relaxed' => $result['relaxed'],
                'driver' => $this->driverName(),
                'used_ai' => $result['used_ai'],
                'fallback_reason' => $result['fallback_reason'],
                'latency_ms' => $latencyMs,
            ]);
        } catch (Throwable $e) {
            Log::warning('AI search log failed', ['error' => $e->getMessage()]);
        }
    }
}
