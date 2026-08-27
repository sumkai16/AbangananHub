<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyUnitResource;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mobile equivalent of Landlord\PropertyUnitController's store/update/
 * destroy/destroyMedia. store() preserves the anti-fraud live-capture rule
 * (>=3 camera-sourced photos) unchanged — see ARCHITECTURE.md "Unit Photos —
 * Live Capture". Expo's camera can't be fed a gallery pick the way a browser
 * file input can, so mobile is if anything harder to forge than web, but the
 * server-side count check stays regardless: it is the one that's actually
 * trusted.
 */
class UnitWriteController extends Controller
{
    private function authorizeProperty(Property $property): void
    {
        abort_if($property->landlord_id !== auth()->id(), 403);
    }

    public function store(Request $request, Property $property): JsonResponse
    {
        $this->authorizeProperty($property);

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'unit_type'           => 'nullable|string|max:50',
            'floor'               => 'nullable|string|max:50',
            'floor_area_sqm'      => 'nullable|numeric|min:1|max:9999.99',
            'rental_fee'          => 'required|numeric|min:500|max:999999.99',
            // Every monthly rental carries a deposit — no longer optional.
            'security_deposit'    => 'required|numeric|min:0|max:999999.99',
            'occupancy_limit'     => 'required|integer|min:1|max:100',
            'availability_status' => 'required|in:Available,Reserved,Occupied,Maintenance',
            'description'         => 'nullable|string|max:300',
            'amenities'           => 'nullable|array',
            'amenities.*'         => 'exists:amenities,amenity_id',
            'photos'              => 'required|array|min:3|max:10',
            'photos.*'            => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'photo_sources'       => 'required|array',
            'photo_sources.*'     => 'in:camera,upload',
            'photo_captions'      => 'nullable|array',
            'photo_captions.*'    => 'nullable|string|max:150',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

        $photos = $request->file('photos', []);
        $sources = $request->input('photo_sources', []);
        $captions = $request->input('photo_captions', []);

        if (count($sources) !== count($photos)) {
            throw ValidationException::withMessages([
                'photos' => ['Photo data is out of sync — please re-add your photos and try again.'],
            ]);
        }

        $liveCount = collect($sources)->filter(fn ($s) => $s === 'camera')->count();
        if ($liveCount < 3) {
            throw ValidationException::withMessages([
                'photos' => ['At least 3 live (camera-captured) photos are required. Uploaded photos count as extras.'],
            ]);
        }

        $unit = DB::transaction(function () use ($validated, $property, $photos, $sources, $captions, $request) {
            $unit = $property->units()->create([
                'unit_label'          => $validated['unit_label'],
                'unit_type'           => $validated['unit_type'] ?? null,
                'floor'               => $validated['floor'] ?? null,
                'floor_area_sqm'      => $validated['floor_area_sqm'] ?? null,
                'rental_fee'          => $validated['rental_fee'],
                'security_deposit'    => $validated['security_deposit'] ?? null,
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'description'         => $validated['description'] ?? null,
                'verification_status' => 'Pending',
            ]);

            if (! empty($validated['amenities'])) {
                $unit->amenities()->attach($validated['amenities']);
            }

            foreach ($photos as $i => $photo) {
                $result = cloudinary()->uploadApi()->upload($photo->getRealPath(), [
                    'folder'        => 'abanganan/units',
                    'resource_type' => 'image',
                ]);
                $unit->media()->create([
                    'media_type' => 'Image',
                    'media_url'  => $result['secure_url'],
                    'source'     => ($sources[$i] ?? 'upload') === 'camera' ? 'camera' : 'upload',
                    'caption'    => $captions[$i] ?? null,
                ]);
            }

            if ($request->hasFile('video')) {
                $result = cloudinary()->uploadApi()->upload($request->file('video')->getRealPath(), [
                    'folder'        => 'abanganan/units/videos',
                    'resource_type' => 'video',
                ]);
                $unit->media()->create([
                    'media_type' => 'Video',
                    'media_url'  => $result['secure_url'],
                    'source'     => 'upload',
                ]);
            }

            return $unit;
        });

        return response()->json(['data' => new PropertyUnitResource($unit->load('media', 'amenities'))], 201);
    }

    public function update(Request $request, Property $property, PropertyUnit $unit): JsonResponse
    {
        $this->authorizeProperty($property);
        abort_if($unit->property_id !== $property->property_id, 404);

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'unit_type'           => 'nullable|string|max:50',
            'floor'               => 'nullable|string|max:50',
            'floor_area_sqm'      => 'nullable|numeric|min:1|max:9999.99',
            'rental_fee'          => 'required|numeric|min:500|max:999999.99',
            // Every monthly rental carries a deposit — no longer optional.
            'security_deposit'    => 'required|numeric|min:0|max:999999.99',
            'occupancy_limit'     => 'required|integer|min:1|max:100',
            'availability_status' => 'required|in:Available,Reserved,Occupied,Maintenance',
            'description'         => 'nullable|string|max:300',
            'amenities'           => 'nullable|array',
            'amenities.*'         => 'exists:amenities,amenity_id',
            'photos'              => 'nullable|array|max:10',
            'photos.*'            => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

        $activeReservation = $unit->activeReservation;
        if ($activeReservation && $validated['availability_status'] !== $unit->availability_status) {
            throw ValidationException::withMessages([
                'availability_status' => ['This unit has an active reservation — manage its status through the reservation instead of editing it manually.'],
            ]);
        }

        $materialChanged = $unit->rental_fee != $validated['rental_fee']
            || $unit->occupancy_limit != $validated['occupancy_limit']
            || $request->hasFile('photos')
            || $request->hasFile('video');

        DB::transaction(function () use ($validated, $request, $unit, $materialChanged) {
            $unit->update([
                'unit_label'          => $validated['unit_label'],
                'unit_type'           => $validated['unit_type'] ?? null,
                'floor'               => $validated['floor'] ?? null,
                'floor_area_sqm'      => $validated['floor_area_sqm'] ?? null,
                'rental_fee'          => $validated['rental_fee'],
                'security_deposit'    => $validated['security_deposit'] ?? null,
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'description'         => $validated['description'] ?? null,
                'verification_status' => $materialChanged ? 'Pending' : $unit->verification_status,
            ]);

            $unit->amenities()->sync($validated['amenities'] ?? []);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $result = cloudinary()->uploadApi()->upload($photo->getRealPath(), [
                        'folder'        => 'abanganan/units',
                        'resource_type' => 'image',
                    ]);
                    $unit->media()->create([
                        'media_type' => 'Image',
                        'media_url'  => $result['secure_url'],
                        'source'     => 'upload',
                    ]);
                }
            }

            if ($request->hasFile('video')) {
                $result = cloudinary()->uploadApi()->upload($request->file('video')->getRealPath(), [
                    'folder'        => 'abanganan/units/videos',
                    'resource_type' => 'video',
                ]);
                $unit->media()->create([
                    'media_type' => 'Video',
                    'media_url'  => $result['secure_url'],
                    'source'     => 'upload',
                ]);
            }
        });

        return response()->json(['data' => new PropertyUnitResource($unit->fresh(['media', 'amenities']))]);
    }

    public function destroy(Property $property, PropertyUnit $unit): JsonResponse
    {
        $this->authorizeProperty($property);
        abort_if($unit->property_id !== $property->property_id, 404);

        $hasActiveReservation = Reservation::where('unit_id', $unit->unit_id)
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->exists();

        if ($hasActiveReservation) {
            throw ValidationException::withMessages(['unit' => ['This unit has an active reservation and cannot be deleted.']]);
        }

        foreach ($unit->media as $media) {
            if ($media->media_url) {
                try {
                    $publicId = pathinfo(parse_url($media->media_url, PHP_URL_PATH), PATHINFO_FILENAME);
                    cloudinary()->uploadApi()->destroy('abanganan/units/'.$publicId);
                } catch (\Exception $e) {
                    // Log but don't block deletion — same as web.
                }
            }
            $media->delete();
        }

        $unit->amenities()->detach();
        $unit->delete();

        return response()->json(['message' => 'Unit removed.']);
    }

    public function destroyMedia(Property $property, PropertyUnit $unit, int $media): JsonResponse
    {
        $this->authorizeProperty($property);
        abort_if($unit->property_id !== $property->property_id, 404);

        $existingImages = $unit->media()->where('media_type', 'Image')->count();
        if ($existingImages <= 3) {
            return response()->json([
                'errors' => ['photos' => ['A unit needs at least 3 photos — upload replacements before removing.']],
            ], 422);
        }

        $photo = $unit->media()->where('media_id', $media)->firstOrFail();

        if ($photo->media_url) {
            try {
                $publicId = pathinfo(parse_url($photo->media_url, PHP_URL_PATH), PATHINFO_FILENAME);
                cloudinary()->uploadApi()->destroy('abanganan/units/'.$publicId);
            } catch (\Exception $e) {
                // Log but don't block deletion — same as web.
            }
        }
        $photo->delete();

        return response()->json(['message' => 'Photo removed.']);
    }
}
