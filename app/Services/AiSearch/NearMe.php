<?php

namespace App\Services\AiSearch;

/**
 * Spots "near me" in a search sentence (English, Tagalog, Bisaya) and takes the phrase out.
 *
 * It is taken out before the AI or the keyword reading sees the sentence: left in, the model files "me" as a
 * text place that matches nothing. The page then asks the browser for the tenant's position and sends it
 * back as a `point` location (plans/near-me-search.md); nothing here ever sees coordinates.
 */
class NearMe
{
    /** Longest first, so "near my location" is not cut down to a shorter hit. Matched as whole words. */
    private const PHRASES = [
        'near where i am', 'near my location', 'near my place', 'closest to me', 'nearest to me', 'close to me',
        'near to me', 'around me', 'near me', 'nearby',
        'malapit sa akin', 'malapit sa akoa', 'malapit sakin',
        'duol sa akoa', 'duol nako', 'duol ra nako', 'dapit nako', 'dapit sa akoa',
    ];

    /**
     * @return array{0: string, 1: bool} the sentence without the phrase, and whether it was there
     */
    public static function split(string $query): array
    {
        $found = false;
        $pattern = '/(?<![\p{L}\p{N}])(?:' . implode('|', array_map(fn ($p) => preg_quote($p, '/'), self::PHRASES)) . ')(?![\p{L}\p{N}])/iu';

        $clean = preg_replace_callback($pattern, function () use (&$found) {
            $found = true;

            return ' ';
        }, $query) ?? $query;

        return [trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean), $found];
    }
}
