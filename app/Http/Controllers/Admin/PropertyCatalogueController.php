<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyCatalogueController extends Controller
{
    /**
     * The catalogue's search/filter/sort applied to the base query. Shared
     * by index() and export() so a CSV always matches exactly what the page
     * is showing.
     */
    private function filteredQuery(Request $request)
    {
        $query = Property::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('landlord', function ($lq) use ($search) {
                      $lq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($verification = $request->input('verification_status')) {
            $query->where('verification_status', $verification);
        }

        if ($type = $request->input('property_type')) {
            $query->where('property_type', $type);
        }

        if ($minFee = $request->input('min_fee')) {
            $query->whereHas('units', fn ($q) => $q->where('rental_fee', '>=', $minFee));
        }

        if ($maxFee = $request->input('max_fee')) {
            $query->whereHas('units', fn ($q) => $q->where('rental_fee', '<=', $maxFee));
        }

        switch ($request->input('health')) {
            case 'no-media':
                $query->doesntHave('media');
                break;
            case 'no-geocode':
                // latitude/longitude are NOT NULL — an un-pinned property
                // gets the Cebu City center default (10.3157, 123.8854)
                // instead, see PropertyController::store()/update().
                $query->where('latitude', 10.3157)->where('longitude', 123.8854);
                break;
            case 'no-units':
                $query->doesntHave('units');
                break;
            case 'stale':
                $query->where('created_at', '<', now()->subDays(90));
                break;
        }

        switch ($request->input('sort', 'newest')) {
            case 'oldest':
                $query->oldest();
                break;
            case 'fee_asc':
                $query->withMin('units as min_fee', 'rental_fee')->orderBy('min_fee', 'asc');
                break;
            case 'fee_desc':
                $query->withMin('units as min_fee', 'rental_fee')->orderByDesc('min_fee');
                break;
            case 'most_units':
                $query->withCount('units')->orderByDesc('units_count');
                break;
            default:
                $query->latest();
        }

        return $query;
    }

    public function index(Request $request)
    {
        $properties = $this->filteredQuery($request)
            ->with(['landlord:user_id,first_name,last_name,email'])
            ->withCount([
                'units',
                'units as available_units_count' => fn ($q) => $q->where('availability_status', 'Available'),
                'units as reserved_units_count'  => fn ($q) => $q->where('availability_status', 'Reserved'),
                'units as occupied_units_count'  => fn ($q) => $q->where('availability_status', 'Occupied'),
            ])
            ->with(['media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1)])
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'total'    => Property::count(),
            'approved' => Property::where('verification_status', 'Approved')->count(),
            'pending'  => Property::where('verification_status', 'Pending')->count(),
            'rejected' => Property::where('verification_status', 'Rejected')->count(),
            'needs_attention' => Property::query()
                ->where(function ($q) {
                    $q->doesntHave('media')
                      ->orWhere(function ($geo) {
                          $geo->where('latitude', 10.3157)->where('longitude', 123.8854);
                      })
                      ->orDoesntHave('units')
                      ->orWhere('created_at', '<', now()->subDays(90));
                })
                ->count(),
        ];

        return view('admin.catalogue.properties.index', [
            'properties' => $properties,
            'counts'     => $counts,
            'search'     => $request->input('search', ''),
            'filters'    => $request->only(['verification_status', 'property_type', 'min_fee', 'max_fee', 'health', 'sort']),
        ]);
    }

    public function show(Property $property)
    {
        $property->load([
            'landlord:user_id,first_name,last_name,email',
            'media',
            'units' => fn ($q) => $q->orderBy('unit_label'),
            'units.media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1),
            'reservations.tenant:user_id,first_name,last_name',
            'reviews' => fn ($q) => $q->where('is_hidden', false),
        ]);

        return view('admin.catalogue.properties.show', compact('property'));
    }

    public function export(Request $request)
    {
        $filename = 'abangananhub-properties-' . now()->format('Y-m-d') . '.csv';

        $query = $this->filteredQuery($request)
            ->with(['landlord:user_id,first_name,last_name,email'])
            ->withCount('units');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Title', 'Type', 'Address', 'Landlord', 'Verification Status',
                'Units', 'Has Coordinates', 'Created', 'Last Updated',
            ]);

            $query->chunk(200, function ($properties) use ($handle) {
                foreach ($properties as $property) {
                    fputcsv($handle, [
                        $property->title,
                        $property->property_type,
                        $property->address,
                        $property->landlord
                            ? trim($property->landlord->first_name . ' ' . $property->landlord->last_name)
                            : '',
                        $property->verification_status,
                        $property->units_count,
                        ($property->latitude == 10.3157 && $property->longitude == 123.8854) ? 'No' : 'Yes',
                        optional($property->created_at)->format('Y-m-d H:i'),
                        optional($property->updated_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
