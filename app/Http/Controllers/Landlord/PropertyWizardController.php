<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
use App\Rules\WithinCebu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Multi-step property creation wizard: Info -> Location -> Amenities ->
 * Documents -> Units -> Review. See plans/property-creation-wizard.md.
 *
 * No property row exists until Location is saved (properties.address/
 * city_municipality/latitude/longitude are all NOT NULL, so there is
 * nothing valid to insert after Info alone) — Info's fields are stashed in
 * the session for that one hop. From Location onward every step operates
 * on a real Property row with publication_status = 'Draft'.
 */
class PropertyWizardController extends Controller
{
    private const REQUIRED_DOCUMENT_TYPES = ['Proof of Ownership', 'Tax Declaration', 'Business Permit'];

    private function authorizeProperty(Property $property): void
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403);
        }
    }

    /**
     * The wizard only ever edits a Draft. Once submitted, changes go
     * through the standalone edit page (PropertyController::edit/update)
     * instead — re-entering the wizard on a live listing would let a
     * landlord silently drop it back into an unlisted state.
     */
    private function guardIsDraft(Property $property): void
    {
        abort_if(! $property->isDraft(), 409, 'This listing has already been submitted for review — edit it from its property page instead.');
    }

    private function targetUnitsSessionKey(Property $property): string
    {
        return "property_wizard.target_units.{$property->property_id}";
    }

    // ─── Step 1: Property Information ────────────────────────

    public function createInfo()
    {
        $old = session('property_wizard.info', []);

        return view('landlord.properties.wizard.info', ['property' => null, 'formValues' => $old]);
    }

    public function storeInfo(Request $request)
    {
        $validated = $request->validate([
            'title'            => ['required', 'string', 'min:10', 'max:150'],
            'property_type'    => ['required', 'in:Bedspace,Room,Apartment,House'],
            'description'      => ['required', 'string', 'min:20', 'max:3000'],
            'number_of_units'  => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        session(['property_wizard.info' => $validated]);

        return redirect()->route('properties.wizard.location.create');
    }

    public function editInfo(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $formValues = [
            'title'           => $property->title,
            'property_type'   => $property->property_type,
            'description'     => $property->description,
            'number_of_units' => session($this->targetUnitsSessionKey($property)),
        ];

        $property->load(['amenities', 'documents', 'units.media']);
        $checklist = $this->buildChecklist($property);

        return view('landlord.properties.wizard.info', compact('property', 'formValues', 'checklist'));
    }

    public function updateInfo(Request $request, Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $validated = $request->validate([
            'title'            => ['required', 'string', 'min:10', 'max:150'],
            'property_type'    => ['required', 'in:Bedspace,Room,Apartment,House'],
            'description'      => ['required', 'string', 'min:20', 'max:3000'],
            'number_of_units'  => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $property->update([
            'title'         => $validated['title'],
            'property_type' => $validated['property_type'],
            'description'   => $validated['description'],
        ]);

        session([$this->targetUnitsSessionKey($property) => $validated['number_of_units']]);

        return redirect()->route('properties.wizard.location.edit', $property)->with('success', 'Property information updated.');
    }

    // ─── Step 2: Location (creates the Draft row) ────────────

    public function createLocation()
    {
        if (! session()->has('property_wizard.info')) {
            return redirect()->route('properties.create')->with('error', 'Start with property information first.');
        }

        return view('landlord.properties.wizard.location', ['property' => null]);
    }

    private function locationRules(int $existingPhotoCount = 0, bool $photosRequired = true): array
    {
        return [
            'address'           => ['required', 'string', 'min:10', 'max:255'],
            'city_municipality' => ['required', 'string', Rule::in(config('cebu.lgus'))],
            'barangay'          => ['nullable', 'string', 'max:100'],
            'latitude'          => ['required', 'numeric', 'between:-90,90', new WithinCebu(request()->input('longitude'))],
            'longitude'         => ['required', 'numeric', 'between:-180,180'],
            'photos'            => $photosRequired
                ? ['required', 'array', 'min:1', 'max:10']
                : ['nullable', 'array', function ($attribute, $value, $fail) use ($existingPhotoCount) {
                    if ($existingPhotoCount + count($value) > 10) {
                        $fail('A property can have at most 10 photos total. Remove some before adding more.');
                    }
                }],
            'photos.*'          => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }

    public function storeLocation(Request $request)
    {
        $info = session('property_wizard.info');

        if (! $info) {
            return redirect()->route('properties.create');
        }

        $validated = $request->validate(
            $this->locationRules(),
            ['city_municipality.in' => 'Choose a city or municipality within Cebu.']
        );

        $property = null;

        DB::transaction(function () use ($info, $validated, $request, &$property) {
            $property = new Property();
            $property->landlord_id         = Auth::user()->user_id;
            $property->title               = $info['title'];
            $property->description         = $info['description'];
            $property->property_type       = $info['property_type'];
            $property->address             = $validated['address'];
            $property->city_municipality   = $validated['city_municipality'];
            $property->barangay            = $validated['barangay'] ?? null;
            $property->latitude            = $validated['latitude'];
            $property->longitude           = $validated['longitude'];
            $property->verification_status = 'Pending';
            $property->publication_status  = 'Draft';
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

        session()->forget('property_wizard.info');
        session([$this->targetUnitsSessionKey($property) => $info['number_of_units']]);

        return redirect()->route('properties.wizard.amenities', $property)
            ->with('success', 'Property created. Continue setting it up below.');
    }

    public function editLocation(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $property->load(['media', 'amenities', 'documents', 'units.media']);
        $checklist = $this->buildChecklist($property);

        return view('landlord.properties.wizard.location', compact('property', 'checklist'));
    }

    public function updateLocation(Request $request, Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $existingPhotoCount = $property->media()->count();

        $validated = $request->validate(
            $this->locationRules($existingPhotoCount, photosRequired: $existingPhotoCount === 0),
            ['city_municipality.in' => 'Choose a city or municipality within Cebu.']
        );

        DB::transaction(function () use ($validated, $request, $property) {
            $property->update([
                'address'           => $validated['address'],
                'city_municipality' => $validated['city_municipality'],
                'barangay'          => $validated['barangay'] ?? null,
                'latitude'          => $validated['latitude'],
                'longitude'         => $validated['longitude'],
            ]);

            if ($request->hasFile('photos')) {
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

        return redirect()->route('properties.wizard.amenities', $property)->with('success', 'Location updated.');
    }

    // ─── Step 3: Property Amenities ──────────────────────────

    public function amenities(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $property->load(['amenities', 'documents', 'units.media']);
        $amenities = Amenity::forProperty()->orderBy('category')->orderBy('amenity_name')->get();
        $checklist = $this->buildChecklist($property);

        return view('landlord.properties.wizard.amenities', compact('property', 'amenities', 'checklist'));
    }

    public function storeAmenities(Request $request, Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $validated = $request->validate([
            'amenities'   => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,amenity_id'],
        ]);

        $property->amenities()->sync($validated['amenities'] ?? []);

        return redirect()->route('properties.wizard.documents', $property)->with('success', 'Amenities saved.');
    }

    // ─── Step 4: Property Documents ──────────────────────────
    // Uploads themselves post to the existing landlord.properties.documents.*
    // routes (Landlord\PropertyDocumentController), which return back() —
    // since that "back" is this wizard page while inside the wizard, no
    // separate store action is needed here.

    public function documents(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $documents = $property->documents()->latest()->get();

        $property->load(['amenities', 'documents', 'units.media']);
        $checklist = $this->buildChecklist($property);

        return view('landlord.properties.wizard.documents', compact('property', 'documents', 'checklist'));
    }

    // ─── Step 5: Units ────────────────────────────────────────
    // Adding/editing a unit reuses the existing landlord.properties.units.*
    // resource (Landlord\PropertyUnitController) — see the `from=wizard`
    // handling there, which redirects back into this step instead of the
    // standalone units index.

    public function units(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $property->load(['units' => fn ($q) => $q->orderBy('unit_label'), 'units.media', 'amenities', 'documents']);
        $checklist = $this->buildChecklist($property);

        // Documents' "Save & Continue" is plain navigation, not a form
        // submit — nothing upstream stops a landlord reaching this step
        // (or its URL directly) with a required document still missing.
        // Amenities isn't guarded the same way; it's genuinely optional
        // (see its own "Optional, but tenants filter on these" copy).
        if (! $checklist['documents']['complete']) {
            return redirect()->route('properties.wizard.documents', $property)
                ->with('warning', 'Please add the required documents before continuing to Units.');
        }

        $targetUnits = session($this->targetUnitsSessionKey($property));

        return view('landlord.properties.wizard.units', compact('property', 'targetUnits', 'checklist'));
    }

    // ─── Step 6: Review & Submit ──────────────────────────────

    private function buildChecklist(Property $property): array
    {
        $submittedDocTypes = $property->documents->reject(fn ($d) => $d->isRequested())->pluck('document_type');
        $missingDocs = collect(self::REQUIRED_DOCUMENT_TYPES)->diff($submittedDocTypes);
        $unitsWithoutPhoto = $property->units->filter(fn ($u) => $u->media->where('media_type', 'Image')->isEmpty());

        return [
            'info' => [
                'label'    => 'Property Information',
                'complete' => true,
                'edit'     => route('properties.wizard.info.edit', $property),
            ],
            'location' => [
                'label'    => 'Location',
                'complete' => true,
                'edit'     => route('properties.wizard.location.edit', $property),
            ],
            'amenities' => [
                'label'    => 'Property Amenities',
                'complete' => $property->amenities->isNotEmpty(),
                'edit'     => route('properties.wizard.amenities', $property),
            ],
            'documents' => [
                'label'    => 'Property Documents',
                'complete' => $missingDocs->isEmpty(),
                'detail'   => $missingDocs->isNotEmpty() ? 'Missing: ' . $missingDocs->implode(', ') : null,
                'edit'     => route('properties.wizard.documents', $property),
            ],
            'units' => [
                'label'    => 'Units',
                'complete' => $property->units->isNotEmpty(),
                'detail'   => $property->units->count() . ' unit' . ($property->units->count() === 1 ? '' : 's') . ' added',
                'edit'     => route('properties.wizard.units', $property),
            ],
            'unit_photos' => [
                'label'    => 'Unit Amenities & Photos',
                'complete' => $property->units->isNotEmpty() && $unitsWithoutPhoto->isEmpty(),
                'detail'   => $unitsWithoutPhoto->isNotEmpty() ? $unitsWithoutPhoto->count() . ' unit(s) missing a photo' : null,
                'edit'     => route('properties.wizard.units', $property),
            ],
        ];
    }

    public function review(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $property->load(['media', 'amenities', 'documents', 'units.media', 'units.amenities']);

        $checklist = $this->buildChecklist($property);

        // Same reasoning as the guard in units(): "Continue to Review" is a
        // plain link with nothing stopping a landlord from reaching this
        // page (or its URL) with zero units, even though the Units step's
        // own copy says at least one is required.
        if (! $checklist['units']['complete']) {
            return redirect()->route('properties.wizard.units', $property)
                ->with('warning', 'Add at least one unit before continuing to review.');
        }

        $canSubmit = collect($checklist)->every(fn ($item) => $item['complete']);
        $targetUnits = session($this->targetUnitsSessionKey($property));

        return view('landlord.properties.wizard.review', compact('property', 'checklist', 'canSubmit', 'targetUnits'));
    }

    public function submit(Property $property)
    {
        $this->authorizeProperty($property);
        $this->guardIsDraft($property);

        $property->load(['amenities', 'documents', 'units.media']);

        $checklist = $this->buildChecklist($property);
        $incomplete = collect($checklist)->reject(fn ($item) => $item['complete']);

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages([
                'wizard' => 'Finish the remaining sections before submitting: ' . $incomplete->pluck('label')->implode(', ') . '.',
            ]);
        }

        $property->update(['publication_status' => 'Published']);
        session()->forget($this->targetUnitsSessionKey($property));

        return redirect()->route('landlord.properties.show', $property)
            ->with('success', "'{$property->title}' has been submitted for review.");
    }

    // ─── Resume — deep-link into a Draft at its furthest step ──

    public function resume(Property $property)
    {
        $this->authorizeProperty($property);

        if (! $property->isDraft()) {
            return redirect()->route('landlord.properties.show', $property);
        }

        return match ($property->resumeWizardStep()) {
            'amenities' => redirect()->route('properties.wizard.amenities', $property),
            'documents' => redirect()->route('properties.wizard.documents', $property),
            'units'     => redirect()->route('properties.wizard.units', $property),
            default     => redirect()->route('properties.wizard.review', $property),
        };
    }
}
