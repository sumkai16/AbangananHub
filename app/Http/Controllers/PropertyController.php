<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($validated, $request) {
            $property = new Property();
            $property->landlord_id = Auth::user()->user_id;
            $property->title = $validated['title'];
            $property->description = $validated['description'];
            $property->property_type = $validated['property_type'];
            $property->address = $validated['address'];
            // TODO: real per-property coordinates once Maps is built — see properties.create note.
            $property->latitude = $validated['latitude'] ?? 10.3157;
            $property->longitude = $validated['longitude'] ?? 123.8854;
            $property->rental_fee = $validated['rental_fee'];
            $property->occupancy_limit = $validated['occupancy_limit'];
            $property->availability_status = 'Available';
            $property->verification_status = 'Pending';
            $property->save();

            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('properties', 'public');
                $property->media()->create([
                    'media_url' => $path,
                ]);
            }
        });

        return redirect()->route('landlord.listings.index')->with('success', 'Property listed successfully! It’s pending admin approval before it goes live.');
    }

    public function edit(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized access.');
        }

        $property->load('media');
        return view('properties.edit', compact('property'));
    }

    public function update(Request $request, Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $existingPhotoCount = $property->media()->count();

        $validated = $request->validate([
            'title' => 'required|string|min:10|max:150',
            'description' => 'required|string|min:20|max:3000',
            'property_type' => 'required|in:Bedspace,Room,Apartment,House',
            'address' => 'required|string|min:10|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'rental_fee' => 'required|numeric|min:500|max:999999.99',
            'occupancy_limit' => 'required|integer|min:1|max:100',
            'photos' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($existingPhotoCount) {
                    if ($existingPhotoCount + count($value) > 10) {
                        $fail('A property can have at most 10 photos total. Remove some before adding more.');
                    }
                },
            ],
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        DB::transaction(function () use ($validated, $request, $property) {
            $photosAdded = $request->hasFile('photos');

            $property->fill($validated);
            $detailsChanged = $property->isDirty();
            $property->save();

            // Any real edit sends the listing back for re-review — content shown
            // to tenants as "Approved" should match what was actually approved.
            if ($detailsChanged || $photosAdded) {
                $property->verification_status = 'Pending';
                $property->save();
            }

            if ($photosAdded) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('properties', 'public');
                    $property->media()->create([
                        'media_url' => $path,
                    ]);
                }
            }
        });

        return redirect()->route('landlord.listings.index')->with('success', 'Property updated. It’s back in the approval queue.');
    }

    public function destroy(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        foreach ($property->media as $media) {
            if (!str_starts_with($media->media_url, 'http')) {
                Storage::disk('public')->delete($media->media_url);
            }
            $media->delete();
        }

        $property->delete();
        return redirect()->route('landlord.listings.index')->with('success', 'Property removed successfully.');
    }

    public function destroyMedia(Property $property, int $media)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($property->media()->count() <= 1) {
            return back()->withErrors(['photos' => 'A listing needs at least one photo — upload a replacement before removing the last one.']);
        }

        $photo = $property->media()->where('media_id', $media)->firstOrFail();

        if (!str_starts_with($photo->media_url, 'http')) {
            Storage::disk('public')->delete($photo->media_url);
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}