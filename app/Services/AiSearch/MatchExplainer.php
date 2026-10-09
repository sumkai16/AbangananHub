<?php

namespace App\Services\AiSearch;

use App\Models\Property;
use App\Support\BrowseFilters;

/**
 * Compares one property with what the tenant asked for, from real columns only.
 * The ✓ / − chips on result cards come from here, never from the AI, so a card
 * can't claim Wi-Fi the listing doesn't have.
 *
 * Needs `units.amenities` and `amenities` loaded (CandidateFinder does).
 * Each hit/miss is {facet, text}; `facet` lets the UI mark which search chip failed.
 */
class MatchExplainer
{
    private const HIT_RULE = ['pets' => 'Pets OK', 'smoking' => 'Smoking OK', 'overnight' => 'Guests OK', 'curfew' => 'No curfew'];
    private const MISS_RULE = ['pets' => 'No pets', 'smoking' => 'No smoking', 'overnight' => 'No overnight guests', 'curfew' => 'Has a curfew'];

    /**
     * @return array{hits: list<array{facet: string, text: string}>, misses: list<array{facet: string, text: string}>, score: int, distance_km: ?float}
     */
    public function explain(Property $property, array $intent): array
    {
        $hits = [];
        $misses = [];
        $penalty = 0;
        $distance = null;

        $units = $property->units
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved');

        // ── Location ──
        if ($loc = $intent['location']) {
            if ($loc['kind'] === 'landmark') {
                $distance = Landmarks::distanceKm((float) $property->latitude, (float) $property->longitude, $loc['lat'], $loc['lng']);
                $km = number_format($distance, 1);
                if ($distance <= $intent['radius_km']) {
                    $hits[] = ['facet' => 'location', 'text' => $km . ' km from ' . $loc['name']];
                } else {
                    // Reads inside "it fits X, but 7.7 km away" as well as on its own chip.
                    $misses[] = ['facet' => 'location', 'text' => $km . ' km away'];
                    $penalty += min(30, 10 + (int) round(($distance - $intent['radius_km']) * 5));
                }
            } else {
                $ok = match ($loc['kind']) {
                    'city' => $this->same($property->city_municipality, $loc['value']),
                    'barangay' => $this->same($property->barangay, $loc['value']),
                    default => stripos($property->address . ' ' . $property->title, $loc['value']) !== false,
                };
                if ($ok) {
                    $hits[] = ['facet' => 'location', 'text' => $loc['name']];
                } else {
                    $misses[] = ['facet' => 'location', 'text' => 'Not in ' . $loc['name']];
                    $penalty += 30;
                }
            }
        }

        // ── Price ──
        $fees = $units->pluck('rental_fee')->map(fn ($f) => (float) $f);
        if ($fees->isNotEmpty()) {
            $cheapest = $fees->min();
            if ($intent['price_max'] !== null) {
                if ($cheapest <= $intent['price_max']) {
                    $hits[] = ['facet' => 'price', 'text' => 'Under budget'];
                } else {
                    $over = $cheapest - $intent['price_max'];
                    $misses[] = ['facet' => 'price', 'text' => '₱' . number_format($over) . ' over budget'];
                    $penalty += min(25, 5 + (int) round(40 * $over / $intent['price_max']));
                }
            }
            if ($intent['price_min'] !== null && $fees->max() < $intent['price_min']) {
                $misses[] = ['facet' => 'price_min', 'text' => 'Below ₱' . number_format($intent['price_min'])];
                $penalty += 8;
            }
        }

        // ── Type ──
        if ($intent['property_type'] !== null) {
            if ($property->property_type === $intent['property_type']) {
                $hits[] = ['facet' => 'type', 'text' => $property->property_type];
            } else {
                $misses[] = ['facet' => 'type', 'text' => $property->property_type . ', not ' . $intent['property_type']];
                $penalty += 20;
            }
        }

        // ── Amenities (property level or any unit) ──
        $have = collect($property->amenities)->pluck('amenity_name')
            ->merge($property->units->flatMap(fn ($u) => $u->amenities->pluck('amenity_name')))
            ->map(fn ($n) => mb_strtolower($n))->unique();
        foreach ($intent['amenities'] as $name) {
            if ($have->contains(mb_strtolower($name))) {
                $hits[] = ['facet' => 'amenity:' . $name, 'text' => $name];
            } else {
                $misses[] = ['facet' => 'amenity:' . $name, 'text' => 'No ' . $name];
                $penalty += 8;
            }
        }

        // ── House rules ("must allow") ──
        $houseRules = (array) $property->house_rules;
        foreach ($intent['rules'] as $key) {
            if (in_array(BrowseFilters::RULES[$key][1], $houseRules, true)) {
                $misses[] = ['facet' => 'rule:' . $key, 'text' => self::MISS_RULE[$key]];
                $penalty += 10;
            } else {
                $hits[] = ['facet' => 'rule:' . $key, 'text' => self::HIT_RULE[$key]];
            }
        }

        // ── Property-level facts ──
        if ($intent['living'] !== null) {
            if ($property->living_arrangement === $intent['living']) {
                $hits[] = ['facet' => 'living', 'text' => $intent['living'] . ' living'];
            } else {
                $misses[] = ['facet' => 'living', 'text' => ($property->living_arrangement ?? 'Other') . ' living'];
                $penalty += 8;
            }
        }
        if ($intent['for'] !== null) {
            $wanted = BrowseFilters::SUITABLE_FOR[$intent['for']];
            if (in_array($property->occupancy_preference, [$wanted, 'No Preference'], true)) {
                $hits[] = ['facet' => 'for', 'text' => 'Open to ' . strtolower($intent['for'] === 'Women' ? 'women' : 'men')];
            } else {
                $misses[] = ['facet' => 'for', 'text' => $property->occupancy_preference];
                $penalty += 15;
            }
        }
        if ($intent['furnishing'] !== null) {
            if ($units->contains('furnishing_status', $intent['furnishing'])) {
                $hits[] = ['facet' => 'furnishing', 'text' => $intent['furnishing']];
            } else {
                $misses[] = ['facet' => 'furnishing', 'text' => 'Not ' . strtolower($intent['furnishing'])];
                $penalty += 8;
            }
        }

        return [
            'hits' => $hits,
            'misses' => $misses,
            'score' => max(0, 100 - $penalty),
            'distance_km' => $distance,
        ];
    }

    private function same(?string $a, string $b): bool
    {
        return $a !== null && Landmarks::normalize($a) === Landmarks::normalize($b);
    }
}
