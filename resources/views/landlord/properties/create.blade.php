@extends('layouts.landlord')

@section('content')
@vite(['resources/js/maps/location-picker.js'])

<div class="min-h-screen py-12">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col gap-4 border-b border-[#E2E8F0] pb-6 mb-8">
            <a href="{{ route('landlord.properties.index') }}" class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to listings
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">List a New Property</h1>
                <p class="text-sm text-[#64748B] mt-1">Fill in the details below. Your listing goes live for tenants once approved.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-8 p-5 bg-[#EF4444]/[0.07] border border-[#EF4444]/20 rounded-3xl flex gap-4">
                <div class="w-10 h-10 rounded-2xl bg-[#EF4444]/[0.12] flex items-center justify-center shrink-0 text-[#DC2626] shadow-sm border border-[#EF4444]/25">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h5 class="text-[14px] font-bold text-[#156F8C]">Please fix the following:</h5>
                    <ul class="list-disc pl-4 mt-1.5 text-[13px] text-[#DC2626] space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('properties.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                <div class="lg:col-span-7 bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 sm:p-8 space-y-6">

                    <h3 class="text-[16px] font-bold text-[#156F8C] border-b border-[#E2E8F0] pb-4">Property details</h3>

                    <div>
                        <label for="title" class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Title</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" minlength="10" maxlength="150"
                            class="w-full h-12 px-4 rounded-2xl border @error('title') border-[#EF4444]/35 @else border-[#E2E8F0] @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="e.g., Cozy studio near IT Park" required>
                        @error('title')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Property type</label>
                            <x-styled-select name="property_type" :options="['Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House']"
                                :selected="old('property_type', '')" placeholder="Select type" required
                                class="w-full h-12 px-4 rounded-2xl border {{ $errors->has('property_type') ? 'border-[#EF4444]/35' : 'border-[#E2E8F0]' }} text-[14px] font-medium text-[#156F8C]" />
                            @error('property_type')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="rental_fee" class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Monthly rent (₱)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#94A3B8] font-bold text-sm">₱</span>
                                <input type="number" step="0.01" min="500" max="999999" name="rental_fee" id="rental_fee" value="{{ old('rental_fee') }}"
                                    class="w-full h-12 pl-8 pr-4 rounded-2xl border @error('rental_fee') border-[#EF4444]/35 @else border-[#E2E8F0] @enderror text-[14px] font-bold text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                                    placeholder="0.00" required>
                            </div>
                            @error('rental_fee')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="border-t border-[#E2E8F0] pt-5 sm:max-w-[220px]">
                        <label for="occupancy_limit" class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Occupancy limit</label>
                        <input type="number" id="occupancy_limit" name="occupancy_limit" value="{{ old('occupancy_limit', 1) }}" min="1" max="100"
                            class="w-full h-12 px-4 rounded-2xl border @error('occupancy_limit') border-[#EF4444]/35 @else border-[#E2E8F0] @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all" required>
                        @error('occupancy_limit')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord location</label>
                            <button type="button" id="location-picker-expand" class="inline-flex items-center gap-1.5 text-[11.5px] font-bold text-[#156F8C] hover:text-[#0E5670] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/>
                                </svg>
                                Expand map
                            </button>
                        </div>
                        <div id="location-picker-map-wrapper" class="relative rounded-2xl overflow-hidden border @error('latitude') border-[#EF4444]/35 @elseif ($errors->has('longitude')) border-[#EF4444]/35 @else border-[#E2E8F0] @enderror">
                            <div id="location-picker-map" class="h-[240px] w-full"></div>
                            <div id="location-picker-hint" class="absolute top-3 left-3 z-[1000] bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full text-[12px] font-bold text-[#156F8C] shadow-sm pointer-events-none">
                                Tap the map to pin your location
                            </div>
                        </div>
                        <div id="location-picker-placeholder" class="hidden h-[240px] w-full rounded-2xl border border-dashed border-[#E2E8F0] bg-[#F7FCFC] items-center justify-center text-[12px] font-medium text-[#94A3B8] text-center px-4">
                            Map opened in full screen — tap Done to bring it back here.
                        </div>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p id="location-picker-address-line" class="text-[12px] text-[#64748B] truncate">Address will appear here after pinning.</p>
                            <p id="location-picker-latlng" class="text-[11px] text-[#94A3B8] shrink-0">Lat — · Lng —</p>
                        </div>
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                        @error('latitude')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                        @error('longitude')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="address" class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}" minlength="10" maxlength="255"
                            class="w-full h-12 px-4 rounded-2xl border @error('address') border-[#EF4444]/35 @else border-[#E2E8F0] @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="Pin your location on the map above, or type it manually" required>
                        @error('address')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Description</label>
                        <textarea name="description" rows="6" minlength="20" maxlength="3000"
                            class="w-full p-4 rounded-2xl border @error('description') border-[#EF4444]/35 @else border-[#E2E8F0] @enderror text-[14px] text-[#156F8C] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="Describe the space, amenities, nearby landmarks, house rules, payment terms..." required>{{ old('description') }}</textarea>
                        @error('description')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="sticky top-8 space-y-6">

                        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6 space-y-4">
                            <h3 class="text-[14px] font-bold text-[#156F8C] border-b border-[#E2E8F0] pb-3">Photos</h3>

                            <div class="border-2 border-dashed @error('photos') border-[#EF4444]/25 @else border-[#E2E8F0] @enderror hover:border-[#2AA7A1] rounded-3xl p-6 bg-[#F7FCFC] text-center transition-colors group">
                                <label class="cursor-pointer block">
                                    <div class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-[#E2E8F0] flex items-center justify-center mx-auto mb-3 text-[#94A3B8] group-hover:text-[#156F8C] transition-all">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span id="upload-label" data-default-label="Select photos to upload" class="text-[13.5px] font-bold text-[#156F8C] group-hover:text-[#156F8C] transition-colors">Select photos to upload</span>
                                    <p class="text-xs text-[#94A3B8] mt-1.5">JPEG, PNG, or WEBP. Max 5MB each, up to 10 photos.</p>
                                    <input type="file" name="photos[]" id="photo-input" class="hidden" multiple accept="image/jpeg,image/png,image/jpg,image/webp" required onchange="previewSelectedPhotos(this)">
                                </label>
                            </div>

                            @error('photos')<p class="text-xs text-[#DC2626]">{{ $message }}</p>@enderror
                            @error('photos.*')<p class="text-xs text-[#DC2626]">{{ $message }}</p>@enderror

                            <div id="live-preview-grid" class="grid grid-cols-3 gap-2 hidden pt-3 border-t border-[#E2E8F0]"></div>
                        </div>

                        <div class="rounded-3xl bg-[#EEF8F8]/50 border border-[#2AA7A1]/20 p-5">
                            <p class="text-sm font-bold text-[#156F8C]">Heads up</p>
                            <p class="text-[12.5px] text-[#64748B] mt-1 leading-relaxed">Your listing won't be visible to tenants until an admin reviews and approves it. That usually doesn't take long.</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('landlord.properties.index') }}" class="w-1/3 h-12 rounded-full border border-[#E2E8F0] bg-white hover:bg-[#F7FCFC] font-bold text-[13.5px] text-[#1F2937] flex items-center justify-center transition-colors">
                                Cancel
                            </a>
                            <button type="submit" class="w-2/3 h-12 rounded-full bg-[#2AA7A1] text-white font-bold text-[13.5px] shadow-sm hover:brightness-95 transition-all duration-300">
                                Create Listing
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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