@extends('layouts.landlord')

@section('content')
    @php
        // Field styling shared with the add-property wizard, so every form reads the same.
        $label = 'block text-[13px] font-semibold text-[#060D26] mb-1.5';
        $input = 'w-full h-11 px-3.5 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200';
        $select = 'w-full h-11 px-3.5 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26]';
        $error = 'text-[12px] text-[#DC2626] mt-1.5';
        $ico = fn (string $d) => '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'" /></svg>';
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-sm text-[#5B6A8E] mb-3">
            <a href="{{ route('landlord.properties.index') }}" class="hover:text-[#060D26] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            <a href="{{ route('landlord.properties.show', $property) }}" class="hover:text-[#060D26] transition-colors duration-200 truncate max-w-[16rem]">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            <a href="{{ route('landlord.properties.units.index', $property) }}" class="hover:text-[#060D26] transition-colors duration-200">Units</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            <span class="text-[#060D26] font-medium">Edit {{ $unit->unit_label }}</span>
        </nav>

        {{-- Header --}}
        <x-page-header title="Edit unit">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                </svg>
            </x-slot:icon>
            <x-slot:badge>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold uppercase
                    @if($unit->verification_status === 'Approved') bg-[#22C55E]/[0.07] text-[#15803D] border border-[#22C55E]/20
                    @elseif($unit->verification_status === 'Pending') bg-[#FBBF24]/[0.10] text-[#B45309] border border-[#FBBF24]/25
                    @else bg-[#EF4444]/[0.07] text-[#DC2626] border border-[#EF4444]/20 @endif">
                    {{ $unit->verification_status }}
                </span>
            </x-slot:badge>
        </x-page-header>

        {{-- Flash / errors --}}
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5" role="alert">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $existingPhotos = $unit->media->where('media_type', 'Image');
            $existingVideo = $unit->media->where('media_type', 'Video')->first();
            $previewPhoto = $existingPhotos->first()?->media_url;
            $statusMeta = [
                'Available' => ['dot' => '#22C55E', 'text' => 'Vacant and ready'],
                'Reserved' => ['dot' => '#FBBF24', 'text' => 'On hold for a tenant'],
                'Occupied' => ['dot' => '#EF4444', 'text' => 'Currently rented'],
                'Maintenance' => ['dot' => '#5B6A8E', 'text' => 'Temporarily unavailable'],
            ];
            $amenityNameMap = $amenities->pluck('amenity_name', 'amenity_id')->toArray();
            $exclusiveAmenityIds = $amenities->whereIn('amenity_name', \App\Models\Amenity::EXCLUSIVE_UNIT_AMENITIES)->pluck('amenity_id')->map(fn ($id) => (string) $id)->values();
            $preselectedAmenities = collect(old('amenities', $unit->amenities->pluck('amenity_id')->all()))
                ->map(fn ($id) => (string) $id)->all();
        @endphp

        {{-- Per-photo delete forms — deliberately OUTSIDE the main edit form
             below, never nested inside it. Nesting a <form> inside another is
             invalid HTML; browsers still create it as its own DOM node, but
             when the OUTER form submits, its "constructing the form data set"
             step still walks in and picks up the inner form's own hidden
             _method/_token/photos[] fields too — so a saved edit's multipart
             body ended up carrying two _method fields (PUT, then a trailing
             DELETE from whichever photo's delete form rendered last). PHP
             keeps the LAST duplicate key, so every "Save Changes" was
             secretly a DELETE to the identical unit URL (PUT and DELETE share
             one route path) — silently deleting the unit outright, unless it
             had an active reservation to refuse it. Reproduced and confirmed
             live (Sept 2026) before this fix; see plans/analyst-checklist-part-b.md Part C2. --}}
        @foreach($existingPhotos as $photo)
            <form id="delete-photo-{{ $photo->media_id }}" method="POST"
                  action="{{ route('landlord.properties.units.media.destroy', [$property, $unit, $photo->media_id]) }}"
                  data-confirm="Remove this photo?"
                  data-confirm-type="error"
                  data-confirm-message="This photo will be permanently removed from the unit."
                  data-confirm-button="Remove">
                @csrf @method('DELETE')
            </form>
        @endforeach

        <form method="POST" action="{{ route('landlord.properties.units.update', [$property, $unit]) }}"
            enctype="multipart/form-data" x-on:submit="submitting = true"
            x-data="{
                unitLabel: @js(old('unit_label', $unit->unit_label)),
                capacity: @js(old('occupancy_limit', $unit->occupancy_limit)),
                floorArea: @js(old('floor_area_sqm', $unit->floor_area_sqm)),
                rentalFee: @js(old('rental_fee', $unit->rental_fee)),
                status: @js(old('availability_status', $unit->availability_status)),
                amenities: @js($preselectedAmenities),
                exclusiveAmenities: @js($exclusiveAmenityIds),
                pickExclusive(id) {
                    id = String(id);
                    if (!this.amenities.includes(id) || !this.exclusiveAmenities.includes(id)) return;
                    this.amenities = this.amenities.filter(a => a === id || !this.exclusiveAmenities.includes(String(a)));
                },
                amenityNames: @js($amenityNameMap),
                statusMeta: @js($statusMeta),
                floor: @js($unit->floor ?? ''),
                submitting: false,
                peso(v) { return (v === '' || v === null || isNaN(v)) ? null : '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
            }">
            @csrf
            @method('PUT')
            @if($fromWizard ?? false)
                <input type="hidden" name="from" value="wizard">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">

                {{-- ── Left column: form fields ──────────────────────────── --}}
                <div class="lg:col-span-7 space-y-6">

                    <x-form-section title="Unit details" description="How this unit is named and described to tenants.">
                        <x-slot:icon>{!! $ico('M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z') !!}</x-slot:icon>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="unit_label" class="{{ $label }}">Unit name / number <span class="text-[#EF4444]">*</span></label>
                                    <input type="text" id="unit_label" name="unit_label" x-model="unitLabel" required maxlength="100"
                                        placeholder="e.g. Room 101, Bed A, Unit 201" class="{{ $input }}">
                                    @error('unit_label')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="occupancy_limit" class="{{ $label }}">Capacity <span class="text-[#EF4444]">*</span></label>
                                    <input type="number" id="occupancy_limit" name="occupancy_limit" x-model="capacity" required min="1" max="100"
                                        placeholder="Maximum number of occupants" class="{{ $input }}">
                                    @error('occupancy_limit')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="floor" class="{{ $label }}">Floor</label>
                                    <input type="text" id="floor" name="floor" x-model="floor" maxlength="50" placeholder="e.g. 1st Floor" class="{{ $input }}">
                                    @error('floor')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="floor_area_sqm" class="{{ $label }}">Floor area (sqm)</label>
                                    <input type="number" id="floor_area_sqm" name="floor_area_sqm" x-model="floorArea" min="1" max="9999.99" step="0.01"
                                        placeholder="e.g. 24" class="{{ $input }}">
                                    @error('floor_area_sqm')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <label for="description" class="{{ $label }}">Description <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                                <textarea id="description" name="description" rows="3" maxlength="300"
                                    placeholder="Add any note or description about this unit..."
                                    class="w-full px-3.5 py-3 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200 resize-none">{{ old('description', $unit->description) }}</textarea>
                                @error('description')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Room features" description="Leave a field on “Not specified” if it doesn't apply.">
                        <x-slot:icon>{!! $ico('M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75') !!}</x-slot:icon>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="bedrooms" class="{{ $label }}">Bedrooms</label>
                                    <input type="number" id="bedrooms" name="bedrooms" value="{{ old('bedrooms', $unit->bedrooms) }}" min="0" max="20"
                                        placeholder="e.g. 2" class="{{ $input }}">
                                    @error('bedrooms')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="bathrooms" class="{{ $label }}">Bathrooms</label>
                                    <input type="number" id="bathrooms" name="bathrooms" value="{{ old('bathrooms', $unit->bathrooms) }}" min="0" max="20"
                                        placeholder="e.g. 1" class="{{ $input }}">
                                    @error('bathrooms')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">Furnishing</label>
                                    <x-styled-select name="furnishing_status"
                                        :options="['Furnished' => 'Furnished', 'Semi-furnished' => 'Semi-furnished', 'Unfurnished' => 'Unfurnished']"
                                        :selected="old('furnishing_status', $unit->furnishing_status ?? '')" placeholder="Not specified" class="{{ $select }}" />
                                    @error('furnishing_status')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Pricing & availability" description="What tenants pay, and whether the unit can be booked.">
                        <x-slot:icon>{!! $ico('M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z') !!}</x-slot:icon>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="rental_fee" class="{{ $label }}">Monthly rent (₱) <span class="text-[#EF4444]">*</span></label>
                                    <input type="number" id="rental_fee" name="rental_fee" x-model="rentalFee" required min="500" max="999999.99" step="0.01"
                                        placeholder="e.g. 3500" class="{{ $input }} font-semibold">
                                    @error('rental_fee')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="security_deposit" class="{{ $label }}">Security deposit (₱) <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                                    <input type="number" id="security_deposit" name="security_deposit" value="{{ old('security_deposit', $unit->security_deposit) }}" min="0"
                                        max="999999.99" step="0.01" placeholder="Leave blank if no deposit" class="{{ $input }}">
                                    @error('security_deposit')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <p class="{{ $label }}">Status <span class="text-[#EF4444]">*</span></p>
                                @php
                                    $statusOptions = [
                                        'Available' => ['label' => 'Available', 'desc' => 'Unit is vacant and ready', 'active' => 'border-[#22C55E]/35 bg-[#22C55E]/[0.07]'],
                                        'Reserved' => ['label' => 'Reserved', 'desc' => 'On hold for a tenant', 'active' => 'border-[#FBBF24]/45 bg-[#FBBF24]/[0.10]'],
                                        'Occupied' => ['label' => 'Occupied', 'desc' => 'Currently rented', 'active' => 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]'],
                                        'Maintenance' => ['label' => 'Maintenance', 'desc' => 'Temporarily unavailable', 'active' => 'border-[#5B6A8E]/35 bg-[#5B6A8E]/[0.08]'],
                                    ];
                                    $inactiveClass = 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]';
                                @endphp
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    @foreach($statusOptions as $value => $opt)
                                        <label class="relative cursor-pointer rounded-xl border px-3.5 py-3 transition-colors duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#FF8A66]"
                                            :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                            <input type="radio" name="availability_status" value="{{ $value }}" x-model="status" class="sr-only">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ ['Available' => 'bg-[#22C55E]', 'Reserved' => 'bg-[#FBBF24]', 'Occupied' => 'bg-[#EF4444]', 'Maintenance' => 'bg-[#5B6A8E]'][$value] }}"></span>
                                                <p class="text-[13px] font-semibold text-[#060D26]">{{ $opt['label'] }}</p>
                                            </div>
                                            <p class="text-[11.5px] text-[#5B6A8E] leading-snug">{{ $opt['desc'] }}</p>
                                        </label>
                                    @endforeach
                                </div>
                                @error('availability_status')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Unit amenities" description="What comes with this specific unit.">
                        <x-slot:icon>{!! $ico('M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z') !!}</x-slot:icon>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
                            @foreach($amenities as $amenity)
                                <label class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 cursor-pointer transition-colors duration-200"
                                    :class="amenities.includes('{{ $amenity->amenity_id }}') ? 'border-[#FF8A66] bg-[#ECEEF6]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]'">
                                    <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" x-model="amenities" @change="pickExclusive('{{ $amenity->amenity_id }}')"
                                        class="w-4 h-4 rounded border-[#5B6A8E]/40 text-[#B35A3D] focus:ring-[#FF8A66]/30">
                                    <span class="text-[12.5px] text-[#060D26] leading-tight">{{ $amenity->name }}</span>
                                </label>
                            @endforeach

                            {{-- Others --}}
                            <div x-data="{ others: false }" class="contents">
                                <label class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 cursor-pointer transition-colors duration-200"
                                    :class="others ? 'border-[#FF8A66] bg-[#ECEEF6]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]'">
                                    <input type="checkbox" x-model="others"
                                        class="w-4 h-4 rounded border-[#5B6A8E]/40 text-[#B35A3D] focus:ring-[#FF8A66]/30">
                                    <span class="text-[12.5px] text-[#060D26] leading-tight">Others</span>
                                </label>
                                <div x-show="others" x-cloak class="col-span-full">
                                    <input type="text" placeholder="Specify other amenity..." aria-label="Specify other amenity"
                                        class="{{ $input }}">
                                </div>
                            </div>
                        </div>
                        @error('amenities')
                            <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                        @enderror
                        @error('amenities.*')
                            <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                        @enderror

                    </x-form-section>

                    {{-- Existing photos — editable: delete down to the 3-photo
                         floor (destroyMedia enforces it, any mix of live/upload), add more below. --}}
                    @php $existingCameraCount = $existingPhotos->where('source', 'camera')->count(); @endphp
                    <x-form-section title="Photos" description="A unit always needs at least 3 photos, live or uploaded — you can remove any extra, but not below that.">
                        <x-slot:icon>{!! $ico('m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z') !!}</x-slot:icon>

                        @if($existingPhotos->isNotEmpty())
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 mb-3">
                                @foreach($existingPhotos as $photo)
                                    <div class="relative aspect-square rounded-xl overflow-hidden bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/15 group">
                                        <img loading="lazy" decoding="async" src="{{ $photo->media_url }}" alt="{{ $photo->caption ?? 'Unit photo' }}" class="w-full h-full object-cover">
                                        @if($photo->source === 'camera')
                                            <span class="absolute top-1 left-1 rounded-full bg-[#FF8A66] text-[#060D26] px-1.5 py-0.5 text-[11px] font-semibold">Live</span>
                                        @endif
                                        @if($photo->caption)
                                            <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[11px] px-1.5 py-1 leading-tight line-clamp-2">{{ $photo->caption }}</span>
                                        @endif
                                        {{-- Deleting a camera photo below the floor is refused
                                             server-side (destroyMedia); an upload can always go.
                                             The actual <form> for this lives OUTSIDE the main
                                             edit form below — see the comment there for why. This
                                             button only references it by id. --}}
                                        <button type="submit" form="delete-photo-{{ $photo->media_id }}" aria-label="Remove photo"
                                            class="absolute top-1 right-1 w-7 h-7 rounded-full bg-white/95 border border-[#E2E4EC] flex items-center justify-center text-[#EF4444] sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition-opacity duration-200">
                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @error('photos')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror

                        @if($existingVideo)
                            <div class="rounded-xl overflow-hidden bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/15 max-w-xs">
                                <video src="{{ $existingVideo->media_url }}" controls class="w-full h-auto"></video>
                            </div>
                        @endif
                    </x-form-section>

                    @include('landlord.units.partials._photo-capture', ['existingLiveCount' => $existingCameraCount])

                    {{-- Footer bar (DESIGN §6f): ghost cancel left, the one primary action right. --}}
                    <div class="sticky bottom-0 z-10 -mx-4 sm:mx-0 pl-4 pr-20 sm:pl-0 lg:pr-0 py-4 bg-[#F7F8FC] border-t border-[#E2E4EC] flex items-center gap-3">
                        <a href="{{ ($fromWizard ?? false) ? route('properties.wizard.units', $property) : route('landlord.properties.units.index', $property) }}"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] text-[#060D26] text-sm font-semibold transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" :disabled="submitting"
                            class="ml-auto h-11 px-7 inline-flex items-center justify-center gap-2 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-sm font-semibold shadow-sm transition-colors duration-200 cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                            <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Saving…' : 'Save changes'"></span>
                        </button>
                    </div>
                </div>

                {{-- ── Right rail: live preview, pinned while the form scrolls ── --}}
                <div class="lg:col-span-5 lg:self-stretch">
                    <div class="space-y-3 [@media(min-height:760px)_and_(min-width:1024px)]:sticky top-8">

                        <div class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden">
                            <div class="px-5 py-4 flex items-center gap-2 border-b border-[#E2E4EC]/70">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <h3 class="text-[15px] font-semibold text-[#060D26] leading-tight">Live preview</h3>
                                <span class="ml-auto text-[12px] text-[#5B6A8E]">Updates as you edit</span>
                            </div>

                            {{-- Image area --}}
                            @if($previewPhoto)
                                <div class="aspect-[16/10] bg-[#ECEEF6] border-b border-[#E2E4EC]/70">
                                    <img src="{{ $previewPhoto }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="aspect-[16/10] bg-[#ECEEF6] flex flex-col items-center justify-center text-[#5B6A8E] border-b border-[#E2E4EC]/70">
                                    <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <p class="text-[12px] mt-1.5">No photos on this unit</p>
                                </div>
                            @endif

                            {{-- Body --}}
                            <div class="p-5 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[16px] font-semibold text-[#060D26] truncate"
                                            x-text="unitLabel || 'Unit name'"
                                            :class="unitLabel ? '' : 'text-[#5B6A8E] font-semibold italic'"></p>
                                        <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                            <span x-text="floor || 'Floor not set'"></span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :style="`border-color:${statusMeta[status].dot}55; background:${statusMeta[status].dot}14; color:#060D26`">
                                        <span class="w-1.5 h-1.5 rounded-full" :style="`background:${statusMeta[status].dot}`"></span>
                                        <span x-text="status"></span>
                                    </span>
                                </div>

                                <div class="flex items-baseline gap-1">
                                    <span class="text-[20px] font-semibold text-[#060D26] tabular-nums" x-text="peso(rentalFee) || '₱—'"></span>
                                    <span class="text-[12px] text-[#5B6A8E]">/ month</span>
                                </div>

                                <div class="grid gap-2" :class="floorArea ? 'grid-cols-2' : 'grid-cols-1'">
                                    <div class="rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-3 py-2.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Capacity</p>
                                        <p class="text-[14px] font-semibold text-[#060D26] mt-0.5"
                                            x-text="capacity ? capacity + (capacity == 1 ? ' person' : ' persons') : '—'"></p>
                                    </div>
                                    <div x-show="floorArea" x-cloak class="rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-3 py-2.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Floor area</p>
                                        <p class="text-[14px] font-semibold text-[#060D26] mt-0.5" x-text="floorArea ? floorArea + ' sqm' : '—'"></p>
                                    </div>
                                </div>

                                {{-- Amenities --}}
                                <div x-show="amenities.length" x-cloak class="pt-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E] mb-1.5">Amenities</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="id in amenities" :key="id">
                                            <span class="inline-flex items-center rounded-full bg-[#ECEEF6] border border-[#FF8A66]/20 px-2 py-0.5 text-[11px] text-[#060D26]"
                                                x-text="amenityNames[id]"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-[12px] text-[#5B6A8E] text-center px-4 leading-relaxed">
                            This is a preview of how the unit's key details will read to tenants once approved.
                        </p>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
