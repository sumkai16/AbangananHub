<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    private function authorizeProperty(Property $property): void
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $landlordId = Auth::user()->user_id;

        $query = Property::where('landlord_id', $landlordId)
            ->withCount([
                'units',
                'units as available_units_count' => fn($q) => $q->where('availability_status', 'Available'),
                'units as reserved_units_count'  => fn($q) => $q->where('availability_status', 'Reserved'),
                'units as occupied_units_count'  => fn($q) => $q->where('availability_status', 'Occupied'),
            ])
            ->with(['media' => fn($q) => $q->where('media_type', 'Image')->orderBy('media_id')->limit(1)]);

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

        return view('landlord.properties.index', compact('properties'));
    }

    public function show(Property $property)
    {
        $this->authorizeProperty($property);

        $property->load([
            'media',
            'amenities',
            'units' => fn($q) => $q->orderBy('unit_label'),
            'units.media',
            'reviews' => fn($q) => $q->with('tenant')->latest()->take(20),
        ]);

        $unitStats = [
            'total'    => $property->units->count(),
            'available' => $property->units->where('availability_status', 'Available')->count(),
            'reserved'  => $property->units->where('availability_status', 'Reserved')->count(),
            'occupied'  => $property->units->where('availability_status', 'Occupied')->count(),
        ];

        return view('landlord.properties.show', compact('property', 'unitStats'));
    }
}