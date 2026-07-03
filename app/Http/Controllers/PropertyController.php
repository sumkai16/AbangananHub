<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
public function index(Request $request)
{
    $query = Property::with(['media', 'landlord', 'amenities', 'units'])
        ->where('verification_status', 'Approved')
        ->whereHas('units', function ($q) {
            $q->where('availability_status', 'Available')
              ->where('verification_status', 'Approved'); // FIX 1: was missing verification_status
        });

    if ($request->filled('location')) {
        $query->where(function ($q) use ($request) {
            $q->where('address', 'like', '%' . $request->location . '%')
            ->orWhere('title', 'like', '%' . $request->location . '%');
        });
    }
    if ($request->filled('type')) {
        $query->where('property_type', $request->type);
    }
    if ($request->filled('price_max')) {
        $query->whereHas('units', function ($q) use ($request) { // FIX 2: actually apply price constraint
            $q->where('availability_status', 'Available')
              ->where('verification_status', 'Approved')
              ->where('rental_fee', '<=', $request->price_max);
        });
    }
    if ($request->boolean('verified')) {
        $query->whereHas('landlord.rentalBusiness');
    }

    $properties = $query->latest('created_at')->paginate(12)->withQueryString();

    $favoritedIds = [];
    if (auth()->check()) {
        $favoritedIds = Favorite::where('tenant_id', auth()->user()->user_id)
            ->pluck('property_id')
            ->toArray();
    }

    $mapProperties = $properties->getCollection()->map(function ($property) {
        $minFee = $property->units
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved') // FIX 3: already correct here, no change
            ->min('rental_fee');
        return [
            'property_id'   => $property->property_id,
            'title'         => $property->title,
            'latitude'      => $property->latitude,
            'longitude'     => $property->longitude,
            'rental_fee'    => $minFee,
            'url'           => route('properties.show', $property->property_id),
            'property_type' => $property->property_type,
            'image'         => $property->media->first()?->media_url ?? null,
        ];
    })->values();

    return view('properties.index', compact('properties', 'favoritedIds', 'mapProperties'));
}
  public function show(Property $property)
    {
        if ($property->verification_status !== 'Approved') {
            abort(404);
        }
        $property->load(['media', 'landlord', 'amenities', 'reviews.tenant', 'units']);
        return view('properties.show', compact('property'));
    }

    public function create()
    {
        return view('properties.create');
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'title'         => 'required|string|min:10|max:150',
        'description'   => 'required|string|min:20|max:3000',
        'property_type' => 'required|in:Bedspace,Room,Apartment,House',
        'address'       => 'required|string|min:10|max:255',
        'latitude'      => 'nullable|numeric|between:-90,90',
        'longitude'     => 'nullable|numeric|between:-180,180',
        'photos'        => 'required|array|min:1|max:10',
        'photos.*'      => 'image|mimes:jpeg,png,jpg,webp|max:5120',
    ]);

    $property = null;

    DB::transaction(function () use ($validated, $request, &$property) {
        $property = new Property();
        $property->landlord_id        = Auth::user()->user_id;
        $property->title              = $validated['title'];
        $property->description        = $validated['description'];
        $property->property_type      = $validated['property_type'];
        $property->address            = $validated['address'];
        $property->latitude           = $validated['latitude'] ?? 10.3157;
        $property->longitude          = $validated['longitude'] ?? 123.8854;
        $property->verification_status = 'Pending';
        $property->save();

        foreach ($request->file('photos') as $photo) {
            $result = cloudinary()->upload($photo->getRealPath(), [
                'folder'        => 'abanganan/properties',
                'resource_type' => 'image',
            ]);
            $property->media()->create([
                'media_type'           => 'Image',
                'media_url'            => $result->getSecurePath(),
                'cloudinary_public_id' => $result->getPublicId(),
            ]);
        }
    });

    return redirect()
        ->route('landlord.properties.units.index', $property)
        ->with('success', 'Property listed! Add units below — they\'re needed before the listing goes live.');
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
        'title'         => 'required|string|min:10|max:150',
        'description'   => 'required|string|min:20|max:3000',
        'property_type' => 'required|in:Bedspace,Room,Apartment,House',
        'address'       => 'required|string|min:10|max:255',
        'latitude'      => 'nullable|numeric|between:-90,90',
        'longitude'     => 'nullable|numeric|between:-180,180',
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

        $property->fill([
            'title'          => $validated['title'],
            'description'    => $validated['description'],
            'property_type'  => $validated['property_type'],
            'address'        => $validated['address'],
            'latitude'       => $validated['latitude'] ?? $property->latitude,
            'longitude'      => $validated['longitude'] ?? $property->longitude,
        ]);

        $detailsChanged = $property->isDirty();

        if ($detailsChanged || $photosAdded) {
            $property->verification_status = 'Pending';
        }

        $property->save();

        if ($photosAdded) {
            foreach ($request->file('photos') as $photo) {
                $result = cloudinary()->upload($photo->getRealPath(), [
                    'folder'        => 'abanganan/properties',
                    'resource_type' => 'image',
                ]);
                $property->media()->create([
                    'media_type'           => 'Image',
                    'media_url'            => $result->getSecurePath(),
                    'cloudinary_public_id' => $result->getPublicId(),
                ]);
            }
        }
    });

    return redirect()->route('landlord.listings.index')->with('success', 'Property updated. It\'s back in the approval queue.');
}

    public function destroy(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        foreach ($property->media as $media) {
            if ($media->cloudinary_public_id) {
                cloudinary()->destroy($media->cloudinary_public_id);
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

        if ($photo->cloudinary_public_id) {
            cloudinary()->destroy($photo->cloudinary_public_id);
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}