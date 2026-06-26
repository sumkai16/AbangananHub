@extends('layouts.app')

@section('content')

    <div class="max-w-7xl mx-auto px-6 py-10 pb-16" x-data="{ mobileView: 'list' }">

        {{-- HEADER --}}
        <x-section-header title="Browse Properties"
            sub="Verified rentals across Cebu — every listing reviewed, every landlord checked." />

        {{-- ACTIVE FILTERS SUMMARY --}}
        @if(request()->hasAny(['location', 'type', 'price_max', 'verified']))
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[13px] text-[#9B9F98] font-medium">Filtering by:</span>

                @if(request('location'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#D7E8F3] text-[#2A2523] border border-[#61B2F0]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ request('location') }}
                        <a href="{{ request()->fullUrlWithoutQuery('location') }}" class="hover:brightness-95">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('type'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#D7E8F3] text-[#2A2523] border border-[#61B2F0]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {{ request('type') }}
                        <a href="{{ request()->fullUrlWithoutQuery('type') }}" class="hover:brightness-95">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('price_max'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#D7E8F3] text-[#2A2523] border border-[#61B2F0]/40 rounded-full text-[13px] font-semibold">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Max ₱{{ number_format(request('price_max')) }}
                        <a href="{{ request()->fullUrlWithoutQuery('price_max') }}" class="hover:brightness-95">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                @if(request('verified'))
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#D7E8F3] text-[#2A2523] border border-[#61B2F0]/40 rounded-full text-[13px] font-semibold">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Verified only
                        <a href="{{ request()->fullUrlWithoutQuery('verified') }}" class="hover:brightness-95">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </a>
                    </span>
                @endif

                <a href="{{ route('properties.index') }}"
                    class="text-[13px] text-[#BD5434] hover:brightness-95 font-semibold ml-2">
                    Clear all
                </a>
            </div>
        @endif

        {{-- RESULTS COUNT --}}
        <p class="text-[13px] text-[#9B9F98] font-medium mb-4">
            {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} found
        </p>

        {{-- MOBILE LIST/MAP TOGGLE — desktop shows both columns, this is mobile-only --}}
        <div class="flex lg:hidden gap-2 mb-5">
            <button type="button" @click="mobileView = 'list'"
                :class="mobileView === 'list' ? 'bg-[#2A2523] text-white' : 'bg-white text-[#2A2523] border border-[#9B9F98]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition">
                List
            </button>
            <button type="button" @click="mobileView = 'map'"
                :class="mobileView === 'map' ? 'bg-[#2A2523] text-white' : 'bg-white text-[#2A2523] border border-[#9B9F98]/30'"
                class="flex-1 py-2 rounded-full text-[13px] font-semibold transition">
                Map
            </button>
        </div>

        @if($properties->count() > 0)
            <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-8">

                {{-- LIST COLUMN --}}
                <div :class="mobileView === 'list' ? 'block' : 'hidden'" class="lg:!block">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @foreach($properties as $property)
                            <div data-property-card="{{ $property->property_id }}"
                                class="group relative cursor-pointer transition-all duration-300 hover:-translate-y-1"
                                onclick="window.location='{{ route('properties.show', $property->property_id) }}'">

                                {{-- IMAGE --}}
                                <div
                                    class="relative w-full aspect-square rounded-3xl overflow-hidden bg-gray-100 shadow-sm group-hover:shadow-lg transition-all duration-500">
                                    @if($property->media->first())
                                        <img src="{{ $property->media->first()->media_url }}" alt="{{ $property->title }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-[#D7E8F3]">
                                            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#61B2F0" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                            </svg>
                                        </div>
                                    @endif

                                    {{-- TYPE BADGE top-left --}}
                                    <div class="absolute top-3 left-3">
                                        <span
                                            class="px-2.5 py-1 bg-white/90 backdrop-blur-sm text-[11px] font-bold text-[#2A2523] rounded-full shadow-sm">
                                            {{ $property->property_type }}
                                        </span>
                                    </div>

                                    {{-- HEART top-right --}}
                                    <button type="button" data-property-id="{{ $property->property_id }}"
                                        data-favorited="{{ in_array($property->property_id, $favoritedIds) ? 'true' : 'false' }}"
                                        onclick="event.stopPropagation(); toggleFavorite(this)"
                                        class="favorite-btn absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 hover:scale-110 active:scale-95 transition-all duration-200">
                                        <svg class="heart-outline {{ in_array($property->property_id, $favoritedIds) ? 'hidden' : '' }}"
                                            width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                        <svg class="heart-filled {{ in_array($property->property_id, $favoritedIds) ? '' : 'hidden' }}"
                                            width="20" height="20" viewBox="0 0 24 24" fill="#61B2F0" stroke="#61B2F0" stroke-width="1">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- TEXT BELOW IMAGE — no card box --}}
                                <div class="mt-3 px-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="text-[14px] font-semibold text-[#2A2523] leading-snug line-clamp-1">
                                            {{ $property->title }}
                                        </h3>
                                        <span
                                            class="text-[12px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0
                                                {{ $property->availability_status === 'Available' ? 'bg-[#D7E8F3] text-[#2A2523]' : 'bg-[#BD5434]/10 text-[#BD5434]' }}">
                                            {{ $property->availability_status }}
                                        </span>
                                    </div>

                                    <p class="text-[13px] text-[#9B9F98] mt-0.5 line-clamp-1 flex items-center gap-1">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                            class="flex-shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ $property->address }}
                                    </p>

                                    <p class="text-[14px] font-semibold text-[#2A2523] mt-1.5">
                                        ₱{{ number_format($property->rental_fee) }}
                                        <span class="text-[13px] font-normal text-[#9B9F98]">/month</span>
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
                <div :class="mobileView === 'map' ? 'block' : 'hidden'" class="lg:!block lg:sticky lg:top-24 lg:self-start">
                    <div id="browse-map"
                        class="w-full h-[400px] lg:h-[calc(100vh-140px)] rounded-2xl overflow-hidden border border-[#9B9F98]/20"></div>
                    <script type="application/json" id="browse-map-data">{!! json_encode($mapProperties) !!}</script>
                </div>

            </div>
        @else
            <x-empty-state title="No properties found"
                message="Try adjusting your filters or search in a different area of Cebu." :href="route('properties.index')"
                cta="Clear filters">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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