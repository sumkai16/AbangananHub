<?php

namespace App\Services\AiSearch;

/**
 * Minutes and kilometres for "10 minutes walk to IT Park". Estimates from straight-line distance until a router
 * answers (Phase 4, TravelTime); the numbers live in config('ai_search.travel') and are shared with the map's JS, so
 * the page and the search never disagree. An estimate is always shown as "about".
 */
class Travel
{
    /** Straight-line km a trip of `$minutes` can cover: the radius of the search circle. */
    public static function radiusKm(int $minutes, string $mode): float
    {
        $t = config('ai_search.travel');
        $moving = max(0, $minutes - self::wait($mode));

        return max(0.3, min(10.0, round(($moving / 60) * self::speed($mode) / $t['detour'], 2)));
    }

    /** The radius the search uses: the minutes when the tenant gave them, else the plain radius. */
    public static function effectiveRadiusKm(array $intent): float
    {
        $m = $intent['max_minutes'] ?? null;

        return is_array($m) ? self::radiusKm((int) $m['minutes'], (string) $m['mode']) : (float) $intent['radius_km'];
    }

    /** Estimated minutes for a straight-line distance. */
    public static function minutes(float $km, string $mode): int
    {
        $t = config('ai_search.travel');

        return (int) max(1, round(($km * $t['detour'] / self::speed($mode)) * 60 + self::wait($mode)));
    }

    public static function label(int $minutes, string $mode, bool $estimate = true): string
    {
        $how = ['walk' => 'walk', 'drive' => 'drive', 'jeep' => 'by jeep'][$mode] ?? 'walk';

        return ($estimate ? 'about ' : '') . $minutes . ' min ' . $how;
    }

    private static function speed(string $mode): float
    {
        return (float) config('ai_search.travel.' . ['walk' => 'walk_kmh', 'drive' => 'drive_kmh', 'jeep' => 'jeep_kmh'][$mode] ?? 'walk_kmh');
    }

    private static function wait(string $mode): int
    {
        return $mode === 'jeep' ? (int) config('ai_search.travel.jeep_wait_min') : 0;
    }
}
