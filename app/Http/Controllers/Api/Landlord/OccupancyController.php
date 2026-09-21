<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\OccupancyActivity;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of Landlord\OccupancyController::index. CSV export is a
 * desktop-only concern and not ported. The web version's `units_url`/`edit_url`
 * fields are dropped — those are web route() URLs, meaningless to a client
 * that navigates natively; unit_id is enough for the client to build its own
 * route.
 */
class OccupancyController extends Controller
{
    private const ACTIVE_STATUSES = Reservation::TERMINAL_STATUSES;

    public function index(Request $request): JsonResponse
    {
        $landlordId = auth()->id();

        $properties = Property::where('landlord_id', $landlordId)->orderBy('title')->get();

        $selectedPropertyId = $request->integer('property') ?: null;
        if ($selectedPropertyId && ! $properties->contains('property_id', $selectedPropertyId)) {
            $selectedPropertyId = null;
        }

        $scopedProperties = $selectedPropertyId
            ? $properties->where('property_id', $selectedPropertyId)
            : $properties;

        $scopedPropertyIds = $scopedProperties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $scopedPropertyIds)
            ->with(['reservations.tenant:user_id,first_name,last_name', 'media', 'amenities'])
            ->get();

        $totalUnits = $units->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $maintenanceUnits = $units->where('availability_status', 'Maintenance')->count();

        $aggregateRate = $selectedPropertyId
            ? OccupancyRateCalculator::forProperty($selectedPropertyId)
            : OccupancyRateCalculator::forLandlord($landlordId);

        $unitStatusOverview = $scopedProperties->map(function (Property $property) use ($units) {
            $propertyUnits = $units->where('property_id', $property->property_id)->values();

            return [
                'property_id' => $property->property_id,
                'title'       => $property->title,
                'total'       => $propertyUnits->count(),
                'available'   => $propertyUnits->where('availability_status', 'Available')->count(),
                'reserved'    => $propertyUnits->where('availability_status', 'Reserved')->count(),
                'occupied'    => $propertyUnits->where('availability_status', 'Occupied')->count(),
                'maintenance' => $propertyUnits->where('availability_status', 'Maintenance')->count(),
                'units'       => $propertyUnits->map(fn (PropertyUnit $unit) => [
                    'unit_id'   => $unit->unit_id,
                    'label'     => $unit->unit_label,
                    'status'    => $unit->availability_status,
                    'tenant'    => $this->tenantNameFor($unit),
                    'type'      => $unit->unit_type,
                    'floor'     => $unit->floor,
                    'rent'      => (float) $unit->rental_fee,
                    'deposit'   => $unit->security_deposit !== null ? (float) $unit->security_deposit : null,
                    'capacity'  => $unit->occupancy_limit,
                    'photo'     => optional($unit->media->firstWhere('media_type', 'Image'))->media_url,
                    'amenities' => $unit->amenities->pluck('amenity_name')->values(),
                ])->values(),
            ];
        })->values();

        $vacantUnits = $units->where('availability_status', 'Available')->values();

        $vacancy = [
            'count'     => $vacantUnits->count(),
            'idle_rent' => (float) $vacantUnits->sum('rental_fee'),
            'units'     => $vacantUnits
                ->map(function (PropertyUnit $unit) use ($scopedProperties) {
                    $since = $unit->vacated_at ?? $unit->created_at;

                    return [
                        'label'     => $unit->unit_label,
                        'property'  => $scopedProperties->firstWhere('property_id', $unit->property_id)?->title,
                        'days'      => $since ? (int) $since->startOfDay()->diffInDays(now()->startOfDay()) : null,
                        'rent'      => (float) $unit->rental_fee,
                        'never_let' => $unit->vacated_at === null,
                    ];
                })
                ->sortByDesc('days')
                ->take(5)
                ->values(),
        ];

        $recentActivities = OccupancyActivity::with([
            'unit:unit_id,unit_label',
            'property:property_id,title',
            'actor:user_id,first_name,last_name',
            'tenant:user_id,first_name,last_name',
        ])
            ->where('landlord_id', $landlordId)
            ->when($selectedPropertyId, fn ($q) => $q->where('property_id', $selectedPropertyId))
            ->latest('activity_id')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => [
                'selected_property_id' => $selectedPropertyId,
                'total_units'          => $totalUnits,
                'available_units'      => $availableUnits,
                'reserved_units'       => $reservedUnits,
                'occupied_units'       => $occupiedUnits,
                'maintenance_units'    => $maintenanceUnits,
                'aggregate_rate'       => $aggregateRate,
                'unit_status_overview' => $unitStatusOverview,
                'vacancy'              => $vacancy,
                'recent_activities'    => $recentActivities,
            ],
        ]);
    }

    private function tenantNameFor(PropertyUnit $unit): ?string
    {
        if (! in_array($unit->availability_status, ['Reserved', 'Occupied'], true)) {
            return null;
        }

        $reservation = $unit->reservations
            ->whereNotIn('rental_status', self::ACTIVE_STATUSES)
            ->sortByDesc('reservation_id')
            ->first();

        $tenant = $reservation?->tenant;

        return $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : null;
    }
}
