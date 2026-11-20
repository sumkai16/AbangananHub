<?php

namespace App\Services\AiSearch;

use App\Models\Amenity;
use App\Models\Property;
use Illuminate\Support\Collection;

/**
 * Finds the properties for an intent: first exactly, then (when there are fewer
 * than `ai_search.min_results`) with filters relaxed one step at a time, so a
 * tenant whose budget is ₱500 too low still sees the closest places.
 *
 * Every row is explained against the ORIGINAL intent (MatchExplainer), so a row
 * found by a relaxed query still says exactly what it misses.
 *
 * Property type is never relaxed: someone who asked for an apartment only gets
 * apartments in the main list. When that list is thin, `alternatives` holds a few
 * listings of OTHER types that still fit the budget, area and everything else,
 * shown separately as "You might also like".
 */
class CandidateFinder
{
    public function __construct(private MatchExplainer $explainer) {}

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, exact_count: int, relaxed: list<string>, alternatives: Collection<int, array<string, mixed>>}
     */
    public function find(array $intent): array
    {
        $min = (int) config('ai_search.min_results', 3);
        [$properties, $relaxed] = $this->relaxedQuery($intent, $min);

        $rows = $properties->map(function (Property $property) use ($intent) {
            return ['property' => $property] + $this->explainer->explain($property, $intent);
        })
            ->sort(fn ($a, $b) => $this->sortKey($a, $intent) <=> $this->sortKey($b, $intent))
            ->take((int) config('ai_search.max_candidates', 20))
            ->values();

        // A named business has its own "More from ..." strip (moreFromHost), which already covers its other types.
        $alternatives = $rows->count() < $min && ! ($intent['host'] ?? null) ? $this->alternatives($intent, $rows->pluck('property.property_id')->all()) : collect();
        $closest = false;

        // Never leave the tenant with an empty page: when not even another type fits exactly, show the closest
        // places of any type, loosened step by step. Each card still says what differs (misses).
        if ($rows->isEmpty() && $alternatives->isEmpty() && ! ($intent['host'] ?? null) && ! IntentValidator::isEmpty($intent)) {
            $alternatives = $this->closestOfAnyType($intent, $min);
            $closest = $alternatives->isNotEmpty();
        }

        return [
            'rows' => $rows,
            'exact_count' => $rows->filter(fn ($r) => empty($r['misses']))->count(),
            'relaxed' => $relaxed,
            'alternatives' => $alternatives,
            'alternatives_closest' => $closest,
        ];
    }

    /**
     * Runs the query, then loosens one step at a time until `$min` places turn up.
     *
     * @return array{0: Collection<int, Property>, 1: list<string>}
     */
    private function relaxedQuery(array $intent, int $min): array
    {
        $properties = $this->query($intent);
        $relaxed = [];

        if ($properties->count() < $min) {
            foreach ($this->ladder($intent) as [$label, $loosened]) {
                $properties = $this->query($loosened);
                $relaxed[] = $label;
                if ($properties->count() >= $min) {
                    break;
                }
            }
        }

        return [$properties, $relaxed];
    }

    /**
     * Last resort: the same search with the property type dropped and every filter loosened, so a sentence like
     * "dorm near IT Park" with no dorm anywhere still shows the nearest places that could work.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function closestOfAnyType(array $intent, int $min): Collection
    {
        $other = array_merge($intent, ['property_type' => null]);
        [$properties] = $this->relaxedQuery($other, $min);

        return $properties
            ->map(fn (Property $p) => ['property' => $p] + $this->explainer->explain($p, $intent))
            ->sort(fn ($a, $b) => $this->sortKey($a, $intent) <=> $this->sortKey($b, $intent))
            ->take(3)
            ->values();
    }

    /**
     * The other places of a business the sentence named, for the "More from ..." strip under the results: its
     * live listings that are not already on the page, whatever the filters said. Nearest first when the tenant
     * shared a position, otherwise best rated. Only for one business; two matching businesses (a shared
     * surname) would make the strip ambiguous, and a named single listing has no "business" to show more of.
     *
     * @param  list<int>  $skipIds  properties already on the page (results and suggestions)
     * @return null|array{properties: Collection<int, Property>, total: int, distances: array<int, float>, landlord_id: int}
     */
    public function moreFromHost(array $intent, array $skipIds, int $limit = 6): ?array
    {
        $host = $intent['host'] ?? null;
        if (! $host || $host['kind'] !== 'business' || count($host['landlord_ids']) !== 1) {
            return null;
        }

        $landlordId = $host['landlord_ids'][0];
        $others = Property::with(['media', 'landlord', 'amenities', 'units.amenities'])
            ->browsable()
            ->where('landlord_id', $landlordId)
            ->whereNotIn('property_id', $skipIds ?: [0])
            ->limit(60)
            ->get();

        if ($others->isEmpty()) {
            return null;
        }

        $loc = $intent['location'] ?? null;
        $distances = [];
        if (is_array($loc) && ($loc['kind'] ?? null) === 'point') {
            foreach ($others as $property) {
                $distances[$property->property_id] = Landmarks::distanceKm((float) $property->latitude, (float) $property->longitude, $loc['lat'], $loc['lng']);
            }
        }

        $sorted = $distances !== []
            ? $others->sortBy(fn (Property $p) => $distances[$p->property_id])
            : $others->sortBy(fn (Property $p) => [-(float) $p->avg_rating, (float) $p->min_rental_fee]);

        return [
            'properties' => $sorted->take($limit)->values(),
            'total' => $others->count(),
            'distances' => $distances,
            'landlord_id' => $landlordId,
        ];
    }

    /**
     * Exact fits first, then the score, then (for "near me") how far it is, then rating and price. Distance only
     * leads for a point: "near me" means nearest, while a landmark search keeps ranking by rating.
     *
     * @return list<float|int>
     */
    private function sortKey(array $row, array $intent): array
    {
        // A requested order replaces "best fit first" inside the exact and the close group, never across them.
        $fee = (float) $row['property']->min_rental_fee;
        $key = [empty($row['misses']) ? 0 : 1, ...match ($intent['sort'] ?? null) {
            'price_asc' => [$fee],
            'price_desc' => [-$fee],
            'rating' => [-(float) $row['property']->avg_rating, -(int) $row['property']->review_count],
            'newest' => [-($row['property']->created_at?->getTimestamp() ?? 0)],
            'nearest' => [$row['distance_km'] ?? INF],
            // With several places to be close to, the one whose farthest place is nearest comes first.
            default => [-$row['score'], ...(($intent['anchors'] ?? []) ? [$row['distance_km'] ?? INF] : [])],
        }];

        if (($intent['location']['kind'] ?? null) === 'point') {
            $key[] = $row['distance_km'] ?? INF;
        }

        return [...$key, -(float) $row['property']->avg_rating, (float) $row['property']->min_rental_fee];
    }

    /**
     * Other property types that fit everything else, exactly (no relaxing). Only when a type was asked for.
     *
     * @param  list<int>  $skipIds  properties already in the main list
     * @return Collection<int, array<string, mixed>>
     */
    private function alternatives(array $intent, array $skipIds): Collection
    {
        if ($intent['property_type'] === null) {
            return collect();
        }

        $other = array_merge($intent, ['property_type' => null]);

        return $this->query($other)
            ->reject(fn (Property $p) => $p->property_type === $intent['property_type'] || in_array($p->property_id, $skipIds, true))
            ->map(fn (Property $p) => ['property' => $p] + $this->explainer->explain($p, $other))
            ->filter(fn ($r) => empty($r['misses']))
            ->sort(fn ($a, $b) => [-$a['score'], -(float) $a['property']->avg_rating, (float) $a['property']->min_rental_fee]
                <=> [-$b['score'], -(float) $b['property']->avg_rating, (float) $b['property']->min_rental_fee])
            ->take(3)
            ->values();
    }

    /** @return Collection<int, Property> */
    private function query(array $intent): Collection
    {
        $query = Property::with([
            'media', 'landlord', 'amenities', 'units.amenities',
            'documents:document_id,property_id,document_type,status,expiry_date',
        ])->browsable();

        if ($loc = $intent['location']) {
            match ($loc['kind']) {
                'city' => $query->where('city_municipality', $loc['value']),
                'cities' => $query->whereIn('city_municipality', $loc['value']),
                'barangay' => $query->where('barangay', $loc['value']),
                'landmark' => $this->withinBox($query, $loc, Travel::effectiveRadiusKm($intent)),
                // The box the tenant was looking at on the map.
                'area' => $query->whereBetween('latitude', [$loc['south'], $loc['north']])->whereBetween('longitude', [$loc['west'], $loc['east']]),
                // A named business shows all of its places, nearest first, so there is no radius to cut at.
                'point' => ($intent['host'] ?? null) ? null : $this->withinBox($query, $loc, Travel::effectiveRadiusKm($intent)),
                default => $query->where(fn ($q) => $q
                    ->where('address', 'like', '%' . addcslashes($loc['value'], '%_\\') . '%')
                    ->orWhere('title', 'like', '%' . addcslashes($loc['value'], '%_\\') . '%')),
            };
        }

        // Places that all have to be close (the planner's anchors): a box around each is the cheap pre-filter.
        foreach ($intent['anchors'] ?? [] as $anchor) {
            $this->withinBox($query, $anchor, $anchor['radius_km']);
        }

        // Named business or listing. Never relaxed by ladder(): asking for Santos Rentals' places means only theirs.
        if ($host = $intent['host'] ?? null) {
            $query->where(fn ($q) => $q
                ->whereIn('landlord_id', $host['landlord_ids'] ?: [0])
                ->orWhereIn('property_id', $host['property_ids'] ?: [0]));
        }

        $amenityIds = $intent['amenities'] === [] ? [] : Amenity::whereIn('amenity_name', $intent['amenities'])->pluck('amenity_id')->all();

        $query->browseFilters([
            'type' => $intent['property_type'],
            'price_min' => $intent['price_min'],
            'price_max' => $intent['price_max'],
            'amenities' => $amenityIds,
            'living' => $intent['living'],
            'for' => $intent['for'],
            'furnishing' => $intent['furnishing'],
            'rules' => $intent['rules'],
        ]);

        // Bedrooms, deposit and group size are facts of a unit, and one unit has to satisfy all of them together.
        if (($intent['bedrooms_min'] ?? null) !== null || ($intent['deposit_max'] ?? null) !== null || ($intent['occupants'] ?? null) !== null) {
            $query->whereHas('units', function ($units) use ($intent) {
                $units->where('availability_status', 'Available')->where('verification_status', 'Approved');
                if (($intent['bedrooms_min'] ?? null) !== null) {
                    $units->where('bedrooms', '>=', $intent['bedrooms_min']);
                }
                if (($intent['deposit_max'] ?? null) !== null) {
                    $units->where('security_deposit', '<=', $intent['deposit_max']);
                }
                if (($intent['occupants'] ?? null) !== null) {
                    $units->where('occupancy_limit', '>=', $intent['occupants']);
                }
            });
        }

        // browseFilters already orders newest first; property_id breaks created_at ties so the 60 are stable.
        $found = $query->orderByDesc('property_id')->limit(60)->get();

        // The box is a cheap pre-filter; the circle is the real test.
        if (($loc['kind'] ?? null) === 'landmark' || (($loc['kind'] ?? null) === 'point' && ! ($intent['host'] ?? null))) {
            $radius = Travel::effectiveRadiusKm($intent);
            $found = $found->filter(fn (Property $p) => Landmarks::distanceKm((float) $p->latitude, (float) $p->longitude, $loc['lat'], $loc['lng']) <= $radius)->values();
        }

        foreach ($intent['anchors'] ?? [] as $anchor) {
            $found = $found->filter(fn (Property $p) => Landmarks::distanceKm((float) $p->latitude, (float) $p->longitude, $anchor['lat'], $anchor['lng']) <= $anchor['radius_km'])->values();
        }

        // "Far from X": nothing inside a circle around X. Entries without coordinates (not found) are ignored.
        foreach ($intent['avoid'] ?? [] as $avoid) {
            if ($avoid['lat'] === null) {
                continue;
            }
            $found = $found->reject(fn (Property $p) => Landmarks::distanceKm((float) $p->latitude, (float) $p->longitude, $avoid['lat'], $avoid['lng']) < $avoid['radius_km'])->values();
        }

        return $found;
    }

    private function withinBox($query, array $loc, float $radiusKm): void
    {
        $dLat = $radiusKm / 111.0;
        $dLng = $radiusKm / (111.0 * max(0.2, cos(deg2rad($loc['lat']))));
        $query->whereBetween('latitude', [$loc['lat'] - $dLat, $loc['lat'] + $dLat])
            ->whereBetween('longitude', [$loc['lng'] - $dLng, $loc['lng'] + $dLng]);
    }

    /**
     * Progressively looser versions of the intent, least important filter first.
     * Cumulative: each step keeps the earlier relaxations.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function ladder(array $intent): array
    {
        $steps = [];
        $current = $intent;
        $add = function (string $label, array $next) use (&$steps, &$current) {
            if ($next !== $current) {
                $steps[] = [$label, $next];
                $current = $next;
            }
        };

        $add('furnishing, living and who it is for', array_merge($current, ['furnishing' => null, 'living' => null, 'for' => null]));
        $add('some amenities', array_merge($current, ['amenities' => array_slice($current['amenities'], 0, 2)]));
        $add('house rules', array_merge($current, ['rules' => []]));
        $add('bedrooms, deposit and group size', array_merge($current, ['bedrooms_min' => null, 'deposit_max' => null, 'occupants' => null]));

        if ($current['price_max'] !== null) {
            $add('budget +15%', array_merge($current, ['price_max' => (int) round($current['price_max'] * 1.15)]));
            $add('budget +30%', array_merge($current, ['price_max' => (int) round($intent['price_max'] * 1.30)]));
        }

        if ($loc = $current['location']) {
            if ($loc['kind'] === 'landmark' || ($loc['kind'] === 'point' && ! ($current['host'] ?? null))) {
                // "10 minutes walk" widens as a plain radius from here on: the minutes are what is being relaxed.
                $base = Travel::effectiveRadiusKm($intent);
                $add('wider area', array_merge($current, ['max_minutes' => null, 'radius_km' => min(10.0, $base * 2)]));
                $add('much wider area', array_merge($current, ['max_minutes' => null, 'radius_km' => min(10.0, $base * 4)]));
            } elseif ($loc['kind'] === 'area') {
                $grow = fn (float $by) => array_merge($loc, [
                    'south' => $loc['south'] - ($loc['north'] - $loc['south']) * $by, 'north' => $loc['north'] + ($loc['north'] - $loc['south']) * $by,
                    'west' => $loc['west'] - ($loc['east'] - $loc['west']) * $by, 'east' => $loc['east'] + ($loc['east'] - $loc['west']) * $by,
                ]);
                $add('wider area', array_merge($current, ['location' => $grow(0.25)]));
                $add('much wider area', array_merge($current, ['location' => $grow(0.75)]));
            } elseif ($loc['kind'] === 'barangay' && ! empty($loc['city'])) {
                $add('the whole city', array_merge($current, ['location' => ['kind' => 'city', 'value' => $loc['city'], 'name' => $loc['city']]]));
            } elseif ($loc['kind'] === 'city') {
                $near = config('ai_search.neighbors.' . $loc['value'], []);
                if ($near) {
                    $add('nearby cities', array_merge($current, ['location' => ['kind' => 'cities', 'value' => array_merge([$loc['value']], $near), 'name' => $loc['name']]]));
                }
            }
        }

        if (($current['anchors'] ?? []) !== []) {
            $grow = fn (int $by) => array_map(fn ($a) => array_merge($a, ['radius_km' => min(10.0, $a['radius_km'] * $by)]), $current['anchors']);
            $add('wider area around each place', array_merge($current, ['anchors' => $grow(2)]));
            $add('much wider area around each place', array_merge($current, ['anchors' => $grow(4)]));
        }

        $add('remaining amenities', array_merge($current, ['amenities' => []]));
        $add('places to avoid', array_merge($current, ['avoid' => []]));

        return $steps;
    }
}
