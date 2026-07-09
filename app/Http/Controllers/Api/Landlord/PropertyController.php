<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * The landlord's own properties with unit counts.
     * Same query as the web Landlord\PropertyController@index.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Property::where('landlord_id', $request->user()->user_id)
            ->withCount([
                'units',
                'units as available_units_count' => fn ($q) => $q->where('availability_status', 'Available'),
                'units as reserved_units_count'  => fn ($q) => $q->where('availability_status', 'Reserved'),
                'units as occupied_units_count'  => fn ($q) => $q->where('availability_status', 'Occupied'),
            ])
            ->with(['media' => fn ($q) => $q->where('media_type', 'Image')->orderBy('media_id')]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('verification_status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('property_type', $type);
        }

        $properties = $query->latest()->paginate(9)->withQueryString();

        return response()->json($properties);
    }

    /**
     * Single property detail (owner only) with units, media, reviews.
     */
    public function show(Request $request, Property $property): JsonResponse
    {
        if ($property->landlord_id !== $request->user()->user_id) {
            abort(403);
        }

        $property->load([
            'media',
            'amenities',
            'units' => fn ($q) => $q->orderBy('unit_label'),
            'units.media',
            'reviews' => fn ($q) => $q->with('tenant:user_id,first_name,last_name,profile_picture')->latest()->take(20),
        ]);

        $unitStats = [
            'total'     => $property->units->count(),
            'available' => $property->units->where('availability_status', 'Available')->count(),
            'reserved'  => $property->units->where('availability_status', 'Reserved')->count(),
            'occupied'  => $property->units->where('availability_status', 'Occupied')->count(),
        ];

        return response()->json([
            'data' => array_merge($property->toArray(), [
                'unit_stats' => $unitStats,
            ]),
        ]);
    }
}
