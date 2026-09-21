@extends('layouts.landlord')

@section('content')
    @vite(['resources/js/maps/location-picker.js'])

    @php
        // Field styling shared with the add-property wizard (wizard/info.blade.php), so both read the same.
        $label = 'block text-[13px] font-semibold text-[#060D26] mb-1.5';
        $input = 'w-full h-11 px-3.5 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200';
        $error = 'text-[12px] text-[#DC2626] mt-1.5';
        $ico = fn (string $d) => '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'" /></svg>';
        $selectedAmenityIds = old('amenities', $property->amenities->pluck('amenity_id')->all());
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

        {{-- Breadcrumb --}}
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-sm text-[#5B6A8E] mb-3">
            <a href="{{ route('landlord.properties.index') }}" class="hover:text-[#060D26] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.show', $property) }}" class="hover:text-[#060D26] transition-colors duration-200 truncate max-w-[16rem]">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#060D26] font-medium">Edit</span>
        </nav>

        {{-- Header --}}
        <x-page-header title="Edit property">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                </svg>
            </x-slot:icon>
            <x-slot:badge>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold uppercase
                    @if($property->verification_status === 'Approved') bg-[#22C55E]/[0.07] text-[#15803D] border border-[#22C55E]/20
                    @elseif($property->verification_status === 'Pending') bg-[#FBBF24]/[0.10] text-[#B45309] border border-[#FBBF24]/25
                    @else bg-[#EF4444]/[0.07] text-[#DC2626] border border-[#EF4444]/20 @endif">
                    {{ $property->verification_status }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold uppercase bg-[#ECEEF6] text-[#060D26] border border-[#E2E4EC]">
                    {{ $property->availability_status }}
                </span>
            </x-slot:badge>
        </x-page-header>

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5" role="alert">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <form action="{{ route('properties.update', $property->property_id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">

                {{-- ── Left column: form fields ───────────────────────────── --}}
                <div class="lg:col-span-7 space-y-6">

                    <x-form-section title="Basics" description="What tenants see first in search results.">
                        <x-slot:icon>{!! $ico('m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10') !!}</x-slot:icon>

                        <div class="space-y-4">
                            <div>
                                <label for="title" class="{{ $label }}">Title</label>
                                <input type="text" id="title" name="title" value="{{ old('title', $property->title) }}" minlength="10" maxlength="150"
                                    class="{{ $input }}" required>
                                @error('title')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">Property type</label>
                                    <x-styled-select name="property_type"
                                        :options="['Apartment' => 'Apartment', 'Condominium' => 'Condominium', 'House' => 'House', 'Boarding House' => 'Boarding House', 'Bedspace' => 'Bedspace']"
                                        :selected="old('property_type', $property->property_type)" required
                                        class="w-full h-11 px-3.5 rounded-xl border border-[#E2E4EC] text-[14px] text-[#060D26]" />
                                    @error('property_type')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="rental_fee" class="{{ $label }}">Monthly rent</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-[#5B6A8E] font-semibold text-sm" aria-hidden="true">₱</span>
                                        <input type="number" step="0.01" min="500" max="999999" name="rental_fee" id="rental_fee"
                                            value="{{ old('rental_fee', $property->rental_fee) }}"
                                            class="{{ $input }} !pl-8 font-semibold" required>
                                    </div>
                                    @error('rental_fee')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <label for="description" class="{{ $label }}">Description</label>
                                <textarea id="description" name="description" rows="6" minlength="20" maxlength="3000"
                                    class="w-full px-3.5 py-3 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200"
                                    required>{{ old('description', $property->description) }}</textarea>
                                @error('description')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Location" description="Pin the property on the map, then confirm the address.">
                        <x-slot:icon>{!! $ico('M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z') !!}</x-slot:icon>

                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="{{ $label }} !mb-0">Map pin</span>
                                    <button type="button" id="location-picker-expand"
                                        class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4" />
                                        </svg>
                                        Expand map
                                    </button>
                                </div>
                                <div id="location-picker-map-wrapper" class="relative rounded-xl overflow-hidden border @error('latitude') border-[#EF4444]/35 @elseif ($errors->has('longitude')) border-[#EF4444]/35 @else border-[#E2E4EC] @enderror">
                                    <div id="location-picker-map" class="h-[240px] w-full"></div>
                                    <div id="location-picker-hint" class="absolute top-3 left-3 z-[1000] bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full text-[12px] font-semibold text-[#060D26] shadow-sm pointer-events-none">
                                        Tap the map to pin your location — Cebu only
                                    </div>
                                </div>
                                <div id="location-picker-placeholder" class="hidden h-[240px] w-full rounded-xl border border-dashed border-[#E2E4EC] bg-[#F7F8FC] items-center justify-center text-[12.5px] font-medium text-[#5B6A8E] text-center px-4">
                                    Map opened in full screen — tap Done to bring it back here.
                                </div>
                                <div class="flex items-center justify-between gap-3 mt-2">
                                    <p id="location-picker-address-line" class="text-[12.5px] text-[#5B6A8E] truncate">Pinned location</p>
                                    <p id="location-picker-latlng" class="text-[11.5px] text-[#5B6A8E] shrink-0 tabular-nums">Lat — · Lng —</p>
                                </div>
                                <p id="location-picker-cebu-warning" class="hidden text-[12px] text-[#DC2626] mt-1.5">That pin looks like it's outside Cebu — double-check before submitting.</p>
                                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $property->latitude) }}">
                                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $property->longitude) }}">
                                @error('latitude')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                @error('longitude')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="address" class="{{ $label }}">Address</label>
                                <input type="text" id="address" name="address" value="{{ old('address', $property->address) }}"
                                    minlength="10" maxlength="255" class="{{ $input }}" required>
                                @error('address')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">City / Municipality</label>
                                    <div id="city-municipality-picker" data-lgus="{{ json_encode(config('cebu.lgus')) }}">
                                        <x-styled-select name="city_municipality"
                                            :options="array_combine(config('cebu.lgus'), config('cebu.lgus'))"
                                            :selected="old('city_municipality', $property->city_municipality)" placeholder="Select — Cebu only" required
                                            class="w-full h-11 px-3.5 rounded-xl border {{ $errors->has('city_municipality') ? 'border-[#EF4444]/35' : 'border-[#E2E4EC]' }} text-[14px] text-[#060D26]" />
                                    </div>
                                    @error('city_municipality')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="barangay" class="{{ $label }}">Barangay <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                                    <input type="text" id="barangay" name="barangay" value="{{ old('barangay', $property->barangay) }}" maxlength="100"
                                        class="{{ $input }}" placeholder="e.g., Lahug">
                                    @error('barangay')<p class="{{ $error }}">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="House rules & utilities" description="Tenants see these on the listing before they inquire.">
                        <x-slot:icon>{!! $ico('M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z') !!}</x-slot:icon>

                        <div class="space-y-5">
                            <x-property-policy-fields :values="\App\Support\PropertyPolicies::formValues($property)" />

                            <div>
                                <p class="{{ $label }}">Included in the rent</p>
                                <p class="text-[12.5px] text-[#5B6A8E] mb-3">Leave a box unchecked if that charge is billed separately — tenants will see it flagged as not included.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ([
                                        ['water_included', 'Water'],
                                        ['electricity_included', 'Electricity'],
                                        ['internet_included', 'Internet'],
                                        ['association_fees_included', 'Association / maintenance fees'],
                                        ['utilities_separately_metered', 'Utilities separately metered'],
                                    ] as [$field, $text])
                                        <label class="flex items-center gap-2.5 rounded-xl border border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] px-3 py-2.5 text-[13.5px] text-[#060D26] cursor-pointer transition-colors duration-200">
                                            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $property->$field ?? false))
                                                class="w-4 h-4 rounded border-[#5B6A8E]/40 text-[#B35A3D] focus:ring-[#FF8A66]/30">
                                            {{ $text }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Property amenities" description="Features shared across the whole property — not what's inside a specific unit. Optional.">
                        <x-slot:icon>{!! $ico('M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z') !!}</x-slot:icon>

                        <div class="space-y-5">
                            @foreach($amenities->groupBy('category') as $category => $group)
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-[#5B6A8E] mb-2">{{ $category }}</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($group as $amenity)
                                            @php $on = collect($selectedAmenityIds)->contains($amenity->amenity_id); @endphp
                                            <label x-data="{ on: @js($on) }"
                                                class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 cursor-pointer transition-colors duration-200 text-[13px] text-[#060D26]"
                                                :class="on ? 'border-[#FF8A66] bg-[#ECEEF6]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]'">
                                                <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" x-model="on"
                                                    class="w-4 h-4 rounded border-[#5B6A8E]/40 text-[#B35A3D] focus:ring-[#FF8A66]/30">
                                                {{ $amenity->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('amenities')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </x-form-section>
                </div>

                {{-- ── Right rail: photos + actions ──────────────────────── --}}
                <div class="lg:col-span-5">
                    <div class="space-y-6">

                        <x-form-section title="Current photos" :description="$property->media->count().' '.\Illuminate\Support\Str::plural('photo', $property->media->count()).' on this listing'">
                            <x-slot:icon>{!! $ico('m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z') !!}</x-slot:icon>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @forelse($property->media as $img)
                                    @php $url = $img->media_url; @endphp
                                    <div class="relative aspect-square rounded-xl overflow-hidden bg-[#ECEEF6] border border-[#E2E4EC] group/img">
                                        <img src="{{ str_starts_with($url, 'http') ? $url : Storage::url($url) }}" loading="lazy" decoding="async"
                                            class="w-full h-full object-cover group-hover/img:scale-105 transition-transform duration-300" alt="Property photo">
                                        {{-- type="submit" + form="..." targets the standalone form rendered
                                             after </form> below, by id — a real <form> nested inside this page's
                                             main edit <form> is invalid HTML. Browsers silently "repair" that by
                                             closing the outer form early at the nested form's closing tag, which
                                             can leave the Save Changes button outside any form, or — worse — let
                                             this delete form's method-spoofing hidden input merge into the outer
                                             form's fields, so submitting "Save Changes" would ship a stray
                                             _method=DELETE the server prioritizes over the edit form's own PUT,
                                             deleting the whole property instead of saving the edit. --}}
                                        <button type="submit" form="destroy-media-{{ $img->media_id }}" aria-label="Remove photo"
                                            class="absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-white/95 hover:bg-[#EF4444]/[0.07] text-[#DC2626] flex items-center justify-center shadow-sm transition-colors duration-200 cursor-pointer">
                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                @empty
                                    <div class="col-span-full py-8 text-center bg-[#F7F8FC] border border-dashed border-[#E2E4EC] rounded-xl">
                                        <p class="text-[12.5px] text-[#5B6A8E]">No photos yet.</p>
                                    </div>
                                @endforelse
                            </div>
                        </x-form-section>

                        <x-form-section title="Add more photos" description="JPEG, PNG or WEBP, up to 5 MB each.">
                            <x-slot:icon>{!! $ico('M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5') !!}</x-slot:icon>

                            <label class="group block cursor-pointer rounded-xl border-2 border-dashed border-[#E2E4EC] hover:border-[#FF8A66] bg-[#F7F8FC] p-6 text-center transition-colors duration-200">
                                <span class="w-11 h-11 rounded-xl bg-white shadow-sm border border-[#E2E4EC] flex items-center justify-center mx-auto mb-3 text-[#5B6A8E] group-hover:text-[#060D26] transition-colors duration-200">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </span>
                                <span id="upload-label" data-default-label="Upload new photos" class="block text-[13.5px] font-semibold text-[#060D26]">Upload new photos</span>
                                <input type="file" name="photos[]" id="photo-input" class="hidden" multiple
                                    accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewSelectedPhotos(this)">
                            </label>

                            @error('photos')<p class="{{ $error }}">{{ $message }}</p>@enderror

                            <div id="live-preview-grid" class="grid grid-cols-3 sm:grid-cols-4 gap-2 hidden pt-4 mt-4 border-t border-[#E2E4EC]"></div>
                        </x-form-section>

                        <div class="flex items-start gap-3 rounded-xl bg-[#FF8A66]/10 border border-[#FF8A66]/30 px-4 py-3">
                            <svg class="w-4 h-4 text-[#B35A3D] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <p class="text-[12.5px] text-[#060D26] leading-relaxed">
                                <span class="font-semibold">Heads up:</span> changing any detail or adding photos sends this listing back for admin review before tenants can see it again.
                            </p>
                        </div>

                        {{-- Footer bar (DESIGN §6f): ghost cancel left, the one primary action right. --}}
                        <div class="flex items-center gap-3 pt-5 border-t border-[#E2E4EC]">
                            <a href="{{ route('landlord.properties.index') }}"
                                class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] text-[#060D26] text-sm font-semibold transition-colors duration-200">
                                Cancel
                            </a>
                            <button type="submit"
                                class="ml-auto h-11 px-7 inline-flex items-center justify-center rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-sm font-semibold shadow-sm transition-colors duration-200 cursor-pointer">
                                Save changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Per-photo delete forms, siblings of (not nested in) the main edit
    form above — see the comment by the "Remove photo" button for why. --}}
    @foreach($property->media as $img)
        <form id="destroy-media-{{ $img->media_id }}"
            action="{{ route('properties.media.destroy', [$property->property_id, $img->media_id]) }}"
            method="POST" data-confirm="Remove this photo?" data-confirm-type="warning"
            data-confirm-message="The photo will be removed from this listing."
            data-confirm-button="Remove" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

    <div id="location-picker-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Pin your property location" aria-hidden="true">
        <div id="location-picker-modal-backdrop" class="absolute inset-0 bg-[#060D26]/60 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>
        <div id="location-picker-modal-panel" class="relative w-full max-w-5xl h-[85vh] bg-white rounded-2xl shadow-xl flex flex-col overflow-hidden opacity-0 translate-y-4 scale-95 transition-all duration-300">
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#E2E4EC] shrink-0">
                <div>
                    <p class="text-[15px] font-semibold text-[#060D26]">Pin your property location</p>
                    <p class="text-[12px] text-[#5B6A8E] mt-0.5">Tap or drag the pin, then confirm.</p>
                </div>
                <button type="button" id="location-picker-modal-close" aria-label="Close" class="w-9 h-9 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="location-picker-modal-slot" class="flex-1 relative min-h-0"></div>
            <div class="px-5 py-4 border-t border-[#E2E4EC] shrink-0 flex items-center justify-end">
                <button type="button" id="location-picker-modal-done" class="h-11 px-6 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-semibold text-sm shadow-sm transition-colors duration-200 cursor-pointer">
                    Done
                </button>
            </div>
        </div>
    </div>

    @include('landlord.properties.partials.photo-preview-script')
@endsection
