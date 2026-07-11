<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OccupancyController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::id();
        $properties = Property::where('landlord_id', $landlordId)->get();
        $propertyIds = $properties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $totalUnits = $units->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();

        $aggregateRate = OccupancyRateCalculator::forLandlord($landlordId);

        $propertyBreakdown = $properties->map(function (Property $property) use ($units) {
            $propertyUnits = $units->where('property_id', $property->property_id);

            return [
                'property_id' => $property->property_id,
                'title' => $property->title,
                'total' => $propertyUnits->count(),
                'available' => $propertyUnits->where('availability_status', 'Available')->count(),
                'reserved' => $propertyUnits->where('availability_status', 'Reserved')->count(),
                'occupied' => $propertyUnits->where('availability_status', 'Occupied')->count(),
                'rate' => OccupancyRateCalculator::forProperty($property->property_id),
            ];
        })->values();

        return view('landlord.occupancy.index', [
            'totalUnits' => $totalUnits,
            'availableUnits' => $availableUnits,
            'reservedUnits' => $reservedUnits,
            'occupiedUnits' => $occupiedUnits,
            'aggregateRate' => $aggregateRate,
            'propertyBreakdown' => $propertyBreakdown,
        ]);
    }
}
