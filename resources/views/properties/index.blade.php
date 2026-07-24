{{-- The hero owns search on this page, so the header's copy is suppressed.
     Both render <x-search-pill>, so there is still only one implementation. --}}
@extends('layouts.app', ['searchBar' => false])

@section('content')

    @php
        // A tall hero is right on arrival and in the way once you're comparing
        // results, so it collapses to a band carrying just the search pill as
        // soon as the page is doing work. $heroStats is null in that state.
        $heroExpanded = $heroStats !== null;
    @endphp

    {{-- ===== HERO =====
         Full-bleed brand gradient, reusing about.blade.php's rather than
         introducing a second one. The white pill's heavy shadow is what lifts
         it off the dark ground — that lift is the page's signature, so nothing
         else in here competes for attention. --}}
    {{-- Expanded, the hero fills the viewport *below the 72px header* rather than a
         flat 100vh — a true 100vh section sits 72px lower than the fold and pushes
         its own bottom edge off screen. min-h (not h) so a short laptop or a
         landscape phone grows the section instead of clipping the search pill. --}}
    <section
        class="relative overflow-hidden bg-gradient-to-br from-[#156F8C] via-[#1F2937] to-[#156F8C] {{ $heroExpanded ? 'min-h-[calc(100vh-72px)] flex items-center py-16' : 'py-7' }}">

        {{-- Depth only: a single aqua bloom off the top-right corner. --}}
        <div class="pointer-events-none absolute -top-32 -right-24 w-[520px] h-[520px] rounded-full bg-[#69D2C6]/10 blur-3xl"
            aria-hidden="true"></div>

        <div class="relative w-full max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">

            @if($heroExpanded)
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#69D2C6] mb-4">
                    Verified rentals across Cebu
                </p>

                <h1 class="font-display text-[32px] sm:text-[52px] lg:text-[60px] leading-[1.05] font-bold text-white tracking-tight max-w-[760px]">
                    Find your next <em class="italic text-[#69D2C6]">home</em> in Cebu
                </h1>

                <p class="mt-5 text-[15px] sm:text-[16px] leading-relaxed text-white/75 max-w-[520px]">
                    Apartments, rooms, bedspaces and houses. Every listing is reviewed and every
                    landlord is ID-verified before it goes live.
                </p>
            @else
                <h1 class="sr-only">Browse properties</h1>
            @endif

            <div class="{{ $heroExpanded ? 'mt-9' : '' }}">
                <x-search-pill variant="hero" />
            </div>

            @if($heroExpanded)
                {{-- Live counts, not platform ambition. See PropertyController@index. --}}
                <div class="mt-6 flex flex-wrap items-center gap-x-7 gap-y-3 text-[13px] font-medium text-white/70">
                    <span class="inline-flex items-center gap-2">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#69D2C6" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {{ $heroStats['listings'] }} {{ Str::plural('listing', $heroStats['listings']) }} live
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#69D2C6" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 7v10m18-10v10M3 17h18M6 12h12" />
                        </svg>
                        {{ $heroStats['units'] }} {{ Str::plural('unit', $heroStats['units']) }} available now
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#69D2C6" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Every landlord ID-verified
                    </span>
                </div>
            @endif

        </div>
    </section>

    {{-- ===== CATEGORY STRIP =====
         Same component the header carries on other pages; here it sits under
         the hero where the reference puts it. --}}
    <div class="border-b border-[#E2E8F0] bg-white">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <x-category-strip />
        </div>
    </div>

    {{-- ===== BROWSE ===== --}}
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16 min-h-[60vh]" x-data="{ mobileView: 'list' }">

        {{-- ACTIVE FILTERS SUMMARY --}}
        @if(request()->hasAny(['location', 'type', 'price_max', 'verified']))
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[13px] text-[#64748B] font-medium">Filtering by:</span>

                @if(request('location'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#EEF8F8] text-[#1F2937] border border-[#2AA7A1]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#EEF8F8] text-[#1F2937] border border-[#2AA7A1]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#EEF8F8] text-[#1F2937] border border-[#2AA7A1]/40 rounded-full text-[13px] font-semibold">
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
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#EEF8F8] text-[#1F2937] border border-[#2AA7A1]/40 rounded-full text-[13px] font-semibold">
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
            <p class="text-[13px] text-[#64748B] font-medium">
                {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
            </p>

            <form method="GET" class="flex items-center gap-2">
                @foreach(request()->except(['sort', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="sort" class="text-[13px] text-[#64748B] font-medium hidden sm:inline">Sort by</label>
                <select id="sort" name="sort" onchange="this.form.submit()"
                    class="h-9 text-[13px] font-semibold rounded-full border border-[#64748B]/30 bg-white text-[#1F2937] pl-3.5 pr-8 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all cursor-pointer">
                    <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>Newest</option>
                    <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>Price: Low to High
                    </option>
                    <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>Price: High to Low
                    </option>
                </select>
            </form>
        </div>

        {{-- MOBILE LIST/MAP TOGGLE — desktop shows both columns, this is mobile-only --}}
        <div class="flex lg:hidden gap-2 mb-5">
            <button type="button" @click="mobileView = 'list'"
                :class="mobileView === 'list' ? 'bg-[#1F2937] text-white' : 'bg-white text-[#1F2937] border border-[#64748B]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition cursor-pointer">
                List
            </button>
            <button type="button" @click="mobileView = 'map'"
                :class="mobileView === 'map' ? 'bg-[#1F2937] text-white' : 'bg-white text-[#1F2937] border border-[#64748B]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition cursor-pointer">
                Map
            </button>
        </div>

        @if($properties->count() > 0)
            {{-- The map used to be a fixed 875px, which at 1440 left the list ~490px
                 to fit three cards — every title truncated. The map now scales with
                 the breakpoint and the grid stays at two columns, so a card is never
                 narrower than its own title. --}}
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] xl:grid-cols-[1fr_560px] gap-6">

                {{-- LIST COLUMN --}}
                <div :class="mobileView === 'list' ? 'block' : 'hidden'" class="lg:!block">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @foreach($properties as $property)
                            <div data-property-card="{{ $property->property_id }}"
                                class="group relative cursor-pointer transition-all duration-300 hover:-translate-y-1 motion-reduce:hover:translate-y-0"
                                onclick="window.location='{{ route('properties.show', $property->property_id) }}'">

                                {{-- IMAGE CAROUSEL --}}
                                <div x-data="{ activeSlide: 0, slides: {{ $property->media->count() }} }"
                                    @mouseenter="$refs.nav.classList.remove('opacity-0')"
                                    @mouseleave="$refs.nav.classList.add('opacity-0')"
                                    class="relative w-full aspect-square rounded-3xl overflow-hidden bg-[#EEF8F8] shadow-sm group-hover:shadow-lg transition-all duration-500">

                                    @if($property->media->count() > 0)
                                        <div class="flex transition-transform duration-500 ease-out h-full"
                                            :style="`transform: translateX(-${activeSlide * 100}%)`">
                                            @foreach($property->media as $media)
                                                <img src="{{ $media->media_url }}" alt="{{ $property->title }}"
                                                    class="w-full h-full object-cover flex-shrink-0 group-hover:scale-105 transition-transform duration-700 ease-out motion-reduce:group-hover:scale-100">
                                            @endforeach
                                        </div>

                                        {{-- Navigation Arrows (visible on hover) --}}
                                        <div x-ref="nav"
                                            class="opacity-0 transition-opacity duration-300 absolute inset-0 flex items-center justify-between px-2"
                                            x-show="slides > 1">
                                            <button @click.stop="activeSlide = activeSlide > 0 ? activeSlide - 1 : slides - 1"
                                                aria-label="Previous photo"
                                                class="w-7 h-7 flex items-center justify-center rounded-full bg-white/80 hover:bg-white hover:scale-110 shadow-sm transition-all text-[#1F2937] cursor-pointer">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                                </svg>
                                            </button>
                                            <button @click.stop="activeSlide = activeSlide < slides - 1 ? activeSlide + 1 : 0"
                                                aria-label="Next photo"
                                                class="w-7 h-7 flex items-center justify-center rounded-full bg-white/80 hover:bg-white hover:scale-110 shadow-sm transition-all text-[#1F2937] cursor-pointer">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </div>

                                        {{-- Pagination Dots --}}
                                        <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5 z-10"
                                            x-show="slides > 1">
                                            <template x-for="i in slides" :key="i">
                                                <div class="w-1.5 h-1.5 rounded-full transition-all duration-300 shadow-sm"
                                                    :class="(i-1) === activeSlide ? 'bg-white scale-125' : 'bg-white/50'"></div>
                                            </template>
                                        </div>
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-[#EEF8F8]">
                                            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1"
                                                stroke-width="1.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                            </svg>
                                        </div>
                                    @endif

                                    {{-- HEART top-right --}}
                                    <button type="button" data-property-id="{{ $property->property_id }}"
                                        data-favorited="{{ in_array($property->property_id, $favoritedIds) ? 'true' : 'false' }}"
                                        aria-label="Save {{ $property->title }} to favorites"
                                        onclick="event.stopPropagation(); toggleFavorite(this)"
                                        class="favorite-btn absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 hover:scale-110 active:scale-95 transition-all duration-200 cursor-pointer">
                                        <svg class="heart-outline {{ in_array($property->property_id, $favoritedIds) ? 'hidden' : '' }}"
                                            width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white"
                                            stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                        <svg class="heart-filled {{ in_array($property->property_id, $favoritedIds) ? '' : 'hidden' }}"
                                            width="20" height="20" viewBox="0 0 24 24" fill="#EF4444" stroke="#EF4444"
                                            stroke-width="1" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- TEXT BELOW IMAGE — no card box --}}
                                <div class="mt-3 px-1">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#156F8C] mb-0.5">
                                        {{ $property->property_type }}
                                    </p>

                                    <h3 class="text-[14px] font-semibold text-[#1F2937] leading-snug line-clamp-1">
                                        {{ $property->title }}
                                    </h3>

                                    <p class="text-[13px] text-[#64748B] mt-0.5 line-clamp-1 flex items-center gap-1">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2" class="flex-shrink-0" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ $property->address }}
                                    </p>
                                    @if($property->review_count > 0)
                                        <div class="flex items-center gap-1 mt-1">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="#FBBF24" stroke="#FBBF24"
                                                stroke-width="1" aria-hidden="true">
                                                <path
                                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                            <span
                                                class="text-[13px] font-semibold text-[#1F2937]">{{ number_format($property->avg_rating, 1) }}</span>
                                            <span class="text-[12px] text-[#64748B]">({{ $property->review_count }})</span>
                                        </div>
                                    @endif
                                    <p class="text-[14px] font-semibold text-[#1F2937] mt-1.5">
                                        @if($property->min_rental_fee)
                                            ₱{{ number_format($property->min_rental_fee) }}
                                            <span class="text-[13px] font-normal text-[#64748B]">/month</span>
                                        @else
                                            <span class="text-[13px] font-normal text-[#64748B]">Price not set</span>
                                        @endif
                                    </p>
                                </div>

                            </div>
                        @endforeach
                    </div>

                    {{-- PAGINATION --}}
                    <div class="flex justify-center mt-10">
                        {{ $properties->links() }}
                    </div>
                </div>

                {{-- MAP COLUMN --}}
                <div :class="mobileView === 'map' ? 'block' : 'hidden'" class="lg:!block lg:sticky lg:top-[72px] lg:self-start">
                    <div id="browse-map"
                        class="w-full h-[400px] lg:h-[calc(100vh-72px)] rounded-2xl overflow-hidden border border-[#64748B]/20">
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
                        window.location.href = "{{ route('login') }}";
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
