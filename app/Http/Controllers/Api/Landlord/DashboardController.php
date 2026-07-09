<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Landlord dashboard stats. Same figures as the web
     * Landlord\DashboardController@index.
     */
    public function index(Request $request): JsonResponse
    {
        $landlordId = $request->user()->user_id;
        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $totalTenants = Reservation::whereIn('property_id', $propertyIds)
            ->where('rental_status', 'Occupied')
            ->distinct('tenant_id')
            ->count('tenant_id');

        $recentUnitActivity = PropertyUnit::whereIn('property_id', $propertyIds)
            ->with('property')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($unit) => [
                'type'        => 'unit',
                'description' => "Unit \"{$unit->unit_label}\" in {$unit->property->title} was updated",
                'status'      => $unit->availability_status,
                'timestamp'   => $unit->updated_at,
            ]);

        $recentReservationActivity = Reservation::whereIn('property_id', $propertyIds)
            ->with(['property', 'unit', 'tenant'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($reservation) => [
                'type'        => 'reservation',
                'description' => "Reservation for {$reservation->property->title}"
                    . ($reservation->unit ? " ({$reservation->unit->unit_label})" : '')
                    . " by {$reservation->tenant->first_name} {$reservation->tenant->last_name} is {$reservation->rental_status}",
                'status'      => $reservation->rental_status,
                'timestamp'   => $reservation->updated_at,
            ]);

        $recentActivity = $recentUnitActivity
            ->concat($recentReservationActivity)
            ->sortByDesc('timestamp')
            ->take(5)
            ->values();

        return response()->json([
            'data' => [
                'total_properties'  => $propertyIds->count(),
                'total_units'       => $units->count(),
                'occupied_units'    => $units->where('availability_status', 'Occupied')->count(),
                'available_units'   => $units->where('availability_status', 'Available')->count(),
                'reserved_units'    => $units->where('availability_status', 'Reserved')->count(),
                'maintenance_units' => $units->where('availability_status', 'Maintenance')->count(),
                'total_tenants'     => $totalTenants,
                'recent_activity'   => $recentActivity,
            ],
        ]);
    }
}
