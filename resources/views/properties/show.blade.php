@extends('layouts.app', ['searchBar' => false])

@section('content')
    @vite(['resources/js/maps/property-map.js'])

    @php
        $approvedUnits = $property->units->where('verification_status', 'Approved')->values();
        $availableUnits = $approvedUnits->where('availability_status', 'Available')->values();
        $isOwner = auth()->check() && (int) auth()->id() === (int) $property->landlord_id;

        $minFee = $availableUnits->min('rental_fee');
        $maxFee = $availableUnits->max('rental_fee');

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
                'thumb' => optional($unit->media->firstWhere('media_type', 'Image'))->media_url,
                'media' => $unit->media->where('media_type', 'Image')->map(fn($m) => ['url' => $m->media_url, 'caption' => $m->caption])->values()->toArray(),
                'description' => $unit->description,
                'amenities' => $unit->amenities->pluck('amenity_name')->values()->toArray(),
                'occupancy' => $unit->occupancy_limit,
                'size' => $unit->size ?? null,
                'available' => $unit->availability_status === 'Available',
                'hasActive' => $hasActiveReservation,
            ];
        })->values();

        $defaultUnitId = optional($unitsPayload->firstWhere('available', true))['id'] ?? null;
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-28 lg:pb-8 min-h-[calc(100vh-72px)]" x-data="{
                                                    tab: 'overview',
                                                    mode: 'inquiry',
                                                    moreUnits: false,
                                                    mobileOpen: false,
                                                    mstep: 1,
                                                    openMobile() { this.mstep = this.selectedUnit ? 2 : 1; this.mobileOpen = true; document.body.style.overflow = 'hidden'; },
                                                    closeMobile() { this.mobileOpen = false; document.body.style.overflow = ''; },
                                                        units: @js($unitsPayload),
                                                    selectedUnit: @js($defaultUnitId),
                                                    msg: '',
                                                    descExpanded: false,
                                                    allAmenities: false,

                                                    slideoutUnit: null,
                                                    slideoutIdx: 0,

                                                    get selected() { return this.units.find(u => u.id === this.selectedUnit) || null },

                                                    selectUnit(id) {
                                                        const u = this.units.find(x => x.id === id);
                                                        if (u && u.available) this.selectedUnit = id;
                                                    },

                                                    openSlideout(id) {
                                                        this.slideoutUnit = this.units.find(u => u.id === id) || null;
                                                        this.slideoutIdx = 0;
                                                        document.body.style.overflow = 'hidden';
                                                    },

                                                    closeSlideout() {
                                                        this.slideoutUnit = null;
                                                        this.slideoutIdx = 0;
                                                        document.body.style.overflow = '';
                                                    },

                                                    slideoutPrev() {
                                                        if (!this.slideoutUnit || this.slideoutUnit.media.length === 0) return;
                                                        this.slideoutIdx = (this.slideoutIdx - 1 + this.slideoutUnit.media.length) % this.slideoutUnit.media.length;
                                                    },

                                                    slideoutNext() {
                                                        if (!this.slideoutUnit || this.slideoutUnit.media.length === 0) return;
                                                        this.slideoutIdx = (this.slideoutIdx + 1) % this.slideoutUnit.media.length;
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
            {{-- LEFT REGION: gallery + units | header + tabs --}}
            {{-- ===================================================== --}}
            <div class="lg:col-span-7 xl:col-span-8">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 items-start">

                {{-- ── Column A: gallery + available units ── --}}
                <div class="space-y-8">

                {{-- ===== 1. IMAGE GALLERY ===== --}}
                @if($property->media->count() > 0)
                    <div>
                        <div
                            class="relative rounded-3xl overflow-hidden bg-[#E2E8F0] aspect-[4/3] border border-[#EEF8F8] shadow-sm group">

                            <span class="absolute top-3 left-3 z-10 inline-flex items-center gap-1.5 bg-[#156F8C] text-white text-[11px] font-bold px-2.5 py-1.5 rounded-full shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Verified Property
                            </span>
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

                {{-- ===== UNIT SELECTION LIST ===== --}}
                @if($approvedUnits->count() > 0)
                    <div>
                        <h2 class="text-base font-bold text-[#1F2937] mb-1">Available Units ({{ $availableUnits->count() }})
                        </h2>
                        <p class="text-sm text-[#64748B] mb-4">Choose a unit to inquire or reserve</p>

                        <div class="space-y-3">
                            @foreach($approvedUnits as $unit)
                                @php $isAvailable = $unit->availability_status === 'Available'; @endphp
                                <div class="relative" @if($loop->index >= 4) x-show="moreUnits" x-cloak @endif>
                                    <button type="button" x-on:click="selectUnit({{ $unit->unit_id }})"
                                        :class="selectedUnit === {{ $unit->unit_id }}
                                                ? 'border-[#2AA7A1] ring-1 ring-[#2AA7A1]'
                                                : '{{ $isAvailable ? 'border-[#E2E8F0] hover:border-[#64748B]/40' : 'border-[#E2E8F0] cursor-not-allowed' }}'"
                                        class="w-full text-left rounded-2xl border bg-white shadow-sm p-3.5 pr-12 flex items-center gap-3 transition-all {{ $isAvailable ? '' : 'opacity-60' }}"
                                        @if(!$isAvailable) disabled @endif>

                                        {{-- Radio indicator --}}
                                        <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
                                            :class="selectedUnit === {{ $unit->unit_id }} ? 'border-[#2AA7A1]' : 'border-[#CBD5E1]'">
                                            <span class="w-2.5 h-2.5 rounded-full bg-[#2AA7A1]"
                                                x-show="selectedUnit === {{ $unit->unit_id }}" x-cloak></span>
                                        </span>

                                        {{-- Thumbnail --}}
                                        @php $unitThumb = $unit->media->firstWhere('media_type', 'Image'); @endphp
                                        @if($unitThumb)
                                            <img src="{{ $unitThumb->media_url }}" alt="{{ $unit->unit_label }}"
                                                class="w-14 h-14 rounded-xl object-cover shrink-0 border border-[#E2E8F0]">
                                        @else
                                            <span class="w-14 h-14 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                                <svg class="w-6 h-6 text-[#64748B]" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                                </svg>
                                            </span>
                                        @endif

                                        {{-- Details --}}
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-bold text-[#1F2937] truncate mb-0.5">{{ $unit->unit_label }}</span>
                                            <span class="block text-xs font-medium text-[#64748B]">
                                                {{ $property->property_type }}
                                                &middot; {{ $unit->occupancy_limit }}
                                                {{ $unit->occupancy_limit > 1 ? 'People' : 'Person' }}
                                                @if(!empty($unit->size))
                                                    &middot; {{ $unit->size }}
                                                @endif
                                            </span>
                                        </span>

                                        {{-- Price + status --}}
                                        <span class="text-right shrink-0 flex flex-col items-end gap-1">
                                            <span class="leading-tight">
                                                <span class="text-sm font-black text-[#1F2937]">₱{{ number_format($unit->rental_fee) }}</span>
                                                <span class="text-[11px] font-semibold text-[#64748B]">/ month</span>
                                            </span>
                                            <span
                                                class="text-[10.5px] font-bold px-2 py-0.5 rounded-md {{ $isAvailable ? 'bg-[#22C55E]/[0.07] text-[#15803D]' : 'bg-[#E2E8F0] text-[#64748B]' }}">
                                                {{ $unit->availability_status }}
                                            </span>
                                        </span>
                                    </button>

                                    {{-- View details button (opens slideout) --}}
                                    <button type="button" x-on:click.stop="openSlideout({{ $unit->unit_id }})"
                                        class="absolute top-3 right-3 w-7 h-7 rounded-lg bg-[#F7FCFC] hover:bg-[#EEF8F8] flex items-center justify-center transition-colors cursor-pointer"
                                        title="View unit details">
                                        <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        @if($approvedUnits->count() > 4)
                            <button type="button" x-show="!moreUnits" x-on:click="moreUnits = true"
                                class="mt-3 w-full h-11 rounded-xl border border-[#E2E8F0] bg-white text-sm font-bold text-[#1F2937] hover:bg-[#F7FCFC] shadow-sm cursor-pointer transition-colors duration-200">
                                View all units ({{ $approvedUnits->count() }})
                            </button>
                        @endif
                    </div>
                @endif
                </div>

                {{-- ── Column B: header + facts + tabs ── --}}
                <div class="space-y-6">

                {{-- ===== 2. PROPERTY HEADER ===== --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2.5 mb-2">
                        <h1 class="text-2xl font-black text-[#1F2937] tracking-tight">
                            {{ $property->title }}
                        </h1>
                        @if($property->landlord->rentalBusiness)
                            <span
                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg bg-[#EEF8F8] text-[#1F2937] shadow-sm">
                                <svg class="w-3.5 h-3.5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
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
                            class="text-[#156F8C] font-bold hover:brightness-95 transition-all underline underline-offset-2">
                            View on map
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5 mb-4">
                        <svg class="w-4 h-4 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                        @if($avgRating)
                            <span class="text-sm font-black text-[#1F2937]">{{ $avgRating }}</span>
                            <button type="button" x-on:click="goTab('reviews')"
                                class="text-sm font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors underline underline-offset-2">
                                ({{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }})
                            </button>
                        @else
                            <span class="text-sm font-semibold text-[#64748B]">No reviews yet</span>
                        @endif
                    </div>

                    @if($property->description)
                        @if(Str::length($property->description) > 200)
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="!descExpanded">
                                {{ Str::limit($property->description, 200) }}
                            </p>
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="descExpanded" x-cloak>
                                {{ $property->description }}
                            </p>
                            <button type="button" x-on:click="descExpanded = !descExpanded"
                                class="mt-1.5 text-sm font-bold text-[#156F8C] hover:brightness-95 transition-all underline underline-offset-2"
                                x-text="descExpanded ? 'Read less' : 'Read more'"></button>
                        @else
                            <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line">{{ $property->description }}</p>
                        @endif
                    @endif
                </div>

                {{-- ===== 3. AMENITY ICON ROW ===== --}}
                @if($property->amenities->count() > 0)
                    <div class="pt-5 border-t border-[#EEF8F8]">
                        <div class="flex flex-wrap gap-x-4 gap-y-3">
                            @foreach($property->amenities as $i => $amenity)
                                <div class="flex flex-col items-center gap-1.5 w-[64px]"
                                    @if($i >= 5) x-show="allAmenities" x-cloak @endif>
                                    <span class="w-11 h-11 rounded-xl border border-[#E2E8F0] bg-white shadow-sm flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <span class="text-[10.5px] font-semibold text-[#64748B] text-center leading-tight truncate w-full">{{ $amenity->amenity_name }}</span>
                                </div>
                            @endforeach

                            @if($property->amenities->count() > 5)
                                <button type="button" x-show="!allAmenities" x-on:click="allAmenities = true"
                                    class="flex flex-col items-center gap-1.5 w-[64px] cursor-pointer group">
                                    <span class="w-11 h-11 rounded-xl border border-[#E2E8F0] bg-white shadow-sm group-hover:bg-[#F7FCFC] flex items-center justify-center text-[12px] font-bold text-[#1F2937] transition-colors duration-200">
                                        +{{ $property->amenities->count() - 5 }}
                                    </span>
                                    <span class="text-[10.5px] font-semibold text-[#64748B]">more</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ===== 4. PROPERTY QUICK FACTS ===== --}}
                <div class="pt-5 border-t border-[#EEF8F8]">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="flex items-start gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold text-[#64748B]">Property Type</p>
                                <p class="text-sm font-bold text-[#1F2937]">{{ $property->property_type }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold text-[#64748B]">Price Range</p>
                                <p class="text-sm font-bold text-[#1F2937]">
                                    @if($minFee)
                                        ₱{{ number_format($minFee) }}@if($maxFee && $maxFee != $minFee)–₱{{ number_format($maxFee) }}@endif
                                        <span class="text-[#64748B] font-semibold">/ month</span>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold text-[#64748B]">Landlord</p>
                                <p class="text-sm font-bold text-[#1F2937] truncate">
                                    {{ $property->landlord->rentalBusiness->business_name ?? ($property->landlord->first_name . ' ' . $property->landlord->last_name) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold text-[#64748B]">Available Units</p>
                                <p class="text-sm font-bold text-[#1F2937]">{{ $availableUnits->count() }} {{ Str::plural('Unit', $availableUnits->count()) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== 5. TABBED CONTENT ===== --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm overflow-hidden">
                    {{-- Tab bar --}}
                    <div class="flex items-center gap-1 px-5 border-b border-[#E2E8F0] overflow-x-auto">
                        <button type="button" x-on:click="goTab('overview')"
                            :class="tab === 'overview' ? 'border-[#2AA7A1] text-[#156F8C]' : 'border-transparent text-[#64748B] hover:text-[#1F2937]'"
                            class="text-sm font-bold px-3 sm:px-4 py-3 border-b-2 -mb-px transition-all whitespace-nowrap cursor-pointer">
                            Overview
                        </button>
                        <button type="button" x-on:click="goTab('location')"
                            :class="tab === 'location' ? 'border-[#2AA7A1] text-[#156F8C]' : 'border-transparent text-[#64748B] hover:text-[#1F2937]'"
                            class="text-sm font-bold px-3 sm:px-4 py-3 border-b-2 -mb-px transition-all whitespace-nowrap cursor-pointer">
                            Location
                        </button>
                        <button type="button" x-on:click="goTab('reviews')"
                            :class="tab === 'reviews' ? 'border-[#2AA7A1] text-[#156F8C]' : 'border-transparent text-[#64748B] hover:text-[#1F2937]'"
                            class="text-sm font-bold px-3 sm:px-4 py-3 border-b-2 -mb-px transition-all whitespace-nowrap cursor-pointer">
                            Reviews
                            @if($property->reviews->count() > 0)
                                <span class="text-xs">({{ $property->reviews->count() }})</span>
                            @endif
                        </button>
                    </div>

                    <div class="p-5">

                    {{-- ── Overview tab ── --}}
                    <div x-show="tab === 'overview'">
                        <h2 class="text-base font-bold text-[#1F2937] mb-2">Property Details</h2>
                        <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line mb-6">
                            {{ $property->description }}
                        </p>

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
                                        <div class="w-8 h-8 rounded-lg bg-[#EF4444]/[0.07] flex items-center justify-center flex-shrink-0">
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
                                    placeholder="Enter your starting address in Cebu" aria-label="Enter your starting address in Cebu"
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
                                @if($reviews->count() > 0)
                                    <span class="text-xs">({{ $reviews->count() }})</span>
                                @endif
                            </h2>
                            @if($avgRating)
                                <div class="flex items-center gap-1.5 bg-[#E2E8F0] px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor"
                                        stroke="none">
                                        <path
                                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                    </svg>
                                    <span class="text-sm font-black text-[#1F2937]">{{ $avgRating }}</span>
                                    <span class="text-xs font-semibold text-[#64748B]">/ 5</span>
                                </div>
                            @endif
                        </div>

                        {{-- Review submission form --}}
                        @auth
                            @if($canReview)
                                <div class="mb-6 bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-sm"
                                    x-data="{ rating: 0, hoverRating: 0 }">
                                    <p class="text-sm font-bold text-[#1F2937] mb-3">Leave a review</p>
                                    <form action="{{ route('reviews.store') }}" method="POST" class="space-y-4">
                                        @csrf
                                        <input type="hidden" name="property_id" value="{{ $property->property_id }}">
                                        <input type="hidden" name="rating" :value="rating">

                                        {{-- Star picker --}}
                                        <div>
                                            <p class="text-xs font-bold text-[#64748B] mb-2">Your rating</p>
                                            <div class="flex items-center gap-1">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <button type="button" x-on:click="rating = {{ $i }}"
                                                        x-on:mouseenter="hoverRating = {{ $i }}" x-on:mouseleave="hoverRating = 0"
                                                        class="p-0.5 transition-transform hover:scale-110">
                                                        <svg class="w-6 h-6 transition-colors"
                                                            :class="(hoverRating || rating) >= {{ $i }} ? 'text-[#FBBF24]' : 'text-[#E2E8F0]'"
                                                            viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                            <path
                                                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                        </svg>
                                                    </button>
                                                @endfor
                                                <span class="text-xs font-bold text-[#64748B] ml-2" x-show="rating > 0" x-cloak
                                                    x-text="rating + ' / 5'"></span>
                                            </div>
                                            @error('rating')
                                                <p class="text-xs text-[#EF4444] font-bold mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Comment --}}
                                        <div>
                                            <label for="review_comment" class="block text-xs font-bold text-[#64748B] mb-1.5">
                                                Your review <span class="font-semibold">(optional)</span>
                                            </label>
                                            <textarea id="review_comment" name="review_comment" rows="3" maxlength="1000"
                                                placeholder="Share your experience living here..."
                                                class="w-full border border-[#EEF8F8] rounded-xl px-4 py-2.5 text-sm text-[#1F2937] bg-[#E2E8F0] focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all resize-none">{{ old('review_comment') }}</textarea>
                                        </div>

                                        <button type="submit" :disabled="rating === 0"
                                            class="px-5 py-2.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]">
                                            Submit Review
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endauth

                        {{-- Review list --}}
                        @forelse($reviews as $review)
                            <div
                                class="mb-6 last:mb-0 bg-white border border-[#E2E8F0] p-4 rounded-2xl shadow-sm {{ $review->is_hidden ? 'opacity-50' : '' }}">
                                @if($review->is_hidden)
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold text-[#64748B] mb-2 bg-[#EEF8F8] px-2.5 py-1.5 rounded-lg w-fit">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        Hidden by admin
                                    </div>
                                @endif

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
                                            <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-[#FBBF24]' : 'text-[#EEF8F8]' }}"
                                                viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <path
                                                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                        @endfor
                                    </div>
                                </div>

                                @if($review->review_comment)
                                    <p class="text-sm text-[#1F2937] leading-relaxed pl-12">{{ $review->review_comment }}</p>
                                @endif

                                {{-- Landlord reply --}}
                                @if($review->landlord_reply)
                                    <div class="ml-12 mt-3 bg-[#EEF8F8] border border-[#E2E8F0] rounded-xl p-3">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <div
                                                class="w-6 h-6 rounded-lg bg-[#156F8C] text-white text-[10px] font-black flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}
                                            </div>
                                            <span class="text-xs font-bold text-[#1F2937]">
                                                {{ $property->landlord->first_name }} {{ $property->landlord->last_name }}
                                            </span>
                                            <span class="text-[10px] font-medium text-[#64748B]">
                                                {{ $review->landlord_replied_at->format('M Y') }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-[#1F2937] leading-relaxed pl-8">{{ $review->landlord_reply }}</p>
                                    </div>
                                @endif

                                {{-- Landlord reply form (owner only, no existing reply) --}}
                                @auth
                                    @if($isOwner && !$review->landlord_reply)
                                        <div class="ml-12 mt-3" x-data="{ showReply: false }">
                                            <button type="button" x-on:click="showReply = !showReply"
                                                class="text-xs font-bold text-[#156F8C] hover:brightness-95 transition-all">
                                                Reply to this review
                                            </button>
                                            <div x-show="showReply" x-cloak class="mt-2">
                                                <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST"
                                                    class="space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                        placeholder="Write your response..."
                                                        class="w-full border border-[#EEF8F8] rounded-xl px-3 py-2 text-sm text-[#1F2937] bg-white focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all resize-none"></textarea>
                                                    <div class="flex items-center gap-2">
                                                        <button type="submit"
                                                            class="px-4 py-2 rounded-lg bg-[#156F8C] hover:brightness-95 text-white text-xs font-bold shadow-sm transition-all">
                                                            Post Reply
                                                        </button>
                                                        <button type="button" x-on:click="showReply = false"
                                                            class="px-4 py-2 rounded-lg bg-[#E2E8F0] text-[#64748B] text-xs font-bold hover:brightness-95 transition-all">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Landlord edit reply (owner, has existing reply) --}}
                                    @if($isOwner && $review->landlord_reply)
                                        <div class="ml-12 mt-2" x-data="{ editing: false }">
                                            <button type="button" x-on:click="editing = !editing"
                                                class="text-xs font-bold text-[#64748B] hover:text-[#1F2937] transition-all">
                                                Edit reply
                                            </button>
                                            <div x-show="editing" x-cloak class="mt-2">
                                                <form action="{{ route('landlord.reviews.reply', $review->review_id) }}" method="POST"
                                                    class="space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <textarea name="landlord_reply" rows="2" maxlength="1000"
                                                        class="w-full border border-[#EEF8F8] rounded-xl px-3 py-2 text-sm text-[#1F2937] bg-white focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all resize-none">{{ $review->landlord_reply }}</textarea>
                                                    <div class="flex items-center gap-2">
                                                        <button type="submit"
                                                            class="px-4 py-2 rounded-lg bg-[#156F8C] hover:brightness-95 text-white text-xs font-bold shadow-sm transition-all">
                                                            Update Reply
                                                        </button>
                                                        <button type="button" x-on:click="editing = false"
                                                            class="px-4 py-2 rounded-lg bg-[#E2E8F0] text-[#64748B] text-xs font-bold hover:brightness-95 transition-all">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @endauth
                            </div>
                        @empty
                            <div class="text-sm font-medium text-[#64748B] py-2">
                                No reviews yet for this property.
                            </div>
                        @endforelse
                    </div>
                    </div>
                </div>

                </div>
                </div>
            </div>

            {{-- ===================================================== --}}
            {{-- RIGHT SIDEBAR --}}
            {{-- ===================================================== --}}
            <div
                class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-[72px] lg:max-h-[calc(100vh_-_72px_-_1.5rem)] lg:overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] space-y-4 w-full hidden lg:block">

                {{-- ===== 1. INQUIRE / RESERVE CARD ===== --}}
                <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm p-6">

                    <h2 class="text-base font-bold text-[#1F2937] mb-4">Inquire / Reserve</h2>

                    {{-- Selected unit preview --}}
                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider mb-3">You're inquiring about:
                    </p>
                    <template x-if="selected">
                        <div class="flex items-center gap-3 mb-5 pb-5 border-b border-[#EEF8F8] sticky">
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

                    {{-- Inquiry / Reserve mode toggle --}}
                    <div class="grid grid-cols-2 gap-2 mb-5">
                        <button type="button" x-on:click="mode = 'inquiry'"
                            :class="mode === 'inquiry' ? 'border-[#2AA7A1] text-[#156F8C] bg-[#EEF8F8]/60' : 'border-[#E2E8F0] text-[#64748B] hover:text-[#1F2937]'"
                            class="h-10 rounded-xl border text-sm font-bold bg-white cursor-pointer transition-all duration-200">
                            Inquiry
                        </button>
                        <button type="button" x-on:click="mode = 'reserve'"
                            :class="mode === 'reserve' ? 'border-[#2AA7A1] text-[#156F8C] bg-[#EEF8F8]/60' : 'border-[#E2E8F0] text-[#64748B] hover:text-[#1F2937]'"
                            class="h-10 rounded-xl border text-sm font-bold bg-white cursor-pointer transition-all duration-200">
                            Reserve
                        </button>
                    </div>

                    @if(!auth()->check())
                        <button type="button" onclick="openAuthModal('login')"
                            class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all">
                            Log in to inquire
                        </button>
                    @elseif($isOwner)
                        <div
                            class="w-full py-3 text-center rounded-xl bg-[#E2E8F0] text-[#64748B] text-sm font-bold cursor-not-allowed">
                            This is your listing
                        </div>
                    @else
                        <form action="{{ route('reservations.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="unit_id" :value="selected ? selected.id : ''">

                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="rounded-xl bg-white border border-[#E2E8F0] px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10">
                                    <label for="target_move_in_date"
                                        class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Move
                                        In</label>
                                    <input type="date" id="target_move_in_date" name="target_move_in_date"
                                        min="{{ now()->toDateString() }}" value="{{ old('target_move_in_date') }}"
                                        class="w-full bg-transparent border-0 p-0 text-sm font-semibold text-[#1F2937] focus:outline-none focus:ring-0 [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer">
                                </div>

                                <div
                                    class="rounded-xl bg-white border border-[#E2E8F0] px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10">
                                    <label for="target_move_out_date"
                                        class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Move
                                        Out <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                                    <input type="date" id="target_move_out_date" name="target_move_out_date"
                                        value="{{ old('target_move_out_date') }}"
                                        class="w-full bg-transparent border-0 p-0 text-sm font-semibold text-[#1F2937] focus:outline-none focus:ring-0 [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer">
                                </div>
                            </div>

                            <div
                                class="rounded-xl bg-white border border-[#E2E8F0] px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10">
                                <label for="inquiry_message"
                                    class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Message
                                    <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                                <textarea id="inquiry_message" name="message" rows="3" maxlength="300" x-model="msg"
                                    placeholder="Hi! I'm interested in this unit..."
                                    class="w-full bg-transparent border-0 p-0 text-sm font-medium text-[#1F2937] placeholder:text-[#64748B]/60 focus:outline-none focus:ring-0 resize-none">{{ old('message') }}</textarea>
                                <p class="text-[10px] font-semibold text-[#64748B]/70 text-right">
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
                                    class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]"
                                    x-text="mode === 'reserve' ? 'Send Reservation Request' : 'Send Inquiry to Landlord'">
                                </button>
                            </template>
                        </form>

                        <p class="text-xs font-medium text-[#64748B] text-center mt-3">
                            Usually responds within a few hours
                        </p>

                        @if(auth()->user()->hasRole('Tenant'))
                            <div x-data="{
                                    fav: @js($isFavorited ?? false),
                                    busy: false,
                                    async toggleFav() {
                                        if (this.busy) return;
                                        this.busy = true;
                                        try {
                                            const res = await fetch('{{ route('favorites.toggle', $property->property_id) }}', {
                                                method: 'POST',
                                                headers: {
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                    'Accept': 'application/json'
                                                }
                                            });
                                            if (res.ok) { const d = await res.json(); this.fav = d.favorited; }
                                        } finally { this.busy = false; }
                                    }
                                }" class="mt-3">
                                <button type="button" x-on:click="toggleFav()" :disabled="busy"
                                    :class="fav ? 'border-[#FF8A65] text-[#FF8A65] bg-[#FF8A65]/5' : 'border-[#E2E8F0] text-[#1F2937] hover:border-[#FF8A65]/50'"
                                    class="w-full py-3 rounded-xl border bg-white text-sm font-bold flex items-center justify-center gap-2 cursor-pointer transition-all duration-200">
                                    <svg class="w-4 h-4" :fill="fav ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                    </svg>
                                    <span x-text="fav ? 'Saved to Favorites' : 'Add to Favorites'"></span>
                                </button>
                            </div>
                        @endif
                    @endif

                    @error('unit')
                        <p class="text-xs text-[#EF4444] font-bold bg-[#E2E8F0] p-2.5 rounded-lg border border-[#EEF8F8] mt-3">
                            {{ $message }}
                        </p>
                    @enderror
                    @error('property')
                        <p class="text-xs text-[#EF4444] font-bold bg-[#E2E8F0] p-2.5 rounded-lg border border-[#EEF8F8] mt-3">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Message Landlord --}}
                <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-sm">
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
                        <button type="button" onclick="openAuthModal('login')"
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
                <div class="bg-white border border-[#E2E8F0] rounded-2xl p-5 shadow-sm">
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
                                <div class="flex items-center gap-1 text-xs text-[#22C55E] font-bold mt-0.5">
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
                    <a href="{{ route('landlord.profile.show', $property->landlord_id) }}"
                        class="block w-full py-2.5 text-center rounded-xl border border-[#EEF8F8] bg-white text-[#1F2937] hover:brightness-95 text-sm font-bold shadow-sm transition-all">
                        View Landlord Profile
                    </a>
                    @auth
                        <a href="{{ route('reports.create', ['property_id' => $property->property_id]) }}"
                            class="block w-full py-2.5 text-center rounded-xl border border-[#E2E8F0] bg-white text-[#64748B] hover:text-[#1F2937] hover:border-[#64748B] text-sm font-semibold transition-all mt-2">
                            Report this listing
                        </a>
                    @endauth
                </div>

            </div>
        </div>

        {{-- ===== MOBILE STICKY INQUIRE BAR + TWO-STEP MODAL ===== --}}
        @if(!$isOwner)
            <template x-teleport="body">
                <div class="lg:hidden fixed inset-x-0 bottom-0 z-20 bg-white border-t border-[#E2E8F0] px-4 py-3 flex items-center justify-between gap-3 shadow-[0_-4px_16px_rgba(15,23,42,0.08)]">
                    <div class="min-w-0">
                        <template x-if="selected">
                            <p class="text-sm font-black text-[#1F2937]">
                                ₱<span x-text="selected.price"></span>
                                <span class="text-[11px] font-semibold text-[#64748B]">/ month &middot; </span>
                                <span class="text-[11px] font-semibold text-[#64748B]" x-text="selected.label"></span>
                            </p>
                        </template>
                        <template x-if="!selected">
                            <p class="text-[12px] font-semibold text-[#64748B]">No unit selected</p>
                        </template>
                    </div>
                    @auth
                        <button type="button" x-on:click="openMobile()"
                            class="h-11 px-6 rounded-xl bg-[#FF8A65] text-white text-sm font-bold hover:brightness-95 shadow-sm cursor-pointer transition-all duration-200 shrink-0">
                            Inquire
                        </button>
                    @else
                        <button type="button" onclick="openAuthModal('login')"
                            class="h-11 px-6 rounded-xl bg-[#FF8A65] text-white text-sm font-bold hover:brightness-95 shadow-sm cursor-pointer transition-all duration-200 shrink-0">
                            Log in to inquire
                        </button>
                    @endauth
                </div>
            </template>

            @auth
                <template x-teleport="body">
                    <div x-show="mobileOpen" x-cloak class="lg:hidden fixed inset-0 z-30 flex items-end sm:items-center justify-center"
                        x-on:keydown.escape.window="closeMobile()">
                        <div class="absolute inset-0 bg-black/40" x-on:click="closeMobile()"></div>
                        <div class="relative w-full sm:max-w-md max-h-[90vh] overflow-y-auto bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl"
                            x-show="mobileOpen" x-transition>

                            {{-- Step 1: Select a Unit --}}
                            <div x-show="mstep === 1">
                                <div class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] sticky top-0 bg-white z-10">
                                    <h3 class="text-base font-bold text-[#1F2937]">Select a Unit</h3>
                                    <button type="button" x-on:click="closeMobile()" aria-label="Close"
                                        class="text-[#64748B] hover:text-[#1F2937] cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div class="flex items-center gap-3 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] p-3">
                                        @if($photo = $property->media->firstWhere('media_type', 'Image'))
                                            <img src="{{ $photo->media_url }}" alt="{{ $property->title }}" class="w-12 h-12 rounded-lg object-cover shrink-0">
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Selected Property</p>
                                            <p class="text-sm font-bold text-[#1F2937] truncate">{{ $property->title }}</p>
                                            <p class="text-[11px] text-[#64748B] truncate">{{ $property->address }}</p>
                                        </div>
                                    </div>

                                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Available Units</p>
                                    <div class="space-y-2.5">
                                        <template x-for="u in units.filter(x => x.available)" :key="u.id">
                                            <button type="button" x-on:click="selectUnit(u.id)"
                                                :class="selectedUnit === u.id ? 'border-[#2AA7A1] ring-1 ring-[#2AA7A1] bg-[#EEF8F8]/40' : 'border-[#E2E8F0]'"
                                                class="w-full text-left rounded-xl border bg-white p-3 flex items-center gap-3 cursor-pointer transition-all duration-200">
                                                <span class="w-[18px] h-[18px] rounded-full border-2 flex items-center justify-center shrink-0"
                                                    :class="selectedUnit === u.id ? 'border-[#2AA7A1]' : 'border-[#CBD5E1]'">
                                                    <span class="w-2 h-2 rounded-full bg-[#2AA7A1]" x-show="selectedUnit === u.id"></span>
                                                </span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-[13px] font-bold text-[#1F2937] truncate" x-text="u.label"></span>
                                                    <span class="block text-[11px] text-[#64748B]"
                                                        x-text="[u.type, u.occupancy ? u.occupancy + (u.occupancy > 1 ? ' Persons' : ' Person') : null, u.size].filter(Boolean).join(' · ')"></span>
                                                </span>
                                                <span class="text-right shrink-0">
                                                    <span class="block text-[13px] font-black text-[#1F2937]">₱<span x-text="u.price"></span></span>
                                                    <span class="block text-[10px] font-semibold text-[#64748B]">/ month</span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>

                                    <button type="button" x-on:click="mstep = 2" :disabled="!selected"
                                        class="w-full h-11 rounded-xl bg-[#2AA7A1] text-white text-sm font-bold hover:brightness-95 cursor-pointer transition-all duration-200 disabled:bg-[#E2E8F0] disabled:text-[#64748B] disabled:cursor-not-allowed">
                                        Confirm Selection
                                    </button>
                                </div>
                            </div>

                            {{-- Step 2: Message Landlord --}}
                            <div x-show="mstep === 2" x-cloak>
                                <div class="flex items-center justify-between px-5 py-4 border-b border-[#E2E8F0] sticky top-0 bg-white z-10">
                                    <button type="button" x-on:click="mstep = 1" aria-label="Back"
                                        class="text-[#64748B] hover:text-[#1F2937] cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                        </svg>
                                    </button>
                                    <h3 class="text-base font-bold text-[#1F2937]">Message Landlord</h3>
                                    <button type="button" x-on:click="closeMobile()" aria-label="Close"
                                        class="text-[#64748B] hover:text-[#1F2937] cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-5">
                                    <p class="text-[12px] text-[#64748B] mb-3">
                                        To: <span class="font-bold text-[#1F2937]">{{ $property->landlord->rentalBusiness->business_name ?? ($property->landlord->first_name . ' ' . $property->landlord->last_name) }}</span>
                                        &middot; {{ $property->title }}
                                    </p>

                                    <template x-if="selected">
                                        <div class="flex items-center gap-3 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] p-3 mb-4">
                                            <template x-if="selected.thumb">
                                                <img :src="selected.thumb" :alt="selected.label" class="w-12 h-12 rounded-lg object-cover shrink-0">
                                            </template>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-[#1F2937] truncate" x-text="selected.label"></p>
                                                <p class="text-[11px] text-[#64748B]" x-text="selected.type"></p>
                                                <p class="text-[13px] font-black text-[#1F2937]">₱<span x-text="selected.price"></span>
                                                    <span class="text-[10px] font-semibold text-[#64748B]">/ month</span>
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <form action="{{ route('reservations.store') }}" method="POST" class="space-y-3.5">
                                        @csrf
                                        <input type="hidden" name="unit_id" :value="selected ? selected.id : ''">

                                        <div>
                                            <label for="m_move_in" class="block text-[11px] font-bold text-[#64748B] mb-1">Target Move In</label>
                                            <input type="date" id="m_move_in" name="target_move_in_date" min="{{ now()->toDateString() }}"
                                                class="w-full h-11 rounded-xl border border-[#E2E8F0] px-3.5 text-sm text-[#1F2937] focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 transition-all">
                                        </div>
                                        <div>
                                            <label for="m_move_out" class="block text-[11px] font-bold text-[#64748B] mb-1">Target Move Out <span class="font-semibold">(Optional)</span></label>
                                            <input type="date" id="m_move_out" name="target_move_out_date"
                                                class="w-full h-11 rounded-xl border border-[#E2E8F0] px-3.5 text-sm text-[#1F2937] focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 transition-all">
                                        </div>
                                        <div>
                                            <label for="m_message" class="block text-[11px] font-bold text-[#64748B] mb-1">Message <span class="font-semibold">(Optional)</span></label>
                                            <textarea id="m_message" name="message" rows="4" maxlength="300" x-model="msg"
                                                placeholder="Hi! I'm interested in this unit..."
                                                class="w-full rounded-xl border border-[#E2E8F0] px-3.5 py-2.5 text-sm text-[#1F2937] placeholder:text-[#64748B]/60 focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 transition-all resize-none"></textarea>
                                            <p class="text-[10px] font-semibold text-[#64748B]/70 text-right mt-0.5"><span x-text="msg.length"></span>/300</p>
                                        </div>

                                        <template x-if="selected && selected.hasActive">
                                            <div class="w-full py-3 text-center rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-bold cursor-not-allowed">
                                                Inquiry already active
                                            </div>
                                        </template>
                                        <template x-if="!selected || !selected.hasActive">
                                            <button type="submit" :disabled="!selected"
                                                class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]">
                                                Send Inquiry
                                            </button>
                                        </template>
                                    </form>
                                    <p class="text-[11px] font-medium text-[#64748B] text-center mt-3">Usually responds within a few hours</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endauth
        @endif

        {{-- ===== UNIT DETAIL SLIDEOUT ===== --}}
        <div x-show="slideoutUnit" x-cloak class="fixed inset-0 z-[998]" x-on:keydown.escape.window="closeSlideout()">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="closeSlideout()" x-show="slideoutUnit"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            </div>

            {{-- Panel --}}
            <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl overflow-y-auto"
                x-show="slideoutUnit" x-transition:enter="transition ease-out duration-250 transform"
                x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full" x-on:click.stop>

                <template x-if="slideoutUnit">
                    <div>
                        {{-- Close button --}}
                        <button type="button" x-on:click="closeSlideout()"
                            class="absolute top-4 right-4 z-10 w-9 h-9 rounded-xl bg-white/90 hover:brightness-95 text-[#1F2937] flex items-center justify-center shadow-sm transition-all">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        {{-- Image gallery --}}
                        <div class="relative aspect-[4/3] bg-[#E2E8F0]">
                            <template x-if="slideoutUnit.media.length > 0">
                                <div class="relative w-full h-full">
                                    <img :src="slideoutUnit.media[slideoutIdx].url" :alt="slideoutUnit.label"
                                        class="w-full h-full object-cover">

                                    {{-- Caption --}}
                                    <template x-if="slideoutUnit.media[slideoutIdx].caption">
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-8 pb-3">
                                            <p class="text-white text-[12.5px] font-medium leading-snug"
                                                x-text="slideoutUnit.media[slideoutIdx].caption"></p>
                                        </div>
                                    </template>

                                    <template x-if="slideoutUnit.media.length > 1">
                                        <div>
                                            <button type="button" x-on:click="slideoutPrev()"
                                                class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 hover:brightness-95 text-[#1F2937] flex items-center justify-center shadow-sm transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15.75 19.5L8.25 12l7.5-7.5" />
                                                </svg>
                                            </button>
                                            <button type="button" x-on:click="slideoutNext()"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 hover:brightness-95 text-[#1F2937] flex items-center justify-center shadow-sm transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                </svg>
                                            </button>

                                            {{-- Counter --}}
                                            <span
                                                class="absolute bottom-3 right-3 bg-black/60 text-white text-xs font-bold px-2.5 py-1 rounded-lg">
                                                <span x-text="slideoutIdx + 1"></span> / <span
                                                    x-text="slideoutUnit.media.length"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="slideoutUnit.media.length === 0">
                                <div class="w-full h-full flex flex-col items-center justify-center text-[#64748B]">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                                    </svg>
                                    <p class="mt-2 text-sm font-semibold">No photos</p>
                                </div>
                            </template>
                        </div>

                        {{-- Content --}}
                        <div class="p-6 space-y-6">

                            {{-- Header --}}
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-xl font-black text-[#1F2937]" x-text="slideoutUnit.label"></h3>
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-md"
                                        :class="slideoutUnit.available ? 'bg-[#22C55E]/10 text-[#1F2937]' : 'bg-[#E2E8F0] text-[#64748B]'"
                                        x-text="slideoutUnit.available ? 'Available' : 'Occupied'"></span>
                                </div>
                                <p class="text-sm font-medium text-[#64748B]" x-text="slideoutUnit.type"></p>
                                <p class="text-2xl font-black text-[#1F2937] mt-2">
                                    ₱<span x-text="slideoutUnit.price"></span>
                                    <span class="text-sm font-semibold text-[#64748B]">/ month</span>
                                </p>
                            </div>

                            {{-- Info pills --}}
                            <div class="flex flex-wrap gap-3 pt-4 border-t border-[#EEF8F8]">
                                <div class="flex items-center gap-2 bg-[#EEF8F8] px-3 py-2 rounded-xl">
                                    <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    <span class="text-sm font-bold text-[#1F2937]">
                                        <span x-text="slideoutUnit.occupancy"></span>
                                        <span x-text="slideoutUnit.occupancy > 1 ? 'People' : 'Person'"></span>
                                    </span>
                                </div>
                                <template x-if="slideoutUnit.size">
                                    <div class="flex items-center gap-2 bg-[#EEF8F8] px-3 py-2 rounded-xl">
                                        <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                        </svg>
                                        <span class="text-sm font-bold text-[#1F2937]" x-text="slideoutUnit.size"></span>
                                    </div>
                                </template>
                            </div>

                            {{-- Description --}}
                            <template x-if="slideoutUnit.description">
                                <div class="pt-4 border-t border-[#EEF8F8]">
                                    <h4 class="text-sm font-bold text-[#1F2937] mb-2">About this unit</h4>
                                    <p class="text-sm text-[#1F2937] leading-relaxed whitespace-pre-line"
                                        x-text="slideoutUnit.description"></p>
                                </div>
                            </template>

                            {{-- Amenities --}}
                            <template x-if="slideoutUnit.amenities.length > 0">
                                <div class="pt-4 border-t border-[#EEF8F8]">
                                    <h4 class="text-sm font-bold text-[#1F2937] mb-3">Unit Amenities</h4>
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <template x-for="amenity in slideoutUnit.amenities" :key="amenity">
                                            <div class="flex items-center gap-2 text-sm font-medium text-[#1F2937]">
                                                <span
                                                    class="w-6 h-6 rounded-md bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                                    <svg class="w-3.5 h-3.5 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </span>
                                                <span x-text="amenity"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Action --}}
                            <div class="pt-4 border-t border-[#EEF8F8]">
                                <template x-if="slideoutUnit.available && !slideoutUnit.hasActive">
                                    <button type="button"
                                        x-on:click="selectUnit(slideoutUnit.id); closeSlideout(); $nextTick(() => { document.getElementById('target_move_in_date')?.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                                        class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all">
                                        Select this unit
                                    </button>
                                </template>
                                <template x-if="slideoutUnit.available && slideoutUnit.hasActive">
                                    <div
                                        class="w-full py-3 text-center rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-bold cursor-not-allowed">
                                        Inquiry already active
                                    </div>
                                </template>
                                <template x-if="!slideoutUnit.available">
                                    <div
                                        class="w-full py-3 text-center rounded-xl bg-[#E2E8F0] text-[#64748B] text-sm font-bold cursor-not-allowed">
                                        Currently occupied
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
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