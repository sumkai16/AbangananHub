<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePropertyRequest;
use App\Http\Requests\Landlord\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Mobile equivalent of the shared (unnamespaced) PropertyController's
 * store/update/destroy/destroyMedia — those live outside the Landlord\
 * namespace on the web side too (Landlord\PropertyController is read-only:
 * index/show). Named PropertyWriteController here instead, since
 * Api\Landlord\PropertyController already owns index/show.
 */
class PropertyWriteController extends Controller
{
    private function authorizeProperty(Property $property): void
    {
        abort_if($property->landlord_id !== auth()->id(), 403);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $property = null;

        DB::transaction(function () use ($validated, $request, &$property) {
            $property = new Property();
            $property->landlord_id         = auth()->id();
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

        return response()->json(['data' => new PropertyResource($property->load('media'))], 201);
    }

    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($property);

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

        return response()->json(['data' => new PropertyResource($property->fresh('media'))]);
    }

    public function destroy(Property $property): JsonResponse
    {
        $this->authorizeProperty($property);

        foreach ($property->media as $media) {
            if ($media->cloudinary_public_id) {
                cloudinary()->uploadApi()->destroy($media->cloudinary_public_id);
            }
            $media->delete();
        }

        $property->delete();

        return response()->json(['message' => 'Property removed successfully.']);
    }

    public function destroyMedia(Property $property, int $media): JsonResponse
    {
        $this->authorizeProperty($property);

        if ($property->media()->count() <= 1) {
            return response()->json([
                'errors' => ['photos' => ['A listing needs at least one photo — upload a replacement before removing the last one.']],
            ], 422);
        }

        $photo = $property->media()->where('media_id', $media)->firstOrFail();

        if ($photo->cloudinary_public_id) {
            cloudinary()->uploadApi()->destroy($photo->cloudinary_public_id);
        }
        $photo->delete();

        return response()->json(['message' => 'Photo removed.']);
    }
}
