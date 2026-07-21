<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitIndexController extends Controller
{
    /**
     * The landlord's units with the page's search/property/status filters
     * applied. Shared by index() and export() so a CSV always matches
     * exactly what the page is showing.
     */
    private function filteredQuery(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('unit_label', 'like', "%{$search}%")
                  ->orWhereHas('property', fn($pq) => $pq->where('title', 'like', "%{$search}%"));
            });
        }

        if ($propertyId = $request->input('property')) {
            $query->where('property_id', $propertyId);
        }

        if ($status = $request->input('status')) {
            $query->where('availability_status', $status);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $units = $this->filteredQuery($request)
            ->with([
                'property',
                'property.media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
                'media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
                'amenities',
                'reservations.tenant:user_id,first_name,last_name',
            ])
            ->latest()->paginate(12)->withQueryString();

        $properties = Auth::user()->properties()
            ->where('verification_status', 'Approved')
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $stats = [
            'total'     => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->count(),
            'available' => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Available')->count(),
            'occupied'  => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Occupied')->count(),
            'reserved'  => PropertyUnit::whereHas('property', fn($q) => $q->where('landlord_id', $landlordId))->where('availability_status', 'Reserved')->count(),
        ];
        return view('landlord.units.all', compact('units', 'properties', 'stats'));
    }

    /**
     * CSV of the currently filtered units. Streamed and chunked so a large
     * portfolio doesn't build the whole file in memory.
     */
    public function export(Request $request)
    {
        $filename = 'abangananhub-units-' . now()->format('Y-m-d') . '.csv';

        $query = $this->filteredQuery($request)
            ->with(['property:property_id,title', 'reservations.tenant:user_id,first_name,last_name']);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Property', 'Unit', 'Type', 'Floor', 'Monthly Rent',
                'Security Deposit', 'Capacity', 'Status', 'Tenant', 'Last Updated',
            ]);

            $query->chunk(200, function ($units) use ($handle) {
                foreach ($units as $unit) {
                    $tenant = $unit->reservations
                        ->firstWhere('rental_status', 'Occupied')?->tenant;

                    fputcsv($handle, [
                        $unit->property->title ?? '',
                        $unit->unit_label,
                        $unit->unit_type ?? '',
                        $unit->floor ?? '',
                        $unit->rental_fee,
                        $unit->security_deposit ?? '',
                        $unit->occupancy_limit ?? '',
                        $unit->availability_status,
                        $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : '',
                        optional($unit->updated_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}