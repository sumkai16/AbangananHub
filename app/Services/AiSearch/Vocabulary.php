<?php

namespace App\Services\AiSearch;

use App\Models\Amenity;
use App\Models\Property;
use App\Support\BrowseFilters;
use App\Support\PropertyPolicies;
use Illuminate\Support\Facades\Cache;

/**
 * Every value the AI is allowed to return, in one place. The prompt shows it to
 * the model and IntentValidator enforces it, so the two can never drift apart.
 */
class Vocabulary
{
    public const PROPERTY_TYPES = ['Apartment', 'Condominium', 'House', 'Boarding House', 'Bedspace'];

    /** Amenity names offered to the AI: the ones tenants filter on (Curfew / Pet Friendly / Visitors Allowed are house rules instead). */
    public static function amenities(): array
    {
        return Cache::remember('ai_search.amenities', 600, fn () => Amenity::orderBy('amenity_name')->pluck('amenity_name')
            ->reject(fn ($n) => in_array($n, BrowseFilters::AMENITIES_REPLACED_BY_RULES, true))
            ->values()->all());
    }

    /** Area names that have bookable listings: barangays and cities. */
    public static function areas(): array
    {
        return array_values(array_unique(array_merge(
            array_map(fn ($a) => $a['label'], Property::searchLocations()),
            config('cebu.lgus', []),
        )));
    }

    /** @return array<string, mixed> */
    public static function forPrompt(): array
    {
        return [
            'property_types' => self::PROPERTY_TYPES,
            'amenities' => self::amenities(),
            'rules' => array_map(fn ($key) => $key . ' = ' . BrowseFilters::RULES[$key][0], array_keys(BrowseFilters::RULES)),
            'living' => array_keys(PropertyPolicies::LIVING_ARRANGEMENTS),
            'for' => array_keys(BrowseFilters::SUITABLE_FOR),
            'furnishing' => BrowseFilters::FURNISHING,
            'cities' => config('cebu.lgus', []),
            'landmarks' => array_map(fn ($l) => $l['key'] . ' = ' . $l['name'], Landmarks::all()),
        ];
    }
}
