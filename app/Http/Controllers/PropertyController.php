<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with(['media', 'landlord', 'amenities'])
            ->where('verification_status', 'Approved')
            ->where('availability_status', 'Available');

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

        $favoritedIds = [];
        if (auth()->check()) {
            $favoritedIds = Favorite::where('tenant_id', auth()->user()->user_id)
                ->pluck('property_id')
                ->toArray();
        }

        return view('properties.index', compact('properties', 'favoritedIds'));
    }

    public function show(Property $property)
    {
        if ($property->verification_status !== 'Approved') {
            abort(404);
        }

        $property->load(['media', 'landlord', 'amenities', 'reviews.tenant']);
        return view('properties.show', compact('property'));
    }

    public function create()
    {
        return view('properties.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|min:10|max:150',
            'description' => 'required|string|min:20|max:3000',
            'property_type' => 'required|in:Bedspace,Room,Apartment,House',
            'address' => 'required|string|min:10|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'rental_fee' => 'required|numeric|min:500|max:999999.99',
            'occupancy_limit' => 'required|integer|min:1|max:100',
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $property = new Property();
        $property->landlord_id = Auth::user()->user_id;
        $property->title = $validated['title'];
        $property->description = $validated['description'];
        $property->property_type = $validated['property_type'];
        $property->address = $validated['address'];
        $property->latitude = $validated['latitude'] ?? 10.3157;
        $property->longitude = $validated['longitude'] ?? 123.8854;
        $property->rental_fee = $validated['rental_fee'];
        $property->occupancy_limit = $validated['occupancy_limit'];
        $property->availability_status = 'Available';
        $property->verification_status = 'Pending';
        $property->save();

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('properties', 'public');
                $property->media()->create([
                    'media_url' => $path, // Match the database column name exactly
                ]);
            }
        }

        return redirect()->route('landlord.listings.index')->with('success', 'Property listed successfully!');
    }

    public function edit(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized access.');
        }
        return view('properties.edit', compact('property'));
    }

    public function update(Request $request, Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'title' => 'required|string|min:10|max:150',
            'description' => 'required|string|min:20|max:3000',
            'property_type' => 'required|in:Bedspace,Room,Apartment,House',
            'address' => 'required|string|min:10|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'rental_fee' => 'required|numeric|min:500|max:999999.99',
            'occupancy_limit' => 'required|integer|min:1|max:100',
        ]);

        $property->update($validated);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('properties', 'public');
                $property->media()->create([
                    'media_url' => $path,
                ]);
            }
        }

        return redirect()->route('landlord.listings.index')->with('success', 'Property updated successfully!');
    }

    public function destroy(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        foreach ($property->media as $media) {
            Storage::disk('public')->delete($media->media_url);
            $media->delete();
        }

        $property->delete();
        return redirect()->route('landlord.listings.index')->with('success', 'Property removed successfully.');
    }
}