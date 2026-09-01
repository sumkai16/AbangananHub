<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyUnitController extends Controller
{
    private function authorizeProperty(Property $property): void
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403);
        }
    }

    public function index(Property $property)
    {
        $this->authorizeProperty($property);
        return redirect()->route('landlord.units.index', ['property' => $property->property_id]);
    }

    public function create(Property $property)
    {
        $this->authorizeProperty($property);
        $amenities = Amenity::forUnit()->orderBy('amenity_name')->get();
        $fromWizard = request()->query('from') === 'wizard';
        return view('landlord.units.create', compact('property', 'amenities', 'fromWizard'));
    }

    public function store(Request $request, Property $property)
    {
        $this->authorizeProperty($property);

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'unit_type'           => 'nullable|string|max:50',
            'floor'               => 'nullable|string|max:50',
            'bedrooms'            => 'nullable|integer|min:0|max:20',
            'bathrooms'           => 'nullable|integer|min:0|max:20',
            'floor_area_sqm'      => 'nullable|numeric|min:1|max:9999.99',
            'is_furnished'        => 'nullable|boolean',
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
                'photos' => 'Photo data is out of sync — please re-add your photos and try again.',
            ]);
        }

<<<<<<< HEAD
=======
        // Live capture is offered but no longer required — a landlord may
        // submit any mix of camera and uploaded photos, including all-upload.
        // The only remaining floor is "photos" => 'min:3' above, unrelated to
        // source. See ARCHITECTURE.md's "Unit Photos — Live Capture" section.

>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
        DB::transaction(function () use ($validated, $request, $property, $photos, $sources, $captions) {
            $unit = $property->units()->create([
                'unit_label'          => $validated['unit_label'],
                'unit_type'           => $validated['unit_type'] ?? null,
                'floor'               => $validated['floor'] ?? null,
                'bedrooms'            => $validated['bedrooms'] ?? null,
                'bathrooms'           => $validated['bathrooms'] ?? null,
                'floor_area_sqm'      => $validated['floor_area_sqm'] ?? null,
                'is_furnished'        => $request->has('is_furnished') ? $validated['is_furnished'] : null,
                'rental_fee'          => $validated['rental_fee'],
                'security_deposit'    => $validated['security_deposit'] ?? null,
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'description'         => $validated['description'] ?? null,
                'verification_status' => 'Pending',
            ]);

            if (!empty($validated['amenities'])) {
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
        });

        return redirect(
                $request->input('from') === 'wizard'
                    ? route('properties.wizard.units', $property)
                    : route('landlord.properties.units.index', $property)
            )
            ->with('success', 'Unit submitted for admin review.');
    }

    public function edit(Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $unit->load('media', 'amenities');
        $amenities = Amenity::forUnit()->orderBy('amenity_name')->get();
        $fromWizard = request()->query('from') === 'wizard';
        return view('landlord.units.edit', compact('property', 'unit', 'amenities', 'fromWizard'));
    }

    public function update(Request $request, Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'unit_type'           => 'nullable|string|max:50',
            'floor'               => 'nullable|string|max:50',
            'bedrooms'            => 'nullable|integer|min:0|max:20',
            'bathrooms'           => 'nullable|integer|min:0|max:20',
            'floor_area_sqm'      => 'nullable|numeric|min:1|max:9999.99',
            'is_furnished'        => 'nullable|boolean',
            'rental_fee'          => 'required|numeric|min:500|max:999999.99',
            // Optional here only — unlike store(), which still requires it on
            // every new unit. A unit that genuinely charges no deposit needs a
            // way to say so after creation; see SCHEMA.md.
            'security_deposit'    => 'nullable|numeric|min:0|max:999999.99',
            'occupancy_limit'     => 'required|integer|min:1|max:100',
            'availability_status' => 'required|in:Available,Reserved,Occupied,Maintenance',
            'description'         => 'nullable|string|max:300',
            'amenities'           => 'nullable|array',
            'amenities.*'         => 'exists:amenities,amenity_id',
            'photos'              => 'nullable|array|max:10',
            'photos.*'            => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'photo_sources'       => 'nullable|array',
            'photo_sources.*'     => 'in:camera,upload',
            'photo_captions'      => 'nullable|array',
            'photo_captions.*'    => 'nullable|string|max:150',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

        $activeReservation = $unit->activeReservation;
        if ($activeReservation && $validated['availability_status'] !== $unit->availability_status) {
            return back()
                ->withInput()
                ->withErrors(['availability_status' => 'This unit has an active reservation — manage its status through the reservation instead of editing it manually.']);
        }

        $photos = $request->file('photos', []);
        $sources = $request->input('photo_sources', []);
        $captions = $request->input('photo_captions', []);

        // Same alignment contract store() enforces — the capture UI keeps
        // photos[]/photo_sources[]/photo_captions[] in one shared iteration
        // order (see the partial's syncInput()/render()), so a mismatch here
        // means the client-side state genuinely desynced, not just "no camera
        // photos this time."
        if (count($sources) !== count($photos)) {
            throw ValidationException::withMessages([
                'photos' => 'Photo data is out of sync — please re-add your photos and try again.',
            ]);
        }

        $materialChanged = $unit->rental_fee != $validated['rental_fee']
            || $unit->occupancy_limit != $validated['occupancy_limit']
            || $request->hasFile('photos')
            || $request->hasFile('video');

        DB::transaction(function () use ($validated, $request, $property, $unit, $materialChanged, $photos, $sources, $captions) {
            $unit->update([
                'unit_label'          => $validated['unit_label'],
                'unit_type'           => $validated['unit_type'] ?? null,
                'floor'               => $validated['floor'] ?? null,
                'bedrooms'            => $validated['bedrooms'] ?? null,
                'bathrooms'           => $validated['bathrooms'] ?? null,
                'floor_area_sqm'      => $validated['floor_area_sqm'] ?? null,
                'is_furnished'        => $request->has('is_furnished') ? $validated['is_furnished'] : null,
                'rental_fee'          => $validated['rental_fee'],
                // array_key_exists, not ?? — the field is nullable now, and a
                // submit that omits the key entirely (rather than sending an
                // empty value) must leave the existing deposit untouched, not
                // silently null it (ARCHITECTURE.md documents this exact
                // shape once already, for this same edit page).
                'security_deposit'    => array_key_exists('security_deposit', $validated) ? $validated['security_deposit'] : $unit->security_deposit,
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'description'         => $validated['description'] ?? null,
                'verification_status' => $materialChanged ? 'Pending' : $unit->verification_status,
            ]);

            $unit->amenities()->sync($validated['amenities'] ?? []);

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
        });

        return redirect(
                $request->input('from') === 'wizard'
                    ? route('properties.wizard.units', $property)
                    : route('landlord.properties.units.index', $property)
            )
            ->with('success', $materialChanged ? 'Unit updated and resubmitted for review.' : 'Unit updated.');
    }

    public function destroy(Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $hasActiveReservation = \App\Models\Reservation::where('unit_id', $unit->unit_id)
            ->whereNotIn('rental_status', \App\Models\Reservation::TERMINAL_STATUSES)
            ->exists();

        if ($hasActiveReservation) {
            return back()->withErrors(['unit' => 'This unit has an active reservation and cannot be deleted.']);
        }

        foreach ($unit->media as $media) {
            if ($media->media_url) {
                try {
                    $publicId = pathinfo(parse_url($media->media_url, PHP_URL_PATH), PATHINFO_FILENAME);
                    cloudinary()->uploadApi()->destroy('abanganan/units/' . $publicId);
                } catch (\Exception $e) {
                    // Log but don't block deletion
                }
            }
            $media->delete();
        }

        $unit->amenities()->detach();
        $unit->delete();

        return redirect()
            ->route('landlord.properties.units.index', $property)
            ->with('success', 'Unit removed.');
    }

    public function destroyMedia(Property $property, PropertyUnit $unit, int $media)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $photo = $unit->media()->where('media_id', $media)->firstOrFail();

        // Live vs. upload no longer matters here — C1 (Sept 2026) dropped the
        // camera-specific floor along with the requirement itself. The only
        // remaining rule is the original one: a unit needs at least 3 photos
        // of any kind.
        $existingImages = $unit->media()->where('media_type', 'Image')->count();
        if ($existingImages <= 3) {
            return back()->withErrors(['photos' => 'A unit needs at least 3 photos — upload replacements before removing.']);
        }

        if ($photo->media_url) {
            try {
                $publicId = pathinfo(parse_url($photo->media_url, PHP_URL_PATH), PATHINFO_FILENAME);
                cloudinary()->uploadApi()->destroy('abanganan/units/' . $publicId);
            } catch (\Exception $e) {
                // Log but don't block deletion
            }
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}