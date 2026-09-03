<?php

namespace App\Http\Controllers;

use App\Http\Requests\Landlord\UpdatePropertyRequest;
use App\Models\Amenity;
use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Review;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $properties = Property::with([
                'media', 'landlord', 'amenities', 'units',
                'documents:document_id,property_id,document_type,status,expiry_date',
            ])
            ->browsable()
            ->browseFilters([
                'location'  => $request->query('location'),
                'type'      => $request->query('type'),
                'price_max' => $request->query('price_max'),
                'verified'  => $request->boolean('verified'),
                'sort'      => $request->query('sort'),
            ])
            ->paginate(12)
            ->withQueryString();

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

        return view('properties.index', compact('properties', 'favoritedIds', 'mapProperties'));
    }

    public function show(Property $property)
    {
        if (! $property->isLive()) {
            abort(404);
        }

        // documents: status/expiry only for the badge — file_path never reaches a renter's browser.
        $property->load([
            'media', 'amenities', 'landlord.rentalBusiness', 'units.amenities', 'units.media',
            'documents:document_id,property_id,document_type,status,expiry_date',
        ]);

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

        // "Nearby" is barangay/city matching, not a geo radius — this platform
        // is Cebu-scoped and address text match is sufficient (ARCHITECTURE.md).
        // browsable() (not just approved()) so a Draft/Unpublished/Suspended
        // listing can never leak in through this block. units eager-loaded:
        // getMinRentalFeeAttribute() reads it unguarded and preventLazyLoading
        // throws in dev otherwise.
        $nearbyProperties = Property::browsable()
            ->where('property_id', '!=', $property->property_id)
            ->where('city_municipality', $property->city_municipality)
            ->with(['media', 'units'])
            ->latest('created_at')
            ->take(12)
            ->get()
            ->sortByDesc(fn ($p) => $p->barangay === $property->barangay)
            ->take(6)
            ->values();

        return view('properties.show', compact('property', 'reviews', 'avgRating', 'canReview', 'isFavorited', 'nearbyProperties'));
    }

    public function edit(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized access.');
        }

        // A Draft isn't submitted yet — it's edited through the wizard it
        // was created in, not this single-page form (which assumes a
        // property that already has everything the form doesn't collect,
        // like units and documents).
        if ($property->isDraft()) {
            return redirect()->route('properties.wizard.resume', $property);
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
                'title'                          => $validated['title'],
                'description'                    => $validated['description'],
                'property_type'                  => $validated['property_type'],
                'living_arrangement'             => $validated['living_arrangement'] ?? null,
                'water_included'                 => $request->boolean('water_included'),
                'electricity_included'           => $request->boolean('electricity_included'),
                'internet_included'              => $request->boolean('internet_included'),
                'association_fees_included'      => $request->boolean('association_fees_included'),
                'utilities_separately_metered'   => $request->boolean('utilities_separately_metered'),
                'address'                        => $validated['address'],
                'city_municipality'              => $validated['city_municipality'],
                'barangay'                       => $validated['barangay'] ?? null,
                'latitude'                       => $validated['latitude'],
                'longitude'                      => $validated['longitude'],
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

    /**
     * Landlord-controlled visibility toggle. Deliberately narrow: only
     * Published <-> Unpublished. A Suspended listing was taken down by
     * admin moderation (Admin\ReportController) and must stay hidden until
     * an admin lifts it (Admin\ListingController::unsuspend) — allowing this
     * endpoint to publish out of Suspended would make that moderation action
     * meaningless. Draft has no producer yet (the property wizard will set
     * it), so it isn't reachable here either.
     */
    public function publish(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        abort_if($property->publication_status !== 'Unpublished', 409, 'This listing cannot be published from its current state.');

        $property->update(['publication_status' => 'Published']);

        return back()->with('success', "'{$property->title}' is visible to tenants again.");
    }

    public function unpublish(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        abort_if($property->publication_status !== 'Published', 409, 'This listing cannot be unpublished from its current state.');

        $property->update(['publication_status' => 'Unpublished']);

        return back()->with('success', "'{$property->title}' has been hidden from tenants. You can publish it again anytime.");
    }

    public function destroy(Property $property)
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $wasDraft = $property->isDraft();

        foreach ($property->media as $media) {
            if ($media->cloudinary_public_id) {
                cloudinary()->uploadApi()->destroy($media->cloudinary_public_id);
            }
            $media->delete();
        }

        $property->delete();

        return redirect()->route('landlord.properties.index')
            ->with('success', $wasDraft ? 'Draft deleted.' : 'Property removed successfully.');
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