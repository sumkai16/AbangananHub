@extends('layouts.app', ['searchBar' => false])

@section('content')
    @vite(['resources/js/maps/property-map.js'])

    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8 relative">

        {{-- Back Button Nav --}}
        <div class="mb-6 relative z-10">
            <a href="{{ route('properties.index') }}"
                class="inline-flex items-center gap-2 text-sm font-semibold text-[#9B9F98] hover:text-[#156F8C] transition-colors group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Back to listings
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start relative z-10">

            {{-- ===== LEFT COLUMN ===== --}}
            <div class="lg:col-span-7 xl:col-span-8 space-y-8">

                {{-- PREMIUM GALLERY --}}
                @if($property->media->count() >= 5)
                    <div class="relative grid grid-cols-4 gap-2 aspect-[2/1] rounded-3xl overflow-hidden shadow-sm border border-[#F7FCFC] group cursor-pointer" onclick="openLightbox(0)">
                        <div class="col-span-2 row-span-2 relative overflow-hidden">
                            <img src="{{ $property->media[0]->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.02] brightness-95">
                        </div>
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

                        <button class="absolute bottom-4 right-4 bg-white/90 backdrop-blur-md text-[#156F8C] text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm hover:bg-white transition-all flex items-center gap-1.5 border border-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 002 2z" />
                            </svg>
                            Show all photos
                        </button>
                    </div>
                @elseif($property->media->count() > 0)
                    <div class="relative rounded-3xl overflow-hidden bg-[#F7FCFC] aspect-[16/9] shadow-sm border border-[#F7FCFC] cursor-pointer group" onclick="openLightbox(0)">
                        <img src="{{ $property->media->first()->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.02]">
                        @if($property->media->count() > 1)
                            <button class="absolute bottom-4 right-4 bg-white/90 backdrop-blur-md text-[#156F8C] text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm hover:bg-white transition-all flex items-center gap-1.5 border border-white/20">
                                Show all {{ $property->media->count() }} photos
                            </button>
                        @endif
                    </div>
                @else
                    <div class="rounded-3xl bg-[#F7FCFC] aspect-[16/9] border border-dashed border-[#9B9F98] flex flex-col items-center justify-center text-[#9B9F98] shadow-sm">
                        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="mt-2 text-sm font-semibold">No photos available yet</p>
                    </div>
                @endif

                {{-- TITLE + META --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-lg bg-[#F7FCFC] text-[#156F8C] border border-[#FF8A65]/20 shadow-sm">
                            {{ $property->property_type }}
                        </span>
                        @if($property->landlord->rentalBusiness)
                            <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100/60 shadow-sm gap-1">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Verified Landlord
                            </span>
                        @endif
                        @php
                            $availableUnits = $property->units->where('availability_status', 'Available')->where('verification_status', 'Approved');
                        @endphp
                        <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-lg shadow-sm
                            {{ $availableUnits->count() > 0
                                 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100/60' : 'bg-amber-50 text-amber-700 border border-amber-100/60' }}">
                            {{ $availableUnits->count() > 0 ? $availableUnits->count() . ' ' . Str::plural('unit', $availableUnits->count()) . ' available' : 'Unavailable' }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-[#156F8C] tracking-tight mb-3">
                        {{ $property->title }}
                    </h1>

                    <div class="space-y-1.5 text-sm font-medium text-[#9B9F98]">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $property->address }}
                        </div>
                    </div>
                </div>

                {{-- ABOUT --}}
                <div class="pt-6 border-t border-[#F7FCFC]">
                    <h2 class="text-base font-bold text-[#156F8C] mb-2">About this place</h2>
                    <p class="text-sm text-[#156F8C]/80 leading-relaxed whitespace-pre-line">{{ $property->description }}</p>
                </div>

                {{-- AMENITIES --}}
                @if($property->amenities->count() > 0)
                    <div class="pt-6 border-t border-[#F7FCFC]">
                        <h2 class="text-base font-bold text-[#156F8C] mb-4">Amenities</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($property->amenities as $amenity)
                                <div class="flex items-center gap-3 text-sm text-[#156F8C] font-medium">
                                    <div class="w-8 h-8 rounded-lg bg-[#F7FCFC] border border-[#FF8A65]/20 flex items-center justify-center flex-shrink-0">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#FF8A65" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    {{ $amenity->amenity_name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ===== UNIT GRID ===== --}}
                @php
                    $approvedUnits = $property->units->where('verification_status', 'Approved');
                @endphp
                @if($approvedUnits->count() > 0)
                    <div class="pt-6 border-t border-[#F7FCFC]">
                        <h2 class="text-base font-bold text-[#156F8C] mb-1">Available units</h2>
                        <p class="text-sm text-[#9B9F98] mb-4">{{ $approvedUnits->count() }} {{ Str::plural('unit', $approvedUnits->count()) }} in this property</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($approvedUnits as $unit)
                                @php
                                    $isAvailable = $unit->availability_status === 'Available';
                                    $isOwner = auth()->check() && (int) auth()->id() === (int) $property->landlord_id;
                                    $hasActiveReservation = auth()->check() && \App\Models\Reservation::where('unit_id', $unit->unit_id)
                                        ->where('tenant_id', auth()->id())
                                        ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                                        ->exists();
                                @endphp
                                <div class="rounded-2xl border {{ $isAvailable ? 'border-[#F7FCFC] bg-white hover:border-[#FF8A65]/40 hover:shadow-md' : 'border-[#F7FCFC] bg-[#F7FCFC]/50' }} p-4 flex flex-col justify-between shadow-sm transition-all duration-200">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2.5">
                                            <h3 class="text-sm font-bold text-[#156F8C] truncate">{{ $unit->unit_label }}</h3>
                                            <span class="shrink-0 text-xs font-bold px-2 py-0.5 rounded-md {{ $isAvailable ? 'bg-emerald-50 text-emerald-700' : 'bg-[#F7FCFC] text-[#9B9F98]' }}">
                                                {{ $unit->availability_status }}
                                            </span>
                                        </div>
                                        <div class="flex items-baseline gap-1 mb-1.5">
                                            <span class="text-lg font-black text-[#156F8C]">₱{{ number_format($unit->rental_fee) }}</span>
                                            <span class="text-xs font-semibold text-[#9B9F98]">/ month</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs font-medium text-[#9B9F98] mb-4">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                            </svg>
                                            Up to {{ $unit->occupancy_limit }} {{ Str::plural('occupant', $unit->occupancy_limit) }}
                                        </div>
                                    </div>

                                    {{-- Action --}}
                                    @if(!$isAvailable)
                                        <div class="w-full text-center bg-[#F7FCFC] text-[#9B9F98] text-xs font-bold py-2.5 rounded-xl cursor-not-allowed">
                                            Unavailable
                                        </div>
                                    @elseif($isOwner)
                                        <div class="w-full text-center bg-[#F7FCFC] text-[#9B9F98] text-xs font-bold py-2.5 rounded-xl cursor-not-allowed">
                                            Your listing
                                        </div>
                                    @elseif(!auth()->check())
                                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'login-modal')"
                                            class="w-full py-2.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-xs font-bold transition-all">
                                            Log in to inquire
                                        </button>
                                    @elseif($hasActiveReservation)
                                        <div class="w-full text-center bg-[#F7FCFC] text-[#156F8C] text-xs font-bold py-2.5 rounded-xl cursor-not-allowed">
                                            Inquiry already active
                                        </div>
                                    @else
                                        <form action="{{ route('reservations.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="unit_id" value="{{ $unit->unit_id }}">
                                            <button type="submit"
                                                class="w-full py-2.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-xs font-bold shadow-sm transition-all">
                                                Inquire
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @error('unit')
                            <p class="text-xs text-red-600 font-bold bg-red-50 p-2.5 rounded-lg border border-red-100 mt-3">{{ $message }}</p>
                        @enderror
                        @error('property')
                            <p class="text-xs text-red-600 font-bold bg-red-50 p-2.5 rounded-lg border border-red-100 mt-3">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- REVIEWS --}}
                <div class="pt-6 border-t border-[#F7FCFC]">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-base font-bold text-[#156F8C]">
                            Reviews
                            @if($property->reviews->count() > 0)
                                <span class="text-xs font-bold text-[#9B9F98] ml-1">({{ $property->reviews->count() }})</span>
                            @endif
                        </h2>
                        @if($property->reviews->count() > 0)
                            @php
                                $avgRating = round($property->reviews->avg('rating'), 1);
                            @endphp
                            <div class="flex items-center gap-1.5 bg-amber-50/60 border border-amber-100/50 px-2.5 py-1 rounded-lg">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="none">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-sm font-black text-[#156F8C]">{{ $avgRating }}</span>
                                <span class="text-xs font-semibold text-[#9B9F98]">/ 5</span>
                            </div>
                        @endif
                    </div>

                    @forelse($property->reviews as $review)
                        <div class="mb-6 last:mb-0 bg-[#F7FCFC]/40 border border-[#F7FCFC] p-4 rounded-2xl">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#FF8A65] text-white text-xs font-black flex items-center justify-center flex-shrink-0 shadow-sm">
                                        {{ strtoupper(substr($review->tenant->first_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-[#156F8C]">
                                            {{ $review->tenant->first_name }} {{ $review->tenant->last_name }}
                                        </div>
                                        <div class="text-[11px] font-medium text-[#9B9F98]">
                                            {{ $review->created_at->format('M Y') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg width="12" height="12" viewBox="0 0 24 24"
                                            fill="{{ $i <= $review->rating ? '#f59e0b' : '#e2e8f0' }}" stroke="none">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                            <p class="text-sm text-[#156F8C]/80 leading-relaxed pl-12">
                                {{ $review->review_comment }}
                            </p>
                        </div>
                    @empty
                        <div class="text-sm font-medium text-[#9B9F98] py-2">
                            No reviews yet for this property.
                        </div>
                    @endforelse
                </div>

                {{-- LOCATION / MAP --}}
                <div class="pt-6 border-t border-[#F7FCFC]">
                    <h2 class="text-base font-bold text-[#156F8C] mb-1">Where you'll be</h2>
                    <p class="text-sm font-medium text-[#9B9F98] mb-4">{{ $property->address }}</p>

                    <div id="property-map"
                        data-lat="{{ $property->latitude }}"
                        data-lng="{{ $property->longitude }}"
                        data-title="{{ $property->title }}"
                        class="w-full h-72 rounded-2xl overflow-hidden border border-[#F7FCFC] bg-[#F7FCFC] shadow-sm relative">
                    </div>

                    {{-- Legend --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4 text-xs font-semibold text-[#9B9F98]">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#FF8A65] flex-shrink-0"></span>
                            This property
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#DC2626] flex-shrink-0"></span>
                            Schools
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#156F8C] flex-shrink-0"></span>
                            Hospitals / clinics
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#9B9F98] flex-shrink-0"></span>
                            Malls / groceries
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#F7FCFC] border border-[#9B9F98] flex-shrink-0"></span>
                            Transport
                        </span>
                    </div>

                    {{-- Directions --}}
                    <div class="mt-5 pt-4 border-t border-[#F7FCFC]">
                        <button type="button" id="get-directions-btn"
                            class="inline-flex items-center gap-2 bg-[#156F8C] hover:brightness-95 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            Get directions
                        </button>

                        <div id="directions-panel" class="mt-3"></div>

                        <form id="manual-origin-form" class="hidden mt-3 flex gap-2">
                            <input type="text" id="manual-origin-input" placeholder="Enter your starting address in Cebu"
                                class="flex-1 border border-[#F7FCFC] rounded-xl px-4 py-2 text-sm text-[#156F8C] focus:outline-none focus:ring-4 focus:ring-[#FF8A65]/10 focus:border-[#FF8A65] transition-all bg-[#F7FCFC]">
                            <button type="submit"
                                class="bg-[#FF8A65] hover:brightness-95 text-white text-xs font-bold px-4 py-2 rounded-xl transition flex-shrink-0">
                                Go
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            {{-- ===== RIGHT COLUMN (Sticky Sidebar) ===== --}}
            <div class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 space-y-4 w-full">

                {{-- Pricing Overview --}}
                <div class="bg-white rounded-3xl border border-[#F7FCFC] shadow-sm p-6">
                    <div class="mb-4 pb-4 border-b border-[#F7FCFC]">
                        <div class="flex items-baseline gap-1">
                            <span class="text-xs font-bold text-[#9B9F98] uppercase tracking-wider">Starting at</span>
                        </div>
                        <div class="flex items-baseline gap-1 mt-1">
                            <span class="text-3xl font-black text-[#156F8C] tracking-tight">
                                @if($property->min_rental_fee)
                                    ₱{{ number_format($property->min_rental_fee) }}
                                @else
                                    —
                                @endif
                            </span>
                            <span class="text-sm font-semibold text-[#9B9F98]">/ month</span>
                        </div>
                        <p class="text-xs font-medium text-[#9B9F98] mt-1">
                            {{ $availableUnits->count() }} {{ Str::plural('unit', $availableUnits->count()) }} available
                        </p>
                    </div>

                    <p class="text-xs font-medium text-[#9B9F98] text-center">
                        Browse units below to start an inquiry
                    </p>
                </div>

                {{-- Message Landlord --}}
                <div class="bg-white border border-[#F7FCFC] rounded-2xl p-5 shadow-sm">
                    @auth
                        @if((int) auth()->id() !== (int) $property->landlord_id)
                            <form action="{{ route('conversations.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->property_id }}">
                                <button type="submit"
                                    class="w-full py-3 px-4 rounded-xl border border-[#F7FCFC] bg-white text-[#156F8C] hover:bg-[#F7FCFC] text-sm font-bold shadow-sm transition-all duration-200 flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#9B9F98]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                    Message landlord
                                </button>
                            </form>
                        @else
                            <div class="w-full py-3 px-4 text-center rounded-xl border border-[#F7FCFC] bg-[#F7FCFC] text-[#9B9F98] text-sm font-bold cursor-not-allowed">
                                This is your listing
                            </div>
                        @endif
                    @else
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'login-modal')"
                            class="w-full py-3 px-4 rounded-xl border border-[#F7FCFC] bg-white text-[#156F8C] hover:bg-[#F7FCFC] text-sm font-bold shadow-sm transition-all duration-200 flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4 text-[#9B9F98]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                            Message landlord
                        </button>
                    @endauth
                </div>

                {{-- Landlord Card --}}
                <div class="bg-white border border-[#F7FCFC] rounded-2xl p-5 shadow-sm">
                    <p class="text-[10px] font-bold text-[#9B9F98] uppercase tracking-wider mb-3">Hosted by</p>
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-[#FF8A65] text-white text-base font-black flex items-center justify-center flex-shrink-0 shadow-sm">
                            {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-[#156F8C]">
                                {{ $property->landlord->first_name }} {{ $property->landlord->last_name }}
                            </div>
                            @if($property->landlord->rentalBusiness)
                                <div class="flex items-center gap-1 text-xs text-emerald-600 font-bold mt-0.5">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Verified Landlord
                                </div>
                            @else
                                <div class="text-xs font-semibold text-[#9B9F98] mt-0.5">Landlord</div>
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

            <button type="button"
                class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="closeLightbox()">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <button type="button" id="lb-prev"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                onclick="event.stopPropagation(); shiftLightbox(-1)">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <img id="lb-img"
                src=""
                alt=""
                class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg select-none"
                onclick="event.stopPropagation()">

            <button type="button" id="lb-next"
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
            const mediaUrls = @json($property->media->pluck('media_url')->values());
            const total = mediaUrls.length;
            let currentIndex = 0;

            window.setHero = function (index) {
                currentIndex = index;
                const heroImg = document.getElementById('hero-img');
                const heroBadge = document.getElementById('hero-index');

                if(heroImg) {
                    heroImg.style.opacity = '0';
                    setTimeout(() => {
                        heroImg.src = mediaUrls[index];
                        heroImg.style.opacity = '1';
                    }, 150);
                }

                if (heroBadge) heroBadge.textContent = index + 1;

                document.querySelectorAll('[id^="thumb-"]').forEach((thumb, i) => {
                    if (i === index) {
                        thumb.classList.add('border-blue-500');
                        thumb.classList.remove('border-transparent', 'opacity-60');
                    } else {
                        thumb.classList.remove('border-blue-500');
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
                if(lbImg) lbImg.src = mediaUrls[currentIndex];
                if (lbCounter) lbCounter.textContent = (currentIndex + 1) + ' / ' + total;
                if (lbPrev) lbPrev.style.display = total <= 1 ? 'none' : '';
                if (lbNext) lbNext.style.display = total <= 1 ? 'none' : '';
            }

            window.openLightbox = function (index) {
                currentIndex = index;
                updateLightbox();
                if(lightbox) {
                    lightbox.classList.remove('hidden');
                    lightbox.classList.add('flex');
                }
                document.body.style.overflow = 'hidden';
            };

            window.closeLightbox = function () {
                if(lightbox) {
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
                if (e.key === 'ArrowLeft')  shiftLightbox(-1);
                if (e.key === 'Escape')     closeLightbox();
            });
        })();
        </script>
    @endpush
@endsection