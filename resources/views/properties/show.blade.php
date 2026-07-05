@extends('layouts.app', ['searchBar' => false])

@section('content')
    @vite(['resources/js/maps/property-map.js'])

    @php
        $approvedUnits = $property->units->where('verification_status', 'Approved')->values();
        $availableUnits = $approvedUnits->where('availability_status', 'Available')->values();
        $isOwner = auth()->check() && (int) auth()->id() === (int) $property->landlord_id;

        $minFee = $availableUnits->min('rental_fee');
        $maxFee = $availableUnits->max('rental_fee');

        $avgRating = $property->reviews->count() > 0 ? round($property->reviews->avg('rating'), 1) : null;

        $unitsPayload = $approvedUnits->map(function ($unit) use ($property) {
            $hasActiveReservation = auth()->check() && \App\Models\Reservation::where('unit_id', $unit->unit_id)
                ->where('tenant_id', auth()->id())
                ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                ->exists();

            return [
                'id' => $unit->unit_id,
                'label' => $unit->unit_label,
                'type' => $property->property_type,
                'price' => number_format($unit->rental_fee),
                'thumb' => optional($unit->media->first())->media_url,
                'occupancy' => $unit->occupancy_limit,
                'size' => $unit->size ?? null,
                'available' => $unit->availability_status === 'Available',
                'hasActive' => $hasActiveReservation,
            ];
        })->values();

        $defaultUnitId = optional($unitsPayload->firstWhere('available', true))['id'] ?? null;
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8" x-data="{
                tab: 'overview',
                mode: 'inquiry',
                units: @js($unitsPayload),
                selectedUnit: @js($defaultUnitId),
                msg: '',
                descExpanded: false,
                allAmenities: false,
                get selected() { return this.units.find(u => u.id === this.selectedUnit) || null },
                selectUnit(id) {
                    const u = this.units.find(x => x.id === id);
                    if (u && u.available) this.selectedUnit = id;
                },
                goTab(name) {
                    this.tab = name;
                    this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                }
            }">

        {{-- ===== BREADCRUMB ===== --}}
        <nav class="mb-6 flex items-center gap-1.5 text-sm font-semibold text-[#64748B]" aria-label="Breadcrumb">
            <a href="{{ url('/') }}" class="hover:text-[#1F2937] transition-colors">Home</a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('properties.index') }}" class="hover:text-[#1F2937] transition-colors">Properties</a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] truncate max-w-[200px] sm:max-w-xs">{{ $property->title }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- ===================================================== --}}
            {{-- LEFT COLUMN --}}
            {{-- ===================================================== --}}
            <div class="lg:col-span-7 xl:col-span-8 space-y-8">

                {{-- ===== 1. IMAGE GALLERY ===== --}}
                @if($property->media->count() > 0)
                    <div>
                        <div
                            class="relative rounded-3xl overflow-hidden bg-[#E2E8F0] aspect-[16/9] border border-[#EEF8F8] shadow-sm group">
                            <img id="hero-img" src="{{ $property->media->first()->media_url }}" alt="{{ $property->title }}"
                                class="w-full h-full object-cover cursor-pointer transition-opacity duration-150"
                                onclick="openLightboxAtHero()">

                            @if($property->media->count() > 1)
                                <button type="button" onclick="shiftHero(-1)"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:brightness-95 text-[#1F2937] flex items-center justify-center shadow-sm transition-all"
                                    aria-label="Previous photo">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                    </svg>
                                </button>
                                <button type="button" onclick="shiftHero(1)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 hover:brightness-95 text-[#1F2937] flex items-center justify-center shadow-sm transition-all"
                                    aria-label="Next photo">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            @endif

                            <button type="button" onclick="openLightboxAtHero()"
                                class="absolute bottom-4 right-4 bg-white/90 text-[#1F2937] text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm hover:brightness-95 transition-all flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                                </svg>
                                Show all photos
                            </button>
                        </div>

                        {{-- Thumbnail strip --}}
                        @if($property->media->count() > 1)
                            <div class="grid grid-cols-5 gap-2 mt-2">
                                @foreach($property->media->take(5) as $i => $mediaItem)
                                    <button type="button" id="thumb-{{ $i }}" onclick="setHero({{ $i }})"
                                        class="relative aspect-[4/3] rounded-xl overflow-hidden border-2 transition-all {{ $i === 0 ? 'border-[#2AA7A1]' : 'border-transparent opacity-60' }}">
                                        <img src="{{ $mediaItem->media_url }}" alt="{{ $property->title }} photo {{ $i + 1 }}"
                                            class="w-full h-full object-cover">
                                        @if($i === 4 && $property->media->count() > 5)
                                            <span
                                                class="absolute inset-0 bg-[#1F2937]/60 flex items-center justify-center text-white text-sm font-black pointer-events-none">
                                                +{{ $property->media->count() - 5 }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div
                        class="rounded-3xl bg-[#E2E8F0] aspect-[16/9] border border-dashed border-[#64748B] flex flex-col items-center justify-center text-[#64748B] shadow-sm">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-2 text-sm font-semibold">No photos available yet</p>
                    </div>
                @endif

                {{-- ===== 2. PROPERTY HEADER ===== --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2.5 mb-2">
                        <h1 class="text-2xl sm:text-3xl font-black text-[#1F2937] tracking-tight">
                            {{ $property->title }}
                        </h1>
                        @if($property->landlord->rentalBusiness)
                            <span
                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-[#EEF8F8] text-[#1F2937] shadow-sm">
                                <svg class="w-3.5 h-3.5 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Verified Landlord
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm font-medium text-[#64748B] mb-3">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $property->address }}
                        </span>
                        <button type="button" x-on:click="goTab('location')"
                            class="text-[#EF4444] font-bold hover:brightness-95 transition-all underline underline-offset-2">
                            View on map
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5 mb-4">
                        <svg class="w-4 h-4 text-[#EF4444]" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                        @if($avgRating)
                            <span class="text-sm font-black text-[#1F2937]">{{ $avgRating }}</span>
                            <button type="button" x-on:click="goTab('reviews')"
                                class="text-sm font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors underline underline-offset-2">
                                ({{ $property->reviews->count() }} {{ Str::plural('review', $property->reviews->count()) }})
                            </button>
                        @else
                            <span class="text-sm font-semibold text-[#64748B]">No reviews yet</span>
                        @endif
                    </div>

                    @if($property->description)
                        @if(Str::length($property->description) > 200)
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="!descExpanded">
                                {{ Str::limit($property->description, 200) }}</p>
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="descExpanded" x-cloak>
                                {{ $property->description }}</p>
                            <button type="button" x-on:click="descExpanded = !descExpanded"
                                class="mt-1.5 text-sm font-bold text-[#EF4444] hover:brightness-95 transition-all underline underline-offset-2"
                                x-text="descExpanded ? 'Read less' : 'Read more'"></button>
                        @else
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">{{ $property->description }}</p>
                        @endif
                    @endif
                </div>

                {{-- ===== 3. AMENITY ICON ROW ===== --}}
                @if($property->amenities->count() > 0)
                    <div class="pt-6 border-t border-[#EEF8F8]">
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                            @foreach($property->amenities as $i => $amenity)
                                <div class="flex items-center gap-2 text-sm font-semibold text-[#1F2937]" @if($i >= 6)
                                x-show="allAmenities" x-cloak @endif>
                                    <span class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    {{ $amenity->amenity_name }}
                                </div>
                            @endforeach

                            @if($property->amenities->count() > 6)
                                <button type="button" x-show="!allAmenities" x-on:click="allAmenities = true"
                                    class="text-xs font-bold text-[#1F2937] bg-[#E2E8F0] hover:brightness-95 px-3 py-1.5 rounded-full transition-all">
                                    +{{ $property->amenities->count() - 6 }} more
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ===== 4. PROPERTY INFO GRID ===== --}}
                <div class="pt-6 border-t border-[#EEF8F8]">
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-4">
                        <div class="bg-[#E2E8F0] rounded-2xl p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Property Type</p>
                            <p class="text-sm font-bold text-[#1F2937]">{{ $property->property_type }}</p>
                        </div>
                        <div class="bg-[#E2E8F0] rounded-2xl p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Price Range</p>
                            <p class="text-sm font-bold text-[#1F2937]">
                                @if($minFee)
                                    ₱{{ number_format($minFee) }}@if($maxFee && $maxFee != $minFee) –
                                    ₱{{ number_format($maxFee) }}@endif
                                    <span class="text-[#64748B] font-semibold">/ month</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div class="bg-[#E2E8F0] rounded-2xl p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Available Units
                            </p>
                            <p class="text-sm font-bold text-[#1F2937]">{{ $availableUnits->count() }}</p>
                        </div>
                        <div class="bg-[#E2E8F0] rounded-2xl p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Landlord</p>
                            <p class="text-sm font-bold text-[#1F2937]">{{ $property->landlord->first_name }}
                                {{ $property->landlord->last_name }}</p>
                        </div>
                    </div>
                </div>

                {{-- ===== 5. TABBED CONTENT ===== --}}
                <div class="pt-6 border-t border-[#EEF8F8]">
                    {{-- Tab bar --}}
                    <div class="flex items-center gap-1 bg-[#E2E8F0] p-1 rounded-xl w-fit mb-6">
                        <button type="button" x-on:click="goTab('overview')"
                            :class="tab === 'overview' ? 'bg-white text-[#1F2937] shadow-sm' : 'text-[#64748B]'"
                            class="text-sm font-bold px-4 py-2 rounded-lg transition-all">
                            Overview
                        </button>
                        <button type="button" x-on:click="goTab('location')"
                            :class="tab === 'location' ? 'bg-white text-[#1F2937] shadow-sm' : 'text-[#64748B]'"
                            class="text-sm font-bold px-4 py-2 rounded-lg transition-all">
                            Location
                        </button>
                        <button type="button" x-on:click="goTab('reviews')"
                            :class="tab === 'reviews' ? 'bg-white text-[#1F2937] shadow-sm' : 'text-[#64748B]'"
                            class="text-sm font-bold px-4 py-2 rounded-lg transition-all">
                            Reviews
                            @if($property->reviews->count() > 0)
                                <span class="text-xs">({{ $property->reviews->count() }})</span>
                            @endif
                        </button>
                    </div>

                    {{-- ── Overview tab ── --}}
                    <div x-show="tab === 'overview'">
                        <h2 class="text-base font-bold text-[#1F2937] mb-2">Property Details</h2>
                        <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line mb-6">
                            {{ $property->description }}</p>

                        @if($property->amenities->count() > 0)
                            <h3 class="text-base font-bold text-[#1F2937] mb-4">Amenities</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                                @foreach($property->amenities as $amenity)
                                    <div class="flex items-center gap-3 text-sm text-[#1F2937] font-medium">
                                        <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        {{ $amenity->amenity_name }}
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(!empty($property->house_rules))
                            <h3 class="text-base font-bold text-[#1F2937] mb-4">House Rules</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($property->house_rules as $rule)
                                    <div class="flex items-center gap-3 text-sm text-[#1F2937] font-medium">
                                        <div class="w-8 h-8 rounded-lg bg-[#E2E8F0] flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-[#EF4444]" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </div>
                                        {{ $rule }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ── Location tab ── --}}
                    <div x-show="tab === 'location'" x-cloak>
                        <h2 class="text-base font-bold text-[#1F2937] mb-1">Where you'll be</h2>
                        <p class="text-sm font-medium text-[#64748B] mb-4">{{ $property->address }}</p>

                        <div id="property-map" data-lat="{{ $property->latitude }}" data-lng="{{ $property->longitude }}"
                            data-title="{{ $property->title }}"
                            class="w-full h-72 rounded-2xl overflow-hidden border border-[#EEF8F8] bg-[#E2E8F0] shadow-sm relative">
                        </div>

                        {{-- Directions --}}
                        <div class="mt-5 pt-4 border-t border-[#EEF8F8]">
                            <button type="button" id="get-directions-btn"
                                class="inline-flex items-center gap-2 bg-[#1F2937] hover:brightness-95 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6 12L3.269 3.126A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                                Get directions
                            </button>

                            <div id="directions-panel" class="mt-3"></div>

                            <form id="manual-origin-form" class="hidden mt-3 flex gap-2">
                                <input type="text" id="manual-origin-input"
                                    placeholder="Enter your starting address in Cebu"
                                    class="flex-1 border border-[#EEF8F8] rounded-xl px-4 py-2 text-sm text-[#1F2937] focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all bg-[#E2E8F0]">
                                <button type="submit"
                                    class="bg-[#2AA7A1] hover:brightness-95 text-white text-xs font-bold px-4 py-2 rounded-xl transition flex-shrink-0">
                                    Go
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ── Reviews tab ── --}}
                    <div x-show="tab === 'reviews'" x-cloak>
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Reviews
                                @if($property->reviews->count() > 0)
                                    <span
                                        class="text-xs font-bold text-[#64748B] ml-1">({{ $property->reviews->count() }})</span>
                                @endif
                            </h2>
                            @if($avgRating)
                                <div class="flex items-center gap-1.5 bg-[#E2E8F0] px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-[#EF4444]" viewBox="0 0 24 24" fill="currentColor"
                                        stroke="none">
                                        <path
                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                    <span class="text-sm font-black text-[#1F2937]">{{ $avgRating }}</span>
                                    <span class="text-xs font-semibold text-[#64748B]">/ 5</span>
                                </div>
                            @endif
                        </div>

                        @forelse($property->reviews as $review)
                            <div class="mb-6 last:mb-0 bg-[#E2E8F0] border border-[#EEF8F8] p-4 rounded-2xl">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-xl bg-[#2AA7A1] text-white text-xs font-black flex items-center justify-center flex-shrink-0 shadow-sm">
                                            {{ strtoupper(substr($review->tenant->first_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-[#1F2937]">
                                                {{ $review->tenant->first_name }} {{ $review->tenant->last_name }}
                                            </div>
                                            <div class="text-[11px] font-medium text-[#64748B]">
                                                {{ $review->created_at->format('M Y') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-0.5">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-[#EF4444]' : 'text-[#EEF8F8]' }}"
                                                viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <path
                                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                        @endfor
                                    </div>
                                </div>
                                <p class="text-sm text-[#1F2937] leading-relaxed pl-12">
                                    {{ $review->review_comment }}
                                </p>
                            </div>
                        @empty
                            <div class="text-sm font-medium text-[#64748B] py-2">
                                No reviews yet for this property.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- ===== 6. UNIT SELECTION LIST ===== --}}
                @if($approvedUnits->count() > 0)
                    <div class="pt-6 border-t border-[#EEF8F8]">
                        <h2 class="text-base font-bold text-[#1F2937] mb-1">Available Units ({{ $availableUnits->count() }})
                        </h2>
                        <p class="text-sm text-[#64748B] mb-4">Choose a unit to inquire or reserve</p>

                        <div class="space-y-3">
                            @foreach($approvedUnits as $unit)
                                @php $isAvailable = $unit->availability_status === 'Available'; @endphp
                                <button type="button" x-on:click="selectUnit({{ $unit->unit_id }})"
                                    :class="selectedUnit === {{ $unit->unit_id }}
                                                    ? 'border-[#EF4444] bg-[#E2E8F0]'
                                                    : '{{ $isAvailable ? 'border-[#EEF8F8] bg-white hover:brightness-95' : 'border-[#E2E8F0] bg-[#E2E8F0] cursor-not-allowed' }}'"
                                    class="w-full text-left rounded-2xl border-2 p-4 flex items-center gap-4 shadow-sm transition-all {{ $isAvailable ? '' : 'opacity-60' }}"
                                    @if(!$isAvailable) disabled @endif>

                                    {{-- Radio indicator --}}
                                    <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
                                        :class="selectedUnit === {{ $unit->unit_id }} ? 'border-[#EF4444]' : 'border-[#64748B]'">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#EF4444]"
                                            x-show="selectedUnit === {{ $unit->unit_id }}" x-cloak></span>
                                    </span>

                                    {{-- Thumbnail --}}
                                    @if($unit->media->first())
                                        <img src="{{ $unit->media->first()->media_url }}" alt="{{ $unit->unit_label }}"
                                            class="w-16 h-16 rounded-xl object-cover shrink-0 border border-[#EEF8F8]">
                                    @else
                                        <span class="w-16 h-16 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                            <svg class="w-6 h-6 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                            </svg>
                                        </span>
                                    @endif

                                    {{-- Details --}}
                                    <span class="flex-1 min-w-0">
                                        <span class="flex items-center gap-2 mb-0.5">
                                            <span class="text-sm font-bold text-[#1F2937] truncate">{{ $unit->unit_label }}</span>
                                            <span
                                                class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-md {{ $isAvailable ? 'bg-[#EEF8F8] text-[#1F2937]' : 'bg-[#E2E8F0] text-[#64748B]' }}">
                                                {{ $unit->availability_status }}
                                            </span>
                                        </span>
                                        <span class="block text-xs font-medium text-[#64748B]">
                                            {{ $property->property_type }}
                                            &middot; {{ $unit->occupancy_limit }}
                                            {{ $unit->occupancy_limit > 1 ? 'People' : 'Person' }}
                                            @if(!empty($unit->size))
                                                &middot; {{ $unit->size }}
                                            @endif
                                        </span>
                                    </span>

                                    {{-- Price --}}
                                    <span class="text-right shrink-0">
                                        <span
                                            class="block text-base font-black text-[#1F2937]">₱{{ number_format($unit->rental_fee) }}</span>
                                        <span class="block text-[11px] font-semibold text-[#64748B]">/ month</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ===================================================== --}}
            {{-- RIGHT SIDEBAR --}}
            {{-- ===================================================== --}}
            <div class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 space-y-4 w-full">

                {{-- ===== 1. INQUIRE / RESERVE CARD ===== --}}
                <div class="bg-white rounded-3xl border border-[#EEF8F8] shadow-sm p-6">

                    {{-- Selected unit preview --}}
                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-3">You're inquiring about:
                    </p>
                    <template x-if="selected">
                        <div class="flex items-center gap-3 mb-5 pb-5 border-b border-[#EEF8F8]">
                            <template x-if="selected.thumb">
                                <img :src="selected.thumb" :alt="selected.label"
                                    class="w-14 h-14 rounded-xl object-cover shrink-0 border border-[#EEF8F8]">
                            </template>
                            <template x-if="!selected.thumb">
                                <span class="w-14 h-14 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                    </svg>
                                </span>
                            </template>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[#1F2937] truncate" x-text="selected.label"></p>
                                <p class="text-xs font-medium text-[#64748B]" x-text="selected.type"></p>
                                <p class="text-sm font-black text-[#1F2937]">
                                    ₱<span x-text="selected.price"></span>
                                    <span class="text-xs font-semibold text-[#64748B]">/ month</span>
                                </p>
                            </div>
                        </div>
                    </template>
                    <template x-if="!selected">
                        <p class="text-sm font-medium text-[#64748B] mb-5 pb-5 border-b border-[#EEF8F8]">
                            No units are currently available for this property.
                        </p>
                    </template>

                    @if(!auth()->check())
                        <button type="button" x-on:click="$dispatch('open-modal', 'login-modal')"
                            class="w-full py-3 rounded-xl bg-[#EF4444] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all">
                            Log in to inquire
                        </button>
                    @elseif($isOwner)
                        <div
                            class="w-full py-3 text-center rounded-xl bg-[#E2E8F0] text-[#64748B] text-sm font-bold cursor-not-allowed">
                            This is your listing
                        </div>
                    @else
                        {{-- Inquiry / Reserve toggle (both submit the same form for now) --}}
                        <div class="grid grid-cols-2 gap-1 bg-[#E2E8F0] p-1 rounded-xl mb-4">
                            <button type="button" x-on:click="mode = 'inquiry'"
                                :class="mode === 'inquiry' ? 'bg-white text-[#1F2937] shadow-sm' : 'text-[#64748B]'"
                                class="text-sm font-bold py-2 rounded-lg transition-all">
                                Inquiry
                            </button>
                            <button type="button" x-on:click="mode = 'reserve'"
                                :class="mode === 'reserve' ? 'bg-white text-[#1F2937] shadow-sm' : 'text-[#64748B]'"
                                class="text-sm font-bold py-2 rounded-lg transition-all">
                                Reserve
                            </button>
                        </div>

                        <form action="{{ route('reservations.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="unit_id" :value="selected ? selected.id : ''">

                            <div>
                                <label for="target_move_in_date" class="block text-xs font-bold text-[#1F2937] mb-1.5">Target
                                    Move In</label>
                                <input type="date" id="target_move_in_date" name="target_move_in_date"
                                    min="{{ now()->toDateString() }}" value="{{ old('target_move_in_date') }}"
                                    class="w-full border border-[#EEF8F8] rounded-xl px-4 py-2.5 text-sm text-[#1F2937] bg-[#E2E8F0] focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all">
                            </div>

                            <div>
                                <label for="target_move_out_date" class="block text-xs font-bold text-[#1F2937] mb-1.5">
                                    Target Move Out <span class="font-semibold text-[#64748B]">(Optional)</span>
                                </label>
                                <input type="date" id="target_move_out_date" name="target_move_out_date"
                                    value="{{ old('target_move_out_date') }}"
                                    class="w-full border border-[#EEF8F8] rounded-xl px-4 py-2.5 text-sm text-[#1F2937] bg-[#E2E8F0] focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all">
                            </div>

                            <div>
                                <label for="inquiry_message" class="block text-xs font-bold text-[#1F2937] mb-1.5">
                                    Your Message <span class="font-semibold text-[#64748B]">(Optional)</span>
                                </label>
                                <textarea id="inquiry_message" name="message" rows="3" maxlength="300" x-model="msg"
                                    placeholder="Hi! I'm interested in this unit..."
                                    class="w-full border border-[#EEF8F8] rounded-xl px-4 py-2.5 text-sm text-[#1F2937] bg-[#E2E8F0] focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all resize-none">{{ old('message') }}</textarea>
                                <p class="text-[11px] font-semibold text-[#64748B] text-right mt-1">
                                    <span x-text="msg.length"></span>/300
                                </p>
                            </div>

                            <template x-if="selected && selected.hasActive">
                                <div
                                    class="w-full py-3 text-center rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-bold cursor-not-allowed">
                                    Inquiry already active
                                </div>
                            </template>
                            <template x-if="!selected || !selected.hasActive">
                                <button type="submit" :disabled="!selected"
                                    class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]">
                                    Send Inquiry to Landlord
                                </button>
                            </template>
                        </form>

                        <p class="text-xs font-medium text-[#64748B] text-center mt-3">
                            Usually responds within a few hours
                        </p>
                    @endif

                    @error('unit')
                        <p class="text-xs text-[#EF4444] font-bold bg-[#E2E8F0] p-2.5 rounded-lg border border-[#EEF8F8] mt-3">
                            {{ $message }}</p>
                    @enderror
                    @error('property')
                        <p class="text-xs text-[#EF4444] font-bold bg-[#E2E8F0] p-2.5 rounded-lg border border-[#EEF8F8] mt-3">
                            {{ $message }}</p>
                    @enderror
                </div>

                {{-- Message Landlord --}}
                <div class="bg-white border border-[#EEF8F8] rounded-2xl p-5 shadow-sm">
                    @auth
                        @if(!$isOwner)
                            <form action="{{ route('conversations.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->property_id }}">
                                <button type="submit"
                                    class="w-full py-3 px-4 rounded-xl border border-[#EEF8F8] bg-white text-[#1F2937] hover:brightness-95 text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#64748B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    Message landlord
                                </button>
                            </form>
                        @else
                            <div
                                class="w-full py-3 px-4 text-center rounded-xl border border-[#EEF8F8] bg-[#E2E8F0] text-[#64748B] text-sm font-bold cursor-not-allowed">
                                This is your listing
                            </div>
                        @endif
                    @else
                        <button type="button" x-on:click="$dispatch('open-modal', 'login-modal')"
                            class="w-full py-3 px-4 rounded-xl border border-[#EEF8F8] bg-white text-[#1F2937] hover:brightness-95 text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4 text-[#64748B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            Message landlord
                        </button>
                    @endauth
                </div>

                {{-- ===== 2. LANDLORD INFORMATION CARD ===== --}}
                <div class="bg-white border border-[#EEF8F8] rounded-2xl p-5 shadow-sm">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-3">Landlord Information</p>
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-11 h-11 rounded-xl bg-[#2AA7A1] text-white text-base font-black flex items-center justify-center flex-shrink-0 shadow-sm">
                            {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}{{ strtoupper(substr($property->landlord->last_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-[#1F2937]">
                                {{ $property->landlord->first_name }} {{ $property->landlord->last_name }}
                            </div>
                            @if($property->landlord->rentalBusiness)
                                <div class="flex items-center gap-1 text-xs text-[#EF4444] font-bold mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Verified Landlord
                                </div>
                            @else
                                <div class="text-xs font-semibold text-[#64748B] mt-0.5">Landlord</div>
                            @endif
                        </div>
                    </div>
                    <a href="#"
                        class="block w-full py-2.5 text-center rounded-xl border border-[#EEF8F8] bg-white text-[#1F2937] hover:brightness-95 text-sm font-bold shadow-sm transition-all">
                        View Landlord Profile
                    </a>
                </div>

            </div>
        </div>
    </div>

    {{-- ===== LIGHTBOX ===== --}}
    @if($property->media->count() > 0)
        <div id="lightbox" class="fixed inset-0 z-[999] bg-black/90 hidden items-center justify-center"
            onclick="closeLightbox()">

            <button type="button"
                class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="closeLightbox()">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <button type="button" id="lb-prev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(-1)">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <img id="lb-img" src="" alt="" class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg select-none"
                onclick="event.stopPropagation()">

            <button type="button" id="lb-next"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(1)">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
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
                const mediaUrls = @json($property->media->pluck('media_url')->values());
                const total = mediaUrls.length;
                let currentIndex = 0;

                window.setHero = function (index) {
                    currentIndex = index;
                    const heroImg = document.getElementById('hero-img');
                    const heroBadge = document.getElementById('hero-index');

                    if (heroImg) {
                        heroImg.style.opacity = '0';
                        setTimeout(() => {
                            heroImg.src = mediaUrls[index];
                            heroImg.style.opacity = '1';
                        }, 150);
                    }

                    if (heroBadge) heroBadge.textContent = index + 1;

                    document.querySelectorAll('[id^="thumb-"]').forEach((thumb, i) => {
                        if (i === index) {
                            thumb.classList.add('border-[#2AA7A1]');
                            thumb.classList.remove('border-transparent', 'opacity-60');
                        } else {
                            thumb.classList.remove('border-[#2AA7A1]');
                            thumb.classList.add('border-transparent', 'opacity-60');
                        }
                    });
                };

                window.shiftHero = function (dir) {
                    setHero((currentIndex + dir + total) % total);
                };

                window.openLightboxAtHero = function () {
                    openLightbox(currentIndex);
                };

                const lightbox = document.getElementById('lightbox');
                const lbImg = document.getElementById('lb-img');
                const lbCounter = document.getElementById('lb-counter');
                const lbPrev = document.getElementById('lb-prev');
                const lbNext = document.getElementById('lb-next');

                function updateLightbox() {
                    if (lbImg) lbImg.src = mediaUrls[currentIndex];
                    if (lbCounter) lbCounter.textContent = (currentIndex + 1) + ' / ' + total;
                    if (lbPrev) lbPrev.style.display = total <= 1 ? 'none' : '';
                    if (lbNext) lbNext.style.display = total <= 1 ? 'none' : '';
                }

                window.openLightbox = function (index) {
                    currentIndex = index;
                    updateLightbox();
                    if (lightbox) {
                        lightbox.classList.remove('hidden');
                        lightbox.classList.add('flex');
                    }
                    document.body.style.overflow = 'hidden';
                };

                window.closeLightbox = function () {
                    if (lightbox) {
                        lightbox.classList.add('hidden');
                        lightbox.classList.remove('flex');
                    }
                    document.body.style.overflow = '';
                };

                window.shiftLightbox = function (dir) {
                    currentIndex = (currentIndex + dir + total) % total;
                    updateLightbox();
                    setHero(currentIndex);
                };

                document.addEventListener('keydown', function (e) {
                    if (!lightbox || lightbox.classList.contains('hidden')) return;
                    if (e.key === 'ArrowRight') shiftLightbox(1);
                    if (e.key === 'ArrowLeft') shiftLightbox(-1);
                    if (e.key === 'Escape') closeLightbox();
                });
            })();
        </script>
    @endpush
@endsection