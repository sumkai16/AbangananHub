<?php

namespace App\Services\AiSearch;

use App\Models\AiSearchLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        private NameMatcher $names,
        private GeoLookup $geo,
    ) {}

    /** @return array{query: string, intent: array<string, mixed>, chips: list<array<string, mixed>>, used_ai: bool, fallback_reason: ?string} */
    public function interpret(string $query, ?string $skipAi = null): array
    {
        $original = $this->clean($query);
        // "near me" comes out first: the AI would file it as a place that matches nothing. The page asks the
        // browser for the position and sends it back with the results call, never with this one.
        [$query, $nearMe] = NearMe::split($original);
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

                        try {
                            return $this->driver->interpret($query, Vocabulary::forPrompt());
                        } catch (Throwable $e) {
                            $this->refundDailyCap(); // a provider outage must not burn the day's allowance

                            throw $e;
                        }
                    });
                    // A place OpenStreetMap knows becomes a map point before the intent is cached, so a repeat is free.
                    $intent = $this->geo->apply($this->validator->validate($raw));
                    $intent = $this->planned($query, $raw, $intent);
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
        $intent['language'] = LanguageDetector::detect($original) ?? $intent['language'];
        $intent['near_me'] = $nearMe;

        // A business or listing named in the sentence ("property ni maria santos"). Read from our own tables,
        // after the AI and keyword readings, so both paths agree and the cached AI intent stays name-free.
        $intent = $this->names->apply($query, $intent);

        $token = $this->parkUsage();

        return [
            'query' => $query,
            'intent' => $intent,
            'chips' => $this->chips->build($intent),
            'used_ai' => $usedAi,
            'fallback_reason' => $reason,
            'usage_token' => $token,
        ];
    }

    /**
     * Reads a screenshot into a search (Phase 7). Returns the validated intent and a plain sentence to search, or
     * null when the feature is off, the AI is not available, a limit is hit, or the image holds no search.
     *
     * @return null|array{intent: array<string, mixed>, sentence: string}
     */
    public function interpretImage(string $dataUri): ?array
    {
        if (! AiFeatures::on('vision_search') || ! ($this->driver instanceof AiSearchAdvanced) || $this->driver instanceof NullDriver) {
            return null;
        }

        try {
            $raw = $this->inSlot(function () use ($dataUri) {
                $this->withinDailyCap() || throw new AiSearchBusy('busy');

                try {
                    return $this->driver->interpretImage($dataUri, Vocabulary::forPrompt());
                } catch (Throwable $e) {
                    $this->refundDailyCap();

                    throw $e;
                }
            });
        } catch (Throwable $e) {
            $e instanceof AiSearchBusy || Log::warning('AI search image failed', ['error' => $e->getMessage()]);

            return null;
        }

        $intent = $this->geo->apply($this->validator->validate($raw));
        app(AiUsage::class)->feature('vision_search');

        return IntentValidator::isEmpty($intent) ? null : ['intent' => $intent, 'sentence' => IntentSentence::from($intent)];
    }

    /**
     * The "Why this fits you" lines (Phase 6). The page asks for them after the cards are on screen, so a slow or failed
     * call never delays results. The listings are found again from the intent (never from anything the browser could
     * have edited), at most 8, and the model's answer is kept only where ReasonGuard says it cannot be wrong.
     *
     * @param  array<string, mixed>  $intent
     * @return array{reasons: array<int, string>, summary: string, summary_en: string}
     */
    public function reasons(array $intent, ?int $userId = null): array
    {
        $empty = ['reasons' => [], 'summary' => '', 'summary_en' => ''];
        if (! AiFeatures::on('reasons') || ! ($this->driver instanceof AiSearchAdvanced) || $this->driver instanceof NullDriver) {
            return $empty;
        }

        $intent = $this->validator->validate($intent);
        if (IntentValidator::isEmpty($intent)) {
            return $empty;
        }

        $found = $this->finder->find($intent);
        $rows = $found['rows']->take(8);
        if ($rows->isEmpty()) {
            return $empty;
        }

        $facts = $rows->mapWithKeys(fn ($r) => [$r['property']->property_id => $this->reasonFacts($r, $intent)])->all();
        $searchNumbers = array_values(array_filter([$intent['price_min'], $intent['price_max'], $intent['bedrooms_min'] ?? null, $intent['occupants'] ?? null], fn ($n) => $n !== null));
        $language = $intent['language'];
        $key = 'ai_search:reasons:' . $this->driverName() . ':' . md5(json_encode([$intent, $facts, $language]));

        try {
            $answer = Cache::get($key) ?: $this->inSlot(function () use ($intent, $facts, $language) {
                $this->withinDailyCap() || throw new AiSearchBusy('busy');

                try {
                    return $this->driver->reasons($intent, array_values($facts), $language);
                } catch (Throwable $e) {
                    $this->refundDailyCap();

                    throw $e;
                }
            });
            Cache::put($key, $answer, (int) config('ai_search.cache.rank_ttl', 600));
        } catch (Throwable $e) {
            $e instanceof AiSearchBusy || Log::warning('AI search reasons failed', ['error' => $e->getMessage()]);

            return $empty;
        }

        app(AiUsage::class)->feature('reasons');
        $safe = ReasonGuard::summaryIsSafe($answer['summary'], $facts, $searchNumbers) && ReasonGuard::summaryIsSafe($answer['summary_en'], $facts, $searchNumbers);

        return [
            'reasons' => ReasonGuard::clean($answer['reasons'], $facts, $searchNumbers),
            'summary' => $safe ? $answer['summary'] : '',
            'summary_en' => $safe ? $answer['summary_en'] : '',
        ];
    }

    /**
     * The public facts of one listing a reason may be built from: nothing that is not already on its card.
     *
     * @param  array<string, mixed>  $row  a CandidateFinder row
     * @return array<string, mixed>
     */
    private function reasonFacts(array $row, array $intent): array
    {
        $p = $row['property'];
        $fee = (int) round((float) $p->min_rental_fee);
        // Worked out here so the model never has to calculate (a number it calculated is a number we cannot check).
        $budget = $intent['price_max'] === null ? [] : ($fee <= $intent['price_max'] ? ['under_budget_by' => $intent['price_max'] - $fee] : ['over_budget_by' => $fee - $intent['price_max']]);

        return $budget + [
            'id' => $p->property_id,
            'title' => $p->title,
            'type' => $p->property_type,
            'area' => trim(($p->barangay ? $p->barangay . ', ' : '') . $p->city_municipality),
            'price_from' => $fee,
            'rating' => $p->avg_rating !== null ? round((float) $p->avg_rating, 1) : null,
            'reviews' => (int) $p->review_count,
            'amenities' => $p->amenities->pluck('amenity_name')->merge($p->units->flatMap(fn ($u) => $u->amenities->pluck('amenity_name')))->unique()->values()->all(),
            'meets' => array_column($row['hits'], 'text'),
            'misses' => array_column($row['misses'], 'text'),
        ];
    }

    /**
     * Runs the planner (tools, a few rounds) when the reading says the sentence needs it: several places at once, a
     * commute, a comparison. On any failure the first reading stands. Costs up to 4 AI calls, all counted against the day.
     *
     * @param  array<string, mixed>  $raw  the model's own answer (for `plan`)
     * @param  array<string, mixed>  $intent  its validated reading
     * @return array<string, mixed>
     */
    private function planned(string $query, array $raw, array $intent): array
    {
        $plan = in_array($raw['plan'] ?? null, ['multi_place', 'commute', 'compare'], true) ? $raw['plan'] : null;
        if ($plan === null || ! AiFeatures::on('planner') || ! ($this->driver instanceof OpenRouterDriver)) {
            return $intent;
        }

        $extra = (int) config('ai_search.planner.max_rounds', 3);
        if (! $this->withinDailyCap($extra)) {
            return $intent;
        }

        try {
            $result = app(SearchPlanner::class)->run($query, $intent, $plan);
        } catch (Throwable $e) {
            Log::warning('AI search planner failed', ['error' => $e->getMessage()]);
            $this->refundDailyCap($extra);

            return $intent;
        }

        if ($result === null) {
            $this->refundDailyCap($extra);

            return $intent;
        }

        app(AiUsage::class)->feature('planner');

        return $this->geo->apply($result['intent']);
    }

    /** The results call is a separate request: park what this one spent so the log can add it up. */
    private function parkUsage(): ?string
    {
        $usage = app(AiUsage::class);
        if ($usage->calls === 0 && $usage->features === []) {
            return null;
        }

        $token = Str::random(20);
        Cache::put('ai_usage:' . $token, $usage->snapshot(), 900);

        return $token;
    }

    /**
     * A follow-up on the search the tenant already has (Phase 3). `$patch` is a ready-made change from a quick chip
     * (no AI); otherwise the message goes to the model, or to the keyword reader when it is not available. The
     * answer has the shape interpret() returns, plus `mode`: "refine" (the current search was changed) or "new".
     *
     * @param  array<string, mixed>  $previous  the intent the page holds (validated again here)
     * @param  array<string, mixed>  $patch  RefinePatch shape
     * @param  array<string, mixed>  $context  what the page knows, e.g. cheapest_fee of the results shown
     * @return array{query: string, intent: array<string, mixed>, chips: list<array<string, mixed>>, used_ai: bool, fallback_reason: ?string, usage_token: ?string, mode: string}
     */
    public function refine(string $message, array $previous, array $patch = [], array $context = [], ?string $skipAi = null): array
    {
        $message = $this->clean($message);
        $previous = $this->validator->validate($previous);
        $reason = $skipAi;
        $usedAi = false;
        $mode = 'refine';

        if ($patch !== [] || $message === '') {
            $intent = $this->validator->validate(RefinePatch::apply($previous, $patch, $context));

            return $this->refined($message, $intent, false, null, 'refine');
        }

        [$text, $nearMe] = NearMe::split($message);
        $intent = null;

        if ($text !== '' && $skipAi === null && $this->driver instanceof AiSearchAdvanced && ! ($this->driver instanceof NullDriver) && AiFeatures::on('refine')) {
            $key = 'ai_search:refine:' . $this->driverName() . ':' . md5(json_encode(RefinePatch::forPrompt($previous)) . '|' . Landmarks::normalize($text));

            try {
                $answer = Cache::get($key) ?: $this->inSlot(function () use ($text, $previous) {
                    $this->withinDailyCap() || throw new AiSearchBusy('busy');

                    try {
                        return $this->driver->refine(RefinePatch::forPrompt($previous), $text, Vocabulary::forPrompt());
                    } catch (Throwable $e) {
                        $this->refundDailyCap();

                        throw $e;
                    }
                });
                Cache::put($key, $answer, 86400);

                $mode = $answer['mode'];
                $candidate = $answer['intent'];
                if ($mode === 'refine') {
                    // What the provider never saw stays: the tenant's position or map area, the radius, "near me".
                    if (in_array($previous['location']['kind'] ?? null, ['point', 'area'], true) && empty($candidate['location'])) {
                        $candidate['location'] = $previous['location'];
                    }
                    $candidate['radius_km'] = $previous['radius_km'];
                    $candidate['near_me'] = $previous['near_me'];
                    // A model sometimes forgets to copy a field. A core filter the message does not release stays.
                    if (! RefinePatch::releasesFilters($text)) {
                        foreach (['location', 'property_type', 'price_min', 'price_max', 'for', 'furnishing', 'living'] as $field) {
                            if (($candidate[$field] ?? null) === null && ($previous[$field] ?? null) !== null) {
                                $candidate[$field] = $previous[$field];
                            }
                        }
                    }
                }
                $intent = $this->validator->validate($candidate);
                $intent = $this->validator->validate(RefinePatch::apply($intent, ['ops' => $answer['ops']], $context));
                $usedAi = true;
                app(AiUsage::class)->feature('refine');
            } catch (AiSearchBusy $e) {
                $reason = $e->reason;
            } catch (Throwable $e) {
                Log::warning('AI search refine failed', ['error' => $e->getMessage()]);
                $reason = 'unavailable';
            }
        }

        if ($intent === null) {
            $merged = RefinePatch::mergeKeywords($previous, $this->validator->validate($this->fallback->parse($text)), $text);
            $intent = $this->validator->validate(RefinePatch::apply($this->validator->validate($merged['intent']), ['ops' => $merged['ops']], $context));
        }

        $intent['language'] = LanguageDetector::detect($message) ?? $intent['language'];
        $intent['near_me'] = $nearMe || ($mode === 'refine' && $previous['near_me'] && ($intent['location']['kind'] ?? null) === 'point');
        $intent = $this->geo->apply($this->names->apply($text, $intent));
        // A name from the earlier search stays when the follow-up does not mention one.
        if ($mode === 'refine' && $intent['host'] === null && $intent['name_miss'] === null && $previous['host'] !== null) {
            $intent['host'] = $previous['host'];
        }

        return $this->refined($message, $intent, $usedAi, $reason, $mode);
    }

    /** @return array{query: string, intent: array<string, mixed>, chips: list<array<string, mixed>>, used_ai: bool, fallback_reason: ?string, usage_token: ?string, mode: string} */
    private function refined(string $message, array $intent, bool $usedAi, ?string $reason, string $mode): array
    {
        return [
            'query' => $message,
            'intent' => $intent,
            'chips' => $this->chips->build($intent),
            'used_ai' => $usedAi,
            'fallback_reason' => $reason,
            'usage_token' => $this->parkUsage(),
            'mode' => $mode,
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
            ? ['rows' => collect(), 'exact_count' => 0, 'relaxed' => [], 'alternatives' => collect()]
            : $this->finder->find($intent);
        $rows = $found['rows'];
        // Real minutes when a router answers (OpenRouteService); otherwise the rows keep their straight-line estimate.
        $routed = app(TravelTime::class)->annotate($rows, $intent);
        if ($routed !== $rows) {
            $rows = $routed;
            $found['exact_count'] = $rows->filter(fn ($r) => empty($r['misses']))->count();
            app(AiUsage::class)->feature('routing');
        }
        $exact = $found['exact_count'];
        $language = $intent['language'];

        $alternatives = $found['alternatives'];
        $more = $this->finder->moreFromHost($intent, $rows->pluck('property.property_id')->merge($alternatives->pluck('property.property_id'))->all());
        $nearest = ($intent['location']['kind'] ?? null) === 'point';
        $summary = $this->composer->compose($rows, $exact, $language, $alternatives, $intent['property_type'], $intent['host'], $nearest, $intent['unmapped'], $found['alternatives_closest'] ?? false, $intent['sort'] ?? null);
        $aiSummary = false;

        if ($usedAi && $rows->isNotEmpty() && config('ai_search.rank_with_ai')) {
            try {
                $ranked = $this->rank($intent, $rows, $language, $exact > 0 && $exact === $rows->count());
                $rows = $this->applyOrder($rows, $ranked['order']);
                if (filled($ranked['summary'])) {
                    $summary = ['summary' => $ranked['summary'], 'summary_en' => $ranked['summary_en'] ?: $ranked['summary'], 'alt_note' => $summary['alt_note']];
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
            'name_notice' => $this->nameNotice($intent, $rows),
            'location_notice' => $this->locationNotice($intent),
            'rows' => $rows,
            'alternatives' => $alternatives,
            'more_from_host' => $more === null ? null : $more + ['name' => $intent['host']['name']],
            'alt_note' => $summary['alt_note'] ?? '',
            'alternatives_closest' => $found['alternatives_closest'] ?? false,
            'exact_count' => $exact,
            'relaxed' => $found['relaxed'],
            'summary' => $summary['summary'],
            'summary_en' => $summary['summary_en'],
            'language' => $language,
            'used_ai' => $usedAi,
            'ai_summary' => $aiSummary,
            'fallback_reason' => $fallbackReason,
        ];

        $result['feedback_token'] = $this->log($result, $userId, (int) round((microtime(true) - $started) * 1000));

        return $result;
    }

    /**
     * What the results page says about "near me": which spot was used (so the tenant can correct it), or why
     * there is none. Coordinates stay out of it; only the area name goes to the page.
     *
     * @return null|array{kind: string, area?: string}
     */
    private function locationNotice(array $intent): ?array
    {
        $location = $intent['location'];

        if (is_array($location) && ($location['kind'] ?? null) === 'point') {
            return ['kind' => 'using', 'area' => (string) $location['name']];
        }

        if ($intent['near_me']) {
            return ['kind' => $intent['geo_outside'] ? 'outside' : 'unavailable'];
        }

        return null;
    }

    /**
     * Tells the tenant when what they typed names something we do not have, instead of a bare "nothing
     * matches". `name` is a business or listing name they typed after "ni" / "owned by" and so on; `place` is
     * an unknown place (no area or landmark we know) that found nothing. Wording never says "no landlord
     * named ...": personal names are not searchable, and the page must not confirm or deny that a person exists.
     *
     * @return null|array{kind: string, name: string, others: bool}
     */
    private function nameNotice(array $intent, Collection $rows): ?array
    {
        if ($intent['host'] !== null) {
            return null;
        }

        if (filled($intent['name_miss'])) {
            return ['kind' => 'name', 'name' => $intent['name_miss'], 'others' => $rows->isNotEmpty()];
        }

        $location = $intent['location'];
        if ($rows->isEmpty() && is_array($location) && ($location['kind'] ?? null) === 'text') {
            return ['kind' => 'place', 'name' => (string) ($location['name'] ?? $location['value']), 'others' => false];
        }

        return null;
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
    /** Counts $calls AI calls against the day (a plain search is 1, a planner search up to 5). */
    private function withinDailyCap(int $calls = 1): bool
    {
        $key = $this->dailyCapKey();
        Cache::add($key, 0, now('Asia/Manila')->endOfDay()->addMinutes(5));

        return Cache::increment($key, $calls) <= (int) config('ai_search.daily_cap', 600);
    }

    /** Gives back the search withinDailyCap() counted when the driver call then failed; never goes below 0. */
    private function refundDailyCap(int $calls = 1): void
    {
        $key = $this->dailyCapKey();

        if ((int) Cache::get($key, 0) > 0) {
            Cache::decrement($key, min($calls, (int) Cache::get($key, 0)));
        }
    }

    private function dailyCapKey(): string
    {
        return 'ai_search:daily:' . now('Asia/Manila')->format('Ymd');
    }

    /** @return ?string the token the page rates this search with (AiSearchFeedbackController) */
    private function log(array $result, ?int $userId, int $latencyMs): ?string
    {
        try {
            // A tenant's position is for one search: the log keeps the area it was named as, never the coordinates.
            $intent = $result['intent'];
            if (($intent['location']['kind'] ?? null) === 'point') {
                $intent['location'] = ['kind' => 'point', 'name' => $intent['location']['name']];
            }

            $log = AiSearchLog::create([
                'user_id' => $userId,
                'query' => $result['query'],
                'language' => $result['language'],
                'intent' => $intent,
                'result_count' => $result['rows']->count(),
                'exact_count' => $result['exact_count'],
                'relaxed' => $result['relaxed'],
                'driver' => $this->driverName(),
                'used_ai' => $result['used_ai'],
                'fallback_reason' => $result['fallback_reason'],
                'latency_ms' => $latencyMs,
                'ai_calls' => app(AiUsage::class)->calls,
                'cost_usd' => round(app(AiUsage::class)->costUsd, 6),
                'features' => app(AiUsage::class)->features ?: null,
            ]);

            return \App\Http\Controllers\AiSearchFeedbackController::tokenFor($log);
        } catch (Throwable $e) {
            Log::warning('AI search log failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
