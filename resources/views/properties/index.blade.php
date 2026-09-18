@extends('layouts.app', ['searchBar' => empty($heroStats)])

@section('themeable', '1')

@section('browse_tools')
    @unless($heroStats)
        @include('properties.partials-browse-tools')
    @endunless
@endsection

@section('content')

    {{-- ===== HERO — full-bleed photo + search + live trust strip. Only on a
         clean arrival (no filter/sort/page active); collapses to the plain
         filter-bar + grid below the moment the visitor does anything, per
         DESIGN.md §6i. The header's own search pill is hidden while this is
         showing (see the searchBar=false above) so there's one search bar,
         not two. ===== --}}
    @if($heroStats)
        <section id="landing-hero" class="relative min-h-[72vh] sm:min-h-[80vh] flex items-center overflow-hidden">
            <img src="{{ asset('images/hero-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-[#060D26]/35 via-[#060D26]/45 to-[#060D26]/55"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_75%_at_50%_40%,rgba(6,13,38,0.45),rgba(6,13,38,0)_70%)]"></div>

            <div class="relative z-10 mx-auto w-full max-w-[900px] px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
                <p class="inline-flex items-center font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] sm:text-[11.5px] font-bold uppercase tracking-[0.14em] text-white px-3.5 py-1.5 rounded-full border border-[#FF8A66]/60 bg-[#FF8A66]/10">
                    Your Next Place Starts Here
                </p>
                <h1 class="mt-5 sm:mt-6 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[36px] sm:text-[52px] lg:text-[62px] font-extrabold leading-[1.1] tracking-tight text-white text-balance">
                    Find a place to call home
                    <span class="block bg-gradient-to-r from-white to-white/55 bg-clip-text text-transparent">in <span class="text-[#FF8A66]">Cebu</span>.</span>
                </h1>
                <p class="mt-5 sm:mt-6 mx-auto max-w-xl text-white/90 text-[15px] sm:text-[17px] font-light">
                    Verified apartments, rooms, boarding houses, and condos &mdash; all in one place.
                </p>

                <div class="mt-8 sm:mt-10 flex justify-center">
                    <x-search-pill variant="hero" />
                </div>

                <div class="mt-6 sm:mt-7 flex flex-wrap items-center justify-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.06em] text-white">
                    <span class="px-3 py-1 rounded-full border border-[#FF8A66]/60 bg-[#060D26]/40">{{ $heroStats['units'] }}+ available units</span>
                    <span class="px-3 py-1 rounded-full border border-[#FF8A66]/60 bg-[#060D26]/40">Verified landlords</span>
                    <span class="px-3 py-1 rounded-full border border-[#FF8A66]/60 bg-[#060D26]/40">Across Cebu</span>
                </div>
            </div>
        </section>

        {{-- Compact hero: once the full hero scrolls out from under the sticky
             header, a slim photo strip carrying the same search pill slides in
             so the search stays one tap away. Fixed (not sticky) so it never
             shifts the layout; the strip's photo is clipped in its own
             layer so the type dropdown can still overflow the bar. --}}
        <div x-data="{ show: false, offset: 0 }"
            x-init="
                const header = document.getElementById('site-header');
                const hero = document.getElementById('landing-hero');
                const update = () => {
                    offset = header ? header.offsetHeight : 0;
                    show = hero.getBoundingClientRect().bottom <= offset + 1;
                };
                update();
                window.addEventListener('scroll', update, { passive: true });
                window.addEventListener('resize', update);
            "
            :style="'top:' + offset + 'px'"
            :class="show ? 'translate-y-0 opacity-100' : '-translate-y-3 opacity-0 pointer-events-none'"
            :aria-hidden="show ? 'false' : 'true'"
            class="fixed inset-x-0 z-[90] transition-all duration-300 ease-out motion-reduce:transition-none">
            <div class="relative border-b-2 border-[#FF8A66] shadow-[0_8px_24px_rgba(6,13,38,0.14)] lg:shadow-none">
                <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
                    <img src="{{ asset('images/hero-bg.jpg') }}" alt="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-[#060D26]/55"></div>
                </div>
                <div class="relative flex justify-center px-3 sm:px-6 py-2">
                    <x-search-pill variant="header" :compact="true" />
                </div>
            </div>
            {{-- Same categories + Show map / Filters row the filtered pages have in the header. --}}
            <div class="relative hidden lg:flex items-center justify-center gap-6 px-6 bg-white border-b border-[#E2E4EC] shadow-[0_8px_24px_rgba(6,13,38,0.10)]">
                <div class="min-w-0"><x-category-strip /></div>
                <div class="flex items-center gap-2 pl-6 border-l border-[#E2E4EC] self-center mb-1">
                    @include('properties.partials-browse-tools')
                </div>
            </div>
        </div>
    @endif

    {{-- ===== BROWSE ===== --}}
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16 min-h-[60vh]" x-data="{ mobileView: 'list', mapVisible: false, filtersOpen: false }"
        x-effect="window.dispatchEvent(new CustomEvent('browse-map-state', { detail: mapVisible }))"
        @browse-toggle-map.window="mapVisible = !mapVisible; if (mapVisible) $nextTick(() => window.browseMapRefit?.())"
        @browse-open-filters.window="filtersOpen = true">

        @if($heroStats)
            @if($areas->count() > 0)
                {{-- ===== BROWSE BY AREA ===== --}}
                <div class="mb-14">
                    <div class="flex items-center gap-3"><span class="inline-flex items-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>Browse by area</span>
                    <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#FF8A66] via-[#FF8A66]/70 to-[#FF8A66]/10" aria-hidden="true"></span>
                    <span class="hidden sm:inline font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.1em] text-[#5B6A8E] whitespace-nowrap">{{ $areas->count() }} {{ Str::plural('area', $areas->count()) }} &middot; {{ $areas->sum('count') }} listings</span></div>
                    <h2 class="mt-2.5 mb-5 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[24px] sm:text-[30px] font-extrabold leading-[1.15] tracking-tight text-[#060D26]">Pick a neighborhood,
                        <span class="block text-[#5B6A8E]">find your place.</span></h2>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        @foreach($areas as $area)
                            <a href="{{ route('properties.index', ['location' => $area['name']]) }}"
                                class="relative flex-1 min-w-[176px] h-36 sm:h-40 rounded-2xl overflow-hidden group border border-[#E2E4EC] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66] hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0">
                                <img src="{{ $area['photo'] }}" alt="{{ $area['name'] }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/35 to-transparent"></div>
                                <div class="absolute bottom-3 left-3 right-3">
                                    <p class="text-white font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[14.5px] font-bold leading-tight truncate [text-shadow:0_1px_6px_rgba(6,13,38,0.5)]">
                                        {{ $area['name'] }}
                                    </p>
                                    <p class="mt-1 inline-flex font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[9.5px] font-bold uppercase tracking-[0.06em] text-white px-2 py-0.5 rounded-full border border-[#FF8A66]/70 bg-[#060D26]/40">
                                        {{ $area['count'] }} {{ Str::plural('listing', $area['count']) }}
                                    </p>
                                </div>
                            </a>
                        @endforeach

                        <a href="{{ route('properties.areas') }}"
                            class="relative flex-1 min-w-[176px] h-36 sm:h-40 rounded-2xl overflow-hidden group border border-[#FF8A66]/60 bg-gradient-to-br from-[#FF8A66]/15 via-[#FF8A66]/5 to-white flex flex-col items-center justify-center text-center gap-2 transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66] hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0">
                            <span class="w-10 h-10 rounded-full bg-[#FF8A66] text-[#060D26] flex items-center justify-center shadow-[0_8px_20px_rgba(255,138,102,0.4)] transition-transform duration-300 group-hover:translate-x-1 motion-reduce:group-hover:translate-x-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" /></svg>
                            </span>
                            <span class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[14.5px] font-extrabold text-[#060D26] leading-tight">View all areas</span>
                        </a>
                    </div>
                </div>
            @endif

            @if($popularProperties->count() > 0)
                {{-- ===== POPULAR PLACES TO STAY ===== --}}
                <div class="mb-14">
                    <div class="flex items-center gap-3"><span class="inline-flex items-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>Top rated</span>
                    <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#FF8A66] via-[#FF8A66]/70 to-[#FF8A66]/10" aria-hidden="true"></span>
                    <span class="hidden sm:inline font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.1em] text-[#5B6A8E] whitespace-nowrap">Highest rated by tenants</span></div>
                    <h2 class="mt-2.5 mb-6 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[24px] sm:text-[30px] font-extrabold leading-[1.15] tracking-tight text-[#060D26]">Popular places
                        <span class="block text-[#5B6A8E]">to stay.</span></h2>
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
                                    <h3 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[14px] font-bold text-[#060D26] truncate mb-2.5">
                                        {{ $property->title }}
                                    </h3>
                                    <div class="flex items-end justify-between gap-2">
                                        <div>
                                            <p class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[19px] font-extrabold text-[#060D26] leading-none">
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
                                                <p class="inline-block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10px] font-bold uppercase tracking-[0.04em] text-[#060D26] px-2 py-0.5 rounded-full border border-[#FF8A66]/60 bg-[#FF8A66]/10">
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
        @if(request()->hasAny(['location', 'type', 'price_max', 'verified', 'amenities']))
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[13px] text-[#5B6A8E] font-medium">Filtering by:</span>

                @if(request('location'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
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

                @foreach($selectedAmenities as $amenity)
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
                        {{ $amenity->name }}
                        {{-- Removing one amenity out of amenities[]=1&amenities[]=2 isn't
                             a plain fullUrlWithoutQuery (that drops the whole key) — rebuild
                             the query with just this one ID subtracted from the array. --}}
                        <a href="{{ route('properties.index', array_merge(request()->except(['amenities', 'page']), ['amenities' => array_values(array_diff((array) request('amenities', []), [$amenity->amenity_id]))])) }}"
                            class="hover:brightness-95"
                            aria-label="Remove {{ $amenity->name }} filter">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endforeach

                <a href="{{ route('properties.index') }}"
                    class="text-[13px] text-[#EF4444] hover:brightness-95 font-semibold ml-2">
                    Clear all
                </a>
            </div>
        @endif

        {{-- ===== ALL LISTINGS — compact header + one toolbar row (type pills left,
             map/filters right). ===== --}}
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>All listings</span>
            <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#FF8A66] via-[#FF8A66]/70 to-[#FF8A66]/10" aria-hidden="true"></span>
            <span class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.1em] text-[#5B6A8E] whitespace-nowrap">
                {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
            </span>
        </div>
        @if($heroStats)
            <h2 class="mt-3 mb-6 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[22px] sm:text-[28px] font-extrabold leading-tight tracking-tight text-[#060D26]">Every place, <span class="text-[#5B6A8E]">ready to view.</span></h2>
        @endif

        @php
            $typePills = ['All' => null, 'Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House'];
            $activeType = request('type');
        @endphp
        <div class="{{ $heroStats ? 'lg:hidden' : 'mt-4' }} mb-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            {{-- Type pills: landing only. Every other view has the header's category strip. --}}
            @if($heroStats)
                <div class="flex items-center gap-2 overflow-x-auto max-w-full [-ms-overflow-style:none] [scrollbar-width:none]">
                    @foreach($typePills as $label => $type)
                        @php $isActive = $type === null ? ! $activeType : $activeType === $type; @endphp
                        <a href="{{ $type ? route('properties.index', ['type' => $type]) : route('properties.index') }}"
                            @if($isActive) aria-current="page" @endif
                            class="flex-shrink-0 h-9 inline-flex items-center px-4 rounded-full border font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold transition-all duration-200 {{ $isActive ? 'bg-[#060D26] border-[#060D26] text-white shadow-md' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] hover:border-[#FF8A66] hover:text-[#060D26]' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-2">
                {{-- Desktop-only: the mobile List/Map switcher below already
                     covers small screens, where the two never share the screen. --}}
                <button type="button"
                    @click="mapVisible = !mapVisible; if (mapVisible) $nextTick(() => window.browseMapRefit?.())"
                    class="{{ $heroStats ? 'hidden lg:inline-flex' : 'hidden' }} items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-sm hover:bg-[#F7F8FC] transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                    </svg>
                    <span x-text="mapVisible ? 'Hide map' : 'Show map'"></span>
                </button>

                {{-- Visible at every breakpoint (unlike Show map above) —
                     there's no mobile-only substitute for it the way the List/
                     Map switcher below covers Show map on small screens. --}}
                @php $activeFilterCount = count((array) request('amenities', [])); @endphp
                <button type="button" @click="filtersOpen = true"
                    class="{{ $heroStats ? '' : 'lg:hidden' }} inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-sm hover:bg-[#F7F8FC] transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                    </svg>
                    Filters
                    @if($activeFilterCount > 0)
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[#FF8A66] text-[#060D26] text-[10px] font-bold">{{ $activeFilterCount }}</span>
                    @endif
                </button>

            </div>
        </div>

        {{-- ===== FILTERS PANEL — bottom sheet on mobile, wide two-column modal from
             `md:` up so all amenity categories fit without scrolling. Header and
             footer are fixed; only the body scrolls (phones, short screens).
             Same x-teleport overlay shell as the inquiry modal on properties/show. ===== --}}
        @php
            $categoryIcons = [
                'Bath & comfort' => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
                'Building & access' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21',
                'Connectivity & power' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
                'Kitchen & laundry' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
                'Rules & extras' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
            ];
            $fallbackCategoryIcon = 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z';
            $initialSelected = count((array) request('amenities', []));
        @endphp
        <template x-teleport="body">
            <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center sm:p-6"
                x-on:keydown.escape.window="filtersOpen = false">
                <div class="absolute inset-0 bg-[#060D26]/50" x-on:click="filtersOpen = false"></div>
                <form method="GET" action="{{ route('properties.index') }}"
                    x-data="{ selected: {{ $initialSelected }}, count() { this.selected = this.$el.querySelectorAll('input[type=checkbox]:checked').length } }"
                    @change="count()"
                    class="relative w-full sm:max-w-6xl max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden"
                    x-show="filtersOpen" x-transition>

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-[#E2E4EC] flex-shrink-0">
                        <div>
                            <span class="inline-flex items-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>Refine results</span>
                            <h3 class="mt-1 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[22px] font-extrabold tracking-tight text-[#060D26]">Filters</h3>
                        </div>
                        <button type="button" x-on:click="filtersOpen = false" aria-label="Close"
                            class="w-9 h-9 flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#5B6A8E] hover:text-[#060D26] hover:border-[#FF8A66] transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Body (only this scrolls) --}}
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4 bg-[#F7F8FC]">
                        {{-- Carries location/type/price_max/sort through untouched —
                             this form only ever sets amenities. --}}
                        @foreach(request()->except(['amenities', 'verified', 'page']) as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $item)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach

                        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                            @foreach($amenityGroups as $category => $group)
                                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-3.5">
                                    <div class="flex items-center gap-2.5 mb-3">
                                        <span class="w-8 h-8 rounded-full bg-[#060D26] text-white flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $categoryIcons[$category] ?? $fallbackCategoryIcon }}" /></svg>
                                        </span>
                                        <h4 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">{{ $category }}</h4>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($group as $amenity)
                                            <label class="relative cursor-pointer">
                                                <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}"
                                                    @checked(in_array($amenity->amenity_id, (array) request('amenities', [])))
                                                    class="peer sr-only">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-[#E2E4EC] bg-white font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-semibold text-[#5B6A8E] hover:border-[#FF8A66] peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66]/50 peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66] peer-checked:text-[#060D26] transition-all">
                                                    <x-amenity-icon :name="$amenity->name" class="w-3.5 h-3.5" />
                                                    {{ $amenity->name }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center gap-4 px-6 py-4 border-t border-[#E2E4EC] bg-white flex-shrink-0">
                        <a href="{{ route('properties.index', request()->except(['amenities', 'verified', 'page'])) }}"
                            class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#EF4444] hover:brightness-95">
                            Clear all
                        </a>
                        <span class="hidden sm:inline font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.08em] text-[#5B6A8E]" x-text="selected + ' selected'"></span>
                        <button type="submit"
                            class="ml-auto px-6 py-2.5 rounded-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13.5px] font-bold text-[#060D26] bg-[#FF8A66] shadow-[0_8px_20px_rgba(255,138,102,0.35)] hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                            Apply filters
                        </button>
                    </div>
                </form>
            </div>
        </template>

        {{-- MOBILE LIST/MAP TOGGLE — desktop shows both columns, this is mobile-only --}}
        <div class="flex lg:hidden gap-2 mb-5">
            <button type="button" @click="mobileView = 'list'"
                :class="mobileView === 'list' ? 'bg-[#FF8A66] text-[#060D26]' : 'bg-white text-[#060D26] border border-[#5B6A8E]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition cursor-pointer">
                List
            </button>
            <button type="button" @click="mobileView = 'map'"
                :class="mobileView === 'map' ? 'bg-[#FF8A66] text-[#060D26]' : 'bg-white text-[#060D26] border border-[#5B6A8E]/30'"
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
            <div class="grid grid-cols-1 gap-6" :class="mapVisible ? 'lg:grid-cols-[2fr_3fr]' : 'lg:grid-cols-1'">

                {{-- LIST COLUMN --}}
                <div :class="mobileView === 'list' ? 'block' : 'hidden'" class="lg:!block">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6"
                        :class="mapVisible ? 'lg:grid-cols-2' : 'lg:grid-cols-3 xl:grid-cols-5'">
                        {{-- Landing (no filter/sort/page): a two-row teaser — 9 listings plus a
                             "view all" tile as the 10th cell. The tile links with sort=newest,
                             which drops heroStats and opens the full paginated list. --}}
                        @foreach($heroStats ? $properties->take(9) : $properties as $property)
                            <x-property-card :property="$property" :favorited-ids="$favoritedIds" />
                        @endforeach

                        @if($heroStats)
                            <a href="{{ route('properties.index', ['sort' => 'newest']) }}"
                                class="group relative flex flex-col items-center justify-center text-center gap-3 rounded-2xl border border-[#FF8A66]/60 bg-gradient-to-br from-[#FF8A66]/15 via-[#FF8A66]/5 to-white p-6 min-h-[260px] transition-all duration-300 hover:-translate-y-1 hover:border-[#FF8A66] hover:shadow-[0_12px_28px_rgba(6,13,38,0.12)] motion-reduce:hover:translate-y-0">
                                <span class="w-12 h-12 rounded-full bg-[#FF8A66] text-[#060D26] flex items-center justify-center shadow-[0_8px_20px_rgba(255,138,102,0.4)] transition-transform duration-300 group-hover:translate-x-1 motion-reduce:group-hover:translate-x-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" />
                                    </svg>
                                </span>
                                <span class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[16px] font-extrabold leading-tight text-[#060D26]">View all rental properties</span>
                                <span class="inline-flex font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10px] font-bold uppercase tracking-[0.06em] text-[#060D26] px-2.5 py-1 rounded-full border border-[#FF8A66]/60 bg-white/70">
                                    {{ $properties->total() }} {{ Str::plural('listing', $properties->total()) }}
                                </span>
                            </a>
                        @endif
                    </div>

                    {{-- PAGINATION --}}
                    @unless($heroStats)
                        <div class="flex justify-center mt-10">
                            {{ $properties->links() }}
                        </div>
                    @endunless
                </div>

                {{-- MAP COLUMN — mobileView governs it below `lg` (independent
                     of the desktop toggle, which is hidden there); mapVisible
                     governs it at `lg` and up. --}}
                <div :class="[mobileView === 'map' ? 'block' : 'hidden', mapVisible ? 'lg:!block' : 'lg:!hidden']"
                    class="lg:sticky {{ $heroStats ? 'lg:top-[150px]' : 'lg:top-[232px]' }} lg:self-start">
                    <div id="browse-map"
                        class="w-full h-[400px] {{ $heroStats ? 'lg:h-[calc(100vh-190px)]' : 'lg:h-[calc(100vh-256px)]' }} lg:min-h-[420px] rounded-2xl overflow-hidden border border-[#FF8A66]">
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
