<?php

namespace App\Services\AiSearch;

/**
 * The safety net for "Why this fits you" lines (plans/ai-search-maps-and-haiku.md, Phase 6). A model writes one short
 * line per listing; this class keeps only the lines that cannot be wrong about the listing:
 *   - the id must be one we sent, with at most one line each;
 *   - at most 120 characters, plain text;
 *   - every number in it (a price, a distance, minutes, a rating) must appear in that listing's own facts;
 *   - every amenity it names (Wi-Fi, aircon, parking, ...) must be one the listing has.
 * A line that fails any check is dropped, never repaired: the card then simply has no line.
 */
class ReasonGuard
{
    public const MAX_LENGTH = 120;

    /** Short spellings people (and models) use, mapped to the amenity they mean. */
    private const ALIASES = [
        'aircon' => 'air conditioning', 'air con' => 'air conditioning', 'ac' => 'air conditioning',
        'wifi' => 'wi-fi', 'wi fi' => 'wi-fi', 'internet' => 'wi-fi',
        'parking' => 'parking space', 'garage' => 'parking space',
        'laundry' => 'laundry area', 'washing machine' => 'washing machine',
    ];

    /**
     * @param  list<array<string, mixed>>  $reasons  what the model returned: [{id, text}, ...]
     * @param  array<int, array<string, mixed>>  $facts  the facts sent for each listing, keyed by id (see AiSearchService::reasonFacts)
     * @param  list<int|float>  $searchNumbers  numbers of the search itself (the budget): the tenant already knows them
     * @return array<int, string> id => text, only the lines that passed
     */
    public static function clean(array $reasons, array $facts, array $searchNumbers = []): array
    {
        $kept = [];

        foreach ($reasons as $reason) {
            $id = is_array($reason) && is_numeric($reason['id'] ?? null) ? (int) $reason['id'] : null;
            $text = is_array($reason) && is_string($reason['text'] ?? null) ? trim(preg_replace('/\s+/u', ' ', strip_tags($reason['text'])) ?? '') : '';

            if ($id === null || ! isset($facts[$id]) || isset($kept[$id]) || $text === '' || mb_strlen($text) > self::MAX_LENGTH) {
                continue;
            }
            if (! self::numbersAreKnown($text, $facts[$id], $searchNumbers) || ! self::amenitiesAreKnown($text, $facts[$id])) {
                continue;
            }

            $kept[$id] = $text;
        }

        return $kept;
    }

    /** Whether a summary passes the same two checks against every listing shown (the AI summary of Phase 6). */
    public static function summaryIsSafe(string $summary, array $facts, array $searchNumbers = []): bool
    {
        if ($summary === '' || mb_strlen($summary) > 400) {
            return false;
        }

        $all = ['numbers' => [], 'amenities' => []];
        foreach ($facts as $fact) {
            unset($fact['id']);
            $all['numbers'] = array_merge($all['numbers'], self::numbersIn(self::flatten($fact)));
            $all['amenities'] = array_merge($all['amenities'], array_map('mb_strtolower', (array) ($fact['amenities'] ?? [])));
        }

        return self::numbersAreKnown($summary, ['_numbers' => $all['numbers']], $searchNumbers) && self::amenitiesAreKnown($summary, ['amenities' => $all['amenities']]);
    }

    /** @param  array<string, mixed>  $fact */
    private static function numbersAreKnown(string $text, array $fact, array $searchNumbers = []): bool
    {
        unset($fact['id']); // a listing's id is not a fact the tenant may be quoted
        $allowed = array_merge($fact['_numbers'] ?? self::numbersIn(self::flatten($fact)), self::numbersIn(implode(' ', array_map('strval', $searchNumbers))));

        foreach (self::numbersIn($text) as $number) {
            if (! in_array($number, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $fact */
    private static function amenitiesAreKnown(string $text, array $fact): bool
    {
        $has = array_map('mb_strtolower', (array) ($fact['amenities'] ?? []));
        $lower = ' ' . mb_strtolower(preg_replace('/[^\p{L}\p{N}\s\-\/]+/u', ' ', $text) ?? $text) . ' ';

        $names = array_unique(array_merge(array_map('mb_strtolower', Vocabulary::amenities()), array_keys(self::ALIASES)));
        foreach ($names as $name) {
            if (! str_contains($lower, ' ' . $name . ' ')) {
                continue;
            }
            $means = self::ALIASES[$name] ?? $name;
            if (! in_array($means, $has, true)) {
                return false;
            }
        }

        return true;
    }

    /** Every digit group in a text, written the same way: "₱3,500" and "3500" are both "3500", "1.5 km" is "1.5". */
    private static function numbersIn(string $text): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/u', $text, $m);

        return array_values(array_unique(array_map(fn ($n) => rtrim(str_replace(',', '', $n), '.'), $m[0])));
    }

    /** @param  array<string, mixed>  $fact */
    private static function flatten(array $fact): string
    {
        $parts = [];
        array_walk_recursive($fact, function ($value) use (&$parts) {
            if (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        });

        return implode(' ', $parts);
    }
}
