<?php

namespace App\Services\AiSearch;

/**
 * What a driver offers beyond reading a sentence (plans/ai-search-maps-and-haiku.md): follow-up messages, the tool
 * using planner, the "why this fits you" lines, and images. Only OpenRouterDriver implements it; the service checks
 * `instanceof` and the feature flag, so Gemini, Anthropic and NullDriver keep working and a search never fails
 * because an optional feature is missing. Same contract as AiSearchDriver: return plain arrays, never touch the
 * database, throw AiSearchUnavailable on any failure.
 */
interface AiSearchAdvanced
{
    /**
     * A follow-up message on a search the tenant already has (Phase 3).
     *
     * @param  array<string, mixed>  $previous  RefinePatch::forPrompt() of the current search
     * @param  array<string, mixed>  $vocab  allowed values, from Vocabulary::forPrompt()
     * @return array{mode: string, ops: list<string>, intent: array<string, mixed>}
     */
    public function refine(array $previous, string $message, array $vocab): array;

    /**
     * One short "why this fits you" line per candidate, plus a summary and its English version (Phase 6). The answer is
     * untrusted: AiSearchService passes it through ReasonGuard before anything is shown.
     *
     * @param  array<string, mixed>  $intent  validated intent
     * @param  list<array<string, mixed>>  $candidates  public facts per listing, keyed by `id`
     * @return array{reasons: list<array{id: int, text: string}>, summary: string, summary_en: string}
     */
    public function reasons(array $intent, array $candidates, string $language): array;

    /**
     * Read a screenshot of a rental post, or a photo of a place, into a raw search (Phase 7).
     *
     * @param  string  $dataUri  a data: URI of a JPEG, already re-encoded (no EXIF)
     * @param  array<string, mixed>  $vocab  Vocabulary::forPrompt()
     * @return array<string, mixed>
     */
    public function interpretImage(string $dataUri, array $vocab): array;
}
