@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-sm text-[#64748B] mb-2">
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

        <form method="POST" action="{{ route('landlord.properties.units.update', [$property, $unit]) }}"
            class="max-w-3xl space-y-6">
            @csrf
            @method('PUT')

            {{-- Unit Details --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-6">
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
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Unit Name / Number <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="text" name="unit_label" value="{{ old('unit_label', $unit->unit_label) }}" required
                            maxlength="100" placeholder="e.g. Room 101, Bed A, Unit 201"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Capacity <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="number" name="occupancy_limit"
                            value="{{ old('occupancy_limit', $unit->occupancy_limit) }}" required min="1" max="100"
                            placeholder="Maximum number of occupants"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Monthly Rent (₱) <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="number" name="rental_fee" value="{{ old('rental_fee', $unit->rental_fee) }}" required
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
                    <div class="grid grid-cols-3 gap-2"
                        x-data="{ status: '{{ old('availability_status', $unit->availability_status) }}' }">
                        @foreach($statusOptions as $value => $opt)
                            <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                    class="sr-only" {{ old('availability_status', $unit->availability_status) === $value ? 'checked' : '' }}>
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
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-6">
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

                @php
                    $existingPhotos = $unit->media->where('media_type', 'Image');
                    $existingVideo = $unit->media->where('media_type', 'Video')->first();
                @endphp

                @if($existingPhotos->isNotEmpty())
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mb-3">
                        @foreach($existingPhotos as $photo)
                            <div class="aspect-square rounded-lg overflow-hidden bg-[#F7FCFC] ring-1 ring-[#64748B]/15">
                                <img src="{{ $photo->media_url }}" alt="Unit photo" class="w-full h-full object-cover">
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
        </form>

    </div>
@endsection