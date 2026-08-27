<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\OccupancyActivity;
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
                    'floorArea' => $unit->floor_area_label,
                    'photo'     => optional($unit->media->firstWhere('media_type', 'Image'))->media_url,
                    'amenities' => $unit->amenities->pluck('amenity_name')->values(),
                    'edit_url'  => route('landlord.properties.units.edit', [$property->property_id, $unit->unit_id]),
                ])->values(),
            ];
        })->values();

        // ── Vacancy Watch ────────────────────────────────────
        // Replaced the 30-day occupancy trend chart. The trend answered "what
        // was my occupancy three weeks ago" — history the landlord already
        // lived through and cannot act on. This answers "which empty units are
        // costing me money, and how much", which is the decision the page is
        // actually opened to make.
        $vacantUnits = $units->where('availability_status', 'Available')->values();

        // A unit that has never been let has no vacated_at, so it has been empty
        // since it was created — that is a longer, more urgent vacancy than a
        // recently-ended tenancy, not an absent one.
        $vacancy = [
            'count'      => $vacantUnits->count(),
            'idle_rent'  => (float) $vacantUnits->sum('rental_fee'),
            'units'      => $vacantUnits
                ->map(function (PropertyUnit $unit) use ($scopedProperties) {
                    $since = $unit->vacated_at ?? $unit->created_at;

                    return [
                        'label'    => $unit->unit_label,
                        'property' => $scopedProperties->firstWhere('property_id', $unit->property_id)?->title,
                        'days'     => $since ? (int) $since->startOfDay()->diffInDays(now()->startOfDay()) : null,
                        'rent'     => (float) $unit->rental_fee,
                        'never_let' => $unit->vacated_at === null,
                        'edit_url' => route('landlord.properties.units.edit', [$unit->property_id, $unit->unit_id]),
                    ];
                })
                ->sortByDesc('days')
                ->take(5)
                ->values(),
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
            'vacancy'            => $vacancy,
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
