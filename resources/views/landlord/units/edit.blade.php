@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

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
            <span class="text-[#1F2937] font-medium">Edit {{ $unit->unit_label }}</span>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Edit Unit</h1>
            <p class="text-sm text-[#64748B] mt-1">Update details for {{ $unit->unit_label }} under {{ $property->title }}.
            </p>
        </div>

        {{-- Flash / errors --}}
        @if($errors->any())
            <div
                class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium flex items-start gap-2.5">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
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
                'Maintenance' => ['dot' => '#64748B', 'text' => 'Temporarily unavailable'],
            ];
            $amenityNameMap = $amenities->pluck('amenity_name', 'amenity_id')->toArray();
            $preselectedAmenities = collect(old('amenities', $unit->amenities->pluck('amenity_id')->all()))
                ->map(fn ($id) => (string) $id)->all();
        @endphp

        <form method="POST" action="{{ route('landlord.properties.units.update', [$property, $unit]) }}"
            enctype="multipart/form-data"
            x-data="{
                unitLabel: @js(old('unit_label', $unit->unit_label)),
                capacity: @js(old('occupancy_limit', $unit->occupancy_limit)),
                rentalFee: @js(old('rental_fee', $unit->rental_fee)),
                status: @js(old('availability_status', $unit->availability_status)),
                amenities: @js($preselectedAmenities),
                amenityNames: @js($amenityNameMap),
                statusMeta: @js($statusMeta),
                unitType: @js($unit->unit_type ?? ''),
                floor: @js($unit->floor ?? ''),
                peso(v) { return (v === '' || v === null || isNaN(v)) ? null : '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
            }">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                {{-- ── Left column: form fields ──────────────────────────── --}}
                <div class="lg:col-span-7 space-y-6">

                    {{-- Unit Details --}}
                    <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-[#1F2937] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                                </svg>
                            </div>
                            <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Details</h2>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="unit_label" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Unit Name / Number <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="text" id="unit_label" name="unit_label" x-model="unitLabel" required
                                    maxlength="100" placeholder="e.g. Room 101, Bed A, Unit 201"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            </div>

                            <div>
                                <label for="occupancy_limit" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Capacity <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="number" id="occupancy_limit" name="occupancy_limit" x-model="capacity" required min="1" max="100"
                                    placeholder="Maximum number of occupants"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="rental_fee" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Monthly Rent (₱) <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="number" id="rental_fee" name="rental_fee" x-model="rentalFee" required
                                    min="500" max="999999.99" step="0.01" placeholder="e.g. 3500"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[12px] font-semibold text-[#1F2937] mb-2">
                                Status <span class="text-[#EF4444]">*</span>
                            </label>
                            @php
                                $statusOptions = [
                                    'Available' => ['label' => 'Available', 'desc' => 'Unit is vacant and ready', 'active' => 'border-emerald-300 bg-emerald-50'],
                                    'Reserved' => ['label' => 'Reserved', 'desc' => 'On hold for a tenant', 'active' => 'border-amber-300 bg-amber-50'],
                                    'Occupied' => ['label' => 'Occupied', 'desc' => 'Currently rented', 'active' => 'border-red-300 bg-red-50'],
                                ];
                                $inactiveClass = 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40';
                            @endphp
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($statusOptions as $value => $opt)
                                    <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                        :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                        <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                            class="sr-only">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span
                                                class="w-1.5 h-1.5 rounded-full shrink-0
                                                        {{ $value === 'Available' ? 'bg-[#22C55E]' : ($value === 'Reserved' ? 'bg-[#FBBF24]' : 'bg-[#EF4444]') }}"></span>
                                            <p class="text-[13px] font-semibold text-[#1F2937]">{{ $opt['label'] }}</p>
                                        </div>
                                        <p class="text-[10.5px] text-[#64748B] leading-snug">{{ $opt['desc'] }}</p>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Existing verification capture (read-only, captured at creation) --}}
                    <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-[#2AA7A1] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-[13px] font-bold text-[#1F2937]">Verification Capture</h2>
                                <p class="text-[11px] text-[#64748B] mt-0.5">Captured live at creation — not editable here.</p>
                            </div>
                        </div>
                        <div class="mb-4 px-3.5 py-3 rounded-xl bg-[#EEF8F8] border border-[#2AA7A1]/20 flex items-start gap-2.5">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2"
                                class="shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <p class="text-[12px] text-[#1F2937]/70 leading-relaxed">
                                Photos and video are locked to the moment this unit was created, to prevent re-uploading old or
                                unrelated media. To recapture, remove this unit and add it again.
                            </p>
                        </div>

                        @if($existingPhotos->isNotEmpty())
                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mb-3">
                                @foreach($existingPhotos as $photo)
                                    <div class="relative aspect-square rounded-lg overflow-hidden bg-[#F7FCFC] ring-1 ring-[#64748B]/15">
                                        <img src="{{ $photo->media_url }}" alt="{{ $photo->caption ?? 'Unit photo' }}" class="w-full h-full object-cover">
                                        @if($photo->source === 'camera')
                                            <span class="absolute top-1 left-1 rounded-full bg-[#2AA7A1] text-white px-1.5 py-0.5 text-[9px] font-semibold">Live</span>
                                        @endif
                                        @if($photo->caption)
                                            <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[9.5px] px-1.5 py-1 leading-tight line-clamp-2">{{ $photo->caption }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($existingVideo)
                            <div class="rounded-lg overflow-hidden bg-[#F7FCFC] ring-1 ring-[#64748B]/15 max-w-xs">
                                <video src="{{ $existingVideo->media_url }}" controls class="w-full h-auto"></video>
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ route('landlord.properties.units.index', $property) }}"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#64748B]/30 text-[#1F2937] text-sm font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                            Save Changes
                        </button>
                    </div>
                </div>

                {{-- ── Right rail: live preview + amenities ───────────────── --}}
                <div class="lg:col-span-5">
                    <div class="space-y-6">
                        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden">
                            <div class="px-5 pt-5 pb-3 flex items-center gap-2 border-b border-[#E2E8F0]/70">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <h3 class="text-[13px] font-bold text-[#156F8C]">Live Preview</h3>
                                <span class="ml-auto text-[10.5px] font-medium text-[#64748B]">Updates as you edit</span>
                            </div>

                            {{-- Image area --}}
                            @if($previewPhoto)
                                <div class="aspect-[4/3] bg-[#EEF8F8] border-b border-[#E2E8F0]/70">
                                    <img src="{{ $previewPhoto }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="aspect-[4/3] bg-[#EEF8F8] flex flex-col items-center justify-center text-[#64748B] border-b border-[#E2E8F0]/70">
                                    <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <p class="text-[11px] mt-1.5">No photos on this unit</p>
                                </div>
                            @endif

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
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :style="`border-color:${statusMeta[status].dot}55; background:${statusMeta[status].dot}14; color:#1F2937`">
                                        <span class="w-1.5 h-1.5 rounded-full" :style="`background:${statusMeta[status].dot}`"></span>
                                        <span x-text="status"></span>
                                    </span>
                                </div>

                                <div class="flex items-baseline gap-1">
                                    <span class="text-[20px] font-bold text-[#156F8C]" x-text="peso(rentalFee) || '₱—'"></span>
                                    <span class="text-[12px] text-[#64748B]">/ month</span>
                                </div>

                                <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Capacity</p>
                                    <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5"
                                        x-text="capacity ? capacity + (capacity == 1 ? ' person' : ' persons') : '—'"></p>
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
                            </div>
                        </div>

                        <p class="text-[11px] text-[#64748B] text-center px-4 leading-relaxed">
                            This is a preview of how the unit's key details will read to tenants once approved.
                        </p>

                        {{-- Unit Amenities --}}
                        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
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
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection