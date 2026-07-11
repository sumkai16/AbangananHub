<?php

namespace App\Services;

use App\Models\PropertyUnit;
use App\Models\Property;

class OccupancyRateCalculator
{
    public static function forProperty(int $propertyId): float
    {
        $units = PropertyUnit::where('property_id', $propertyId)
            ->where('verification_status', 'Approved')
            ->get();

        return self::rate($units);
    }

    public static function forLandlord(int $landlordId): float
    {
        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)
            ->where('verification_status', 'Approved')
            ->get();

        return self::rate($units);
    }

    private static function rate($units): float
    {
        $total = $units->count();
        if ($total === 0) {
            return 0.0;
        }

        $occupied = $units->where('availability_status', 'Occupied')->count();

        return round(($occupied / $total) * 100, 1);
    }
}
