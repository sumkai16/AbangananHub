@php
    $isEdit = isset($property);
    $types = [
        'Bedspace' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10m0-10h18M3 7l3-3h12l3 3M3 17h18M7 17v-4a2 2 0 012-2h6a2 2 0 012 2v4"/>',
        'Room' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 21V7a2 2 0 012-2h10a2 2 0 012 2v14M9 21V11h6v10"/>',
        'Apartment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        'House' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/>',
    ];
@endphp

<div class="space-y-8">

    {{-- Property Type --}}
    <div>
        <label class="block text-sm font-bold text-[#156F8C] mb-3">Property Type</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach($types as $type => $svgPath)
                <label class="relative cursor-pointer">
                    <input type="radio" name="property_type" value="{{ $type }}" {{ old('property_type', $property->property_type ?? '') === $type ? 'checked' : '' }} class="peer sr-only">
                    <div
                        class="flex flex-col items-center gap-2 py-4 px-2 rounded-xl border-2 border-gray-200 text-gray-500 peer-checked:border-[#FF8A65] peer-checked:bg-[#F7FCFC] peer-checked:text-[#156F8C] transition-colors">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">{!! $svgPath !!}</svg>
                        <span class="text-xs font-semibold">{{ $type }}</span>
                    </div>
                </label>
            @endforeach
        </div>
        @error('property_type')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
    </div>

    {{-- Title --}}
    <div>
        <label for="title" class="block text-sm font-bold text-[#156F8C] mb-2">Listing Title</label>
        <input type="text" id="title" name="title" value="{{ old('title', $property->title ?? '') }}"
            placeholder="e.g. Cozy 2BR Apartment near IT Park"
            class="w-full h-12 px-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
        @error('title')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
    </div>

    {{-- Description --}}
    <div>
        <label for="description" class="block text-sm font-bold text-[#156F8C] mb-2">Description</label>
        <textarea id="description" name="description" rows="5"
            placeholder="Describe the space, what's included, house rules, nearby landmarks..."
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">{{ old('description', $property->description ?? '') }}</textarea>
        @error('description')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
    </div>

    {{-- Address --}}
    <div>
        <label for="address" class="block text-sm font-bold text-[#156F8C] mb-2">Address</label>
        <input type="text" id="address" name="address" value="{{ old('address', $property->address ?? '') }}"
            placeholder="e.g. 123 Gorordo Ave, Cebu City"
            class="w-full h-12 px-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
        @error('address')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
    </div>

    {{-- Coordinates --}}
    <div>
        <label class="block text-sm font-bold text-[#156F8C] mb-2">Coordinates</label>
        <p class="text-xs text-[#9B9F98] mb-3">
            Map picker isn't built yet — for now, open
            <a href="https://www.google.com/maps" target="_blank"
                class="text-[#FF8A65] font-semibold hover:underline">Google Maps</a>,
            right-click your property's location, and copy the two numbers shown at the top.
        </p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <input type="number" step="0.0000001" name="latitude" id="latitude"
                    value="{{ old('latitude', $property->latitude ?? '') }}" placeholder="10.3157"
                    class="w-full h-12 px-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
                <span class="text-xs text-[#9B9F98] mt-1 block">Latitude</span>
                @error('latitude')<p class="mt-1 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="number" step="0.0000001" name="longitude" id="longitude"
                    value="{{ old('longitude', $property->longitude ?? '') }}" placeholder="123.8854"
                    class="w-full h-12 px-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
                <span class="text-xs text-[#9B9F98] mt-1 block">Longitude</span>
                @error('longitude')<p class="mt-1 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- Rental fee + Occupancy --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="rental_fee" class="block text-sm font-bold text-[#156F8C] mb-2">Monthly Rent</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                <input type="number" step="0.01" id="rental_fee" name="rental_fee"
                    value="{{ old('rental_fee', $property->rental_fee ?? '') }}" placeholder="5000"
                    class="w-full h-12 pl-8 pr-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
            </div>
            @error('rental_fee')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="occupancy_limit" class="block text-sm font-bold text-[#156F8C] mb-2">Max Occupants</label>
            <input type="number" id="occupancy_limit" name="occupancy_limit"
                value="{{ old('occupancy_limit', $property->occupancy_limit ?? '') }}" placeholder="2"
                class="w-full h-12 px-4 rounded-xl border border-gray-200 focus:border-[#FF8A65] focus:ring-2 focus:ring-[#FF8A65]/30 outline-none text-sm">
            @error('occupancy_limit')<p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Existing photos (edit only, read-only for now) --}}
    @if($isEdit && $property->media->count() > 0)
        <div>
            <label class="block text-sm font-bold text-[#156F8C] mb-2">Current Photos</label>
            <p class="text-xs text-[#9B9F98] mb-3">Removing individual photos isn't supported yet — this is add-only for
                now.</p>
            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                @foreach($property->media as $media)
                    @php $url = str_starts_with($media->media_url, 'http') ? $media->media_url : Storage::url($media->media_url); @endphp
                    <img src="{{ $url }}" class="w-full h-20 object-cover rounded-lg border border-gray-200">
                @endforeach
            </div>
        </div>
    @endif

    {{-- Photo upload --}}
    <div>
        <label class="block text-sm font-bold text-[#156F8C] mb-2">{{ $isEdit ? 'Add More Photos' : 'Photos' }}</label>
        <label for="photos"
            class="flex flex-col items-center justify-center gap-2 h-32 rounded-xl border-2 border-dashed border-gray-300 hover:border-[#FF8A65] cursor-pointer transition-colors">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                class="text-gray-400">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3" />
            </svg>
            <span id="photo-label" class="text-sm