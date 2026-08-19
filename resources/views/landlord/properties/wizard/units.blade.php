@extends('layouts.landlord')

@section('content')
<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if(session('success'))
            <div class="max-w-2xl mb-6 px-4 py-3 rounded-xl bg-[#22C55E]/[0.08] border border-[#22C55E]/25 text-[#15803D] text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11">

            <x-property-wizard-stepper current="units" :property="$property" />

            <div class="min-w-0">
                <div class="max-w-2xl flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 5 of 6</p>
                        <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">Add your units</h1>
                        <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">
                            The individual rooms or spaces available for rent — at least one is required.
                            @if($targetUnits)
                                <span class="text-[#94A3B8]">({{ $property->units->count() }} of {{ $targetUnits }} planned)</span>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('landlord.properties.units.create', $property) }}?from=wizard"
                        class="inline-flex items-center gap-1.5 h-10 px-5 rounded-xl bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Unit
                    </a>
                </div>

                <div class="mt-7 max-w-2xl">
                    @if($property->units->isEmpty())
                        <div class="border border-dashed border-[#E2E8F0] rounded-2xl p-10 text-center">
                            <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6z" />
                                </svg>
                            </div>
                            <p class="text-[14px] font-semibold text-[#1F2937]">No units added yet</p>
                            <p class="text-[13px] text-[#64748B] mt-1 mb-5">This listing can't be submitted without at least one.</p>
                            <a href="{{ route('landlord.properties.units.create', $property) }}?from=wizard"
                                class="inline-flex items-center gap-1.5 h-10 px-5 rounded-xl bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                                Add Unit
                            </a>
                        </div>
                    @else
                        <div class="space-y-2.5">
                            @foreach($property->units as $unit)
                                @php
                                    $hasPhoto = $unit->media->where('media_type', 'Image')->isNotEmpty();
                                    $statusDot = match($unit->availability_status) {
                                        'Available' => '#22C55E', 'Reserved' => '#FBBF24', 'Occupied' => '#EF4444', default => '#94A3B8',
                                    };
                                @endphp
                                <div class="border border-[#E2E8F0] rounded-xl p-3.5 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-11 h-11 rounded-lg bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($hasPhoto)
                                                <img src="{{ $unit->media->firstWhere('media_type', 'Image')->media_url }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[#EF4444]">
                                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">{{ $unit->unit_label }}</p>
                                            <p class="text-[12px] text-[#64748B] flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $statusDot }}"></span>
                                                ₱{{ number_format($unit->rental_fee, 0) }} / month
                                                @unless($hasPhoto)
                                                    <span class="text-[#EF4444] font-medium">· needs a photo</span>
                                                @endunless
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ route('landlord.properties.units.edit', [$property, $unit]) }}?from=wizard"
                                        class="text-[12.5px] font-semibold text-[#156F8C] hover:text-[#0E5670] transition-colors shrink-0">
                                        Edit
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-7 pt-5 border-t border-[#E2E8F0] max-w-2xl flex items-center gap-3">
                    <a href="{{ route('properties.wizard.documents', $property) }}"
                        class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                        Back
                    </a>
                    <a href="{{ route('properties.wizard.review', $property) }}"
                        class="ml-auto px-9 py-3 rounded-xl text-sm font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all duration-150">
                        Continue to Review
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
