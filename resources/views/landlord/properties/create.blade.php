@extends('layouts.landlord')

@section('content')
<div class="min-h-screen py-12">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px]">

        <div class="flex flex-col gap-4 border-b border-gray-150 pb-6 mb-8">
            <a href="{{ route('landlord.properties.index') }}" class="inline-flex items-center gap-2 text-[13px] font-bold text-gray-400 hover:text-[#156F8C] transition-colors w-fit">
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
            <div class="mb-8 p-5 bg-red-50 border border-red-100 rounded-3xl flex gap-4">
                <div class="w-10 h-10 rounded-2xl bg-red-100 flex items-center justify-center shrink-0 text-red-600 shadow-sm border border-red-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h5 class="text-[14px] font-bold text-[#156F8C]">Please fix the following:</h5>
                    <ul class="list-disc pl-4 mt-1.5 text-[13px] text-red-700 space-y-0.5">
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

                <div class="lg:col-span-7 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 sm:p-8 space-y-6">

                    <h3 class="text-[16px] font-bold text-[#156F8C] border-b border-gray-50 pb-4">Property details</h3>

                    <div>
                        <label for="title" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Title</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" minlength="10" maxlength="150"
                            class="w-full h-12 px-4 rounded-2xl border @error('title') border-red-300 @else border-gray-200 @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="e.g., Cozy studio near IT Park" required>
                        @error('title')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Property type</label>
                            <select name="property_type"
                                class="w-full h-12 px-4 rounded-2xl border @error('property_type') border-red-300 @else border-gray-200 @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all" required>
                                <option value="">Select type</option>
                                <option value="Bedspace" {{ old('property_type') == 'Bedspace' ? 'selected' : '' }}>Bedspace</option>
                                <option value="Room" {{ old('property_type') == 'Room' ? 'selected' : '' }}>Room</option>
                                <option value="Apartment" {{ old('property_type') == 'Apartment' ? 'selected' : '' }}>Apartment</option>
                                <option value="House" {{ old('property_type') == 'House' ? 'selected' : '' }}>House</option>
                            </select>
                            @error('property_type')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="rental_fee" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Monthly rent (₱)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 font-bold text-sm">₱</span>
                                <input type="number" step="0.01" min="500" max="999999" name="rental_fee" id="rental_fee" value="{{ old('rental_fee') }}"
                                    class="w-full h-12 pl-8 pr-4 rounded-2xl border @error('rental_fee') border-red-300 @else border-gray-200 @enderror text-[14px] font-bold text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                                    placeholder="0.00" required>
                            </div>
                            @error('rental_fee')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-gray-50 pt-5">
                        <div>
                            <label for="occupancy_limit" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Occupancy limit</label>
                            <input type="number" id="occupancy_limit" name="occupancy_limit" value="{{ old('occupancy_limit', 1) }}" min="1" max="100"
                                class="w-full h-12 px-4 rounded-2xl border @error('occupancy_limit') border-red-300 @else border-gray-200 @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all" required>
                            @error('occupancy_limit')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="latitude" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Latitude</label>
                            <input type="number" step="any" min="-90" max="90" name="latitude" id="latitude" value="{{ old('latitude') }}" placeholder="10.3157"
                                class="w-full h-12 px-4 rounded-2xl border @error('latitude') border-red-300 @else border-gray-200 @enderror text-[14px] text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                            @error('latitude')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="longitude" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Longitude</label>
                            <input type="number" step="any" min="-180" max="180" name="longitude" id="longitude" value="{{ old('longitude') }}" placeholder="123.8854"
                                class="w-full h-12 px-4 rounded-2xl border @error('longitude') border-red-300 @else border-gray-200 @enderror text-[14px] text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                            @error('longitude')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-[11.5px] text-gray-400 -mt-2">Leave latitude/longitude blank for now if you're not sure — you'll be able to set it precisely once map pinning is added.</p>

                    <div>
                        <label for="address" class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}" minlength="10" maxlength="255"
                            class="w-full h-12 px-4 rounded-2xl border @error('address') border-red-300 @else border-gray-200 @enderror text-[14px] font-medium text-[#156F8C] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="Building, street, barangay, city" required>
                        @error('address')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Description</label>
                        <textarea name="description" rows="6" minlength="20" maxlength="3000"
                            class="w-full p-4 rounded-2xl border @error('description') border-red-300 @else border-gray-200 @enderror text-[14px] text-[#156F8C] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all"
                            placeholder="Describe the space, amenities, nearby landmarks, house rules, payment terms..." required>{{ old('description') }}</textarea>
                        @error('description')<p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="sticky top-8 space-y-6">

                        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6 space-y-4">
                            <h3 class="text-[14px] font-bold text-[#156F8C] border-b border-gray-50 pb-3">Photos</h3>

                            <div class="border-2 border-dashed @error('photos') border-red-200 @else border-gray-200 @enderror hover:border-[#2AA7A1] rounded-3xl p-6 bg-gray-50/50 text-center transition-colors group">
                                <label class="cursor-pointer block">
                                    <div class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center mx-auto mb-3 text-gray-400 group-hover:text-[#156F8C] transition-all">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span id="upload-label" data-default-label="Select photos to upload" class="text-[13.5px] font-bold text-[#156F8C] group-hover:text-[#156F8C] transition-colors">Select photos to upload</span>
                                    <p class="text-xs text-gray-400 mt-1.5">JPEG, PNG, or WEBP. Max 5MB each, up to 10 photos.</p>
                                    <input type="file" name="photos[]" id="photo-input" class="hidden" multiple accept="image/jpeg,image/png,image/jpg,image/webp" required onchange="previewSelectedPhotos(this)">
                                </label>
                            </div>

                            @error('photos')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('photos.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                            <div id="live-preview-grid" class="grid grid-cols-3 gap-2 hidden pt-3 border-t border-gray-100"></div>
                        </div>

                        <div class="rounded-3xl bg-[#EEF8F8]/50 border border-[#2AA7A1]/20 p-5">
                            <p class="text-sm font-bold text-[#156F8C]">Heads up</p>
                            <p class="text-[12.5px] text-gray-500 mt-1 leading-relaxed">Your listing won't be visible to tenants until an admin reviews and approves it. That usually doesn't take long.</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('landlord.properties.index') }}" class="w-1/3 h-12 rounded-full border border-gray-200 bg-white hover:bg-gray-50 font-bold text-[13.5px] text-gray-700 flex items-center justify-center transition-colors">
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

@include('landlord.properties.partials.photo-preview-script')
@endsection