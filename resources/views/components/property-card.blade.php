@props(['property', 'favoritedIds' => []])

{{--
    The image-is-the-card property listing card. Shared by the main browse
    grid and the "Popular places" hero-section grid so the two can never
    drift into two different card designs — see properties/index.blade.php.
--}}
<div data-property-card="{{ $property->property_id }}"
    class="group relative cursor-pointer transition-all duration-300 hover:-translate-y-1 motion-reduce:hover:translate-y-0"
    onclick="window.location='{{ route('properties.show', $property->property_id) }}'">

    {{-- IMAGE CAROUSEL --}}
    <div x-data="{ activeSlide: 0, slides: {{ $property->media->count() }} }"
        @mouseenter="$refs.nav.classList.remove('opacity-0')"
        @mouseleave="$refs.nav.classList.add('opacity-0')"
        class="relative w-full aspect-square rounded-3xl overflow-hidden bg-[#ECEEF6] shadow-sm group-hover:shadow-lg transition-all duration-500">

        @if($property->hasVerifiedDocuments())
            <span class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 bg-[#060D26] text-[#F7F4ED] text-[10.5px] font-bold px-2 py-1 rounded-full shadow-sm">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Verified
            </span>
        @endif

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
                    class="w-7 h-7 flex items-center justify-center rounded-full bg-white/80 hover:bg-white hover:scale-110 shadow-sm transition-all text-[#060D26] cursor-pointer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button @click.stop="activeSlide = activeSlide < slides - 1 ? activeSlide + 1 : 0"
                    aria-label="Next photo"
                    class="w-7 h-7 flex items-center justify-center rounded-full bg-white/80 hover:bg-white hover:scale-110 shadow-sm transition-all text-[#060D26] cursor-pointer">
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
            <div class="w-full h-full flex items-center justify-center bg-[#ECEEF6]">
                <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#8a6e1e"
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
        <p class="text-[11px] font-bold uppercase tracking-wide text-[#060D26] mb-0.5">
            {{ $property->property_type }}
        </p>

        <h3 class="text-[14px] font-normal text-[#060D26] leading-snug line-clamp-1">
            {{ $property->title }}
        </h3>

        <p class="text-[13px] text-[#5B6A8E] mt-0.5 line-clamp-1 flex items-center gap-1">
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
                    class="text-[13px] font-semibold text-[#060D26]">{{ number_format($property->avg_rating, 1) }}</span>
                <span class="text-[12px] text-[#5B6A8E]">({{ $property->review_count }})</span>
            </div>
        @endif
        <p class="text-[14px] font-semibold text-[#060D26] mt-1.5">
            @if($property->min_rental_fee)
                ₱{{ number_format($property->min_rental_fee) }}
                <span class="text-[13px] font-normal text-[#5B6A8E]">/month</span>
            @else
                <span class="text-[13px] font-normal text-[#5B6A8E]">Price not set</span>
            @endif
        </p>
    </div>

</div>
