<?php

namespace App\Services\AiSearch;

/** Lookup over config/landmarks.php, plus the Haversine distance used for "near X". */
class Landmarks
{
    /** @return list<array{key: string, name: string, aliases: list<string>, lat: float, lng: float, city: string}> */
    public static function all(): array
    {
        return config('landmarks', []);
    }

    public static function byKey(string $key): ?array
    {
        foreach (self::all() as $landmark) {
            if ($landmark['key'] === $key) {
                return $landmark;
            }
        }

        return null;
    }

    /** Match a key, a name or any alias (case- and punctuation-insensitive). Longest alias wins on contains-match. */
    public static function find(string $text): ?array
    {
        $needle = self::normalize($text);
        if ($needle === '') {
            return null;
        }

        foreach (self::all() as $landmark) {
            if ($landmark['key'] === $needle || self::normalize($landmark['name']) === $needle) {
                return $landmark;
            }
        }

        $best = null;
        $bestLength = 0;
        foreach (self::all() as $landmark) {
            foreach ($landmark['aliases'] as $alias) {
                $alias = self::normalize($alias);
                if ($alias === $needle) {
                    return $landmark;
                }
                if (strlen($alias) > $bestLength && preg_match('/(^|\s)' . preg_quote($alias, '/') . '(\s|$)/', $needle)) {
                    $best = $landmark;
                    $bestLength = strlen($alias);
                }
            }
        }

        return $best;
    }

    /** Great-circle distance in km. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['ñ'], ['n'], $text);
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
