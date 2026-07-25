<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\OccupancyActivity;
use App\Models\OccupancySnapshot;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OccupancyController extends Controller
{
    /**
     * Named for what it selects against, not what it holds — the one use below
     * is a whereNotIn. The values live on the model so a new terminal status
     * reaches every occupancy query at once.
     */
    private const ACTIVE_STATUSES = Reservation::TERMINAL_STATUSES;

    public function index(Request $request)
    {
        $landlordId = Auth::id();

        $properties = Property::where('landlord_id', $landlordId)
            ->orderBy('title')
            ->get();

        // Optional property filter (?property=ID)
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

        // ── Headline counts ──────────────────────────────────
        $totalUnits = $units->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();
        $reservedUnits = $units->where('availability_status', 'Reserved')->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $maintenanceUnits = $units->where('availability_status', 'Maintenance')->count();

        $aggregateRate = $selectedPropertyId
            ? OccupancyRateCalculator::forProperty($selectedPropertyId)
            : OccupancyRateCalculator::forLandlord($landlordId);

        // ── Unit Status Overview (per property → units + tenant) ──
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
                'units_url'   => route('landlord.properties.units.index', $property->property_id),
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
                    'edit_url'  => route('landlord.properties.units.edit', [$property->property_id, $unit->unit_id]),
                ])->values(),
            ];
        })->values();

        // ── Occupancy Trend (last 30 days of snapshots) ──────
        $snapshots = OccupancySnapshot::where('landlord_id', $landlordId)
            ->where('snapshot_date', '>=', now()->subDays(29)->toDateString())
            ->orderBy('snapshot_date')
            ->get();

        $trend = [
            'labels' => $snapshots->map(fn ($s) => $s->snapshot_date->format('M j'))->values()->all(),
            'data'   => $snapshots->map(fn ($s) => (float) $s->occupancy_rate)->values()->all(),
        ];

        // ── Recent Activities ────────────────────────────────
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

        return view('landlord.occupancy.index', [
            'properties'         => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'totalUnits'         => $totalUnits,
            'availableUnits'     => $availableUnits,
            'reservedUnits'      => $reservedUnits,
            'occupiedUnits'      => $occupiedUnits,
            'maintenanceUnits'   => $maintenanceUnits,
            'aggregateRate'      => $aggregateRate,
            'unitStatusOverview' => $unitStatusOverview,
            'trend'              => $trend,
            'recentActivities'   => $recentActivities,
        ]);
    }

    public function export(Request $request)
    {
        $landlordId = Auth::id();

        $propertyIds = Property::where('landlord_id', $landlordId)->pluck('property_id');

        $selectedPropertyId = $request->integer('property') ?: null;
        if ($selectedPropertyId && ! $propertyIds->contains($selectedPropertyId)) {
            $selectedPropertyId = null;
        }

        $filename = 'abangananhub-occupancy-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($propertyIds, $selectedPropertyId) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property', 'Unit', 'Type', 'Status', 'Tenant', 'Last updated']);

            PropertyUnit::whereIn('property_id', $selectedPropertyId ? [$selectedPropertyId] : $propertyIds)
                ->with(['property:property_id,title', 'reservations.tenant:user_id,first_name,last_name'])
                ->chunk(200, function ($units) use ($handle) {
                    foreach ($units as $unit) {
                        fputcsv($handle, [
                            $unit->property->title ?? '',
                            $unit->unit_label,
                            $unit->unit_type ?? '',
                            $unit->availability_status,
                            $this->tenantNameFor($unit) ?? '',
                            optional($unit->updated_at)->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Tenant name for Reserved/Occupied units (latest non-terminal reservation).
     */
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

        return $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : null;
    }
}
