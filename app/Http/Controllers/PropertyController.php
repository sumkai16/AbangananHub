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

        if ($request->filled('location')) {
            $query->where('address', 'like', '%' . $request->location . '%');
        }
        if ($request->filled('type')) {
            $query->where('property_type', $request->type);
        }
        if ($request->filled('price_max')) {
            $query->where('rental_fee', '<=', $request->price_max);
        }
        if ($request->boolean('verified')) {
            $query->whereHas('landlord.roles', function ($q) {
                $q->where('role', 'Landlord');
            })->whereHas('landlord.verificationApplication', function ($q) {
                $q->where('verification_status', 'Approved');
            });
        }

        $properties = $query->latest('created_at')->paginate(12)->withQueryString();

        // Fetch current user's favorited property IDs for heart state
        $favoritedIds = \App\Models\Favorite::where('tenant_id', auth()->user()->user_id)
            ->pluck('property_id')
            ->toArray();

        return view('properties.index', compact('properties', 'favoritedIds'));
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