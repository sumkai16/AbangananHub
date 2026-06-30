@extends('layouts.landlord')

@section('content')
    <div class="w-full px-4 sm:px-8 lg:px-[50px] py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-sm text-[#9B9F98] mb-2">
            <a href="{{ route('landlord.properties.index') }}"
                class="hover:text-[#0F172A] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.show', $property) }}"
                class="hover:text-[#0F172A] transition-colors duration-200">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.units.index', $property) }}"
                class="hover:text-[#0F172A] transition-colors duration-200">Units</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#0F172A] font-medium">Add New Unit</span>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#0F172A] leading-tight">Add New Unit</h1>
            <p class="text-sm text-[#9B9F98] mt-1">Add a new rental unit under {{ $property->title }}.</p>
        </div>

        {{-- Flash / errors --}}
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm font-medium">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('landlord.properties.units.store', $property) }}" enctype="multipart/form-data"
            class="max-w-3xl space-y-6">
            @csrf

            {{-- Unit Details --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#9B9F98]/15 p-6">
                <h2 class="text-sm font-bold text-[#0F172A] mb-4">Unit Details</h2>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[12px] font-semibold text-[#0F172A] mb-1.5">
                            Unit Name / Number <span class="text-[#BD5434]">*</span>
                        </label>
                        <input type="text" name="unit_label" value="{{ old('unit_label') }}" required maxlength="100"
                            placeholder="e.g. Room 101, Bed A, Unit 201"
                            class="h-11 w-full rounded-xl border border-[#9B9F98]/30 px-3.5 text-[13.5px] text-[#0F172A] placeholder-[#9B9F98] focus:outline-none focus:ring-2 focus:ring-[#3B82F6]/30 transition">
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#0F172A] mb-1.5">
                            Capacity <span class="text-[#BD5434]">*</span>
                        </label>
                        <input type="number" name="occupancy_limit" value="{{ old('occupancy_limit') }}" required min="1"
                            max="100" placeholder="Maximum number of occupants"
                            class="h-11 w-full rounded-xl border border-[#9B9F98]/30 px-3.5 text-[13.5px] text-[#0F172A] placeholder-[#9B9F98] focus:outline-none focus:ring-2 focus:ring-[#3B82F6]/30 transition">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[12px] font-semibold text-[#0F172A] mb-1.5">
                            Monthly Rent (₱) <span class="text-[#BD5434]">*</span>
                        </label>
                        <input type="number" name="rental_fee" value="{{ old('rental_fee') }}" required min="500"
                            max="999999.99" step="0.01" placeholder="e.g. 3500"
                            class="h-11 w-full rounded-xl border border-[#9B9F98]/30 px-3.5 text-[13.5px] text-[#0F172A] placeholder-[#9B9F98] focus:outline-none focus:ring-2 focus:ring-[#3B82F6]/30 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-[#0F172A] mb-2">
                        Status <span class="text-[#BD5434]">*</span>
                    </label>
                    @php
                        $statusOptions = [
                            'Available' => ['label' => 'Available', 'desc' => 'Unit is vacant and ready', 'active' => 'border-emerald-300 bg-emerald-50'],
                            'Reserved' => ['label' => 'Reserved', 'desc' => 'On hold for a tenant', 'active' => 'border-amber-300 bg-amber-50'],
                            'Occupied' => ['label' => 'Occupied', 'desc' => 'Currently rented', 'active' => 'border-red-300 bg-red-50'],
                        ];
                        $inactiveClass = 'border-[#9B9F98]/25 bg-white hover:border-[#9B9F98]/40';
                    @endphp
                    <div class="grid grid-cols-3 gap-2"
                        x-data="{ status: '{{ old('availability_status', 'Available') }}' }">
                        @foreach($statusOptions as $value => $opt)
                            <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                    class="sr-only" {{ $value === 'Available' ? 'checked' : '' }}>
                                <p class="text-[13px] font-semibold text-[#0F172A]">{{ $opt['label'] }}</p>
                                <p class="text-[10.5px] text-[#9B9F98] mt-0.5 leading-snug">{{ $opt['desc'] }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Live verification capture --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#9B9F98]/15 p-6">
                <h2 class="text-sm font-bold text-[#0F172A] mb-1">Live Verification Capture</h2>
                <p class="text-[12px] text-[#9B9F98] mb-4">
                    To verify this unit actually exists at the listed property, photos and video must be captured
                    live using your device's camera — uploading existing files isn't allowed.
                </p>

                <div class="space-y-4">
                    <x-camera-capture-photo name="photos" :min="3" :max="10" />
                    <x-camera-capture-video name="video" />
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('landlord.properties.units.index', $property) }}"
                    class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#9B9F98]/30 text-[#0F172A] text-sm font-semibold hover:bg-[#F1F5F9] transition-colors duration-200">
                    Cancel
                </a>
                <button type="submit"
                    class="h-11 px-6 inline-flex items-center justify-center rounded-full bg-[#0F172A] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                    Save Unit
                </button>
            </div>
        </form>

    </div>
@endsection