@extends('layouts.app', ['searchBar' => empty($heroStats)])

@section('themeable', '1')

{{-- Browse (filter/search view): keep the search + category band pinned under the nav on desktop; the listings are what scrolls. --}}
@section('sticky_search', '1')

@section('content')

    {{-- ===== HERO — full-bleed photo + search + live trust strip. Only on a
         clean arrival (no filter/sort/page active); collapses to the plain
         filter-bar + grid below the moment the visitor does anything, per
         DESIGN.md §6i. The header's own search pill is hidden while this is
         showing (see the searchBar=false above) so there's one search bar,
         not two. ===== --}}
    @if($heroStats)
        <section id="landing-hero" class="relative min-h-[72vh] sm:min-h-[80vh] flex items-center overflow-hidden"
            x-data="{ slides: [
                    '{{ asset('images/pexels-photo-10530237.webp') }}',
                    '{{ asset('images/photo-1448630360428-65456885c650.webp') }}'
                ], active: -1 }"
            x-init="setInterval(() => active = active >= slides.length - 1 ? -1 : active + 1, 12000)">
            <img src="{{ asset('images/hero-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            <template x-for="(slide, i) in slides" :key="i">
                <img :src="slide" alt="" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out motion-reduce:transition-none"
                    :class="active === i ? 'opacity-100' : 'opacity-0'">
            </template>
            <div class="absolute inset-0 bg-gradient-to-b from-[#060D26]/35 via-[#060D26]/45 to-[#060D26]/55"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_75%_at_50%_40%,rgba(6,13,38,0.45),rgba(6,13,38,0)_70%)]"></div>

            <div class="relative z-10 mx-auto w-full max-w-[900px] px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
                <p class="inline-flex items-center font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] sm:text-[11.5px] font-bold uppercase tracking-[0.14em] text-white px-3.5 py-1.5 rounded-full border border-[#FF8A66]/60 bg-[#FF8A66]/10">
                    Your Next Place Starts Here
                </p>
                <h1 class="mt-5 sm:mt-6 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[40px] sm:text-[58px] lg:text-[70px] font-extrabold leading-[1.08] tracking-tight text-white text-balance">
                    Find a place to call home
                    <span class="block">in <span class="text-[#FF8A66]">Cebu</span>.</span>
                </h1>
                <p class="mt-5 sm:mt-6 mx-auto max-w-xl text-white/90 text-[15px] sm:text-[17px] font-normal">
                    Verified apartments, rooms, boarding houses, and condos &mdash; all in one place.
                </p>

                <div class="mt-8 sm:mt-10 flex justify-center">
                    <x-search-pill variant="hero" />
                </div>

                <div class="mt-6 sm:mt-7 flex flex-wrap items-center justify-center gap-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.06em] text-white">
                    <span class="px-3 py-1 rounded-full border border-white/25 bg-[#060D26]/40">{{ $heroStats['units'] }}+ available units</span>
                    <span class="px-3 py-1 rounded-full border border-white/25 bg-[#060D26]/40">Verified landlords</span>
                    <span class="px-3 py-1 rounded-full border border-white/25 bg-[#060D26]/40">Across Cebu</span>
                </div>
            </div>
        </section>

    @endif

    {{-- ===== BROWSE ===== --}}
    <div class="relative isolate overflow-hidden">
    @if($heroStats)
        {{-- Background texture for the wide side margins on the landing page: soft colour glows, a dot
             grid, diagonal hairlines and flowing curves. Purely decorative, low contrast, behind content. --}}
        <x-section-texture />
    @endif
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16 min-h-[60vh]" x-data="{ mobileView: 'list', mapVisible: false, filtersOpen: false }"
        x-effect="window.dispatchEvent(new CustomEvent('browse-map-state', { detail: mapVisible }))"
        @browse-toggle-map.window="mapVisible = !mapVisible; if (mapVisible) $nextTick(() => window.browseMapRefit?.())"
        @browse-open-filters.window="filtersOpen = true">

        @if($heroStats)
            @if($areas->count() > 0)
                {{-- ===== BROWSE BY AREA ===== --}}
                <div class="mb-14" x-data="{
                        scrollByPage(dir) { const el = this.$refs.track; el.scrollBy({ left: dir * el.clientWidth * 0.9, behavior: 'smooth' }); }
                    }">
                    <div class="flex items-end justify-between gap-4 mb-5">
                        <div>
                            <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[20px] sm:text-[24px] font-bold leading-tight text-[#060D26]">Browse by neighborhood</h2>
                            <span class="mt-1 block text-[13px] text-[#5B6A8E]">{{ $areas->count() }} {{ Str::plural('area', $areas->count()) }} &middot; {{ $areas->sum('count') }} listings</span>
                        </div>
                        <div class="hidden sm:flex items-center gap-2 shrink-0">
                            <button type="button" @click="scrollByPage(-1)" aria-label="Scroll left"
                                class="w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] flex items-center justify-center transition-colors duration-200 hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                            </button>
                            <button type="button" @click="scrollByPage(1)" aria-label="Scroll right"
                                class="w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] flex items-center justify-center transition-colors duration-200 hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                    {{-- Same swipeable scroll-snap row as "Popular places to stay": ~5 tiles visible on
                         desktop, ~1.5 on phones (next tile peeks in), "View all areas" is the last one. --}}
                    <div x-ref="track" class="flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth -mx-4 px-4 sm:mx-0 sm:px-0 py-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach($areas as $area)
                            <a href="{{ $area['url'] }}"
                                class="relative shrink-0 snap-start w-[62%] sm:w-[calc((100%-1.5rem)/3)] lg:w-[calc((100%-3rem)/5)] h-36 sm:h-40 rounded-2xl overflow-hidden group border border-[#E2E4EC] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66] hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                                <img src="{{ $area['photo'] }}" alt="{{ $area['name'] }}" loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/35 to-transparent"></div>
                                <div class="absolute bottom-3 left-3 right-3">
                                    <p class="text-white font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[14.5px] font-bold leading-tight truncate [text-shadow:0_1px_6px_rgba(6,13,38,0.5)]">{{ $area['name'] }}</p>
                                    <p class="mt-1 inline-flex font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12px] font-semibold text-white px-2.5 py-0.5 rounded-full border border-white/30 bg-[#060D26]/40">
                                        {{ $area['count'] }} {{ Str::plural('listing', $area['count']) }}
                                    </p>
                                </div>
                            </a>
                        @endforeach

                        <x-view-all-tile :href="route('properties.areas')" label="View all areas"
                            :sub="$areas->count() . ' neighborhoods'"
                            :photos="$areas->reverse()->pluck('photo')->all()"
                            class="w-[62%] sm:w-[calc((100%-1.5rem)/3)] lg:w-[calc((100%-3rem)/5)] h-36 sm:h-40" />
                    </div>
                </div>
            @endif

            @if($popularProperties->count() > 0)
                {{-- ===== POPULAR PLACES TO STAY ===== --}}
                <div class="mb-14" x-data="{
                        scrollByPage(dir) { const el = this.$refs.track; el.scrollBy({ left: dir * el.clientWidth * 0.9, behavior: 'smooth' }); }
                    }">
                    <div class="flex items-end justify-between gap-4 mb-6">
                        <div>
                            <p class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12px] font-semibold uppercase tracking-[0.08em] text-[#B35A3D]">Highest rated by tenants</p>
                            <h2 class="mt-1.5 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[20px] sm:text-[24px] font-bold leading-tight text-[#060D26]">Popular places to stay</h2>
                        </div>
                        <div class="hidden sm:flex items-center gap-2 shrink-0">
                            <button type="button" @click="scrollByPage(-1)" aria-label="Scroll left"
                                class="w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] flex items-center justify-center transition-colors duration-200 hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                            </button>
                            <button type="button" @click="scrollByPage(1)" aria-label="Scroll right"
                                class="w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] flex items-center justify-center transition-colors duration-200 hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                    {{-- A different card treatment than the main grid's borderless
                         image-is-the-card pattern (DESIGN.md §6), deliberately — this
                         boxed white-card style is specific to this section, mirroring
                         the reference mockup exactly. Only one use site, so inlined
                         rather than a new shared component. --}}
                    {{-- One swipeable row (scroll-snap): ~5 cards visible on desktop, ~1.5 on phones
                         so the next card peeks in as a swipe hint. "View all" is the last item. --}}
                    <div x-ref="track" class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth -mx-4 px-4 sm:mx-0 sm:px-0 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach($popularProperties as $property)
                            @php
                                $availableCount = $property->units->where('availability_status', 'Available')->count();
                                $photo = $property->media->firstWhere('media_type', 'Image')?->media_url;
                            @endphp
                            <a href="{{ route('properties.show', $property->property_id) }}"
                                class="block shrink-0 snap-start w-[72%] sm:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-4rem)/5)] bg-white border border-[#E2E4EC] rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66]/60 hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
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
                                                <p class="inline-block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10px] font-bold uppercase tracking-[0.04em] text-[#060D26] px-2 py-0.5 rounded-full bg-[#ECEEF6]">
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

                        <x-view-all-tile :href="route('properties.index', ['sort' => 'top_rated'])" label="View all popular properties"
                            sub="Highest rated by tenants"
                            :photos="$popularProperties->reverse()->map(fn ($p) => $p->media->firstWhere('media_type', 'Image')?->media_url)->all()"
                            class="w-[72%] sm:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-4rem)/5)]" />
                    </div>
                </div>
            @endif
        @endif

        {{-- ACTIVE FILTERS SUMMARY --}}
        @if(request()->hasAny(['location', 'type', 'price_min', 'price_max', 'verified', 'amenities']))
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

                @if(request('price_min') || request('price_max'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ECEEF6] text-[#060D26] border border-[#FF8A66]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        @if(request('price_min') && request('price_max'))
                            ₱{{ number_format(request('price_min')) }}&ndash;₱{{ number_format(request('price_max')) }}
                        @elseif(request('price_min'))
                            Min ₱{{ number_format(request('price_min')) }}
                        @else
                            Max ₱{{ number_format(request('price_max')) }}
                        @endif
                        <a href="{{ request()->fullUrlWithoutQuery(['price_min', 'price_max']) }}" class="hover:brightness-95"
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
        @if($heroStats)
        <div class="flex items-end justify-between gap-4 mb-6">
                <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[20px] sm:text-[24px] font-bold leading-tight text-[#060D26]">Every place, ready to view</h2>
                <span class="text-[13px] text-[#5B6A8E] whitespace-nowrap">
                    {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
                </span>
        </div>
        @endif

        @php
            $typePills = ['All' => null, 'Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House'];
            $activeType = request('type');
        @endphp
        <div class="mb-4 flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
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
                <div class="min-w-0">
                    <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[20px] font-bold leading-tight text-[#060D26]">{{ request('location') ? 'Rentals in ' . request('location') : 'All listings' }}</h2>
                    <p class="mt-0.5 text-[13.5px] text-[#5B6A8E]">{{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found</p>
                </div>
            @endif

            <div class="flex items-center gap-2">
                {{-- Desktop-only: the mobile List/Map switcher below already
                     covers small screens, where the two never share the screen. --}}
                <button type="button"
                    @click="mapVisible = !mapVisible; if (mapVisible) $nextTick(() => window.browseMapRefit?.())"
                    class="hidden lg:inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-[0_1px_3px_rgba(15,23,42,0.06)] hover:bg-[#F7F8FC] transition cursor-pointer">
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
                    class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-[0_1px_3px_rgba(15,23,42,0.06)] hover:bg-[#F7F8FC] transition cursor-pointer">
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
                        {{-- Carries location/type/price_min/price_max/sort through untouched —
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
                            <x-view-all-tile :href="route('properties.index', ['sort' => 'newest'])" label="View all rental properties"
                                :sub="$properties->total() . ' ' . Str::plural('listing', $properties->total())"
                                :photos="$properties->reverse()->map(fn ($p) => $p->media->firstWhere('media_type', 'Image')?->media_url)->all()"
                                class="min-h-[260px]" />
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
                    class="lg:sticky lg:top-[240px] lg:self-start">
                    <div id="browse-map"
                        class="w-full h-[400px] lg:h-[calc(100vh-256px)] lg:min-h-[420px] rounded-2xl overflow-hidden border border-[#FF8A66]">
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
    </div>

    @if($heroStats)
        {{-- How it works — after the listings, where it reassures rather than blocks the way to them. --}}
        {{-- Slides up into view the first time it scrolls on screen; steps stagger 150ms apart. --}}
        <section class="relative isolate overflow-hidden bg-white border-t border-[#E2E4EC]" aria-labelledby="how-it-works-title"
            x-data="{ shown: false }"
            x-init="if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) { shown = true; return; }
                    const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { shown = true; io.disconnect(); } }, { threshold: 0.25 });
                    io.observe($el);">
            <x-section-texture compact />
            <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
                <h2 id="how-it-works-title" class="sr-only">How AbangananHub works</h2>
                <ol class="grid gap-8 sm:grid-cols-3 sm:gap-10">
                    @foreach([
                        ['01', 'Find a verified place', 'Search by area, budget and type. Every landlord is identity-checked before listing.'],
                        ['02', 'Message the landlord', 'Ask questions, agree on move-in date and terms, all in one conversation.'],
                        ['03', 'Sign, pay, move in', 'Sign the rental agreement online and pay through the platform. Your deposit is held safely until move-in.'],
                    ] as $i => [$num, $title, $body])
                        <li class="transition-all duration-700 ease-out"
                            style="transition-delay: {{ $i * 150 }}ms"
                            :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'">
                            <span class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[32px] font-extrabold leading-none tracking-tight text-[#060D26] tabular-nums">{{ $num }}</span>
                            <span class="mt-2 block h-0.5 rounded-full bg-[#FF8A66] transition-all duration-700 ease-out"
                                style="transition-delay: {{ $i * 150 + 300 }}ms"
                                :class="shown ? 'w-8' : 'w-0'" aria-hidden="true"></span>
                            <h3 class="mt-3 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[16px] font-bold text-[#060D26]">{{ $title }}</h3>
                            <p class="mt-1 text-[14px] leading-relaxed text-[#5B6A8E] max-w-xs">{{ $body }}</p>
                        </li>
                    @endforeach
                </ol>
                <a href="{{ route('about') }}#how-it-works"
                    style="transition-delay: 600ms"
                    :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                    class="mt-8 inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-[#B35A3D] hover:underline transition-all duration-700 ease-out">
                    See the full step-by-step guide
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>
        </section>
    @endif

    @if($heroStats && !(auth()->check() && auth()->user()->hasRole('Landlord')))
        @php
            $ownerPerks = [
                ['Reach tenants already searching in Cebu', 'M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z'],
                ['Manage units, inquiries and rent in one place', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h6m-6 4h4'],
                ['Digital agreements, no paperwork chasing', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['A Verified badge that builds tenant trust', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ];
        @endphp
        <section class="relative isolate overflow-hidden bg-[#ECEEF6]" aria-labelledby="landlord-cta-title">
            <x-section-texture compact />
            <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16 grid gap-10 md:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] md:items-center lg:gap-16">
                <div>
                    <p class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#B35A3D]">For property owners</p>
                    <h2 id="landlord-cta-title" class="mt-2 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[26px] sm:text-[34px] font-extrabold leading-[1.15] tracking-tight text-[#060D26] text-balance">Have a room or unit to rent out?</h2>
                    <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                        @foreach($ownerPerks as [$perk, $icon])
                            <li class="flex items-center gap-3 rounded-2xl bg-white/70 border border-[#E2E4EC] p-3.5">
                                <span class="w-10 h-10 shrink-0 rounded-xl bg-[#060D26] text-[#FF8A66] flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                                </span>
                                <span class="text-[14px] leading-snug font-medium text-[#060D26]">{{ $perk }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ auth()->check() ? route('landlord.verification.create') : route('register') }}"
                        class="group relative isolate mt-9 w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-2xl bg-[#FF8A66] text-[#060D26] text-[17px] font-extrabold font-['Plus_Jakarta_Sans',_Inter,_sans-serif] shadow-[0_14px_32px_rgba(255,138,102,0.55)] transition-all duration-300 hover:bg-[#E96F4F] hover:-translate-y-0.5 hover:shadow-[0_18px_38px_rgba(255,138,102,0.65)] motion-reduce:hover:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26] focus-visible:ring-offset-2 focus-visible:ring-offset-[#ECEEF6]">
                        {{-- Slow pulsing halo so the button reads as the primary action of the section. --}}
                        <span class="absolute inset-0 -z-10 rounded-2xl bg-[#FF8A66] motion-safe:animate-[owner-cta-pulse_2.4s_ease-out_infinite]" aria-hidden="true"></span>
                        List your property
                        <span class="w-7 h-7 rounded-full bg-[#060D26] text-[#FF8A66] flex items-center justify-center transition-transform duration-300 group-hover:translate-x-1 motion-reduce:group-hover:translate-x-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" /></svg>
                        </span>
                    </a>
                </div>

                {{-- Decorative "landlord dashboard" scene: a lit building on navy with floating status chips. --}}
                <div class="hidden md:block relative h-[340px] lg:h-[380px] rounded-[28px] bg-[#060D26] overflow-hidden" aria-hidden="true">
                    <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full bg-[#FF8A66]/25 blur-3xl"></div>
                    <div class="absolute -bottom-20 -left-10 w-64 h-64 rounded-full bg-[#5B6A8E]/30 blur-3xl"></div>

                    {{-- 3D-style isometric building: lit left/right faces, a light roof, glowing windows,
                         and a soft ground platform. Window grids are drawn flat then skewed onto each face. --}}
                    @php
                        $litLeft  = [[1,0,0],[0,1,0],[1,0,1]];
                        $litRight = [[0,1,1],[1,0,0],[0,1,0],[1,1,0]];
                    @endphp
                    <svg viewBox="0 0 260 310" class="absolute left-[43%] bottom-[2%] -translate-x-1/2 h-[90%] w-auto" fill="none">
                        <defs>
                            <linearGradient id="ow-left" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1C2858"/><stop offset="1" stop-color="#2A3A78"/></linearGradient>
                            <linearGradient id="ow-right" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4356A6"/><stop offset="1" stop-color="#2E3F86"/></linearGradient>
                            <linearGradient id="ow-roof" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7183CC"/><stop offset="1" stop-color="#4A5DAA"/></linearGradient>
                            <linearGradient id="ow-lit" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFC7AF"/><stop offset="1" stop-color="#FF8A66"/></linearGradient>
                            <linearGradient id="ow-dim" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#A6B2DA" stop-opacity=".55"/><stop offset="1" stop-color="#6A79B0" stop-opacity=".4"/></linearGradient>
                            <linearGradient id="ow-plat" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2B3A78"/><stop offset="1" stop-color="#141D45"/></linearGradient>
                            <radialGradient id="ow-shadow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".55"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
                        </defs>

                        <ellipse cx="128" cy="292" rx="122" ry="16" fill="url(#ow-shadow)"/>
                        <path d="M8 262l112 42 132-46-132-52z" fill="url(#ow-plat)"/>
                        <path d="M8 262l112 42 132-46" stroke="#5F70B8" stroke-opacity=".5" stroke-width="1.5" stroke-linejoin="round"/>

                        {{-- faces --}}
                        <path d="M30 60l90 35v190l-90-35z" fill="url(#ow-left)"/>
                        <path d="M120 95l90-35v190l-90 35z" fill="url(#ow-right)"/>
                        <path d="M30 60l90-35 90 35-90 35z" fill="url(#ow-roof)"/>
                        {{-- rooftop unit --}}
                        <path d="M92 52l22-8 22 8-22 8z" fill="#8FA0E0"/>
                        <path d="M92 52v12l22 8V60z" fill="#3B4C98"/><path d="M114 60v12l22-8V52z" fill="#5468BC"/>

                        {{-- left-face windows + lobby door --}}
                        <g transform="translate(30 60) skewY(21.25)">
                            @foreach($litLeft as $r => $row)
                                @foreach($row as $c => $lit)
                                    @if($lit)<rect x="{{ 8 + $c * 26 }}" y="{{ 12 + $r * 44 }}" width="22" height="30" rx="3" fill="#FF8A66" opacity=".28"/>@endif
                                    <rect x="{{ 10 + $c * 26 }}" y="{{ 14 + $r * 44 }}" width="18" height="26" rx="2.5" fill="url(#{{ $lit ? 'ow-lit' : 'ow-dim' }})"/>
                                @endforeach
                            @endforeach
                            <rect x="30" y="144" width="30" height="46" rx="3" fill="#0B1435"/>
                            <rect x="26" y="138" width="38" height="7" rx="2" fill="#FF8A66"/>
                        </g>

                        {{-- right-face windows --}}
                        <g transform="translate(120 95) skewY(-21.25)">
                            @foreach($litRight as $r => $row)
                                @foreach($row as $c => $lit)
                                    @if($lit)<rect x="{{ 8 + $c * 26 }}" y="{{ 12 + $r * 44 }}" width="22" height="30" rx="3" fill="#FF8A66" opacity=".28"/>@endif
                                    <rect x="{{ 10 + $c * 26 }}" y="{{ 14 + $r * 44 }}" width="18" height="26" rx="2.5" fill="url(#{{ $lit ? 'ow-lit' : 'ow-dim' }})"/>
                                @endforeach
                            @endforeach
                        </g>

                        {{-- edge highlights sell the 3D volume --}}
                        <path d="M30 60l90-35 90 35" stroke="#B5C2F5" stroke-opacity=".65" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M120 95v190" stroke="#8FA0E6" stroke-opacity=".5" stroke-width="1.5"/>
                        <path d="M30 60l90 35 90-35" stroke="#9AA9EA" stroke-opacity=".45" stroke-width="1.2" stroke-linejoin="round"/>
                    </svg>

                    {{-- 3D-style landlord: rounded "clay" shapes with gradients and soft highlights. --}}
                    <svg viewBox="0 0 110 236" class="absolute bottom-[3%] left-[62%] h-[56%] w-auto" fill="none">
                        <defs>
                            <radialGradient id="ow-skin" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="#FFDCC6"/><stop offset="1" stop-color="#E29A78"/></radialGradient>
                            <linearGradient id="ow-jacket" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFB094"/><stop offset="1" stop-color="#E2603C"/></linearGradient>
                            <linearGradient id="ow-sleeve" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF9C7A"/><stop offset="1" stop-color="#CF5433"/></linearGradient>
                            <linearGradient id="ow-pants" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#5D6FAE"/><stop offset="1" stop-color="#2C3970"/></linearGradient>
                            <linearGradient id="ow-hair" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#434C7E"/><stop offset="1" stop-color="#10152C"/></linearGradient>
                            <linearGradient id="ow-shoe" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#BCC6E6"/></linearGradient>
                            <linearGradient id="ow-gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFE9A3"/><stop offset="1" stop-color="#E0A21B"/></linearGradient>
                            <radialGradient id="ow-pshadow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".6"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
                        </defs>
                        <ellipse cx="55" cy="230" rx="42" ry="7" fill="url(#ow-pshadow)"/>
                        {{-- legs + sneakers --}}
                        <rect x="34" y="130" width="17" height="94" rx="8" fill="url(#ow-pants)"/>
                        <rect x="56" y="130" width="17" height="94" rx="8" fill="url(#ow-pants)"/>
                        <rect x="29" y="216" width="26" height="14" rx="7" fill="url(#ow-shoe)"/>
                        <rect x="53" y="216" width="26" height="14" rx="7" fill="url(#ow-shoe)"/>
                        {{-- left arm --}}
                        <rect x="13" y="66" width="20" height="68" rx="10" fill="url(#ow-sleeve)"/>
                        <circle cx="23" cy="138" r="8" fill="url(#ow-skin)"/>
                        {{-- torso --}}
                        <rect x="24" y="60" width="62" height="80" rx="20" fill="url(#ow-jacket)"/>
                        <ellipse cx="42" cy="82" rx="12" ry="20" fill="#fff" opacity=".16"/>
                        <path d="M74 64c8 4 12 14 12 26v34a18 18 0 01-18 16h6c10 0 18-8 18-18V84c0-10-6-18-18-20z" fill="#B8431F" opacity=".22"/>
                        <path d="M45 61h22L56 94z" fill="#fff"/>
                        {{-- raised arm holding the key --}}
                        <path d="M78 72l19 21a9 9 0 01-3 14l-6 3" stroke="url(#ow-sleeve)" stroke-width="18" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="87" cy="108" r="8.5" fill="url(#ow-skin)"/>
                        <g transform="translate(90 80) rotate(20)">
                            <circle cx="0" cy="0" r="6.5" stroke="url(#ow-gold)" stroke-width="3.4"/>
                            <path d="M0 6.5v20m0-6h6.5m-6.5 6h5" stroke="url(#ow-gold)" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                        {{-- neck, head, ears, hair, face --}}
                        <rect x="48" y="46" width="14" height="20" rx="6" fill="#DB9270"/>
                        <circle cx="35" cy="36" r="4.5" fill="#E9A583"/><circle cx="75" cy="36" r="4.5" fill="#E9A583"/>
                        <circle cx="55" cy="34" r="21" fill="url(#ow-skin)"/>
                        <path d="M34 33c0-15 9-23 21-23s21 8 21 23c-4-7-10-10-17-10-8 0-18 3-25 10z" fill="url(#ow-hair)"/>
                        <ellipse cx="46" cy="16" rx="8" ry="3.5" fill="#fff" opacity=".22"/>
                        <circle cx="47" cy="38" r="2.3" fill="#1B2140"/><circle cx="63" cy="38" r="2.3" fill="#1B2140"/>
                        <circle cx="47.8" cy="37.2" r=".8" fill="#fff"/><circle cx="63.8" cy="37.2" r=".8" fill="#fff"/>
                        <circle cx="41" cy="45" r="3.4" fill="#FF8F7A" opacity=".45"/><circle cx="69" cy="45" r="3.4" fill="#FF8F7A" opacity=".45"/>
                        <path d="M49 46c3.5 3.5 8.5 3.5 12 0" stroke="#1B2140" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>

                    <div class="absolute top-6 left-5 flex items-center gap-2.5 rounded-2xl bg-white px-3.5 py-2.5 shadow-[0_12px_28px_rgba(0,0,0,0.3)] motion-safe:animate-[owner-float_6s_ease-in-out_infinite]">
                        <span class="w-8 h-8 rounded-full bg-[#E7F6EC] text-[#1F8A4C] flex items-center justify-center">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ownerPerks[3][1] }}" /></svg>
                        </span>
                        <span class="text-[12.5px] font-bold text-[#060D26] leading-tight">Verified<span class="block text-[10.5px] font-medium text-[#5B6A8E]">Landlord</span></span>
                    </div>
                    <div class="absolute top-24 right-5 flex items-center gap-2.5 rounded-2xl bg-white px-3.5 py-2.5 shadow-[0_12px_28px_rgba(0,0,0,0.3)] motion-safe:animate-[owner-float_7s_ease-in-out_-2s_infinite]">
                        <span class="w-8 h-8 rounded-full bg-[#FFE9E1] text-[#B35A3D] flex items-center justify-center">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2.5-3H19a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14z" /></svg>
                        </span>
                        <span class="text-[12.5px] font-bold text-[#060D26] leading-tight">New inquiry<span class="block text-[10.5px] font-medium text-[#5B6A8E]">A tenant is interested</span></span>
                    </div>
                    <div class="absolute bottom-10 left-5 flex items-center gap-2.5 rounded-2xl bg-white px-3.5 py-2.5 shadow-[0_12px_28px_rgba(0,0,0,0.3)] motion-safe:animate-[owner-float_8s_ease-in-out_-4s_infinite]">
                        <span class="w-8 h-8 rounded-full bg-[#E8ECFA] text-[#2A3A75] flex items-center justify-center text-[15px] font-extrabold">&#8369;</span>
                        <span class="text-[12.5px] font-bold text-[#060D26] leading-tight">Rent received<span class="block text-[10.5px] font-medium text-[#5B6A8E]">Tracked automatically</span></span>
                    </div>
                </div>
                <style>@keyframes owner-cta-pulse { 0% { opacity: .55; transform: scale(1); } 70%, 100% { opacity: 0; transform: scale(1.14, 1.35); } }
                @keyframes owner-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }</style>
            </div>
        </section>
    @endif

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
