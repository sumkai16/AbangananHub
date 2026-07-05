@extends('layouts.app', ['searchBar' => false])

@section('content')
<div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-10 pb-16">

    {{-- HEADER --}}
    <x-section-header
        title="Saved Listings"
        sub="Properties you've saved for later."
    />

    {{-- Search & Filter Bar --}}
    <form method="GET" action="{{ route('favorites.index') }}"
        class="flex flex-col sm:flex-row gap-3 mb-8 p-4 bg-white rounded-2xl border border-[#64748B]/20 shadow-sm">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B] pointer-events-none"
                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search saved properties…"
                class="w-full pl-9 pr-4 py-2.5 text-sm text-[#1F2937] bg-[#F7FCFC] border border-[#64748B]/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 focus:border-[#2AA7A1] transition placeholder-[#64748B]" />
        </div>
        <select name="type"
            class="px-4 py-2.5 text-sm font-semibold text-[#1F2937] bg-[#F7FCFC] border border-[#64748B]/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 focus:border-[#2AA7A1] transition">
            <option value="" {{ !request('type') ? 'selected' : '' }}>Any type</option>
            @foreach(['Bedspace', 'Room', 'Apartment', 'House'] as $t)
                <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
        <select name="availability"
            class="px-4 py-2.5 text-sm font-semibold text-[#1F2937] bg-[#F7FCFC] border border-[#64748B]/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 focus:border-[#2AA7A1] transition">
            <option value="" {{ !request('availability') ? 'selected' : '' }}>Any status</option>
            <option value="Available" {{ request('availability') === 'Available' ? 'selected' : '' }}>Available</option>
            <option value="Unavailable" {{ request('availability') === 'Unavailable' ? 'selected' : '' }}>Unavailable</option>
        </select>
        <button type="submit"
            class="px-5 py-2.5 text-sm font-bold text-white bg-[#2AA7A1] hover:brightness-95 rounded-xl shadow-sm transition-all duration-200">
            Search
        </button>
        @if(request()->hasAny(['search', 'type', 'availability']))
            <a href="{{ route('favorites.index') }}"
                class="px-4 py-2.5 text-sm font-semibold text-[#1F2937] bg-[#F7FCFC] border border-[#64748B]/30 hover:bg-[#EEF8F8] rounded-xl transition-all duration-200 text-center">
                Clear
            </a>
        @endif
    </form>

    @if($favorites->count() > 0)

        <p class="text-[13px] text-gray-400 font-medium mb-6">
            {{ $favorites->count() }} {{ Str::plural('property', $favorites->count()) }} saved
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($favorites as $favorite)
                @php $property = $favorite->property; @endphp

                {{-- Skip if property was deleted or de-approved --}}
                @if(!$property || $property->verification_status !== 'Approved')
                    @continue
                @endif

                <div class="group relative cursor-pointer"
                    onclick="window.location='{{ route('properties.show', $property->property_id) }}'">

                    {{-- IMAGE --}}
                    <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-gray-100">
                        @if($property->media->first())
                            <img
                                src="{{ $property->media->first()->media_url }}"
                                alt="{{ $property->title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-[#EBF3FF]">
                                <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                            </div>
                        @endif

                        {{-- TYPE BADGE --}}
                        <div class="absolute top-3 left-3">
                            <span class="px-2.5 py-1 bg-white/90 backdrop-blur-sm text-[11px] font-bold text-gray-700 rounded-full shadow-sm">
                                {{ $property->property_type }}
                            </span>
                        </div>

                        {{-- HEART — always filled on this page, but still toggleable --}}
                        <button
                            type="button"
                            data-property-id="{{ $property->property_id }}"
                            data-favorited="true"
                            onclick="event.stopPropagation(); toggleFavorite(this, {{ $favorite->favorite_id ?? 'null' }})"
                            class="favorite-btn absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 transition-colors">
                            <svg class="heart-outline hidden"
                                width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            <svg class="heart-filled"
                                width="20" height="20" viewBox="0 0 24 24" fill="#EF4444" stroke="#EF4444" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </div>

                    {{-- TEXT --}}
                    <div class="mt-3 px-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-[14px] font-semibold text-[#156F8C] leading-snug line-clamp-1">
                                {{ $property->title }}
                            </h3>
                            <span class="text-[12px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0
                                {{ $property->availability_status === 'Available' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $property->availability_status }}
                            </span>
                        </div>

                        <p class="text-[13px] text-gray-400 mt-0.5 line-clamp-1 flex items-center gap-1">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $property->address }}
                        </p>

                        <p class="text-[14px] font-semibold text-[#156F8C] mt-1.5">
                            ₱{{ number_format($property->rental_fee) }}
                            <span class="text-[13px] font-normal text-gray-400">/month</span>
                        </p>
                    </div>

                </div>
            @endforeach
        </div>

    @else
        <x-empty-state
            title="No saved listings yet"
            message="Heart a property while browsing to save it here."
            :href="route('properties.index')"
            cta="Browse properties"
        >
            <x-slot name="icon">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </x-slot>
        </x-empty-state>
    @endif

</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    window.toggleFavorite = function (btn) {
        const propertyId = btn.dataset.propertyId;
        const isFavorited = btn.dataset.favorited === 'true';

        fetch(`/favorites/${propertyId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(res => res.json())
        .then(data => {
            btn.dataset.favorited = data.favorited ? 'true' : 'false';
            btn.querySelector('.heart-outline').classList.toggle('hidden', data.favorited);
            btn.querySelector('.heart-filled').classList.toggle('hidden', !data.favorited);

            // If unfavorited on this page, fade and remove the card
            if (!data.favorited) {
                const card = btn.closest('.group');
                card.style.transition = 'opacity 0.3s ease';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
        })
        .catch(() => {});
    };
})();
</script>
@endpush