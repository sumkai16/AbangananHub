<?php

namespace App\Services\AiSearch;

use App\Models\GeoPlace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds a place the tenant named that is not in config/landmarks.php ("near Skyrise", "far from Cebu Pacific
 * office") on OpenStreetMap (Nominatim), inside Cebu only, and keeps the answer in geo_places.
 *
 * What leaves the server: the place NAME the tenant typed, nothing else. Never a position, never account data
 * (RULES.md, AI search). Nominatim's policy asks for one request a second and an identifying User-Agent, so a cache
 * lock spaces the calls out; a slow or failing lookup just leaves the place as plain text, as before.
 *
 * A found place becomes an ordinary `landmark` location with `source: geo`, so distance, widening, the map circle and
 * sorting all work for it with no further code.
 */
class GeoLookup
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @return null|array{name: string, city: ?string, lat: float, lng: float}
     */
    public function find(string $name): ?array
    {
        $key = Landmarks::normalize($name);
        if ($key === '' || strlen($key) < 3) {
            return null;
        }

        $known = GeoPlace::where('query_key', $key)->first();
        if ($known) {
            return $known->lat === null ? null : $this->shape($known->name, $known->city, $known->lat, $known->lng);
        }

        $hit = $this->ask($name);
        GeoPlace::updateOrCreate(['query_key' => $key], [
            'name' => $hit['name'] ?? null,
            'city' => $hit['city'] ?? null,
            'lat' => $hit['lat'] ?? null,
            'lng' => $hit['lng'] ?? null,
            'osm_ref' => $hit['ref'] ?? null,
            'fetched_at' => now(),
        ]);

        return $hit === null ? null : $this->shape($hit['name'], $hit['city'], $hit['lat'], $hit['lng']);
    }

    /**
     * Resolves what the validator left open: a text place becomes a map point when OpenStreetMap knows it, and a
     * "far from X" entry with no coordinates gets them (or is dropped). Only when the feature is on.
     *
     * @param  array<string, mixed>  $intent  a validated intent
     * @return array<string, mixed>
     */
    public function apply(array $intent): array
    {
        if (! AiFeatures::on('geo_lookup')) {
            return $intent;
        }

        $loc = $intent['location'];
        if (is_array($loc) && $loc['kind'] === 'text') {
            if ($place = $this->find($loc['value'])) {
                $intent['location'] = IntentValidator::geoLocation($place);
                app(AiUsage::class)->feature('geo_lookup');
            }
        }

        $avoid = [];
        foreach ($intent['avoid'] ?? [] as $entry) {
            if ($entry['lat'] === null && ($place = $this->find($entry['name']))) {
                $entry['lat'] = $place['lat'];
                $entry['lng'] = $place['lng'];
                $entry['name'] = $place['name'];
                app(AiUsage::class)->feature('geo_lookup');
            }
            $entry['lat'] === null || $avoid[] = $entry;
        }
        $intent['avoid'] = $avoid;

        return $intent;
    }

    /** @return null|array{name: string, city: ?string, lat: float, lng: float, ref: string} */
    private function ask(string $name): ?array
    {
        $bounds = config('cebu.bounds');

        try {
            return Cache::lock('geo_lookup_nominatim', 6)->block(4, function () use ($name, $bounds) {
                // One request a second, whoever asks.
                $last = (float) Cache::get('geo_lookup_last', 0);
                $wait = 1.05 - (microtime(true) - $last);
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }

                $response = Http::timeout(4)
                    ->withUserAgent('AbangananHub/1.0 (' . config('app.url') . ')')
                    ->get(self::URL, [
                        'q' => $name,
                        'format' => 'jsonv2',
                        'limit' => 3,
                        'countrycodes' => 'ph',
                        'addressdetails' => 1,
                        'bounded' => 1,
                        'viewbox' => $bounds['min_lng'] . ',' . $bounds['max_lat'] . ',' . $bounds['max_lng'] . ',' . $bounds['min_lat'],
                    ]);
                Cache::put('geo_lookup_last', microtime(true), 60);

                if ($response->failed()) {
                    return null;
                }

                foreach ((array) $response->json() as $row) {
                    $lat = (float) ($row['lat'] ?? 0);
                    $lng = (float) ($row['lon'] ?? 0);
                    if ($lat < $bounds['min_lat'] || $lat > $bounds['max_lat'] || $lng < $bounds['min_lng'] || $lng > $bounds['max_lng']) {
                        continue;
                    }
                    $address = (array) ($row['address'] ?? []);

                    return [
                        'name' => mb_substr(trim((string) ($row['name'] ?? '')) ?: trim(explode(',', (string) ($row['display_name'] ?? $name))[0]), 0, 80),
                        'city' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? null,
                        'lat' => round($lat, 5),
                        'lng' => round($lng, 5),
                        'ref' => mb_substr(($row['osm_type'] ?? '') . ($row['osm_id'] ?? ''), 0, 40),
                    ];
                }

                return null;
            });
        } catch (Throwable) {
            return null; // slow, blocked or down: the place stays plain text
        }
    }

    /** @return array{name: string, city: ?string, lat: float, lng: float} */
    private function shape(?string $name, ?string $city, float $lat, float $lng): array
    {
        return ['name' => (string) $name, 'city' => $city, 'lat' => $lat, 'lng' => $lng];
    }
}
