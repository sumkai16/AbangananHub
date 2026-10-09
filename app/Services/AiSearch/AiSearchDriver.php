<?php

namespace App\Services\AiSearch;

/**
 * The only part of AI search that talks to a model. A driver never touches the
 * database and returns plain arrays; anything it returns is validated by the
 * caller before use. Throw AiSearchUnavailable on any failure so the keyword
 * fallback can answer instead.
 */
interface AiSearchDriver
{
    /**
     * Turn a tenant's sentence (English, Tagalog, Bisaya or mixed) into raw
     * intent. See IntentValidator for the shape; unknown values are dropped there.
     *
     * @param  array<string, mixed>  $vocab  allowed values, from Vocabulary::forPrompt()
     * @return array<string, mixed>
     */
    public function interpret(string $query, array $vocab): array;

    /**
     * Order the candidates and write the summary.
     *
     * @param  array<string, mixed>  $intent      validated intent
     * @param  list<array<string, mixed>>  $candidates  public facts + hit/miss text per property, keyed by `id`
     * @return array{order: list<int>, summary: string, summary_en: string}
     */
    public function rank(array $intent, array $candidates, string $language, bool $exact): array;
}
