<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with(['media', 'landlord', 'amenities'])
            ->where('verification_status', 'Approved')
            ->where('availability_status', '!=', 'Occupied');

        // Location — text search against address
        if ($request->filled('location')) {
            $query->where('address', 'like', '%' . $request->location . '%');
        }

        // Property type
        if ($request->filled('type')) {
            $query->where('property_type', $request->type);
        }

        // Budget — max rental fee
        if ($request->filled('price_max')) {
            $query->where('rental_fee', '<=', $request->price_max);
        }

        // Verified landlord filter
        if ($request->boolean('verified')) {
            $query->whereHas('landlord.roles', function ($q) {
                $q->where('role', 'Landlord');
            })->whereHas('landlord.verificationApplication', function ($q) {
                $q->where('verification_status', 'Approved');
            });
        }

        $properties = $query
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('properties.index', compact('properties'));
    }

    public function show(Property $property)
    {
        // Only show approved properties
        if ($property->verification_status !== 'Approved') {
            abort(404);
        }

        $property->load(['media', 'landlord', 'amenities', 'reviews.tenant']);

        return view('properties.show', compact('property'));
    }
}