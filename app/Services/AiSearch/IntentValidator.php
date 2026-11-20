<?php

namespace App\Services\AiSearch;

use App\Models\Property;
use App\Support\BrowseFilters;
use App\Support\PropertyPolicies;

/**
 * Turns whatever a model (or the keyword fallback, or a client posting an edited
 * chip list) hands us into an intent that is safe to query with: every value is
 * matched against our own lists and anything unknown is dropped, never passed on.
 *
 * Intent shape:
 *  location       null | {kind: city|barangay|landmark|text, value, name, city?, lat?, lng?}
 *  radius_km      float (landmark only)
 *  property_type  ?string      price_min / price_max  ?int (PHP)
 *  amenities      list<string> (names from `amenities`)    rules  list<string> (BrowseFilters::RULES keys)
 *  living / for / furnishing  ?string     occupants ?int (units must sleep this many)
 *  sort           ?string  price_asc|price_desc|rating|newest|nearest (Schemas::SORTS); nearest needs a place with coordinates
 *  bedrooms_min   ?int 1-6     deposit_max ?int (0 = no deposit): both read from the available units
 *  language       en|tl|ceb|mixed     unmapped  list<string> (understood but not filterable)
 *  max_minutes    null | {minutes: 3-60, mode: walk|drive|jeep}: "10 minutes walk to IT Park". Needs a place with coordinates;
 *                 the search circle is then Travel::radiusKm(), not radius_km. Straight-line estimate until a router answers.
 *  avoid          list<{name, lat, lng, radius_km}> (max 2): "far from Colon". Entries without coordinates are left for
 *                 GeoLookup (interpret) and ignored everywhere else.
 *  anchors        list<{name, lat, lng, radius_km}> (max 3): places that ALL have to be close ("between IT Park and USC"). Set by the
 *                 planner (SearchPlanner) or posted back by the page; each needs coordinates in Cebu or a landmark name.
 *  plan_steps     list<string> (max 5): what the planner did, in our own words, for the "How I searched" note.
 *  host           null | {kind: business|listing, landlord_ids, property_ids, name, typed}: the sentence named a
 *                 business or listing we have (NameMatcher). Ids are re-checked here; the name comes from our table.
 *  name_miss      null | string: it named one we do not have. Only a note for the results page, never a filter.
 *  near_me        bool: the sentence said "near me" (NearMe). The page then sends the position as a `point` location.
 *  geo_outside    bool: the position sent was outside Cebu and was dropped.
 * location kind `landmark` with `source: geo` = a place found on OpenStreetMap by GeoLookup ("near Skyrise"): same shape as a
 * landmark, `value` is `geo:<slug>`, coordinates clamped to Cebu.
 * location kind `area` = {kind, value: 'map area', name, south, west, north, east}: what the map showed when the tenant
 * pressed "Search this area". From the page only, clamped to Cebu and to 25 km across; never sent to a provider.
 * location kind `point` = {kind, value, name, city, lat, lng}: the tenant's position, from the page only. It is
 * clamped to Cebu and rounded to about 110 m, and `name` is always the nearest area we know (NearestArea), never
 * text from the request.
 */
class IntentValidator
{
    public function __construct(private NameMatcher $names, private NearestArea $areas) {}

    private const PRICE_MIN = 500;

    private const PRICE_MAX = 200000;

    /** @param array<string, mixed> $raw */
    public function validate(array $raw): array
    {
        $location = $this->location($raw['location'] ?? null);
        $sentPoint = is_array($raw['location'] ?? null) && ($raw['location']['kind'] ?? null) === 'point';
        $min = $this->price($raw['price_min'] ?? null);
        $max = $this->price($raw['price_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $radius = (float) ($raw['radius_km'] ?? config('ai_search.default_radius_km', 2));

        $host = $this->names->validateHost($raw['host'] ?? null);
        $nameMiss = $host === null && is_string($raw['name_miss'] ?? null)
            ? (mb_substr(trim(strip_tags($raw['name_miss'])), 0, 60) ?: null)
            : null;

        return [
            'location' => $location,
            'radius_km' => max(0.5, min(10.0, $radius)),
            'property_type' => $this->pick($raw['property_type'] ?? null, Vocabulary::propertyTypes()),
            'price_min' => $min,
            'price_max' => $max,
            'amenities' => $this->pickMany($raw['amenities'] ?? [], Vocabulary::amenities()),
            'rules' => array_values(array_intersect(array_keys(BrowseFilters::RULES), array_map('strval', array_filter((array) ($raw['rules'] ?? []), 'is_string')))),
            'living' => $this->pick($raw['living'] ?? null, array_keys(PropertyPolicies::LIVING_ARRANGEMENTS)),
            'for' => $this->pick($raw['for'] ?? null, array_keys(BrowseFilters::SUITABLE_FOR)),
            'furnishing' => $this->pick($raw['furnishing'] ?? null, BrowseFilters::FURNISHING),
            'occupants' => is_numeric($raw['occupants'] ?? null) ? max(1, min(20, (int) $raw['occupants'])) : null,
            'sort' => $this->sort($raw['sort'] ?? null, $location),
            'max_minutes' => $this->maxMinutes($raw['max_minutes'] ?? null, $location),
            'avoid' => $this->avoid($raw['avoid'] ?? []),
            'anchors' => $this->anchors($raw['anchors'] ?? []),
            'plan_steps' => $this->planSteps($raw['plan_steps'] ?? []),
            'bedrooms_min' => AiFeatures::on('sort') && is_numeric($raw['bedrooms_min'] ?? null) ? max(1, min(6, (int) $raw['bedrooms_min'])) : null,
            'deposit_max' => AiFeatures::on('sort') && is_numeric($raw['deposit_max'] ?? null) ? max(0, min(200000, (int) $raw['deposit_max'])) : null,
            'language' => in_array($raw['language'] ?? null, ['en', 'tl', 'ceb', 'mixed'], true) ? $raw['language'] : 'en',
            'host' => $host,
            'name_miss' => $nameMiss,
            'near_me' => (bool) ($raw['near_me'] ?? false),
            'geo_outside' => $sentPoint && $location === null,
            'unmapped' => array_slice(array_values(array_filter(array_map(
                fn ($u) => is_string($u) ? mb_substr(trim(strip_tags($u)), 0, 60) : null,
                (array) ($raw['unmapped'] ?? []),
            ))), 0, 5),
        ];
    }

    /** True when nothing searchable survived validation. */
    public static function isEmpty(array $intent): bool
    {
        return $intent['location'] === null
            && ($intent['host'] ?? null) === null
            && $intent['property_type'] === null
            && $intent['price_min'] === null && $intent['price_max'] === null
            && $intent['amenities'] === [] && $intent['rules'] === []
            && $intent['living'] === null && $intent['for'] === null && $intent['furnishing'] === null
            && ($intent['sort'] ?? null) === null && ($intent['bedrooms_min'] ?? null) === null
            && ($intent['deposit_max'] ?? null) === null && ($intent['occupants'] ?? null) === null
            && ($intent['avoid'] ?? []) === [] && ($intent['anchors'] ?? []) === [];
    }

    /**
     * @return list<array{name: string, lat: float, lng: float, radius_km: float}>
     */
    private function anchors(mixed $raw): array
    {
        if (! AiFeatures::on('planner') || ! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($raw), 0, 12) as $entry) {
            if (count($out) >= 3) {
                break;
            }
            if (! is_array($entry)) {
                continue;
            }
            $name = mb_substr(trim(strip_tags((string) ($entry['name'] ?? $entry['place'] ?? ''))), 0, 80);
            if ($name === '') {
                continue;
            }

            if ($this->inCebu($entry['lat'] ?? null, $entry['lng'] ?? null)) {
                $lat = (float) $entry['lat'];
                $lng = (float) $entry['lng'];
            } elseif ($landmark = Landmarks::find($name)) {
                [$name, $lat, $lng] = [$landmark['name'], $landmark['lat'], $landmark['lng']];
            } else {
                continue; // a place with no coordinates cannot be measured from
            }

            $minutes = $entry['max_minutes'] ?? null;
            $radius = is_array($minutes) && is_numeric($minutes['minutes'] ?? null)
                ? Travel::radiusKm(max(3, min(60, (int) $minutes['minutes'])), in_array($minutes['mode'] ?? null, Schemas::TRAVEL_MODES, true) ? $minutes['mode'] : 'walk')
                : max(0.5, min(10.0, (float) ($entry['radius_km'] ?? config('ai_search.default_radius_km', 2))));

            $out[] = ['name' => $name, 'lat' => round($lat, 5), 'lng' => round($lng, 5), 'radius_km' => round($radius, 2)];
        }

        return $out;
    }

    /** @return list<string> */
    private function planSteps(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return array_slice(array_values(array_filter(array_map(fn ($s) => is_string($s) ? mb_substr(trim(strip_tags($s)), 0, 120) : null, $raw))), 0, 5);
    }

    /** A resolved place from GeoLookup as a location, the same shape a landmark has. */
    public static function geoLocation(array $place): array
    {
        return [
            'kind' => 'landmark',
            'value' => 'geo:' . substr(md5(Landmarks::normalize($place['name']) . $place['lat'] . $place['lng']), 0, 10),
            'name' => mb_substr(trim(strip_tags((string) $place['name'])), 0, 80),
            'city' => $place['city'] ?? null,
            'lat' => round((float) $place['lat'], 5),
            'lng' => round((float) $place['lng'], 5),
            'source' => 'geo',
        ];
    }

    private function inCebu(mixed $lat, mixed $lng): bool
    {
        $b = config('cebu.bounds');

        return is_numeric($lat) && is_numeric($lng)
            && $lat >= $b['min_lat'] && $lat <= $b['max_lat'] && $lng >= $b['min_lng'] && $lng <= $b['max_lng'];
    }

    /** @return null|array{minutes: int, mode: string} */
    private function maxMinutes(mixed $raw, ?array $location): ?array
    {
        if (! AiFeatures::on('geo_lookup') || ! is_array($raw) || ! isset($location['lat']) || ! is_numeric($raw['minutes'] ?? null)) {
            return null;
        }

        return [
            'minutes' => max(3, min(60, (int) $raw['minutes'])),
            'mode' => in_array($raw['mode'] ?? null, Schemas::TRAVEL_MODES, true) ? $raw['mode'] : 'walk',
        ];
    }

    /**
     * Places to stay away from. A name we know (a landmark) gets its coordinates now; any other name is kept without
     * them for GeoLookup to find, and is ignored by the search until it does.
     *
     * @return list<array{name: string, lat: ?float, lng: ?float, radius_km: float}>
     */
    private function avoid(mixed $raw): array
    {
        if (! AiFeatures::on('geo_lookup') || ! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($raw), 0, 2) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = mb_substr(trim(strip_tags((string) ($entry['name'] ?? $entry['place'] ?? ''))), 0, 80);
            if ($name === '') {
                continue;
            }
            $radius = max(0.5, min(5.0, (float) ($entry['radius_km'] ?? 1.5)));

            if ($this->inCebu($entry['lat'] ?? null, $entry['lng'] ?? null)) {
                $out[] = ['name' => $name, 'lat' => round((float) $entry['lat'], 5), 'lng' => round((float) $entry['lng'], 5), 'radius_km' => $radius];
            } elseif ($landmark = Landmarks::find($name)) {
                $out[] = ['name' => $landmark['name'], 'lat' => $landmark['lat'], 'lng' => $landmark['lng'], 'radius_km' => $radius];
            } else {
                $out[] = ['name' => $name, 'lat' => null, 'lng' => null, 'radius_km' => $radius];
            }
        }

        return $out;
    }

    /** A sort the page can honour: "nearest" needs a place with coordinates (a landmark or the tenant's position). */
    private function sort(mixed $raw, ?array $location): ?string
    {
        if (! AiFeatures::on('sort') || ! is_string($raw) || ! in_array($raw, Schemas::SORTS, true)) {
            return null;
        }

        return $raw === 'nearest' && ! isset($location['lat']) ? null : $raw;
    }

    private function location(mixed $raw): ?array
    {
        if (is_array($raw) && ($raw['kind'] ?? null) === 'point') {
            return $this->point($raw);
        }
        if (is_array($raw) && ($raw['kind'] ?? null) === 'area') {
            return AiFeatures::on('area') ? $this->area($raw) : null;
        }
        // A place GeoLookup found, posted back by the page: coordinates must be in Cebu, the name is text.
        if (is_array($raw) && ($raw['source'] ?? null) === 'geo' && AiFeatures::on('geo_lookup') && $this->inCebu($raw['lat'] ?? null, $raw['lng'] ?? null)) {
            return self::geoLocation(['name' => (string) ($raw['name'] ?? $raw['value'] ?? ''), 'city' => is_string($raw['city'] ?? null) ? mb_substr(strip_tags($raw['city']), 0, 80) : null, 'lat' => $raw['lat'], 'lng' => $raw['lng']]);
        }

        if (is_string($raw)) {
            $raw = ['kind' => 'text', 'value' => $raw];
        }
        if (! is_array($raw) || ! is_string($raw['value'] ?? null) || trim($raw['value']) === '') {
            return null;
        }

        $value = mb_substr(trim(strip_tags($raw['value'])), 0, 80);
        $kind = $raw['kind'] ?? 'text';

        if (in_array($kind, ['landmark', 'text', 'barangay'], true)) {
            if ($landmark = Landmarks::find($value)) {
                return ['kind' => 'landmark', 'value' => $landmark['key'], 'name' => $landmark['name'], 'city' => $landmark['city'], 'lat' => $landmark['lat'], 'lng' => $landmark['lng']];
            }
        }

        if ($city = $this->matchCity($value)) {
            return ['kind' => 'city', 'value' => $city, 'name' => $city];
        }

        foreach (Property::searchLocations() as $area) {
            if ($area['type'] === 'barangay' && Landmarks::normalize($area['label']) === Landmarks::normalize($value)) {
                $city = trim(substr($area['value'], strlen($area['label']) + 2));

                return ['kind' => 'barangay', 'value' => $area['label'], 'name' => $area['label'], 'city' => $city];
            }
        }

        // Unknown place name: search addresses for it rather than silently dropping what the tenant asked for.
        return ['kind' => 'text', 'value' => $value, 'name' => $value];
    }

    /** The map view the tenant pressed "Search this area" on: a box in Cebu, at most 25 km across. */
    private function area(array $raw): ?array
    {
        foreach (['south', 'west', 'north', 'east'] as $k) {
            if (! is_numeric($raw[$k] ?? null)) {
                return null;
            }
        }
        [$s, $w, $n, $e] = [(float) $raw['south'], (float) $raw['west'], (float) $raw['north'], (float) $raw['east']];
        if ($s >= $n || $w >= $e || ! $this->inCebu($s, $w) || ! $this->inCebu($n, $e)) {
            return null;
        }
        // Too wide a view is not an area: keep the middle 25 km.
        $maxLat = 25 / 111.0;
        if ($n - $s > $maxLat) {
            $mid = ($n + $s) / 2;
            [$s, $n] = [$mid - $maxLat / 2, $mid + $maxLat / 2];
        }
        $maxLng = 25 / (111.0 * max(0.2, cos(deg2rad(($n + $s) / 2))));
        if ($e - $w > $maxLng) {
            $mid = ($e + $w) / 2;
            [$w, $e] = [$mid - $maxLng / 2, $mid + $maxLng / 2];
        }

        return [
            'kind' => 'area',
            'value' => 'map area',
            'name' => 'Map area',
            'south' => round($s, 4), 'west' => round($w, 4), 'north' => round($n, 4), 'east' => round($e, 4),
        ];
    }

    /** The tenant's position from the browser. Null (and `geo_outside`) when it is not a position in Cebu. */
    private function point(array $raw): ?array
    {
        $lat = $raw['lat'] ?? null;
        $lng = $raw['lng'] ?? null;
        $bounds = config('cebu.bounds');

        if (! is_numeric($lat) || ! is_numeric($lng)
            || $lat < $bounds['min_lat'] || $lat > $bounds['max_lat']
            || $lng < $bounds['min_lng'] || $lng > $bounds['max_lng']) {
            return null;
        }

        $lat = round((float) $lat, 3);
        $lng = round((float) $lng, 3);
        $area = $this->areas->nearest($lat, $lng);

        return [
            'kind' => 'point',
            'value' => $lat . ',' . $lng,
            'name' => $area['name'] ?? 'your location',
            'city' => $area['city'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function matchCity(string $value): ?string
    {
        $needle = preg_replace('/\scity$/', '', Landmarks::normalize($value));
        foreach (config('cebu.lgus', []) as $lgu) {
            if (preg_replace('/\scity$/', '', Landmarks::normalize($lgu)) === $needle) {
                return $lgu;
            }
        }

        return null;
    }

    private function price(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $float = round((float) $value);

        // Range first: casting a float past PHP_INT_MAX to int throws on PHP 8.5+.
        return is_finite($float) && $float >= self::PRICE_MIN && $float <= self::PRICE_MAX ? (int) $float : null;
    }

    /** @param list<string> $allowed */
    private function pick(mixed $value, array $allowed): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $needle = Landmarks::normalize($value);
        foreach ($allowed as $option) {
            if (Landmarks::normalize($option) === $needle) {
                return $option;
            }
        }

        return null;
    }

    /** @param list<string> $allowed @return list<string> */
    private function pickMany(mixed $values, array $allowed): array
    {
        $picked = [];
        foreach ((array) $values as $value) {
            if ($match = $this->pick($value, $allowed)) {
                $picked[$match] = true;
            }
        }

        return array_keys($picked);
    }
}
