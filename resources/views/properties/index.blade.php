@extends('layouts.app', ['searchBar' => empty($heroStats)])

@section('content')

    {{-- ===== HERO — full-bleed photo + search + live trust strip. Only on a
         clean arrival (no filter/sort/page active); collapses to the plain
         filter-bar + grid below the moment the visitor does anything, per
         DESIGN.md §6i. The header's own search pill is hidden while this is
         showing (see the searchBar=false above) so there's one search bar,
         not two. ===== --}}
    @if($heroStats)
        <section class="relative min-h-[64vh] flex flex-col justify-end overflow-hidden">
            <img src="{{ asset('images/hero-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-[#060D26]/25 to-[#060D26]/80"></div>

            <div class="relative z-10 max-w-[1400px] mx-auto w-full px-4 sm:px-6 lg:px-8 pt-24 pb-10">
                <h1 class="font-display text-[32px] sm:text-[44px] font-normal leading-[1.1] text-white text-balance">
                    Find your next home<br>in <span class="italic text-[#C9A84C]">Cebu</span>
                </h1>
                <p class="mt-2 text-white/70 text-[15px] sm:text-base font-light max-w-md">
                    Apartments, rooms, boarding houses &amp; condos &mdash; all verified.
                </p>

                <div class="mt-6">
                    <x-search-pill variant="hero" />
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-white/70">
                    <span>{{ $heroStats['listings'] }} {{ Str::plural('listing', $heroStats['listings']) }} live</span>
                    <span class="hidden sm:inline" aria-hidden="true">&middot;</span>
                    <span>{{ $heroStats['units'] }} {{ Str::plural('unit', $heroStats['units']) }} available now</span>
                    <span class="hidden sm:inline" aria-hidden="true">&middot;</span>
                    <span>Every landlord ID-verified</span>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== BROWSE ===== --}}
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16 min-h-[60vh]" x-data="{ mobileView: 'list', mapVisible: true }">

        @if($heroStats)
            @if($areas->count() > 0)
                {{-- ===== BROWSE BY AREA ===== --}}
                <div class="mb-10">
                    <p class="text-[12px] font-bold uppercase tracking-[0.08em] text-[#5B6A8E] mb-3">Browse by area</p>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        @foreach($areas as $area)
                            <a href="{{ route('properties.index', ['location' => $area['name']]) }}"
                                class="relative flex-shrink-0 w-28 h-20 rounded-xl overflow-hidden group">
                                <img src="{{ $area['photo'] }}" alt="{{ $area['name'] }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/85 via-[#060D26]/10 to-transparent"></div>
                                <p class="absolute bottom-2 left-2 right-2 text-white text-[12px] font-semibold truncate">
                                    {{ $area['name'] }}
                                </p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($popularProperties->count() > 0)
                {{-- ===== POPULAR PLACES TO STAY ===== --}}
                <div class="mb-12">
                    <div class="flex items-center gap-2.5 mb-1.5">
                        <div class="h-px w-4 bg-[#C9A84C]"></div>
                        <span class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#8a6e1e]">Top rated</span>
                    </div>
                    <x-section-header title="Popular places to stay" />
                    {{-- A different card treatment than the main grid's borderless
                         image-is-the-card pattern (DESIGN.md §6), deliberately — this
                         boxed white-card style is specific to this section, mirroring
                         the reference mockup exactly. Only one use site, so inlined
                         rather than a new shared component. --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                        @foreach($popularProperties as $property)
                            @php
                                $availableCount = $property->units->where('availability_status', 'Available')->count();
                                $photo = $property->media->firstWhere('media_type', 'Image')?->media_url;
                            @endphp
                            <a href="{{ route('properties.show', $property->property_id) }}"
                                class="block bg-white border border-[#E2E4EC] rounded-2xl overflow-hidden hover:shadow-md transition-shadow">
                                <div class="relative h-[110px] bg-[#ECEEF6]">
                                    @if($photo)
                                        <img src="{{ $photo }}" alt="{{ $property->title }}" class="w-full h-full object-cover">
                                    @endif
                                    @if($property->hasVerifiedDocuments())
                                        <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-0.5 bg-white/95 text-[#060D26] text-[10.5px] font-semibold px-2 py-1 rounded-full">
                                            &#10003; Verified
                                        </span>
                                    @endif
                                </div>
                                <div class="p-3.5">
                                    <p class="text-[11px] text-[#5B6A8E] mb-0.5 truncate">
                                        {{ $property->property_type }} &middot; {{ $property->city_municipality }}
                                    </p>
                                    <h3 class="font-display text-[14px] font-normal text-[#060D26] truncate mb-2.5">
                                        {{ $property->title }}
                                    </h3>
                                    <div class="flex items-end justify-between gap-2">
                                        <div>
                                            <p class="font-display text-[19px] font-normal text-[#060D26] leading-none">
                                                @if($property->min_rental_fee)
                                                    &#8369;{{ number_format($property->min_rental_fee) }}
                                                @else
                                                    &#8212;
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-[#5B6A8E] mt-0.5">/ month &middot; from</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            @if($availableCount > 0)
                                                <p class="text-[11px] font-semibold text-[#8a6e1e]">
                                                    {{ $availableCount }} {{ Str::plural('unit', $availableCount) }} free
                                                </p>
                                            @endif
                                            @if($property->review_count > 0)
                                                <p class="text-[11px] text-[#060D26] mt-0.5">
                                                    &#9733; {{ number_format($property->avg_rating, 1) }} ({{ $property->review_count }})
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        {{-- ACTIVE FILTERS SUMMARY --}}
        @if(request()->hasAny(['location', 'type', 'price_max', 'verified']))
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[13px] text-[#5B6A8E] font-medium">Filtering by:</span>

                @if(request('location'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#C9A84C]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ request('location') }}
                        <a href="{{ request()->fullUrlWithoutQuery('location') }}" class="hover:brightness-95"
                            aria-label="Remove location filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('type'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#C9A84C]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {{ request('type') }}
                        <a href="{{ request()->fullUrlWithoutQuery('type') }}" class="hover:brightness-95"
                            aria-label="Remove type filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('price_max'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#C9A84C]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Max ₱{{ number_format(request('price_max')) }}
                        <a href="{{ request()->fullUrlWithoutQuery('price_max') }}" class="hover:brightness-95"
                            aria-label="Remove budget filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('verified'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#C9A84C]/40 rounded-full text-[13px] font-semibold">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Verified only
                        <a href="{{ request()->fullUrlWithoutQuery('verified') }}" class="hover:brightness-95"
                            aria-label="Remove verified filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                <a href="{{ route('properties.index') }}"
                    class="text-[13px] text-[#EF4444] hover:brightness-95 font-semibold ml-2">
                    Clear all
                </a>
            </div>
        @endif

        {{-- RESULTS COUNT + SORT --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <p class="text-[13px] text-[#5B6A8E] font-medium">
                {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
            </p>

            <div class="flex items-center gap-2">
                {{-- Desktop-only: the mobile List/Map switcher below already
                     covers small screens, where the two never share the screen. --}}
                <button type="button"
                    @click="mapVisible = !mapVisible; if (mapVisible) $nextTick(() => window.browseMap?.invalidateSize())"
                    class="hidden lg:inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#5B6A8E]/30 bg-white text-[#060D26] text-[13px] font-semibold hover:bg-[#F7F8FC] transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                    </svg>
                    <span x-text="mapVisible ? 'Hide map' : 'Show map'"></span>
                </button>

            <form method="GET" class="flex items-center gap-2">
                @foreach(request()->except(['sort', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label id="sort-label" class="text-[13px] text-[#5B6A8E] font-medium hidden sm:inline">Sort by</label>
                <x-styled-select name="sort" :options="[
                    'newest' => 'Newest',
                    'price_low' => 'Price: Low to High',
                    'price_high' => 'Price: High to Low',
                ]" :selected="request('sort', 'newest')" aria-labelledby="sort-label" :autosubmit="true"
                    class="h-9 text-[13px] font-semibold rounded-full border border-[#5B6A8E]/30 bg-white text-[#060D26] pl-3.5 pr-8" />
            </form>
            </div>
        </div>

        {{-- MOBILE LIST/MAP TOGGLE — desktop shows both columns, this is mobile-only --}}
        <div class="flex lg:hidden gap-2 mb-5">
            <button type="button" @click="mobileView = 'list'"
                :class="mobileView === 'list' ? 'bg-[#060D26] text-[#F7F4ED]' : 'bg-white text-[#060D26] border border-[#5B6A8E]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition cursor-pointer">
                List
            </button>
            <button type="button" @click="mobileView = 'map'"
                :class="mobileView === 'map' ? 'bg-[#060D26] text-[#F7F4ED]' : 'bg-white text-[#060D26] border border-[#5B6A8E]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition cursor-pointer">
                Map
            </button>
        </div>

        @if($properties->count() > 0)
            {{-- Sept 2026 — cards sized down and the map shrunk to mirror the
                 reference mockup. A 3-column grid + narrow map was tried once
                 before (see git history) and reverted because a fixed-875px
                 map left ~155px cards with every title truncated; this isn't
                 that config — the map column is wider (320px, not a fixed
                 875px) and shorter (560px, not full viewport height), which
                 keeps cards at ~200-300px depending on breakpoint, comfortably
                 above the width that caused the original truncation. --}}
            <div class="grid grid-cols-1 gap-6" :class="mapVisible ? 'lg:grid-cols-[1fr_320px]' : 'lg:grid-cols-1'">

                {{-- LIST COLUMN --}}
                <div :class="mobileView === 'list' ? 'block' : 'hidden'" class="lg:!block">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
                        :class="mapVisible ? '' : 'xl:grid-cols-4'">
                        @foreach($properties as $property)
                            <x-property-card :property="$property" :favorited-ids="$favoritedIds" />
                        @endforeach
                    </div>

                    {{-- PAGINATION --}}
                    <div class="flex justify-center mt-10">
                        {{ $properties->links() }}
                    </div>
                </div>

                {{-- MAP COLUMN — mobileView governs it below `lg` (independent
                     of the desktop toggle, which is hidden there); mapVisible
                     governs it at `lg` and up. --}}
                <div :class="[mobileView === 'map' ? 'block' : 'hidden', mapVisible ? 'lg:!block' : 'lg:!hidden']"
                    class="lg:sticky lg:top-[72px] lg:self-start">
                    <div id="browse-map"
                        class="w-full h-[400px] lg:h-[560px] rounded-2xl overflow-hidden border border-[#5B6A8E]/20">
                    </div>
                    <script type="application/json" id="browse-map-data">{!! json_encode($mapProperties) !!}</script>
                </div>

            </div>
        @else
            <x-empty-state title="No properties found"
                message="Try adjusting your filters or search in a different area of Cebu." :href="route('properties.index')"
                cta="Clear filters">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </x-slot>
            </x-empty-state>
        @endif

    </div>

    @push('scripts')
        @vite(['resources/js/maps/browse-map.js'])
        <script>
            (function () {
                function toggleFavorite(button) {
                    const isAuthenticated = document.querySelector('meta[name="user-authenticated"]').content === '1';

                    if (!isAuthenticated) {
                        openAuthModal('login');
                        return;
                    }

                    const propertyId = button.dataset.propertyId;
                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const outline = button.querySelector('.heart-outline');
                    const filled = button.querySelector('.heart-filled');

                    fetch(`/favorites/${propertyId}/toggle`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Favorite toggle failed: ' + response.status);
                            return response.json();
                        })
                        .then(data => {
                            button.dataset.favorited = data.favorited ? 'true' : 'false';
                            outline.classList.toggle('hidden', data.favorited);
                            filled.classList.toggle('hidden', !data.favorited);
                        })
                        .catch(err => console.error(err));
                }

                window.toggleFavorite = toggleFavorite;
            })();
        </script>
    @endpush

@endsection
