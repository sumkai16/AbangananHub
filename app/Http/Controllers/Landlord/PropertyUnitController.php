<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $units = $property->units()->with('media')->orderBy('unit_label')->get();
        return view('landlord.units.index', compact('property', 'units'));
    }

    public function create(Property $property)
    {
        $this->authorizeProperty($property);
        return view('landlord.units.create', compact('property'));
    }

    public function store(Request $request, Property $property)
    {
        $this->authorizeProperty($property);

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'rental_fee'          => 'required|numeric|min:500|max:999999.99',
            'occupancy_limit'     => 'required|integer|min:1|max:100',
            'availability_status' => 'required|in:Available,Reserved,Occupied',
            'photos'              => 'required|array|min:3|max:10',
            'photos.*'            => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'video'               => 'required|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

        DB::transaction(function () use ($validated, $request, $property) {
            $unit = $property->units()->create([
                'unit_label'          => $validated['unit_label'],
                'rental_fee'          => $validated['rental_fee'],
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'verification_status' => 'Pending',
            ]);

            foreach ($request->file('photos') as $photo) {
                $result = cloudinary()->upload($photo->getRealPath(), [
                    'folder'        => 'abanganan/units',
                    'resource_type' => 'image',
                ]);
                $unit->media()->create([
                    'property_id'          => $property->property_id,
                    'media_type'           => 'Image',
                    'media_url'            => $result->getSecurePath(),
                    'cloudinary_public_id' => $result->getPublicId(),
                ]);
            }

            $result = cloudinary()->upload($request->file('video')->getRealPath(), [
                'folder'        => 'abanganan/units/videos',
                'resource_type' => 'video',
            ]);
            $unit->media()->create([
                'property_id'          => $property->property_id,
                'media_type'           => 'Video',
                'media_url'            => $result->getSecurePath(),
                'cloudinary_public_id' => $result->getPublicId(),
            ]);
        });

        return redirect()
            ->route('landlord.properties.units.index', $property)
            ->with('success', 'Unit submitted for admin review.');
    }

    public function edit(Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $unit->load('media');
        return view('landlord.units.edit', compact('property', 'unit'));
    }

    public function update(Request $request, Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        $validated = $request->validate([
            'unit_label'          => 'required|string|max:100',
            'rental_fee'          => 'required|numeric|min:500|max:999999.99',
            'occupancy_limit'     => 'required|integer|min:1|max:100',
            'availability_status' => 'required|in:Available,Reserved,Occupied',
            'photos'              => 'nullable|array|max:10',
            'photos.*'            => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'video'               => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

        DB::transaction(function () use ($validated, $request, $property, $unit) {
            $unit->update([
                'unit_label'          => $validated['unit_label'],
                'rental_fee'          => $validated['rental_fee'],
                'occupancy_limit'     => $validated['occupancy_limit'],
                'availability_status' => $validated['availability_status'],
                'verification_status' => 'Pending',
            ]);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $result = cloudinary()->upload($photo->getRealPath(), [
                        'folder'        => 'abanganan/units',
                        'resource_type' => 'image',
                    ]);
                    $unit->media()->create([
                        'property_id'          => $property->property_id,
                        'media_type'           => 'Image',
                        'media_url'            => $result->getSecurePath(),
                        'cloudinary_public_id' => $result->getPublicId(),
                    ]);
                }
            }

            if ($request->hasFile('video')) {
                $result = cloudinary()->upload($request->file('video')->getRealPath(), [
                    'folder'        => 'abanganan/units/videos',
                    'resource_type' => 'video',
                ]);
                $unit->media()->create([
                    'property_id'          => $property->property_id,
                    'media_type'           => 'Video',
                    'media_url'            => $result->getSecurePath(),
                    'cloudinary_public_id' => $result->getPublicId(),
                ]);
            }
        });

        return redirect()
            ->route('landlord.properties.units.index', $property)
            ->with('success', 'Unit updated and resubmitted for review.');
    }

    public function destroy(Property $property, PropertyUnit $unit)
    {
        $this->authorizeProperty($property);

        if ($unit->property_id !== $property->property_id) {
            abort(404);
        }

        foreach ($unit->media as $media) {
            if ($media->cloudinary_public_id) {
                cloudinary()->destroy($media->cloudinary_public_id);
            }
            $media->delete();
        }

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

        $existingImages = $unit->media()->where('media_type', 'Image')->count();
        if ($existingImages <= 3) {
            return back()->withErrors(['photos' => 'A unit needs at least 3 photos — upload replacements before removing.']);
        }

        $photo = $unit->media()->where('media_id', $media)->firstOrFail();

        if ($photo->cloudinary_public_id) {
            cloudinary()->destroy($photo->cloudinary_public_id);
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}