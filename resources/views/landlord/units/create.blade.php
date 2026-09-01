@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-2">
            <a href="{{ route('landlord.properties.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.show', $property) }}"
                class="hover:text-[#1F2937] transition-colors duration-200">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.units.index', $property) }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Units</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">Add New Unit</span>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Add New Unit</h1>
            <p class="text-sm text-[#64748B] mt-1">Add a new rental unit under your property.</p>
        </div>

        {{-- Flash / errors --}}
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $statusMeta = [
                'Available' => ['dot' => '#22C55E', 'text' => 'Vacant and ready'],
                'Reserved' => ['dot' => '#FBBF24', 'text' => 'On hold for a tenant'],
                'Occupied' => ['dot' => '#EF4444', 'text' => 'Currently rented'],
                'Maintenance' => ['dot' => '#64748B', 'text' => 'Temporarily unavailable'],
            ];
            $amenityNameMap = $amenities->pluck('amenity_name', 'amenity_id')->toArray();
            $preselectedAmenities = collect(old('amenities', []))->map(fn ($id) => (string) $id)->all();
        @endphp

        <form method="POST" action="{{ route('landlord.properties.units.store', $property) }}" enctype="multipart/form-data"
            x-on:submit="submitting = true"
            x-data="{
                unitLabel: @js(old('unit_label', '')),
                unitType: @js(old('unit_type', '')),
                floor: @js(old('floor', '')),
                rentalFee: @js(old('rental_fee', '')),
                securityDeposit: @js(old('security_deposit', '')),
                // A monthly rental always carries a deposit now (required
                // below). Mirroring the rent into it as it's typed means the
                // landlord confirms a number instead of inventing one from a
                // blank field — one month's rent is the standard arrangement
                // in the coverage area. Stops mirroring the moment they edit
                // the deposit themselves.
                securityDepositTouched: @js(old('security_deposit') !== null),
                capacity: @js(old('occupancy_limit', '')),
                floorArea: @js(old('floor_area_sqm', '')),
                status: @js(old('availability_status', 'Available')),
                description: @js(old('description', '')),
                amenities: @js($preselectedAmenities),
                amenityNames: @js($amenityNameMap),
                statusMeta: @js($statusMeta),
                submitting: false,
                peso(v) { return (v === '' || v === null || isNaN(v)) ? null : '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
            }">
            @csrf
            @if($fromWizard ?? false)
                <input type="hidden" name="from" value="wizard">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                {{-- ── Left column: form fields ──────────────────────────── --}}
                <div class="lg:col-span-7 space-y-6">

                    {{-- Property Information (read-only) --}}
                    <x-card flush class="p-6">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-[#156F8C] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <h2 class="text-[13px] font-bold text-[#1F2937]">Property Information</h2>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="rental-business" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Rental Business</label>
                                <input type="text" id="rental-business" value="{{ $property->rentalBusiness->business_name ?? 'N/A' }}" disabled
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                            </div>
                            <div>
                                <label for="property-display" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Property</label>
                                <input type="text" id="property-display" value="{{ $property->title . ' - ' . $property->address }}" disabled
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                            </div>
                        </div>
                    </x-card>

                    {{-- Unit Details --}}
                    <x-card flush class="p-6">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-[#1F2937] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                                </svg>
                            </div>
                            <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Details</h2>
                        </div>

                        {{-- Row 1 --}}
                        <div class="grid sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="unit_label" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Unit Name / Number <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="text" id="unit_label" name="unit_label" x-model="unitLabel" required maxlength="100"
                                    placeholder="e.g. Room 101, Bed A"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('unit_label')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Unit Type</label>
                                <x-styled-select name="unit_type" x-model="unitType"
                                    :options="array_combine(['Bedspace', 'Room', 'Apartment', 'Studio', 'Dormitory'], ['Bedspace', 'Room', 'Apartment', 'Studio', 'Dormitory'])"
                                    :selected="old('unit_type', '')" placeholder="Select type"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white" />
                                @error('unit_type')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="floor" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Floor</label>
                                <input type="text" id="floor" name="floor" x-model="floor" maxlength="50"
                                    placeholder="e.g. 1st Floor"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('floor')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Room details --}}
                        <div class="grid sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="bedrooms" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Bedrooms</label>
                                <input type="number" id="bedrooms" name="bedrooms" value="{{ old('bedrooms') }}" min="0" max="20"
                                    placeholder="e.g. 2"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('bedrooms')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="bathrooms" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Bathrooms</label>
                                <input type="number" id="bathrooms" name="bathrooms" value="{{ old('bathrooms') }}" min="0" max="20"
                                    placeholder="e.g. 1"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('bathrooms')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="floor_area_sqm" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Floor area (sqm)</label>
                                <input type="number" id="floor_area_sqm" name="floor_area_sqm" x-model="floorArea" min="1" max="9999.99" step="0.01" placeholder="e.g. 24"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('floor_area_sqm')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Furnished?</label>
                            @php $furnishedOld = old('is_furnished'); @endphp
                            <div class="flex items-center gap-4 h-11">
                                <label class="inline-flex items-center gap-1.5 text-[13px] text-[#1F2937] cursor-pointer">
                                    <input type="radio" name="is_furnished" value="1" @checked((string) $furnishedOld === '1') class="text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                    Yes
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-[13px] text-[#1F2937] cursor-pointer">
                                    <input type="radio" name="is_furnished" value="0" @checked((string) $furnishedOld === '0') class="text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                    No
                                </label>
                            </div>
                            @error('is_furnished')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Row 2 --}}
                        <div class="grid sm:grid-cols-3 gap-4 mb-5">
                            <div>
                                <label for="rental_fee" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Monthly Rent (₱) <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="number" id="rental_fee" name="rental_fee" x-model="rentalFee" required min="500"
                                    max="999999.99" step="0.01" placeholder="e.g. 3500"
                                    x-on:input="if (!securityDepositTouched) securityDeposit = rentalFee"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('rental_fee')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="security_deposit" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Security Deposit (₱) <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="number" id="security_deposit" name="security_deposit" x-model="securityDeposit" required min="0"
                                    max="999999.99" step="0.01" placeholder="e.g. 3500"
                                    x-on:input="securityDepositTouched = true"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('security_deposit')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Capacity <span class="text-[#EF4444]">*</span>
                                </label>
                                @php
                                    $capacityOptions = collect(range(1, 20))
                                        ->mapWithKeys(fn ($i) => [(string) $i => $i . ' ' . ($i === 1 ? 'person' : 'persons')])
                                        ->all();
                                @endphp
                                <x-styled-select name="occupancy_limit" x-model="capacity" required
                                    :options="$capacityOptions" :selected="old('occupancy_limit', '')" placeholder="Select capacity"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white" />
                                @error('occupancy_limit')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="mb-5">
                            <label class="block text-[12px] font-semibold text-[#1F2937] mb-2">
                                Status <span class="text-[#EF4444]">*</span>
                            </label>
                            @php
                                $statusOptions = [
                                    'Available' => ['desc' => 'Unit is vacant and ready', 'dot' => 'bg-[#22C55E]', 'active' => 'border-[#22C55E]/35 bg-[#22C55E]/[0.07]'],
                                    'Reserved' => ['desc' => 'On hold for a tenant', 'dot' => 'bg-[#FBBF24]', 'active' => 'border-[#FBBF24]/45 bg-[#FBBF24]/[0.10]'],
                                    'Occupied' => ['desc' => 'Currently rented', 'dot' => 'bg-[#EF4444]', 'active' => 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]'],
                                    'Maintenance' => ['desc' => 'Temporarily unavailable', 'dot' => 'bg-[#64748B]', 'active' => 'border-[#E2E8F0] bg-[#F7FCFC]'],
                                ];
                                $inactiveClass = 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40';
                            @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach($statusOptions as $value => $opt)
                                    <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                        :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                        <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                            class="sr-only">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $opt['dot'] }}"></span>
                                            <p class="text-[13px] font-semibold text-[#1F2937]">{{ $value }}</p>
                                        </div>
                                        <p class="text-[10.5px] text-[#64748B] leading-snug">{{ $opt['desc'] }}</p>
                                    </label>
                                @endforeach
                            </div>
                            @error('availability_status')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-[12px] font-semibold text-[#1F2937]">Description</label>
                                <span class="text-[11px] text-[#64748B]" x-text="description.length + ' / 300'"></span>
                            </div>
                            <textarea name="description" rows="3" maxlength="300" x-model="description"
                                placeholder="Add any note or description about this unit..."
                                class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-none"></textarea>
                            @error('description')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </x-card>

                    {{-- Unit Photos --}}
                    @include('landlord.units.partials._photo-capture', ['existingLiveCount' => 0])

                    {{-- Actions --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ ($fromWizard ?? false) ? route('properties.wizard.units', $property) : route('landlord.properties.units.index', $property) }}"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#64748B]/30 text-[#1F2937] text-sm font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit" :disabled="submitting"
                            class="h-11 px-6 inline-flex items-center justify-center gap-2 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 disabled:opacity-70 disabled:cursor-wait">
                            <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Uploading photos…' : 'Save Unit'"></span>
                        </button>
                    </div>
                </div>

                {{-- ── Right rail: live preview + amenities ───────────────── --}}
                <div class="lg:col-span-5">
                    <div class="space-y-6">
                        <x-card flush>
                            <div class="px-5 pt-5 pb-3 flex items-center gap-2 border-b border-[#E2E8F0]/70">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <h3 class="text-[13px] font-bold text-[#156F8C]">Live Preview</h3>
                                <span class="ml-auto text-[10.5px] font-medium text-[#64748B]">Updates as you type</span>
                            </div>

                            {{-- Image area --}}
                            <div class="aspect-[4/3] bg-[#EEF8F8] flex flex-col items-center justify-center text-[#64748B] border-b border-[#E2E8F0]/70">
                                <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-[11px] mt-1.5">Photos appear here</p>
                            </div>

                            {{-- Body --}}
                            <div class="p-5 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[15px] font-bold text-[#1F2937] truncate"
                                            x-text="unitLabel || 'Unit name'"
                                            :class="unitLabel ? '' : 'text-[#64748B] font-semibold italic'"></p>
                                        <p class="text-[12px] text-[#64748B] mt-0.5">
                                            <span x-text="unitType || 'Type not set'"></span><template x-if="floor"><span> · <span x-text="floor"></span></span></template>
                                        </p>
                                    </div>
                                    {{-- Status pill --}}
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :style="`border-color:${statusMeta[status].dot}55; background:${statusMeta[status].dot}14; color:#1F2937`">
                                        <span class="w-1.5 h-1.5 rounded-full" :style="`background:${statusMeta[status].dot}`"></span>
                                        <span x-text="status"></span>
                                    </span>
                                </div>

                                {{-- Rent --}}
                                <div class="flex items-baseline gap-1">
                                    <span class="text-[20px] font-bold text-[#156F8C]" x-text="peso(rentalFee) || '₱—'"></span>
                                    <span class="text-[12px] text-[#64748B]">/ month</span>
                                </div>

                                {{-- Meta rows --}}
                                <div class="grid grid-cols-2 gap-2 pt-1">
                                    <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Capacity</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5"
                                            x-text="capacity ? capacity + (capacity == 1 ? ' person' : ' persons') : '—'"></p>
                                    </div>
                                    <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Deposit</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5" x-text="peso(securityDeposit) || '—'"></p>
                                    </div>
                                    <div x-show="floorArea" x-cloak class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Floor area</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5" x-text="floorArea ? floorArea + ' sqm' : '—'"></p>
                                    </div>
                                </div>

                                {{-- Amenities --}}
                                <div x-show="amenities.length" x-cloak class="pt-1">
                                    <p class="text-[10px] uppercase tracking-wide text-[#64748B] mb-1.5">Amenities</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="id in amenities" :key="id">
                                            <span class="inline-flex items-center rounded-full bg-[#EEF8F8] border border-[#2AA7A1]/20 px-2 py-0.5 text-[11px] text-[#1F2937]"
                                                x-text="amenityNames[id]"></span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <p class="text-[12px] text-[#64748B] leading-relaxed line-clamp-3 pt-1"
                                    x-show="description" x-cloak x-text="description"></p>
                            </div>
                        </x-card>

                        <p class="text-[11px] text-[#64748B] text-center px-4 leading-relaxed">
                            This is a preview of how the unit's key details will read to tenants once approved.
                        </p>

                        {{-- Unit Amenities --}}
                        <x-card flush class="p-6">
                            <div class="flex items-center gap-2.5 mb-5">
                                <div class="w-8 h-8 rounded-lg bg-[#2AA7A1] flex items-center justify-center shrink-0">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                    </svg>
                                </div>
                                <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Amenities</h2>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($amenities as $amenity)
                                    <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150"
                                        :class="amenities.includes('{{ $amenity->amenity_id }}') ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40'">
                                        <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" x-model="amenities"
                                            class="w-4 h-4 rounded border-[#64748B]/40 text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                        <span class="text-[12.5px] text-[#1F2937] leading-tight">{{ $amenity->name }}</span>
                                    </label>
                                @endforeach

                                {{-- Others --}}
                                <div x-data="{ others: false }" class="contents">
                                    <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150"
                                        :class="others ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40'">
                                        <input type="checkbox" x-model="others"
                                            class="w-4 h-4 rounded border-[#64748B]/40 text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                        <span class="text-[12.5px] text-[#1F2937] leading-tight">Others</span>
                                    </label>
                                    <div x-show="others" x-cloak class="col-span-full">
                                        <input type="text" placeholder="Specify other amenity..." aria-label="Specify other amenity"
                                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                    </div>
                                </div>
                            </div>
                            @error('amenities')
                                <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                            @enderror
                            @error('amenities.*')
                                <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                            @enderror
                        </x-card>
                    </div>
                </div>

            </div>
        </form>

    </div>

@endsection
