<?php

namespace App\Services\AiSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real walking and driving minutes from a searched place to each result, from OpenRouteService
 * (https://openrouteservice.org, free key in ORS_API_KEY). One matrix call covers up to 20 listings; each pair is
 * cached for 30 days since a listing does not move. Without a key, with the feature off, on a timeout or any error,
 * nothing changes: the straight-line estimate ("about 8 min walk", Travel) stays.
 *
 * What leaves the server: the coordinates of a LANDMARK or a found place and of public listings. Never the tenant's
 * own position or a drawn map area (RULES.md, AI search): those anchors are skipped here.
 *
 * Jeepneys have no transit data for Cebu anywhere, so "by jeep" is the driving time slowed down and a waiting time
 * added (config ai_search.travel), and is always shown as "about".
 */
class TravelTime
{
    private const URL = 'https://api.openrouteservice.org/v2/matrix/';

    private const PROFILES = ['walk' => 'foot-walking', 'drive' => 'driving-car', 'jeep' => 'driving-car'];

    public static function available(): bool
    {
        return AiFeatures::on('routing') && filled(config('services.openrouteservice.key'));
    }

    /**
     * Puts real minutes on the location hit or miss of each row, and turns a hit into a miss when the real time is
     * over the minutes asked for. Rows come back in the same order (exact ones first).
     *
     * @param  Collection<int, array<string, mixed>>  $rows  CandidateFinder rows
     * @param  array<string, mixed>  $intent
     * @return Collection<int, array<string, mixed>>
     */
    public function annotate(Collection $rows, array $intent): Collection
    {
        $loc = $intent['location'] ?? null;
        $trip = $intent['max_minutes'] ?? null;
        if (! self::available() || $rows->isEmpty() || ($loc['kind'] ?? null) !== 'landmark' || ! isset($loc['lat'])) {
            return $rows;
        }

        $mode = is_array($trip) ? $trip['mode'] : 'walk';
        $minutes = $this->minutes(
            ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng']],
            $rows->take(20)->mapWithKeys(fn ($r) => [$r['property']->property_id => ['lat' => (float) $r['property']->latitude, 'lng' => (float) $r['property']->longitude]])->all(),
            $mode,
        );
        if ($minutes === []) {
            return $rows;
        }

        $rows = $rows->map(function ($row) use ($minutes, $mode, $trip, $loc) {
            $min = $minutes[$row['property']->property_id] ?? null;
            if ($min === null) {
                return $row;
            }

            $text = $min . ' min ' . ['walk' => 'walk', 'drive' => 'drive', 'jeep' => 'by jeep'][$mode] . ' to ' . $loc['name'];
            $within = ! is_array($trip) || $min <= $trip['minutes'];

            // Replace the straight-line location fact with the real one.
            $row['hits'] = array_values(array_filter($row['hits'], fn ($h) => $h['facet'] !== 'location'));
            $row['misses'] = array_values(array_filter($row['misses'], fn ($m) => $m['facet'] !== 'location'));
            $within ? array_unshift($row['hits'], ['facet' => 'location', 'text' => $text]) : array_unshift($row['misses'], ['facet' => 'location', 'text' => $text]);
            $row['route_minutes'] = $min;
            $within || $row['score'] = max(0, $row['score'] - 20);

            return $row;
        });

        // Exact first (a stable sort keeps the rest of the order the finder chose).
        return $rows->sortBy(fn ($r) => empty($r['misses']) ? 0 : 1)->values();
    }

    /**
     * Minutes from one anchor to several places.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array<int, array{lat: float, lng: float}>  $to  keyed by property id
     * @return array<int, int> property id => minutes; ids the router could not answer are left out
     */
    public function minutes(array $from, array $to, string $mode): array
    {
        $out = [];
        $missing = [];
        foreach ($to as $id => $point) {
            $hit = Cache::get($this->key($from, $point, $mode));
            $hit === null ? $missing[$id] = $point : $out[$id] = (int) $hit;
        }
        if ($missing === []) {
            return $out;
        }

        try {
            $response = Http::timeout(4)
                ->withHeaders(['Authorization' => (string) config('services.openrouteservice.key')])
                ->post(self::URL . (self::PROFILES[$mode] ?? 'foot-walking'), [
                    'locations' => array_merge([[$from['lng'], $from['lat']]], array_map(fn ($p) => [$p['lng'], $p['lat']], array_values($missing))),
                    'sources' => [0],
                    'destinations' => range(1, count($missing)),
                    'metrics' => ['duration'],
                ]);
            if ($response->failed()) {
                return $out;
            }
            $durations = (array) ($response->json('durations.0') ?? []);
        } catch (Throwable) {
            return $out; // slow, blocked or down: the estimate stays
        }

        foreach (array_keys($missing) as $i => $id) {
            if (! is_numeric($durations[$i] ?? null)) {
                continue;
            }
            $minutes = $this->toMinutes((float) $durations[$i], $mode);
            Cache::put($this->key($from, $missing[$id], $mode), $minutes, now()->addDays(30));
            $out[$id] = $minutes;
        }

        return $out;
    }

    /** Seconds from the router to minutes; the jeepney is the car, slowed down, plus the wait. */
    private function toMinutes(float $seconds, string $mode): int
    {
        $t = config('ai_search.travel');
        $min = $seconds / 60;

        if ($mode === 'jeep') {
            $min = $min * ($t['drive_kmh'] / $t['jeep_kmh']) + $t['jeep_wait_min'];
        }

        return max(1, (int) round($min));
    }

    private function key(array $from, array $to, string $mode): string
    {
        return 'ai_search:route:' . $mode . ':' . round($from['lat'], 4) . ',' . round($from['lng'], 4) . ':' . round($to['lat'], 4) . ',' . round($to['lng'], 4);
    }
}
