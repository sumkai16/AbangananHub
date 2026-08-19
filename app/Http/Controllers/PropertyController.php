<?php

namespace App\Http\Controllers;

use App\Http\Requests\Landlord\StorePropertyRequest;
use App\Http\Requests\Landlord\UpdatePropertyRequest;
use App\Models\Amenity;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Review;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with(['media', 'landlord', 'amenities', 'units'])
            ->where('verification_status', 'Approved')
            ->whereHas('units', function ($q) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved');
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
            $query->whereHas('units', function ($q) use ($request) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved')
                  ->where('rental_fee', '<=', $request->price_max);
            });
        }
        if ($request->boolean('verified')) {
            $query->whereHas('landlord.rentalBusiness');
        }

        $query->withMin(['units as min_rental_fee' => function ($q) {
            $q->where('availability_status', 'Available')
              ->where('verification_status', 'Approved');
        }], 'rental_fee');
        $query->withAvg(['reviews as avg_rating' => function ($q) {
            $q->where('is_hidden', false);
        }], 'rating');

        $query->withCount(['reviews as review_count' => function ($q) {
            $q->where('is_hidden', false);
        }]);
        match ($request->query('sort')) {
            'price_low'  => $query->orderBy('min_rental_fee', 'asc'),
            'price_high' => $query->orderByDesc('min_rental_fee'),
            default      => $query->latest('created_at'),
        };

        $properties = $query->paginate(12)->withQueryString();

        $favoritedIds = [];
        if (auth()->check()) {
            $favoritedIds = Favorite::where('tenant_id', auth()->user()->user_id)
                ->pluck('property_id')
                ->toArray();
        }

        $mapProperties = $properties->getCollection()->map(function ($property) {
            $minFee = $property->units
                ->where('availability_status', 'Available')
                ->where('verification_status', 'Approved')
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

        // The hero's trust strip states live totals, not the whole-platform
        // pitch — quoting an aspirational "2,400+ listings" on a page showing
        // 13 is the one claim a tenant can check instantly. Only computed when
        // the full hero renders (clean page 1); the compact band shows none.
        $heroStats = null;
        if (! $request->hasAny(['location', 'type', 'price_max', 'verified', 'sort']) && $request->integer('page', 1) <= 1) {
            $availableUnits = fn($q) => $q->where('availability_status', 'Available')
                ->where('verification_status', 'Approved');

            $heroStats = [
                'listings' => Property::where('verification_status', 'Approved')
                    ->whereHas('units', $availableUnits)->count(),
                'units'    => PropertyUnit::where('availability_status', 'Available')
                    ->where('verification_status', 'Approved')->count(),
            ];
        }

        return view('properties.index', compact('properties', 'favoritedIds', 'mapProperties', 'heroStats'));
    }

    public function show(Property $property)
    {
        if ($property->verification_status !== 'Approved') {
            abort(404);
        }

        $property->load(['media', 'amenities', 'landlord.rentalBusiness', 'units.amenities', 'units.media']);

        $reviews = $property->reviews()
            ->with('tenant')
            ->when(!auth()->check() || !auth()->user()->roles()->where('role', 'Admin')->exists(), function ($q) {
                $q->where('is_hidden', false);
            })
            ->latest()
            ->get();

        $avgRating = $reviews->where('is_hidden', false)->avg('rating');
        $avgRating = $avgRating ? round($avgRating, 1) : null;

        $canReview = auth()->check() && Review::canReview(auth()->id(), $property->property_id);

        $isFavorited = auth()->check() && Favorite::where('tenant_id', auth()->id())
            ->where('property_id', $property->property_id)
            ->exists();

        return view('properties.show', compact('property', 'reviews', 'avgRating', 'canReview', 'isFavorited'));
    }

    public function create()
    {
        $amenities = Amenity::forProperty()->orderBy('category')->orderBy('amenity_name')->get();

        return view('landlord.properties.create', compact('amenities'));
    }

    public function store(StorePropertyRequest $request)
    {
        $validated = $request->validated();

        $property = null;

        DB::transaction(function () use ($validated, $request, &$property) {
            $property = new Property();
            $property->landlord_id         = Auth::user()->user_id;
            $property->title               = $validated['title'];
            $property->description         = $validated['description'];
            $property->property_type       = $validated['property_type'];
            $property->address             = $validated['address'];
            $property->city_municipality   = $validated['city_municipality'];
            $property->barangay            = $validated['barangay'] ?? null;
            $property->latitude            = $validated['latitude'];
            $property->longitude           = $validated['longitude'];
            $property->verification_status = 'Pending';
            $property->save();

            $property->amenities()->sync($validated['amenities'] ?? []);

            foreach ($request->file('photos') as $photo) {
                $result = cloudinary()->uploadApi()->upload($photo->getRealPath(), [
                    'folder'        => 'abanganan/properties',
                    'resource_type' => 'image',
                ]);
                $property->media()->create([
                    'media_type'           => 'Image',
                    'media_url'            => $result['secure_url'],
                    'cloudinary_public_id' => $result['public_id'],
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

        $property->load('media', 'amenities');
        $amenities = Amenity::forProperty()->orderBy('category')->orderBy('amenity_name')->get();

        return view('landlord.properties.edit', compact('property', 'amenities'));
    }

    public function update(UpdatePropertyRequest $request, Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $property) {
            $photosAdded = $request->hasFile('photos');

            $property->fill([
                'title'             => $validated['title'],
                'description'       => $validated['description'],
                'property_type'     => $validated['property_type'],
                'address'           => $validated['address'],
                'city_municipality' => $validated['city_municipality'],
                'barangay'          => $validated['barangay'] ?? null,
                'latitude'          => $validated['latitude'],
                'longitude'         => $validated['longitude'],
            ]);

            $detailsChanged = $property->isDirty();

            if ($detailsChanged || $photosAdded) {
                $property->verification_status = 'Pending';
            }

            $property->save();

            $property->amenities()->sync($validated['amenities'] ?? []);

            if ($photosAdded) {
                foreach ($request->file('photos') as $photo) {
                    $result = cloudinary()->uploadApi()->upload($photo->getRealPath(), [
                        'folder'        => 'abanganan/properties',
                        'resource_type' => 'image',
                    ]);
                    $property->media()->create([
                        'media_type'           => 'Image',
                        'media_url'            => $result['secure_url'],
                        'cloudinary_public_id' => $result['public_id'],
                    ]);
                }
            }
        });

        return redirect()->route('landlord.properties.index')->with('success', 'Property updated. It\'s back in the approval queue.');
    }

    public function destroy(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        foreach ($property->media as $media) {
            if ($media->cloudinary_public_id) {
                cloudinary()->uploadApi()->destroy($media->cloudinary_public_id);
            }
            $media->delete();
        }

        $property->delete();
        return redirect()->route('landlord.properties.index')->with('success', 'Property removed successfully.');
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
            cloudinary()->uploadApi()->destroy($photo->cloudinary_public_id);
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}