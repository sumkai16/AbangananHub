@extends('layouts.landlord')

@section('content')
@vite(['resources/js/maps/location-picker.js'])

<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if($errors->any())
            <div class="max-w-3xl mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
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

        <div class="grid lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11">

            <x-property-wizard-stepper current="location" :property="$property" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 2 of 6</p>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">Where is it located?</h1>
                <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">Pin the exact spot, then add a few photos of the building.</p>

                <form method="POST" action="{{ $property ? route('properties.wizard.location.update', $property) : route('properties.wizard.location.store') }}"
                    enctype="multipart/form-data" x-data="{ submitting: false }" x-on:submit="submitting = true"
                    class="mt-7 max-w-4xl space-y-7">
                    @csrf
                    @if($property) @method('PUT') @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[13px] font-semibold text-[#1F2937]">Pin your location</label>
                            <button type="button" id="location-picker-expand" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#156F8C] hover:text-[#0E5670] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/>
                                </svg>
                                Expand map
                            </button>
                        </div>
                        <div id="location-picker-map-wrapper" class="relative rounded-xl overflow-hidden border @error('latitude') border-[#EF4444]/40 @elseif ($errors->has('longitude')) border-[#EF4444]/40 @else border-[#E2E8F0] @enderror">
                            <div id="location-picker-map" class="h-[300px] w-full"></div>
                            <div id="location-picker-hint" class="absolute top-3 left-3 z-[1000] bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full text-[12px] font-bold text-[#156F8C] shadow-sm pointer-events-none">
                                Tap the map to pin your location — Cebu only
                            </div>
                        </div>
                        <div id="location-picker-placeholder" class="hidden h-[300px] w-full rounded-xl border border-dashed border-[#E2E8F0] bg-[#F7FCFC] items-center justify-center text-[12px] font-medium text-[#94A3B8] text-center px-4">
                            Map opened in full screen — tap Done to bring it back here.
                        </div>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p id="location-picker-address-line" class="text-[12px] text-[#64748B] truncate">Address will appear here after pinning.</p>
                            <p id="location-picker-latlng" class="text-[11px] text-[#94A3B8] shrink-0">Lat — · Lng —</p>
                        </div>
                        <p id="location-picker-cebu-warning" class="hidden text-xs text-[#DC2626] mt-1.5">That pin looks like it's outside Cebu — double-check before submitting.</p>
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $property->latitude ?? '') }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $property->longitude ?? '') }}">
                        @error('latitude')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                        @error('longitude')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">City / Municipality</label>
                            <div id="city-municipality-picker" data-lgus="{{ json_encode(config('cebu.lgus')) }}">
                                <x-styled-select name="city_municipality"
                                    :options="array_combine(config('cebu.lgus'), config('cebu.lgus'))"
                                    :selected="old('city_municipality', $property->city_municipality ?? '')" placeholder="Select — Cebu only" required
                                    class="w-full h-12 px-4 rounded-xl border {{ $errors->has('city_municipality') ? 'border-[#EF4444]/40' : 'border-[#E2E8F0]' }} text-[14px] text-[#1F2937]" />
                            </div>
                            @error('city_municipality')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="barangay" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Barangay <span class="text-[#94A3B8] font-normal">(optional)</span></label>
                            <input type="text" id="barangay" name="barangay" value="{{ old('barangay', $property->barangay ?? '') }}" maxlength="100"
                                class="w-full h-12 px-4 rounded-xl border @error('barangay') border-[#EF4444]/40 @else border-[#E2E8F0] @enderror text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/25 focus:border-[#2AA7A1] transition-all"
                                placeholder="e.g., Lahug">
                            @error('barangay')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Street / Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $property->address ?? '') }}" minlength="10" maxlength="255"
                            class="w-full h-12 px-4 rounded-xl border @error('address') border-[#EF4444]/40 @else border-[#E2E8F0] @enderror text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/25 focus:border-[#2AA7A1] transition-all"
                            placeholder="Pin your location on the map above, or type it manually" required>
                        @error('address')<p class="text-xs text-[#EF4444] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="pt-2 border-t border-[#E2E8F0]">
                        <label class="block text-[13px] font-semibold text-[#1F2937] mt-6 mb-3">Photos</label>

                        @if($property && $property->media->isNotEmpty())
                            <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 mb-3">
                                @foreach($property->media as $media)
                                    <div class="aspect-square rounded-lg overflow-hidden bg-[#EEF8F8] border border-[#E2E8F0]">
                                        <img src="{{ $media->media_url }}" alt="" class="w-full h-full object-cover">
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-[11.5px] text-[#94A3B8] mb-3">{{ $property->media->count() }} photo(s) already added. Add more below, or manage them from the property page after submitting.</p>
                        @endif

                        <div class="border-2 border-dashed @error('photos') border-[#EF4444]/30 @else border-[#E2E8F0] @enderror hover:border-[#2AA7A1] rounded-2xl p-6 bg-[#F7FCFC] text-center transition-colors group">
                            <label class="cursor-pointer block">
                                <div class="w-11 h-11 rounded-xl bg-white shadow-sm border border-[#E2E8F0] flex items-center justify-center mx-auto mb-3 text-[#94A3B8] group-hover:text-[#156F8C] transition-all">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <span id="upload-label" data-default-label="Select photos to upload" class="text-[13.5px] font-semibold text-[#156F8C] transition-colors">Select photos to upload</span>
                                <p class="text-xs text-[#94A3B8] mt-1.5">JPEG, PNG, or WEBP. Max 5MB each, up to 10 photos.</p>
                                <input type="file" name="photos[]" id="photo-input" class="hidden" multiple accept="image/jpeg,image/png,image/jpg,image/webp" {{ ($property && $property->media->isNotEmpty()) ? '' : 'required' }} onchange="previewSelectedPhotos(this)">
                            </label>
                        </div>
                        @error('photos')<p class="text-xs text-[#EF4444] mt-2">{{ $message }}</p>@enderror
                        @error('photos.*')<p class="text-xs text-[#EF4444] mt-2">{{ $message }}</p>@enderror

                        <div id="live-preview-grid" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 hidden pt-3"></div>
                    </div>

                    <div class="mt-7 pt-5 border-t border-[#E2E8F0] flex items-center gap-3">
                        <a href="{{ $property ? route('properties.wizard.info.edit', $property) : route('properties.create') }}"
                            class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                            Back
                        </a>
                        <button type="submit" :disabled="submitting"
                            class="ml-auto inline-flex items-center gap-2 px-9 py-3 rounded-xl text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all duration-150 disabled:opacity-60 disabled:cursor-wait disabled:hover:brightness-100">
                            <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Uploading photos…' : 'Save & Continue'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="location-picker-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Pin your property location" aria-hidden="true">
    <div id="location-picker-modal-backdrop" class="absolute inset-0 bg-[#0F172A]/60 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>
    <div id="location-picker-modal-panel" class="relative w-full max-w-5xl h-[85vh] bg-white rounded-2xl shadow-xl flex flex-col overflow-hidden opacity-0 translate-y-4 scale-95 transition-all duration-300">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] shrink-0">
            <div>
                <p class="text-[15px] font-bold text-[#1F2937]">Pin your property location</p>
                <p class="text-[12px] text-[#64748B] mt-0.5">Tap or drag the pin, then confirm.</p>
            </div>
            <button type="button" id="location-picker-modal-close" aria-label="Close" class="w-9 h-9 rounded-full flex items-center justify-center text-[#64748B] hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="location-picker-modal-slot" class="flex-1 relative min-h-0"></div>
        <div class="px-5 py-4 border-t border-[#E2E8F0] shrink-0 flex items-center justify-end">
            <button type="button" id="location-picker-modal-done" class="h-11 px-6 rounded-full bg-[#2AA7A1] text-white font-bold text-[13.5px] shadow-sm hover:brightness-95 transition-all">
                Done
            </button>
        </div>
    </div>
</div>

@include('landlord.properties.partials.photo-preview-script')
@endsection
