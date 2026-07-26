<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;

class UnitCatalogueController extends Controller
{
    /**
     * The catalogue's search/filter/sort applied to the base query. Shared
     * by index() and export() so a CSV always matches exactly what the page
     * is showing.
     */
    private function filteredQuery(Request $request)
    {
        $query = PropertyUnit::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('unit_label', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($pq) use ($search) {
                      $pq->where('title', 'like', "%{$search}%")
                         ->orWhereHas('landlord', function ($lq) use ($search) {
                             $lq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        if ($verification = $request->input('verification_status')) {
            $query->where('verification_status', $verification);
        }

        if ($availability = $request->input('availability_status')) {
            $query->where('availability_status', $availability);
        }

        if ($propertyId = $request->input('property_id')) {
            $query->where('property_id', $propertyId);
        }

        if ($minFee = $request->input('min_fee')) {
            $query->where('rental_fee', '>=', $minFee);
        }

        if ($maxFee = $request->input('max_fee')) {
            $query->where('rental_fee', '<=', $maxFee);
        }

        switch ($request->input('health')) {
            case 'long-vacant':
                $query->where('availability_status', 'Available')
                      ->where('vacated_at', '<', now()->subDays(60));
                break;
            case 'stuck-maintenance':
                $query->where('availability_status', 'Maintenance')
                      ->where('updated_at', '<', now()->subDays(30));
                break;
            case 'no-media':
                $query->doesntHave('media');
                break;
        }

        switch ($request->input('sort', 'newest')) {
            case 'oldest':
                $query->oldest();
                break;
            case 'fee_asc':
                $query->orderBy('rental_fee', 'asc');
                break;
            case 'fee_desc':
                $query->orderByDesc('rental_fee');
                break;
            case 'vacant_longest':
                $query->orderBy('vacated_at', 'asc');
                break;
            default:
                $query->latest();
        }

        return $query;
    }

    public function index(Request $request)
    {
        $units = $this->filteredQuery($request)
            ->with([
                'property:property_id,title,landlord_id',
                'property.landlord:user_id,first_name,last_name',
                'media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
                'activeReservation.tenant:user_id,first_name,last_name',
            ])
            ->paginate(15)
            ->withQueryString();

        $total = PropertyUnit::count();
        $counts = [
            'total'       => $total,
            'occupied'    => PropertyUnit::where('availability_status', 'Occupied')->count(),
            'reserved'    => PropertyUnit::where('availability_status', 'Reserved')->count(),
            'available'   => PropertyUnit::where('availability_status', 'Available')->count(),
            'maintenance' => PropertyUnit::where('availability_status', 'Maintenance')->count(),
        ];
        $counts['occupancy_rate'] = $total > 0 ? round($counts['occupied'] / $total * 100) : 0;

        return view('admin.catalogue.units.index', [
            'units'   => $units,
            'counts'  => $counts,
            'search'  => $request->input('search', ''),
            'filters' => $request->only(['verification_status', 'availability_status', 'property_id', 'min_fee', 'max_fee', 'health', 'sort']),
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'abangananhub-unit-catalogue-' . now()->format('Y-m-d') . '.csv';

        $query = $this->filteredQuery($request)
            ->with(['property:property_id,title', 'activeReservation.tenant:user_id,first_name,last_name']);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Property', 'Unit', 'Monthly Rent', 'Capacity',
                'Availability Status', 'Verification Status', 'Tenant',
                'Vacated At', 'Rejection Reason', 'Last Updated',
            ]);

            $query->chunk(200, function ($units) use ($handle) {
                foreach ($units as $unit) {
                    fputcsv($handle, [
                        $unit->property->title ?? '',
                        $unit->unit_label,
                        $unit->rental_fee,
                        $unit->occupancy_limit ?? '',
                        $unit->availability_status,
                        $unit->verification_status,
                        $unit->activeReservation?->tenant
                            ? trim($unit->activeReservation->tenant->first_name . ' ' . $unit->activeReservation->tenant->last_name)
                            : '',
                        optional($unit->vacated_at)->format('Y-m-d'),
                        $unit->rejection_reason ?? '',
                        optional($unit->updated_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
