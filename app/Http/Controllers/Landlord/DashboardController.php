<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $landlordId = Auth::id();

        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $totalProperties = $propertyIds->count();

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $totalUnits = $units->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $maintenanceUnits = $units->where('availability_status', 'Maintenance')->count();

        $totalTenants = Reservation::whereIn('property_id', $propertyIds)
            ->where('reservation_status', 'Approved')
            ->distinct('tenant_id')
            ->count('tenant_id');

        // Recent activity — derived from real unit and reservation timestamps, no fabricated data
        $recentUnitActivity = PropertyUnit::whereIn('property_id', $propertyIds)
            ->with('property')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($unit) {
                return [
                    'type' => 'unit',
                    'description' => "Unit \"{$unit->unit_label}\" in {$unit->property->title} was updated",
                    'status' => $unit->availability_status,
                    'timestamp' => $unit->updated_at,
                ];
            });

        $recentReservationActivity = Reservation::whereIn('property_id', $propertyIds)
            ->with(['property', 'unit', 'tenant'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($reservation) {
                return [
                    'type' => 'reservation',
                    'description' => "Reservation for {$reservation->property->title}"
                        . ($reservation->unit ? " ({$reservation->unit->unit_label})" : '')
                        . " by {$reservation->tenant->first_name} {$reservation->tenant->last_name} is {$reservation->reservation_status}",
                    'status' => $reservation->reservation_status,
                    'timestamp' => $reservation->updated_at,
                ];
            });

        $recentActivity = $recentUnitActivity
            ->concat($recentReservationActivity)
            ->sortByDesc('timestamp')
            ->take(5)
            ->values();

        return view('landlord.dashboard.index', [
            'totalProperties' => $totalProperties,
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'reservedUnits' => $reservedUnits,
            'maintenanceUnits' => $maintenanceUnits,
            'totalTenants' => $totalTenants,
            'recentActivity' => $recentActivity,
        ]);
    }
}