<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): JsonResponse
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
            $property->landlord_id         = auth()->id();
            $property->title               = $validated['title'];
            $property->description         = $validated['description'];
            $property->property_type       = $validated['property_type'];
            $property->address             = $validated['address'];
            $property->latitude            = $validated['latitude'] ?? 10.3157;
            $property->longitude           = $validated['longitude'] ?? 123.8854;
            $property->verification_status = 'Pending';
            $property->save();

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

    public function update(Request $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($property);

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
                'title'         => $validated['title'],
                'description'   => $validated['description'],
                'property_type' => $validated['property_type'],
                'address'       => $validated['address'],
                'latitude'      => $validated['latitude'] ?? $property->latitude,
                'longitude'     => $validated['longitude'] ?? $property->longitude,
            ]);

            $detailsChanged = $property->isDirty();

            if ($detailsChanged || $photosAdded) {
                $property->verification_status = 'Pending';
            }

            $property->save();

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
