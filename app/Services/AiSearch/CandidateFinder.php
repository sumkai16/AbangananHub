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
 */
class CandidateFinder
{
    public function __construct(private MatchExplainer $explainer)
    {
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, exact_count: int, relaxed: list<string>}
     */
    public function find(array $intent): array
    {
        $min = (int) config('ai_search.min_results', 3);
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

        $rows = $properties->map(function (Property $property) use ($intent) {
            return ['property' => $property] + $this->explainer->explain($property, $intent);
        })
            ->sort(fn ($a, $b) => [empty($a['misses']) ? 0 : 1, -$a['score'], -(float) $a['property']->avg_rating, (float) $a['property']->min_rental_fee]
                <=> [empty($b['misses']) ? 0 : 1, -$b['score'], -(float) $b['property']->avg_rating, (float) $b['property']->min_rental_fee])
            ->take((int) config('ai_search.max_candidates', 20))
            ->values();

        return [
            'rows' => $rows,
            'exact_count' => $rows->filter(fn ($r) => empty($r['misses']))->count(),
            'relaxed' => $relaxed,
        ];
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
                'landmark' => $this->withinBox($query, $loc, $intent['radius_km']),
                default => $query->where(fn ($q) => $q
                    ->where('address', 'like', '%' . addcslashes($loc['value'], '%_\\') . '%')
                    ->orWhere('title', 'like', '%' . addcslashes($loc['value'], '%_\\') . '%')),
            };
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

        $found = $query->limit(60)->get();

        // The box is a cheap pre-filter; the circle is the real test.
        if (($loc['kind'] ?? null) === 'landmark') {
            $found = $found->filter(fn (Property $p) => Landmarks::distanceKm((float) $p->latitude, (float) $p->longitude, $loc['lat'], $loc['lng']) <= $intent['radius_km'])->values();
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

        if ($current['price_max'] !== null) {
            $add('budget +15%', array_merge($current, ['price_max' => (int) round($current['price_max'] * 1.15)]));
            $add('budget +30%', array_merge($current, ['price_max' => (int) round($intent['price_max'] * 1.30)]));
        }

        if ($loc = $current['location']) {
            if ($loc['kind'] === 'landmark') {
                $add('wider area', array_merge($current, ['radius_km' => min(10.0, $intent['radius_km'] * 2)]));
                $add('much wider area', array_merge($current, ['radius_km' => min(10.0, $intent['radius_km'] * 4)]));
            } elseif ($loc['kind'] === 'barangay' && ! empty($loc['city'])) {
                $add('the whole city', array_merge($current, ['location' => ['kind' => 'city', 'value' => $loc['city'], 'name' => $loc['city']]]));
            } elseif ($loc['kind'] === 'city') {
                $near = config('ai_search.neighbors.' . $loc['value'], []);
                if ($near) {
                    $add('nearby cities', array_merge($current, ['location' => ['kind' => 'cities', 'value' => array_merge([$loc['value']], $near), 'name' => $loc['name']]]));
                }
            }
        }

        $add('property type', array_merge($current, ['property_type' => null]));
        $add('remaining amenities', array_merge($current, ['amenities' => []]));

        return $steps;
    }
}
