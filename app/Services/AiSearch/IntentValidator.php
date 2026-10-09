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
 *  living / for / furnishing  ?string     occupants ?int
 *  language       en|tl|ceb|mixed     unmapped  list<string> (understood but not filterable)
 */
class IntentValidator
{
    private const PRICE_MIN = 500;
    private const PRICE_MAX = 200000;

    /** @param array<string, mixed> $raw */
    public function validate(array $raw): array
    {
        $location = $this->location($raw['location'] ?? null);
        $min = $this->price($raw['price_min'] ?? null);
        $max = $this->price($raw['price_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $radius = (float) ($raw['radius_km'] ?? config('ai_search.default_radius_km', 2));

        return [
            'location' => $location,
            'radius_km' => max(0.5, min(10.0, $radius)),
            'property_type' => $this->pick($raw['property_type'] ?? null, Vocabulary::PROPERTY_TYPES),
            'price_min' => $min,
            'price_max' => $max,
            'amenities' => $this->pickMany($raw['amenities'] ?? [], Vocabulary::amenities()),
            'rules' => array_values(array_intersect(array_keys(BrowseFilters::RULES), array_map('strval', array_filter((array) ($raw['rules'] ?? []), 'is_string')))),
            'living' => $this->pick($raw['living'] ?? null, array_keys(PropertyPolicies::LIVING_ARRANGEMENTS)),
            'for' => $this->pick($raw['for'] ?? null, array_keys(BrowseFilters::SUITABLE_FOR)),
            'furnishing' => $this->pick($raw['furnishing'] ?? null, BrowseFilters::FURNISHING),
            'occupants' => is_numeric($raw['occupants'] ?? null) ? max(1, min(20, (int) $raw['occupants'])) : null,
            'language' => in_array($raw['language'] ?? null, ['en', 'tl', 'ceb', 'mixed'], true) ? $raw['language'] : 'en',
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
            && $intent['property_type'] === null
            && $intent['price_min'] === null && $intent['price_max'] === null
            && $intent['amenities'] === [] && $intent['rules'] === []
            && $intent['living'] === null && $intent['for'] === null && $intent['furnishing'] === null;
    }

    private function location(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $raw = ['kind' => 'text', 'value' => $raw];
        }
        if (! is_array($raw) || ! is_string($raw['value'] ?? null) || trim($raw['value']) === '') {
            return null;
        }

        $value = mb_substr(trim(strip_tags($raw['value'])), 0, 80);
        $kind = $raw['kind'] ?? 'text';

        if ($kind === 'landmark' || $kind === 'text') {
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
        $int = (int) round((float) $value);

        return $int >= self::PRICE_MIN && $int <= self::PRICE_MAX ? $int : null;
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
