<?php

namespace App\Services\AiSearch;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;

/**
 * Names the spot a "near me" search used: the closest barangay (or city) that has live listings, worked out
 * from our own listings so the tenant's coordinates never go to a third party. If they stand somewhere with
 * no listings it names the nearest neighbouring area, which is why the page lets them correct it.
 */
class NearestArea
{
    /**
     * @return ?array{name: string, city: string, distance_km: float}
     */
    public function nearest(float $lat, float $lng): ?array
    {
        $best = null;
        $bestKm = INF;

        foreach ($this->areas() as $area) {
            $km = Landmarks::distanceKm($lat, $lng, $area['lat'], $area['lng']);
            if ($km < $bestKm) {
                $best = $area;
                $bestKm = $km;
            }
        }

        return $best === null ? null : ['name' => $best['name'], 'city' => $best['city'], 'distance_km' => $bestKm];
    }

    /**
     * One centre per area, from the average position of its live listings. Cached with the other search lists.
     *
     * @return list<array{name: string, city: string, lat: float, lng: float}>
     */
    private function areas(): array
    {
        return Cache::remember('search.area_centres', now()->addMinutes(10), function () {
            return Property::live()
                ->whereHas('units', fn ($q) => $q->where('availability_status', 'Available')->where('verification_status', 'Approved'))
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->selectRaw('city_municipality, barangay, AVG(latitude) as lat, AVG(longitude) as lng')
                ->groupBy('city_municipality', 'barangay')
                ->get()
                ->map(fn ($row) => [
                    'name' => filled($row->barangay) && $row->barangay !== $row->city_municipality
                        ? $row->barangay . ', ' . $row->city_municipality
                        : (string) $row->city_municipality,
                    'city' => (string) $row->city_municipality,
                    'lat' => (float) $row->lat,
                    'lng' => (float) $row->lng,
                ])
                ->values()
                ->all();
        });
    }
}
