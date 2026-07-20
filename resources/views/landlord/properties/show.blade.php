@extends('layouts.landlord')    
@section('content')
@vite(['resources/js/maps/property-map.js'])

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

    {{-- Back --}}
    <a href="{{ route('landlord.properties.index') }}"
       class="inline-flex items-center gap-2 text-sm text-[#64748B] hover:text-[#1F2937] transition-colors duration-200 mb-6">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
        Back to Properties
    </a>

    @php
        $images   = $property->media->where('media_type', 'Image')->values();
        $verified = $property->landlord->rentalBusiness()->exists();
        $reviews = $property->reviews()->with('tenant')->latest()->get();
        $mapsUrl  = ($property->latitude && $property->longitude)
            ? 'https://www.google.com/maps?q=' . $property->latitude . ',' . $property->longitude
            : 'https://www.google.com/maps?q=' . urlencode($property->address);

        // SVG donut chart values (r=40, cx=56, cy=56 → circ ≈ 251.33)
        $circ    = 251.33;
        $tot     = max($unitStats['total'], 1);
        $dOcc    = round($unitStats['occupied']  / $tot * $circ, 2);
        $dAvl    = round($unitStats['available'] / $tot * $circ, 2);
        $dRsv    = round($unitStats['reserved']  / $tot * $circ, 2);
        $dStart  = $circ * 0.25; // 12 o'clock
    @endphp

    {{-- ═══════════════════════════════════════════════════════
         TOP SECTION: image | details | status card
    ══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col lg:flex-row gap-6 mb-6">

        {{-- Hero image + thumbnails --}}
        <div class="w-full lg:w-[400px] shrink-0">
            <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden bg-[#EEF8F8]">
                @if($images->isNotEmpty())
                    <img id="main-photo"
                         src="{{ $images->first()->media_url }}"
                         alt="{{ $property->title }}"
                         class="w-full h-full object-cover cursor-pointer"
                         onclick="openLightbox(0)">
                    @if($images->count() > 1)
                        <button onclick="openLightbox(0)"
                                class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 bg-white/90 backdrop-blur-sm text-[#1F2937] text-xs font-semibold px-3 py-1.5 rounded-xl shadow-sm hover:bg-white transition-all duration-200">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5z"/>
                            </svg>
                            +{{ $images->count() }} photos
                        </button>
                    @endif
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                        <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/50">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5z"/>
                        </svg>
                        <span class="text-sm text-[#64748B]">No photos uploaded</span>
                    </div>
                @endif
            </div>

            {{-- Thumbnail strip --}}
            @if($images->count() > 1)
                <div class="flex gap-2 mt-2 overflow-x-auto pb-1">
                    @foreach($images->take(4) as $i => $img)
                        <button onclick="openLightbox({{ $i }}); highlightThumb({{ $i }})"
                                id="thumb-{{ $i }}"
                                class="shrink-0 w-[72px] h-[54px] rounded-xl overflow-hidden bg-[#EEF8F8] hover:opacity-80 transition-opacity duration-200 border-2 {{ $i === 0 ? 'border-[#1F2937]' : 'border-transparent' }}">
                            <img src="{{ $img->media_url }}" alt="{{ $property->title }} photo {{ $i + 1 }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                    @if($images->count() > 4)
                        <button onclick="openLightbox(4)"
                                class="shrink-0 w-[72px] h-[54px] rounded-xl overflow-hidden bg-[#1F2937]/80 hover:bg-[#1F2937] transition-colors duration-200 flex items-center justify-center">
                            <span class="text-white text-xs font-bold">+{{ $images->count() - 4 }}</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Property details --}}
        <div class="flex-1 min-w-0 flex flex-col" x-data="{ moreOpen: false }">

            {{-- Action buttons --}}
            <div class="flex flex-wrap items-center justify-start lg:justify-end gap-2 mb-4">
                <a href="{{ route('properties.edit', $property) }}"
                   class="inline-flex items-center gap-1.5 h-9 px-4 rounded-full border border-[#64748B]/30 text-[#1F2937] text-sm font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z"/>
                    </svg>
                    Edit Property
                </a>
                <a href="{{ route('landlord.properties.units.create', $property) }}"
                   class="inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Unit
                </a>
                <div class="relative">
                    <button @click="moreOpen = !moreOpen"
                            class="inline-flex items-center gap-1 h-9 px-3 rounded-full border border-[#64748B]/30 text-[#1F2937] text-sm font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>
                        </svg>
                        More
                    </button>
                    <div x-show="moreOpen" x-cloak @click.outside="moreOpen = false"
                         class="absolute right-0 top-11 w-48 bg-white rounded-xl shadow-lg ring-1 ring-black/5 py-1 z-20">
                        <a href="{{ route('landlord.properties.units.index', $property) }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm text-[#1F2937] hover:bg-[#EEF8F8] transition-colors">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                            </svg>
                            Manage Units
                        </a>
                        <div class="h-px bg-[#64748B]/10 mx-3 my-1"></div>
                        <form method="POST" action="{{ route('properties.destroy', $property) }}"
                              data-confirm="Delete this property?"
                              data-confirm-type="error"
                              data-confirm-message="The property and its units will be permanently deleted. This cannot be undone."
                              data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-[#DC2626] hover:bg-[#EF4444]/[0.07] transition-colors">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                                Delete Property
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Verified badge --}}
            @if($verified)
                <div class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#15803D] mb-2 w-fit">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="text-[#15803D]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                    </svg>
                    Verified Property
                </div>
            @endif

            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight mb-1">{{ $property->title }}</h1>

            <p class="text-sm text-[#64748B] flex items-center gap-1.5 mb-3 flex-wrap">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>
                </svg>
                {{ $property->address }}
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                   class="text-[#2AA7A1] hover:underline font-medium">View on Map</a>
            </p>

            @if($property->description)
                <p class="text-sm text-[#1F2937]/70 leading-relaxed mb-4 line-clamp-3">{{ $property->description }}</p>
            @endif

            {{-- Amenity chips --}}
            @if($property->amenities->isNotEmpty())
                <div class="flex flex-wrap gap-2 mt-auto pt-2">
                    @foreach($property->amenities->take(5) as $amenity)
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#1F2937] bg-[#EEF8F8] rounded-full px-3 py-1.5">
                            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="text-[#2AA7A1]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                            {{ $amenity->amenity_name }}
                        </span>
                    @endforeach
                    @if($property->amenities->count() > 5)
                        <span class="inline-flex items-center text-xs font-medium text-[#64748B] bg-[#EEF8F8] rounded-full px-3 py-1.5">
                            +{{ $property->amenities->count() - 5 }} more
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Property Status card --}}
        <div class="w-full lg:w-56 shrink-0">
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-xs font-bold text-[#64748B] uppercase tracking-widest">Property Status</h3>
                    @php
                        $statusBg = match($property->verification_status) {
                            'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                            'Pending'  => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                            'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
                            default    => 'bg-[#EEF8F8] text-[#64748B]',
                        };
                    @endphp
                    <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full {{ $statusBg }} shrink-0">
                        {{ $property->verification_status }}
                    </span>
                </div>
                <div class="space-y-2.5 text-[13px]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Property ID</span>
                        <span class="font-semibold text-[#1F2937] font-mono text-xs">PRP-{{ str_pad($property->property_id, 4, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Total Units</span>
                        <span class="font-semibold text-[#1F2937]">{{ $unitStats['total'] }} units</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Occupied</span>
                        <span class="font-semibold text-[#1F2937]">
                            {{ $unitStats['occupied'] }} units
                            @if($unitStats['total'] > 0)
                                <span class="text-[#64748B] font-normal">({{ round($unitStats['occupied'] / $unitStats['total'] * 100) }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Available</span>
                        <span class="font-semibold text-[#15803D]">
                            {{ $unitStats['available'] }} units
                            @if($unitStats['total'] > 0)
                                <span class="text-[#64748B] font-normal">({{ round($unitStats['available'] / $unitStats['total'] * 100) }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Reserved</span>
                        <span class="font-semibold text-[#B45309]">
                            {{ $unitStats['reserved'] }} units
                            @if($unitStats['total'] > 0)
                                <span class="text-[#64748B] font-normal">({{ round($unitStats['reserved'] / $unitStats['total'] * 100) }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[#64748B]">Created Date</span>
                        <span class="font-semibold text-[#1F2937]">{{ $property->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         TABS
    ══════════════════════════════════════════════════════════ --}}
    <div x-data="{ tab: 'overview' }">

        {{-- Tab nav --}}
        <div class="flex items-center gap-1 border-b border-[#64748B]/20 mb-6 overflow-x-auto">
            @foreach([
                ['key' => 'overview',     'label' => 'Overview'],
                ['key' => 'units',        'label' => 'Units (' . $property->units->count() . ')'],
                ['key' => 'amenities',    'label' => 'Amenities'],
                ['key' => 'photos',       'label' => 'Photos (' . $images->count() . ')'],
                ['key' => 'reviews',      'label' => 'Reviews (' . $reviews->count() . ')'],
                ['key' => 'activity_log', 'label' => 'Activity Log'],
            ] as $t)
                <button @click="tab = '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}' ? 'border-b-2 border-[#1F2937] text-[#1F2937] font-semibold' : 'text-[#64748B] hover:text-[#1F2937]'"
                        class="shrink-0 pb-3 px-4 text-sm transition-colors duration-200">
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ─── Overview tab ─────────────────────────────────── --}}
        <div x-show="tab === 'overview'" x-cloak>

            {{-- 3-column: Property Info | Location | Statistics --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">

                {{-- Property Information --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h3 class="text-sm font-bold text-[#1F2937] mb-4 flex items-center gap-2">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B]">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-3.75h.008v.008H12V8.25z"/>
                        </svg>
                        Property Information
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-[10px] text-[#64748B] font-semibold uppercase tracking-wider mb-0.5">Property Type</p>
                            <p class="text-[#1F2937] font-semibold">{{ $property->property_type }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-[#64748B] font-semibold uppercase tracking-wider mb-0.5">Address</p>
                            <p class="text-[#1F2937] font-semibold">{{ $property->address }}</p>
                        </div>
                        @if($property->description)
                            <div>
                                <p class="text-[10px] text-[#64748B] font-semibold uppercase tracking-wider mb-0.5">Property Description</p>
                                <p class="text-[#1F2937]/75 leading-relaxed text-xs">{{ $property->description }}</p>
                            </div>
                        @endif
                        @if($property->amenities->isNotEmpty())
                            <div>
                                <p class="text-[10px] text-[#64748B] font-semibold uppercase tracking-wider mb-1.5">Amenities</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($property->amenities->take(5) as $amenity)
                                        <span class="text-[11px] text-[#1F2937] bg-[#EEF8F8] rounded-full px-2.5 py-0.5">{{ $amenity->amenity_name }}</span>
                                    @endforeach
                                    @if($property->amenities->count() > 5)
                                        <span class="text-[11px] text-[#64748B] bg-[#EEF8F8] rounded-full px-2.5 py-0.5">+{{ $property->amenities->count() - 5 }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Location --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 flex flex-col">
                    <h3 class="text-sm font-bold text-[#1F2937] mb-3 flex items-center gap-2">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B]">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>
                        </svg>
                        Location
                    </h3>
                    <div id="property-map"
                         data-lat="{{ $property->latitude }}"
                         data-lng="{{ $property->longitude }}"
                         data-title="{{ $property->title }}"
                         class="w-full flex-1 min-h-[160px] rounded-xl overflow-hidden border border-[#64748B]/20 bg-[#EEF8F8] mb-3">
                    </div>
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 w-full h-9 rounded-xl border border-[#64748B]/30 text-sm text-[#1F2937] font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B]">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>
                        </svg>
                        Open in Google Maps
                    </a>
                </div>

                {{-- Statistics (SVG donut) --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                    <h3 class="text-sm font-bold text-[#1F2937] mb-4 flex items-center gap-2">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B]">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zm4.5-7.5A1.125 1.125 0 0 1 8.625 4.5h2.25c.621 0 1.125.504 1.125 1.125v13.5c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 7.5 19.125V5.625zm4.5-3A1.125 1.125 0 0 1 13.125 1.5h2.25C16.496 1.5 17 2.004 17 2.625v17.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 12 19.875V2.625z"/>
                        </svg>
                        Statistics
                    </h3>

                    {{-- Donut chart --}}
                    <div class="flex items-center justify-center mb-4">
                        <div class="relative w-32 h-32">
                            <svg viewBox="0 0 112 112" class="w-full h-full -rotate-90">
                                {{-- Track --}}
                                <circle cx="56" cy="56" r="40" fill="none" stroke="#EEF8F8" stroke-width="16"/>
                                @if($unitStats['total'] > 0)
                                    {{-- Occupied (emerald) --}}
                                    @if($dOcc > 0)
                                        <circle cx="56" cy="56" r="40" fill="none" stroke="#10b981" stroke-width="16"
                                                stroke-dasharray="{{ $dOcc }} {{ $circ - $dOcc }}"
                                                stroke-dashoffset="{{ $dStart }}"/>
                                    @endif
                                    {{-- Available (blue) --}}
                                    @if($dAvl > 0)
                                        <circle cx="56" cy="56" r="40" fill="none" stroke="#2AA7A1" stroke-width="16"
                                                stroke-dasharray="{{ $dAvl }} {{ $circ - $dAvl }}"
                                                stroke-dashoffset="{{ $dStart - $dOcc }}"/>
                                    @endif
                                    {{-- Reserved (amber) --}}
                                    @if($dRsv > 0)
                                        <circle cx="56" cy="56" r="40" fill="none" stroke="#f59e0b" stroke-width="16"
                                                stroke-dasharray="{{ $dRsv }} {{ $circ - $dRsv }}"
                                                stroke-dashoffset="{{ $dStart - $dOcc - $dAvl }}"/>
                                    @endif
                                @else
                                    <circle cx="56" cy="56" r="40" fill="none" stroke="#E2E8F0" stroke-width="16"/>
                                @endif
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-2xl font-bold text-[#1F2937] leading-none">{{ $unitStats['total'] }}</span>
                                <span class="text-[10px] text-[#64748B] mt-0.5">Total Units</span>
                            </div>
                        </div>
                    </div>

                    {{-- Legend --}}
                    <div class="space-y-1.5">
                        @php
                            $legend = [
                                ['label' => 'Occupied',    'count' => $unitStats['occupied'],  'color' => 'bg-[#22C55E]'],
                                ['label' => 'Available',   'count' => $unitStats['available'], 'color' => 'bg-[#2AA7A1]'],
                                ['label' => 'Reserved',    'count' => $unitStats['reserved'],  'color' => 'bg-[#FBBF24]'],
                                ['label' => 'Maintenance', 'count' => 0,                       'color' => 'bg-[#64748B]/40'],
                            ];
                        @endphp
                        @foreach($legend as $row)
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $row['color'] }} shrink-0"></span>
                                    <span class="text-[#64748B]">{{ $row['label'] }}</span>
                                </div>
                                <span class="font-semibold text-[#1F2937]">
                                    {{ $row['count'] }} ({{ $unitStats['total'] > 0 ? round($row['count'] / $unitStats['total'] * 100) : 0 }}%)
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('landlord.properties.units.index', $property) }}"
                       class="mt-4 flex items-center justify-center gap-1.5 text-xs font-semibold text-[#2AA7A1] hover:underline">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zm4.5-7.5A1.125 1.125 0 0 1 8.625 4.5h2.25c.621 0 1.125.504 1.125 1.125v13.5c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 7.5 19.125V5.625zm4.5-3A1.125 1.125 0 0 1 13.125 1.5h2.25C16.496 1.5 17 2.004 17 2.625v17.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0 1 12 19.875V2.625z"/>
                        </svg>
                        View Full Analytics
                    </a>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-[#1F2937]">Recent Activity</h3>
                    <a href="{{ route('landlord.properties.units.index', $property) }}"
                       class="text-xs text-[#2AA7A1] font-medium hover:underline flex items-center gap-1">
                        View All Activity
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                        </svg>
                    </a>
                </div>

                @php
                    $recentUnits = $property->units->sortByDesc('updated_at')->take(3);
                @endphp

                @if($recentUnits->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach($recentUnits as $recentUnit)
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-[#EEF8F8]/50">
                                <div class="w-8 h-8 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 mt-0.5">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#2AA7A1]">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold text-[#1F2937] leading-snug">Unit "{{ $recentUnit->unit_label }}" updated</p>
                                    <p class="text-[11px] text-[#64748B] mt-0.5">{{ $recentUnit->updated_at->format('M d, Y \a\t h:i A') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-[#64748B]/40 mb-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                        <p class="text-sm text-[#64748B]">No recent activity</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ─── Units tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'units'" x-cloak>
            @if($property->units->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-[#EEF8F8]/40 flex flex-col items-center justify-center py-14 text-center">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z"/>
                    </svg>
                    <p class="text-sm font-semibold text-[#1F2937]">No units added yet</p>
                    <p class="text-xs text-[#64748B] mt-1 mb-4">Add units so tenants can reserve specific spaces.</p>
                    <a href="{{ route('landlord.properties.units.create', $property) }}"
                       class="inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-[#1F2937] text-white text-xs font-semibold hover:brightness-95 transition-all duration-200">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add First Unit
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($property->units as $unit)
                        @php
                            $thumb = $unit->media->first();
                            [$avBg] = match($unit->availability_status) {
                                'Available' => ['bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25'],
                                'Reserved'  => ['bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35'],
                                'Occupied'  => ['bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25'],
                                default     => ['bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20'],
                            };
                            [$vrBg] = match($unit->verification_status) {
                                'Approved' => ['bg-[#22C55E]/[0.07] text-[#15803D]'],
                                'Pending'  => ['bg-[#FBBF24]/[0.10] text-[#B45309]'],
                                'Rejected' => ['bg-[#EF4444]/[0.07] text-[#DC2626]'],
                                default    => ['bg-[#EEF8F8] text-[#64748B]'],
                            };
                        @endphp
                        <div class="flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/15 hover:shadow-md transition-shadow duration-300">
                            <div class="relative h-36 bg-[#EEF8F8] overflow-hidden">
                                @if($thumb)
                                    <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/40">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5z"/>
                                        </svg>
                                    </div>
                                @endif
                                <span class="absolute top-2 right-2 text-[10px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ $avBg }}">
                                    {{ $unit->availability_status }}
                                </span>
                            </div>
                            <div class="p-3 flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[13px] font-bold text-[#1F2937]">{{ $unit->unit_label }}</p>
                                        <p class="text-[12px] text-[#EF4444] font-semibold mt-0.5">
                                            ₱{{ number_format($unit->rental_fee, 0) }}<span class="text-[#64748B] font-normal">/mo</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-medium px-2 py-0.5 rounded-full {{ $vrBg }}">
                                        {{ $unit->verification_status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-[#64748B] flex items-center gap-1">
                                    <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    Up to {{ $unit->occupancy_limit }} {{ Str::plural('person', $unit->occupancy_limit) }}
                                </p>
                                <div class="flex items-center gap-2 pt-2 border-t border-[#64748B]/10">
                                    <a href="{{ route('landlord.properties.units.edit', [$property, $unit]) }}"
                                       class="flex-1 h-8 flex items-center justify-center gap-1 rounded-full border border-[#64748B]/30 text-[#1F2937] text-[11px] font-medium hover:bg-[#EEF8F8] transition-colors duration-200">
                                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z"/>
                                        </svg>
                                        Edit
                                    </a>
                                    <form method="POST"
                                          action="{{ route('landlord.properties.units.destroy', [$property, $unit]) }}"
                                          data-confirm="Remove {{ $unit->unit_label }}?"
                                          data-confirm-type="error"
                                          data-confirm-message="The unit will be permanently removed. This cannot be undone."
                                          data-confirm-button="Remove unit">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="h-8 px-3 flex items-center gap-1 rounded-full border border-[#EF4444]/25 text-[#DC2626] text-[11px] font-medium hover:bg-[#EF4444]/[0.07] transition-colors duration-200">
                                            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Amenities tab ──────────────────────────────────── --}}
        <div x-show="tab === 'amenities'" x-cloak>
            @if($property->amenities->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-[#EEF8F8]/40 flex flex-col items-center justify-center py-14 text-center">
                    <p class="text-sm font-semibold text-[#1F2937]">No amenities listed</p>
                    <p class="text-xs text-[#64748B] mt-1">Edit this property to add amenities.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($property->amenities as $amenity)
                        <div class="flex items-center gap-3 bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] px-4 py-3">
                            <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="text-[#2AA7A1]">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-[#1F2937]">{{ $amenity->amenity_name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Photos tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'photos'" x-cloak>
            @if($images->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-[#EEF8F8]/40 flex flex-col items-center justify-center py-14 text-center">
                    <p class="text-sm font-semibold text-[#1F2937]">No photos uploaded</p>
                    <p class="text-xs text-[#64748B] mt-1">Edit this property to add photos.</p>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($images as $i => $img)
                        <button onclick="openLightbox({{ $i }})"
                                class="aspect-square rounded-xl overflow-hidden bg-[#EEF8F8] hover:opacity-90 transition-opacity duration-200">
                            <img src="{{ $img->media_url }}" alt="{{ $property->title }} photo {{ $i + 1 }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Reviews tab ─────────────────────────────────────── --}}
        <div x-show="tab === 'reviews'" x-cloak>
            @php
                $visibleReviews = $reviews->where('is_hidden', false);
                $reviewAvg = $visibleReviews->count() > 0 ? round($visibleReviews->avg('rating'), 1) : null;
            @endphp

            @if($visibleReviews->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-[#EEF8F8]/40 flex flex-col items-center justify-center py-14 text-center">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/>
                    </svg>
                    <p class="text-sm font-semibold text-[#1F2937]">No reviews yet</p>
                    <p class="text-xs text-[#64748B] mt-1">Reviews will appear here once tenants submit them.</p>
                </div>
            @else
                {{-- Summary bar --}}
                <div class="flex items-center gap-4 mb-5">
                    <div class="flex items-center gap-1.5 bg-[#EEF8F8] px-3 py-1.5 rounded-lg">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="none">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-sm font-bold text-[#1F2937]">{{ $reviewAvg }}</span>
                        <span class="text-xs text-[#64748B]">/ 5</span>
                    </div>
                    <span class="text-sm text-[#64748B]">{{ $visibleReviews->count() }} {{ Str::plural('review', $visibleReviews->count()) }}</span>
                </div>

                <div class="space-y-3">
                    @foreach($visibleReviews as $review)
                        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 text-sm font-bold text-[#156F8C]">
                                        {{ strtoupper(substr($review->tenant->first_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1F2937]">{{ $review->tenant->first_name }} {{ $review->tenant->last_name }}</p>
                                        <p class="text-[11px] text-[#64748B]">{{ $review->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5 shrink-0">
                                    @for($s = 1; $s <= 5; $s++)
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="{{ $s <= $review->rating ? '#f59e0b' : 'none' }}" stroke="#f59e0b" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>

                            @if($review->review_comment)
                                <p class="text-sm text-[#1F2937]/75 leading-relaxed mb-3">{{ $review->review_comment }}</p>
                            @endif

                            {{-- Existing landlord reply --}}
                            @if($review->landlord_reply)
                                <div class="mt-3 bg-[#EEF8F8] rounded-xl p-3.5">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <div class="w-6 h-6 rounded-lg bg-[#156F8C] text-white text-[10px] font-black flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}
                                        </div>
                                        <span class="text-xs font-semibold text-[#1F2937]">You</span>
                                        <span class="text-[10px] text-[#64748B]">{{ $review->landlord_replied_at->format('M d, Y') }}</span>
                                    </div>
                                    <p class="text-sm text-[#1F2937]/75 leading-relaxed pl-8">{{ $review->landlord_reply }}</p>
                                </div>

                                {{-- Edit reply --}}
                                <div class="mt-2 pl-8" x-data="{ editing: false }">
                                    <button type="button" x-on:click="editing = !editing"
                                        class="text-xs font-medium text-[#64748B] hover:text-[#1F2937] transition-colors">
                                        Edit reply
                                    </button>
                                    <div x-show="editing" x-cloak class="mt-2">
                                        <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                class="w-full border border-[#64748B]/20 rounded-xl px-3 py-2 text-sm text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#156F8C]/20 focus:border-[#156F8C] transition-all resize-none">{{ $review->landlord_reply }}</textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-lg bg-[#156F8C] hover:brightness-95 text-white text-xs font-semibold transition-all">
                                                    Update Reply
                                                </button>
                                                <button type="button" x-on:click="editing = false"
                                                    class="px-4 py-2 rounded-lg bg-[#EEF8F8] text-[#64748B] text-xs font-medium hover:bg-[#E2E8F0] transition-all">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- Reply form --}}
                                <div class="mt-3" x-data="{ showReply: false }">
                                    <button type="button" x-on:click="showReply = !showReply"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#156F8C] hover:brightness-95 transition-all">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                        </svg>
                                        Reply to this review
                                    </button>
                                    <div x-show="showReply" x-cloak class="mt-2">
                                        <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                placeholder="Write your response..."
                                                class="w-full border border-[#64748B]/20 rounded-xl px-3 py-2 text-sm text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#156F8C]/20 focus:border-[#156F8C] transition-all resize-none"></textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-lg bg-[#156F8C] hover:brightness-95 text-white text-xs font-semibold transition-all">
                                                    Post Reply
                                                </button>
                                                <button type="button" x-on:click="showReply = false"
                                                    class="px-4 py-2 rounded-lg bg-[#EEF8F8] text-[#64748B] text-xs font-medium hover:bg-[#E2E8F0] transition-all">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ─── Activity Log tab ───────────────────────────────── --}}
        <div x-show="tab === 'activity_log'" x-cloak>
            <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-[#EEF8F8]/40 flex flex-col items-center justify-center py-14 text-center">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/50 mb-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
                <p class="text-sm font-semibold text-[#1F2937]">Activity log coming soon</p>
                <p class="text-xs text-[#64748B] mt-1">A full history of actions on this property will appear here.</p>
            </div>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     Lightbox
══════════════════════════════════════════════════════════════ --}}
@if($images->isNotEmpty())
    <div id="lightbox"
         class="fixed inset-0 z-[999] bg-black/90 hidden items-center justify-center"
         onclick="closeLightbox()">
        <button type="button"
                class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="closeLightbox()">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
        <button type="button"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(-1)">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <img id="lb-img" src="" alt=""
             class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg select-none"
             onclick="event.stopPropagation()">
        <button type="button"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(1)">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 text-white/70 text-[13px] font-medium">
            <span id="lb-counter"></span>
        </div>
    </div>
@endif

@push('scripts')
<script>
(function () {
    const mediaUrls = @json($images->pluck('media_url')->values());
    const total = mediaUrls.length;
    let currentIndex = 0;

    const lightbox  = document.getElementById('lightbox');
    const lbImg     = document.getElementById('lb-img');
    const lbCounter = document.getElementById('lb-counter');

    function updateLightbox() {
        if (lbImg)     lbImg.src = mediaUrls[currentIndex];
        if (lbCounter) lbCounter.textContent = (currentIndex + 1) + ' / ' + total;
    }

    window.openLightbox = function (index) {
        currentIndex = index;
        updateLightbox();
        if (lightbox) { lightbox.classList.remove('hidden'); lightbox.classList.add('flex'); }
        document.body.style.overflow = 'hidden';
    };

    window.closeLightbox = function () {
        if (lightbox) { lightbox.classList.add('hidden'); lightbox.classList.remove('flex'); }
        document.body.style.overflow = '';
    };

    window.shiftLightbox = function (dir) {
        currentIndex = (currentIndex + dir + total) % total;
        updateLightbox();
    };

    window.highlightThumb = function (index) {
        document.querySelectorAll('[id^="thumb-"]').forEach(function(el, i) {
            el.classList.toggle('border-[#1F2937]', i === index);
            el.classList.toggle('border-transparent', i !== index);
        });
    };

    document.addEventListener('keydown', function (e) {
        if (!lightbox || lightbox.classList.contains('hidden')) return;
        if (e.key === 'ArrowRight') shiftLightbox(1);
        if (e.key === 'ArrowLeft')  shiftLightbox(-1);
        if (e.key === 'Escape')     closeLightbox();
    });
})();
</script>
@endpush

@endsection
