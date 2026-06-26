@extends('layouts.app', ['searchBar' => false])

@section('content')
    @vite(['resources/js/maps/property-map.js'])

    <div class="max-w-[1200px] mx-auto px-5 md:px-10 py-8">

        {{-- Back --}}
        <a href="{{ route('properties.index') }}"
            class="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#2A2523] hover:underline mb-6">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to listings
        </a>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-10">

            {{-- ===== LEFT COLUMN ===== --}}
            <div>

                {{-- PREMIUM GALLERY --}}
                @if($property->media->count() >= 5)
                    <div class="relative grid grid-cols-4 gap-2 aspect-[2/1] rounded-3xl overflow-hidden mb-8 group cursor-pointer" onclick="openLightbox(0)">
                        {{-- Main Hero Image --}}
                        <div class="col-span-2 row-span-2 relative overflow-hidden">
                            <img src="{{ $property->media[0]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.02] brightness-95 hover:brightness-100">
                        </div>
                        {{-- Small Grid Images --}}
                        <div class="col-span-1 row-span-1 relative overflow-hidden">
                            <img src="{{ $property->media[1]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105 brightness-90 hover:brightness-100">
                        </div>
                        <div class="col-span-1 row-span-1 relative overflow-hidden">
                            <img src="{{ $property->media[2]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105 brightness-90 hover:brightness-100">
                        </div>
                        <div class="col-span-1 row-span-1 relative overflow-hidden">
                            <img src="{{ $property->media[3]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105 brightness-90 hover:brightness-100">
                        </div>
                        <div class="col-span-1 row-span-1 relative overflow-hidden">
                            <img src="{{ $property->media[4]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105 brightness-90 hover:brightness-100">
                        </div>

                        {{-- Show all photos button --}}
                        <button class="absolute bottom-4 right-4 bg-white/90 backdrop-blur border border-gray-200 px-4 py-2 rounded-xl text-[13px] font-bold text-gray-800 shadow-sm hover:bg-white transition flex items-center gap-2">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            Show all photos
                        </button>
                    </div>
                @elseif($property->media->count() > 0)
                    {{-- Single Hero Fallback --}}
                    <div class="relative rounded-3xl overflow-hidden bg-gray-100 aspect-[16/9] mb-8 cursor-pointer group" onclick="openLightbox(0)">
                        <img src="{{ $property->media->first()->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        @if($property->media->count() > 1)
                            <button class="absolute bottom-4 right-4 bg-white/90 backdrop-blur border border-gray-200 px-4 py-2 rounded-xl text-[13px] font-bold text-gray-800 shadow-sm hover:bg-white transition flex items-center gap-2">
                                Show all {{ $property->media->count() }} photos
                            </button>
                        @endif
                    </div>
                @else
                    {{-- No media placeholder --}}
                    <div class="rounded-3xl bg-gray-100 aspect-[16/9] mb-8 flex flex-col items-center justify-center text-gray-400">
                        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="mt-3 text-[13px] font-medium">No photos yet</p>
                    </div>
                @endif

                {{-- TITLE + META --}}
                <div class="mt-7">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="text-[12px] font-semibold px-3 py-1 rounded-full bg-blue-50 text-blue-700">
                            {{ $property->property_type }}
                        </span>
                        @if(optional($property->landlord->verificationApplication)->verification_status === 'Approved')
                            <span class="text-[12px] font-semibold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 flex items-center gap-1">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Verified Landlord
                            </span>
                        @endif
                        <span class="text-[12px] font-medium px-3 py-1 rounded-full
                            {{ $property->availability_status === 'Available' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $property->availability_status }}
                        </span>
                    </div>

                    <h1 class="text-[24px] font-extrabold text-[#2A2523] leading-tight">
                        {{ $property->title }}
                    </h1>

                    <div class="flex items-center gap-1.5 mt-2 text-[13.5px] text-gray-500">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $property->address }}
                    </div>

                    @if($property->occupancy_limit)
                        <div class="flex items-center gap-1.5 mt-1.5 text-[13px] text-gray-500">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Up to {{ $property->occupancy_limit }} {{ Str::plural('occupant', $property->occupancy_limit) }}
                        </div>
                    @endif
                </div>

                {{-- DIVIDER --}}
                <div class="border-t border-gray-100 my-7"></div>

                {{-- ABOUT --}}
                <div>
                    <h2 class="text-[16px] font-bold text-[#2A2523] mb-3">About this place</h2>
                    <p class="text-[14.5px] text-gray-600 leading-relaxed whitespace-pre-line">{{ $property->description }}</p>
                </div>

                {{-- AMENITIES --}}
                @if($property->amenities->count() > 0)
                    <div class="border-t border-gray-100 my-7"></div>
                    <div>
                        <h2 class="text-[16px] font-bold text-[#2A2523] mb-4">Amenities</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($property->amenities as $amenity)
                                <div class="flex items-center gap-2.5 text-[13.5px] text-gray-700">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#61B2F0" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    {{ $amenity->amenity_name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- REVIEWS --}}
                <div class="border-t border-gray-100 my-7"></div>
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-[16px] font-bold text-[#2A2523]">
                            Reviews
                            @if($property->reviews->count() > 0)
                                <span class="text-[13px] font-normal text-gray-400 ml-1">({{ $property->reviews->count() }})</span>
                            @endif
                        </h2>
                        @if($property->reviews->count() > 0)
                            @php
                                $avgRating = round($property->reviews->avg('rating'), 1);
                            @endphp
                            <div class="flex items-center gap-1.5">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="none">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-[14px] font-bold text-[#2A2523]">{{ $avgRating }}</span>
                                <span class="text-[13px] text-gray-400">/ 5</span>
                            </div>
                        @endif
                    </div>

                    @forelse($property->reviews as $review)
                        <div class="mb-6 last:mb-0">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-full bg-[#61B2F0] text-[#2A2523] text-[13px] font-bold flex items-center justify-center flex-shrink-0">
                                        {{ strtoupper(substr($review->tenant->first_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-[13.5px] font-semibold text-[#2A2523]">
                                            {{ $review->tenant->first_name }} {{ $review->tenant->last_name }}
                                        </div>
                                        <div class="text-[11px] text-gray-400">
                                            {{ $review->created_at->format('M Y') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg width="13" height="13" viewBox="0 0 24 24"
                                            fill="{{ $i <= $review->rating ? '#f59e0b' : '#e5e7eb' }}" stroke="none">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                            <p class="text-[13.5px] text-gray-600 leading-relaxed pl-[46px]">
                                {{ $review->review_comment }}
                            </p>
                        </div>
                    @empty
                        <div class="text-[13.5px] text-gray-400 py-4">
                            No reviews yet for this property.
                        </div>
                    @endforelse
                </div>

                {{-- LOCATION / MAP --}}
                <div class="border-t border-gray-100 my-7"></div>
                <div>
                    <h2 class="text-[16px] font-bold text-[#2A2523] mb-1">Where you'll be</h2>
                    <p class="text-[13.5px] text-gray-500 mb-4">{{ $property->address }}</p>

                    <div id="property-map"
                        data-lat="{{ $property->latitude }}"
                        data-lng="{{ $property->longitude }}"
                        data-title="{{ $property->title }}"
                        class="w-full h-[380px] sm:h-[440px] rounded-2xl overflow-hidden border border-gray-200 bg-gray-100">
                    </div>

                    {{-- Legend --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4 text-[12px] text-gray-500">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#61B2F0] flex-shrink-0"></span>
                            This property
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#BD5434] flex-shrink-0"></span>
                            Schools
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#5E6968] flex-shrink-0"></span>
                            Hospitals / clinics
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#9B9F98] flex-shrink-0"></span>
                            Malls / groceries
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#D7E8F3] border border-[#2A2523] flex-shrink-0"></span>
                            Transport
                        </span>
                    </div>

                    {{-- Directions --}}
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <button type="button" id="get-directions-btn"
                            class="inline-flex items-center gap-2 bg-[#2A2523] hover:brightness-95 text-white text-[13.5px] font-bold px-4 py-2.5 rounded-xl transition">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            Get directions
                        </button>

                        <div id="directions-panel" class="mt-3"></div>

                        <form id="manual-origin-form" class="hidden mt-3 flex gap-2">
                            <input type="text" id="manual-origin-input" placeholder="Enter your starting address in Cebu"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-[13px] text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#61B2F0]/30 focus:border-[#61B2F0]">
                            <button type="submit"
                                class="bg-[#61B2F0] hover:brightness-95 text-[#2A2523] text-[13px] font-bold px-4 py-2 rounded-lg transition flex-shrink-0">
                                Go
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            {{-- ===== RIGHT COLUMN (sticky sidebar) ===== --}}
            <div class="lg:sticky lg:top-[88px] lg:self-start space-y-4">

                {{-- Pricing + CTA Card --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-[0_6px_24px_rgba(0,0,0,0.06)]">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-[26px] font-extrabold text-[#2A2523]">
                            ₱{{ number_format($property->rental_fee) }}
                        </span>
                        <span class="text-[13px] text-gray-400 font-medium">/month</span>
                    </div>
                    <p class="text-[12px] text-gray-400 mb-5">
                        Up to {{ $property->occupancy_limit }} {{ Str::plural('occupant', $property->occupancy_limit) }}
                    </p>
                    @php
                        $isOwner = auth()->check() && (int) auth()->id() === (int) $property->landlord_id;
                    @endphp

                    @if($property->availability_status !== 'Available')
                        <div class="block w-full text-center bg-gray-100 text-gray-400 text-[14px] font-bold py-3 rounded-xl mb-3 cursor-not-allowed">
                            Currently {{ $property->availability_status }}
                        </div>
                   @elseif($isOwner)
                        {{-- Owner notice already shown below in the Message-landlord slot --}}
                    @elseif(auth()->check())
                        <form action="{{ route('reservations.store', $property) }}" method="POST" class="mb-3">
                            @csrf

                            @error('property')
                                <p class="text-[12.5px] text-red-600 font-medium mb-2">{{ $message }}</p>
                            @enderror
                            @error('reservation_date')
                                <p class="text-[12.5px] text-red-600 font-medium mb-2">{{ $message }}</p>
                            @enderror

                            <label for="reservation_date" class="block text-[12px] font-semibold text-gray-600 mb-1">
                                Preferred move-in date
                            </label>
                            <input type="date" id="reservation_date" name="reservation_date"
                                min="{{ now()->toDateString() }}"
                                value="{{ old('reservation_date') }}"
                                required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13.5px] text-gray-700 mb-3 focus:outline-none focus:ring-2 focus:ring-[#286CD2]/30 focus:border-[#286CD2]">

                            <label for="remarks" class="block text-[12px] font-semibold text-gray-600 mb-1">
                                Note to landlord <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <textarea id="remarks" name="remarks" rows="2"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13.5px] text-gray-700 mb-3 resize-none focus:outline-none focus:ring-2 focus:ring-[#286CD2]/30 focus:border-[#286CD2]">{{ old('remarks') }}</textarea>

                            <button type="submit"
                                class="block w-full text-center bg-[#286CD2] hover:bg-[#1D4ED8] text-white text-[14px] font-bold py-3 rounded-xl transition shadow-sm">
                                Reserve this property
                            </button>
                        </form>
                    @else
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'login-modal')"
                            class="block w-full text-center bg-[#286CD2] hover:bg-[#1D4ED8] text-white text-[14px] font-bold py-3 rounded-xl transition mb-3 shadow-sm">
                            Log in to reserve
                        </button>
                    @endif

                    @auth
                        @if((int) auth()->id() !== (int) $property->landlord_id)
                            <form action="{{ route('conversations.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->property_id }}">
                                <button type="submit"
                                    class="block w-full text-center border-2 border-[#286CD2] text-[#286CD2] hover:bg-[#286CD2] hover:text-white text-[14px] font-bold py-2.5 rounded-xl transition-colors">
                                    Message landlord
                                </button>
                            </form>
                        @else
                            <div class="block w-full text-center border-2 border-gray-200 text-gray-400 text-[14px] font-bold py-2.5 rounded-xl cursor-not-allowed">
                                This is your listing
                            </div>
                        @endif
                    @else
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'login-modal')"
                            class="block w-full text-center border-2 border-[#286CD2] text-[#286CD2] hover:bg-[#286CD2] hover:text-white text-[14px] font-bold py-2.5 rounded-xl transition-colors">
                            Message landlord
                        </button>
                    @endauth

                    <p class="text-center text-[11px] text-gray-400 mt-4">
                        You won't be charged yet
                    </p>
                </div>

                {{-- Landlord Card --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-3">Hosted by</p>
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-[#61B2F0] text-[#2A2523] text-[15px] font-bold flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-[14px] font-bold text-[#2A2523]">
                                {{ $property->landlord->first_name }} {{ $property->landlord->last_name }}
                            </div>
                            @if(optional($property->landlord->verificationApplication)->verification_status === 'Approved')
                                <div class="flex items-center gap-1 text-[12px] text-emerald-600 font-medium mt-0.5">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Verified landlord
                                </div>
                            @else
                                <div class="text-[12px] text-gray-400 mt-0.5">Landlord</div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ===== LIGHTBOX ===== --}}
    @if($property->media->count() > 0)
        <div id="lightbox"
            class="fixed inset-0 z-[999] bg-black/90 hidden items-center justify-center"
            onclick="closeLightbox()">

            {{-- Close button --}}
            <button type="button"
                class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="closeLightbox()">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Prev --}}
            <button type="button" id="lb-prev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(-1)">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Image --}}
            <img id="lb-img"
                src=""
                alt=""
                class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg select-none"
                onclick="event.stopPropagation()">

            {{-- Next --}}
            <button type="button" id="lb-next"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(1)">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Counter --}}
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

                heroImg.style.opacity = '0';
                setTimeout(() => {
                    heroImg.src = mediaUrls[index];
                    heroImg.style.opacity = '1';
                }, 150);

                if (heroBadge) heroBadge.textContent = index + 1;

                document.querySelectorAll('[id^="thumb-"]').forEach((thumb, i) => {
                    if (i === index) {
                        thumb.classList.add('border-[#61B2F0]');
                        thumb.classList.remove('border-transparent', 'opacity-60');
                    } else {
                        thumb.classList.remove('border-[#61B2F0]');
                        thumb.classList.add('border-transparent', 'opacity-60');
                    }
                });
            };

            const lightbox = document.getElementById('lightbox');
            const lbImg    = document.getElementById('lb-img');
            const lbCounter = document.getElementById('lb-counter');
            const lbPrev   = document.getElementById('lb-prev');
            const lbNext   = document.getElementById('lb-next');

            function updateLightbox() {
                lbImg.src = mediaUrls[currentIndex];
                if (lbCounter) lbCounter.textContent = (currentIndex + 1) + ' / ' + total;
                if (lbPrev) lbPrev.style.display = total <= 1 ? 'none' : '';
                if (lbNext) lbNext.style.display = total <= 1 ? 'none' : '';
            }

            window.openLightbox = function (index) {
                currentIndex = index;
                updateLightbox();
                lightbox.classList.remove('hidden');
                lightbox.classList.add('flex');
                document.body.style.overflow = 'hidden';
            };

            window.closeLightbox = function () {
                lightbox.classList.add('hidden');
                lightbox.classList.remove('flex');
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
                if (e.key === 'ArrowLeft')  shiftLightbox(-1);
                if (e.key === 'Escape')     closeLightbox();
            });
        })();
        </script>
    @endpush
@endsection