@extends('layouts.app', ['searchBar' => false])

@section('content')
    @vite(['resources/js/maps/property-map.js'])

    @php
        $approvedUnits = $property->units->where('verification_status', 'Approved')->values();
        $availableUnits = $approvedUnits->where('availability_status', 'Available')->values();
        $isOwner = auth()->check() && (int) auth()->id() === (int) $property->landlord_id;

        $minFee = $availableUnits->min('rental_fee');
        $maxFee = $availableUnits->max('rental_fee');

        // Amenities are held per unit (unit_amenities); property_amenities is
        // empty and has no landlord-facing form, so the property's list is
        // derived from what its approved units actually offer. Counting the
        // units carrying each one lets the section say "some units" instead of
        // implying the whole property has something only one room does.
        $amenityUnitCounts = [];
        foreach ($approvedUnits as $unit) {
            foreach ($unit->amenities->pluck('amenity_name')->unique() as $amenityName) {
                $amenityUnitCounts[$amenityName] = ($amenityUnitCounts[$amenityName] ?? 0) + 1;
            }
        }
        ksort($amenityUnitCounts, SORT_NATURAL | SORT_FLAG_CASE);

        $offeredAmenities = collect($amenityUnitCounts)
            ->map(fn($unitsWithIt, $name) => [
                'name' => $name,
                'inEveryUnit' => $unitsWithIt === $approvedUnits->count(),
            ])
            ->values();

        // The "Some units" tag only means something next to an untagged row.
        // When no amenity is in every unit it would land on all of them and
        // distinguish nothing — the section's subtitle already says the list
        // spans units, so the tag is suppressed rather than repeated.
        $tagPartialAmenities = $offeredAmenities->contains('inEveryUnit', true);

        $unitsPayload = $approvedUnits->map(function ($unit) use ($property) {
            $hasActiveReservation = auth()->check() && \App\Models\Reservation::where('unit_id', $unit->unit_id)
                ->where('tenant_id', auth()->id())
                ->whereNotIn('rental_status', \App\Models\Reservation::TERMINAL_STATUSES)
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
                // Raw as well as formatted: the cost breakdown needs to sum
                // rent + deposit, and re-parsing "9,500" client-side to do it
                // would be a comma-separated bug waiting to happen.
                'rentRaw' => (float) $unit->rental_fee,
                'depositRaw' => $unit->security_deposit !== null ? (float) $unit->security_deposit : null,
                'deposit' => $unit->security_deposit !== null ? number_format($unit->security_deposit) : null,
                'size' => $unit->size ?? null,
                'available' => $unit->availability_status === 'Available',
                'hasActive' => $hasActiveReservation,
            ];
        })->values();

        $defaultUnitId = optional($unitsPayload->firstWhere('available', true))['id'] ?? null;
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-28 lg:pb-8 min-h-[calc(100vh-72px)]" x-data="{
                                                    mode: 'inquiry',
                                                    inquireOpen: false,

                                                    reportOpen: false,
                                                    reportCategory: '',
                                                    reportDetails: '',
                                                    reportSending: false,
                                                    reportError: '',

                                                    async submitReport() {
                                                        if (this.reportSending) return;
                                                        this.reportSending = true;
                                                        this.reportError = '';

                                                        try {
                                                            const res = await fetch('{{ route('reports.store') }}', {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'Accept': 'application/json',
                                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                                },
                                                                body: JSON.stringify({
                                                                    target_type: 'property',
                                                                    property_id: {{ $property->property_id }},
                                                                    category: this.reportCategory,
                                                                    details: this.reportDetails,
                                                                }),
                                                            });

                                                            if (!res.ok) {
                                                                // 422 carries Laravel's field errors; anything else is
                                                                // a failure the user can only retry.
                                                                const body = await res.json().catch(() => ({}));
                                                                this.reportError = body.errors
                                                                    ? Object.values(body.errors).flat()[0]
                                                                    : 'Could not submit your report. Please try again.';
                                                                return;
                                                            }

                                                            const data = await res.json();
                                                            this.reportOpen = false;
                                                            this.reportCategory = '';
                                                            this.reportDetails = '';

                                                            window.dispatchEvent(new CustomEvent('show-modal', {
                                                                detail: {
                                                                    type: 'success',
                                                                    title: 'Report submitted',
                                                                    message: data.message,
                                                                },
                                                            }));
                                                        } catch (e) {
                                                            this.reportError = 'Network error. Please try again.';
                                                        } finally {
                                                            this.reportSending = false;
                                                        }
                                                    },
                                                    moreUnits: false,
                                                    mobileOpen: false,
                                                    mstep: 1,
                                                    openMobile() { this.mstep = this.selectedUnit ? 2 : 1; this.mobileOpen = true; document.body.style.overflow = 'hidden'; },

                                                    init() {
                                                        // Below lg the desktop sidebar form is hidden, so a validation
                                                        // error can only have come from the mobile sheet — reopen it on
                                                        // step 2 or the message renders inside a closed panel.
                                                        @if($errors->any())
                                                            if (window.matchMedia('(max-width: 1023px)').matches) {
                                                                this.$nextTick(() => this.openMobile());
                                                            }
                                                        @endif
                                                    },
                                                    closeMobile() { this.mobileOpen = false; document.body.style.overflow = ''; },
                                                        units: @js($unitsPayload),
                                                    selectedUnit: @js($defaultUnitId),
                                                    msg: @js(old('message', '')),

                                                    moveIn: @js(old('target_move_in_date', '')),
                                                    moveOut: @js(old('target_move_out_date', '')),
                                                    minMoveIn: @js(now()->toDateString()),
                                                    maxMoveIn: @js(now()->addYear()->toDateString()),

                                                    // Move-out can never precede move-in. Bumping move-in past an
                                                    // already-picked move-out clears it rather than leaving an
                                                    // invalid pair sitting in the form.
                                                    onMoveInChange() {
                                                        if (this.moveOut && this.moveOut <= this.moveIn) this.moveOut = '';
                                                    },

                                                    // Inquiry needs only a unit; a date is meaningless until
                                                    // the tenant is actually reserving.
                                                    get canSubmit() {
                                                        if (!this.selected) return false;
                                                        return this.mode === 'reserve' ? !!this.moveIn : true;
                                                    },

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

                                                    // Sections are always in the DOM now that the tabs are gone,
                                                    // so the map no longer needs a resize nudge on reveal.
                                                }">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

            {{-- ===================================================== --}}
            {{-- MEDIA RAIL — gallery + units, sticky as one panel      --}}
            {{--                                                        --}}
            {{-- Sticky with its own max-height and scroll rather than a --}}
            {{-- fixed full-height rail: a one-unit property gets the    --}}
            {{-- immersive rail, a twelve-unit one scrolls its list      --}}
            {{-- inside the rail instead of pushing the page out of      --}}
            {{-- alignment. Static below lg, where it simply stacks.     --}}
            {{-- ===================================================== --}}
            <div class="lg:col-span-5 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] space-y-6">

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

            {{-- ===================================================== --}}
            {{-- EDITORIAL COLUMN — hero, then stacked detail sections  --}}
            {{-- ===================================================== --}}
            <div class="lg:col-span-7 min-w-0">

                {{-- ===== HERO ===== --}}
                <nav class="flex items-center gap-1.5 text-[13px] font-semibold text-[#64748B] mb-5" aria-label="Breadcrumb">
                    <a href="{{ url('/') }}" class="hover:text-[#1F2937] transition-colors">Home</a>
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('properties.index') }}" class="hover:text-[#1F2937] transition-colors">Properties</a>
                    <span aria-hidden="true">·</span>
                    <span class="text-[#1F2937] truncate max-w-[220px]">{{ $property->title }}</span>
                </nav>

                {{-- font-display is Source Serif 4, reserved for page titles
                     this size. Card headings and section labels stay on
                     font-heading (Poppins) — see DESIGN.md §4. --}}
                <h1 class="font-display text-[30px] sm:text-[38px] font-bold leading-[1.12] tracking-[-0.015em] text-[#1F2937] text-balance">
                    {{ $property->title }}
                </h1>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13.5px] font-medium text-[#64748B]">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 shrink-0 text-[#EF4444]" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $property->address }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                        @if($avgRating)
                            <span class="font-bold text-[#1F2937]">{{ $avgRating }}</span>
                            <a href="#reviews" class="hover:text-[#1F2937] underline underline-offset-2">
                                {{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }}
                            </a>
                        @else
                            <span>No reviews yet</span>
                        @endif
                    </span>
                </div>

                {{-- Price follows the unit picked in the rail, so there is one
                     source of truth rather than a hero range that can disagree
                     with what the form is about to submit. --}}
                <p class="mt-5 flex items-baseline gap-2">
                    <span class="font-display text-[34px] sm:text-[40px] font-bold tracking-tight text-[#156F8C]">
                        &#8369;<span x-text="selected ? selected.price : '{{ number_format($property->units->min('rental_fee') ?? 0) }}'"></span>
                    </span>
                    <span class="text-[15px] font-medium text-[#64748B]">/ month</span>
                </p>

                @if($property->description)
                    <div class="mt-4 max-w-[62ch]">
                        @if(Str::length($property->description) > 220)
                            <p class="text-[15px] text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="!descExpanded">
                                {{ Str::limit($property->description, 220) }}
                            </p>
                            <p class="text-[15px] text-[#1F2937] leading-relaxed whitespace-pre-line" x-show="descExpanded"
                                x-cloak>{{ $property->description }}</p>
                            <button type="button" x-on:click="descExpanded = !descExpanded"
                                class="mt-1.5 text-[14px] font-bold text-[#156F8C] hover:brightness-95 transition-all underline underline-offset-2"
                                x-text="descExpanded ? 'Read less' : 'Read more'"></button>
                        @else
                            <p class="text-[15px] text-[#1F2937] leading-relaxed whitespace-pre-line">
                                {{ $property->description }}</p>
                        @endif
                    </div>
                @endif

                {{-- ===== FACT TILES ===== --}}
                <dl class="mt-6 grid grid-cols-2 gap-3">
                    @php
                        $availableUnits = $property->units->where('availability_status', 'Available')->count();
                        $maxCapacity = $property->units->max('capacity');
                    @endphp
                    @foreach ([
                        ['Type', $property->property_type],
                        ['Capacity', $maxCapacity ? $maxCapacity . ' ' . Str::plural('person', $maxCapacity) : '—'],
                        ['Landlord', trim($property->landlord->first_name . ' ' . $property->landlord->last_name)],
                        ['Available', $availableUnits . ' ' . Str::plural('unit', $availableUnits)],
                    ] as [$label, $value])
                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">{{ $label }}</dt>
                            <dd class="mt-0.5 text-[14.5px] font-bold text-[#1F2937] truncate">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- ===== PRIMARY ACTION ===== --}}
                <div class="mt-6 flex items-stretch gap-3">
                    @if(!auth()->check())
                        <button type="button" onclick="openAuthModal('login')"
                            class="flex-1 py-3.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-[15px] font-bold shadow-sm transition-all">
                            Log in to inquire
                        </button>
                    @elseif($isOwner)
                        <div class="flex-1 py-3.5 text-center rounded-xl bg-[#E2E8F0] text-[#64748B] text-[15px] font-bold cursor-not-allowed">
                            This is your listing
                        </div>
                    @else
                        <button type="button" x-on:click="inquireOpen = true"
                            class="flex-1 py-3.5 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-[15px] font-bold shadow-sm transition-all cursor-pointer"
                            x-text="selected && selected.hasActive ? 'Inquiry already active' : 'Send Inquiry'"
                            :disabled="selected && selected.hasActive"
                            :class="selected && selected.hasActive ? 'opacity-60 cursor-not-allowed' : ''">
                        </button>

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
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    'Accept': 'application/json',
                                                },
                                            });
                                            if (res.ok) { const d = await res.json(); this.fav = d.favorited; }
                                        } finally { this.busy = false; }
                                    }
                                }">
                                <button type="button" x-on:click="toggleFav()" :disabled="busy"
                                    :aria-pressed="fav"
                                    class="h-full aspect-square rounded-xl border border-[#E2E8F0] bg-white flex items-center justify-center hover:border-[#2AA7A1] transition-all disabled:opacity-50 cursor-pointer">
                                    <span class="sr-only" x-text="fav ? 'Remove from favorites' : 'Add to favorites'"></span>
                                    <svg class="w-5 h-5 transition-colors" :class="fav ? 'text-[#EF4444]' : 'text-[#64748B]'"
                                        :fill="fav ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

                <p class="mt-2.5 text-[12.5px] font-medium text-[#64748B]">Usually responds within a few hours</p>

                {{-- ===== LANDLORD ROW ===== --}}
                <div class="mt-6 pt-6 border-t border-[#E2E8F0] flex items-center gap-3">
                    <div class="w-11 h-11 shrink-0 rounded-full bg-[#2AA7A1] text-white flex items-center justify-center text-[14px] font-bold">
                        {{ strtoupper(substr($property->landlord->first_name, 0, 1)) }}{{ strtoupper(substr($property->landlord->last_name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[14.5px] font-bold text-[#1F2937] truncate">
                            {{ trim($property->landlord->first_name . ' ' . $property->landlord->last_name) }}
                        </p>
                        {{-- Built in PHP: a directive placed immediately after a
                             word character isn't matched by Blade's \B@ regex,
                             so "Landlord@if(...)" renders literally and orphans
                             the @endif. Same trap as chat-panel's $occupiedNote. --}}
                        @php
                            $hostLine = $property->landlord->rentalBusiness
                                ? 'Landlord · Verified Host'
                                : 'Landlord';
                        @endphp
                        <p class="text-[12.5px] text-[#64748B]">{{ $hostLine }}</p>
                    </div>
                    <a href="{{ route('landlord.profile.show', $property->landlord_id) }}"
                        class="shrink-0 text-[13.5px] font-bold text-[#156F8C] hover:brightness-95 transition-all">
                        View profile &rarr;
                    </a>
                </div>

                @auth
                    <button type="button" x-on:click="reportOpen = true"
                        class="mt-3 inline-block text-[12.5px] font-semibold text-[#64748B] hover:text-[#1F2937] underline underline-offset-2 transition-colors cursor-pointer">
                        Report this listing
                    </button>
                @endauth

                @if($offeredAmenities->isNotEmpty())
                <section id="amenities" class="mt-10 pt-8 border-t border-[#E2E8F0]">
                    <h2 class="font-heading text-[19px] font-bold tracking-tight text-[#1F2937] mb-4">What this place offers</h2>
                    @if($approvedUnits->count() > 1)
                        <p class="-mt-2 mb-4 text-[12.5px] text-[#64748B]">
                            Across {{ $approvedUnits->count() }} units. Select a unit to see exactly what it includes.
                        </p>
                    @endif
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($offeredAmenities as $amenity)
                            <div class="flex items-center gap-3 text-sm text-[#1F2937] font-medium">
                                <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="min-w-0">
                                    {{ $amenity['name'] }}
                                    @if($tagPartialAmenities && ! $amenity['inEveryUnit'])
                                        <span class="ml-1 align-middle text-[10.5px] font-semibold uppercase tracking-wide text-[#156F8C] bg-[#EEF8F8] rounded px-1.5 py-0.5 whitespace-nowrap">Some units</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif

                @if(!empty($property->house_rules))
                <section id="house-rules" class="mt-10 pt-8 border-t border-[#E2E8F0]">
                    <h2 class="font-heading text-[19px] font-bold tracking-tight text-[#1F2937] mb-4">House rules</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($property->house_rules as $rule)
                            <div class="flex items-center gap-3 text-sm text-[#1F2937] font-medium">
                                <div class="w-8 h-8 rounded-lg bg-[#EF4444]/[0.07] flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-[#EF4444]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                {{ $rule }}
                            </div>
                        @endforeach
                    </div>
                </section>
                @endif

                {{-- ===== LOCATION — compact utility card =====
                     One bordered card: header (title + address + mode control),
                     full-bleed map, summary bar. The mode control sits in the
                     header rather than appearing after routing, so the choice is
                     visible before you commit to sharing your location. --}}
                <section id="location" class="mt-10 pt-8 border-t border-[#E2E8F0]">
                    <div class="rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">

                        {{-- Header --}}
                        <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-9 h-9 shrink-0 rounded-xl bg-[#EEF8F8] flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#EF4444]" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <h2 class="font-heading text-[15px] font-bold tracking-tight text-[#1F2937]">
                                        Where you'll be</h2>
                                    <p class="text-[12.5px] text-[#64748B] truncate">{{ $property->address }}</p>
                                </div>
                            </div>

                            {{-- Segmented control. Always visible: it's a preference,
                                 and switching before routing simply decides which
                                 estimate appears once a route exists. --}}
                            <div id="directions-modes"
                                class="flex items-center gap-0.5 p-1 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0]"
                                role="group" aria-label="Travel mode">
                                @foreach ([
                                    ['motorcycle', 'Moto', 'M6.75 18a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm15 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0ZM4.5 18h4.125L12 12h4.5l3 6M9 12h6'],
                                    ['car', 'Car', 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
                                    ['walk', 'Walk', 'M13.5 4.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM10.5 9l-3 3.75M10.5 9l3 1.5 1.5 4.5M10.5 9 9 21m6-6-1.5 6'],
                                ] as $i => [$mode, $label, $path])
                                    <button type="button" data-mode="{{ $mode }}"
                                        aria-pressed="{{ $i === 0 ? 'true' : 'false' }}"
                                        class="directions-mode inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg text-[12px] font-bold transition-colors
                                            {{ $i === 0 ? 'bg-white text-[#156F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                        </svg>
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Map — full-bleed between the two bars --}}
                        <div id="property-map" data-lat="{{ $property->latitude }}" data-lng="{{ $property->longitude }}"
                            data-title="{{ $property->title }}"
                            class="w-full h-64 sm:h-72 bg-[#E2E8F0] border-y border-[#E2E8F0] relative">
                        </div>

                        {{-- Summary bar --}}
                        <div class="px-4 sm:px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div id="directions-panel" class="min-w-0">
                                    <p class="text-[13.5px] text-[#64748B]">See how far this is from you.</p>
                                </div>

                                <button type="button" id="get-directions-btn"
                                    class="shrink-0 inline-flex items-center gap-2 bg-[#1F2937] hover:brightness-95 text-white text-[13px] font-bold px-5 py-2.5 rounded-xl shadow-sm transition-all">
                                    Get directions
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </button>
                            </div>

                            <form id="manual-origin-form" class="hidden mt-3 flex gap-2">
                                <label for="manual-origin-input" class="sr-only">Your starting address in Cebu</label>
                                <input type="text" id="manual-origin-input"
                                    placeholder="Enter your starting address in Cebu"
                                    class="flex-1 min-w-0 border border-[#E2E8F0] rounded-xl px-4 py-2 text-sm text-[#1F2937] bg-white focus:outline-none focus:ring-4 focus:ring-[#2AA7A1]/10 focus:border-[#2AA7A1] transition-all">
                                <button type="submit"
                                    class="shrink-0 bg-[#2AA7A1] hover:brightness-95 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
                                    Go
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <section id="reviews" class="mt-10 pt-8 border-t border-[#E2E8F0]">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-heading text-[19px] font-bold tracking-tight text-[#1F2937]">
                            Reviews
                            @if($reviews->count() > 0)
                                <span class="text-[14px] font-semibold text-[#64748B]">({{ $reviews->count() }})</span>
                            @endif
                        </h2>
                        @if($avgRating)
                            <div class="flex items-center gap-1.5 bg-[#F7FCFC] border border-[#E2E8F0] px-2.5 py-1 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor"
                                    aria-hidden="true">
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
                </section>

            </div>
        </div>

        {{-- ===== REPORT LISTING MODAL =====
             Posts by fetch rather than navigating to /reports: the standalone
             page redirects back to itself on success, which from here would
             throw away the selected unit and the scroll position for what is a
             fire-and-forget action. The controller returns JSON when the
             request expects it and keeps its redirect for the standalone page,
             so both entry points share one validated store(). --}}
        @auth
            @if(!$isOwner)
                <template x-teleport="body">
                    <div x-show="reportOpen" x-cloak class="fixed inset-0 z-[200] overflow-y-auto bg-black/40 backdrop-blur-sm"
                        x-on:keydown.escape.window="reportOpen = false"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0">
                        <div class="min-h-full flex items-center justify-center p-4 sm:p-6">
                            <div x-on:click.outside="reportOpen = false" role="dialog" aria-modal="true"
                                aria-labelledby="report-modal-title"
                                class="w-full max-w-md rounded-2xl bg-white shadow-xl overflow-hidden"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95 motion-reduce:scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-end="opacity-0 scale-95 motion-reduce:scale-100">

                                <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h2 id="report-modal-title" class="text-[16px] font-bold text-[#1F2937]">
                                            Report this listing</h2>
                                        <p class="mt-0.5 text-[12.5px] text-[#64748B] truncate">{{ $property->title }}</p>
                                    </div>
                                    <button type="button" x-on:click="reportOpen = false"
                                        class="shrink-0 -mr-1 w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#EEF8F8] hover:text-[#1F2937] transition-colors">
                                        <span class="sr-only">Close</span>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <form x-on:submit.prevent="submitReport" class="px-6 py-5 space-y-4">
                                    <div>
                                        <label for="report_category"
                                            class="block text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1.5">
                                            What's wrong?</label>
                                        <select id="report_category" x-model="reportCategory" required
                                            class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3.5 py-2.5 text-sm text-[#1F2937] focus:border-[#2AA7A1] focus:ring-4 focus:ring-[#2AA7A1]/10 outline-none transition-all">
                                            <option value="" disabled>Choose a reason</option>
                                            @foreach (\App\Http\Controllers\ReportController::CATEGORIES as $category)
                                                <option value="{{ $category }}">{{ $category }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="report_details"
                                            class="block text-[11px] font-bold uppercase tracking-wider text-[#64748B] mb-1.5">
                                            Details <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                                        <textarea id="report_details" x-model="reportDetails" rows="4" maxlength="1000"
                                            placeholder="Tell us what you noticed. The more specific, the faster we can act."
                                            class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3.5 py-2.5 text-sm text-[#1F2937] placeholder:text-[#64748B]/60 focus:border-[#2AA7A1] focus:ring-4 focus:ring-[#2AA7A1]/10 outline-none transition-all resize-none"></textarea>
                                        <p class="mt-1 text-[10.5px] font-semibold text-[#64748B]/70 text-right">
                                            <span x-text="reportDetails.length"></span>/1000</p>
                                    </div>

                                    <template x-if="reportError">
                                        <p class="text-[12.5px] font-semibold text-[#EF4444]" x-text="reportError"></p>
                                    </template>

                                    <p class="text-[11.5px] text-[#64748B] leading-relaxed">
                                        Reports go to the AbangananHub team, not to the landlord. They won't know who
                                        filed it.
                                    </p>

                                    <div class="flex flex-wrap items-center gap-3 pt-1">
                                        <button type="submit" :disabled="!reportCategory || reportSending"
                                            class="px-6 py-2.5 rounded-xl bg-[#EF4444] text-white text-sm font-bold hover:brightness-95 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                                            x-text="reportSending ? 'Sending…' : 'Submit report'"></button>
                                        <button type="button" x-on:click="reportOpen = false"
                                            class="px-4 py-2.5 rounded-xl text-sm font-semibold text-[#1F2937] hover:bg-[#EEF8F8] transition-colors cursor-pointer">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        @endauth

        {{-- ===== INQUIRY MODAL =====
             The hero carries a single CTA, so the form it used to sit beside
             lives here. Teleported to body: the editorial column is inside a
             sticky/overflow context, and a dialog rendered there would be
             clipped by it. Desktop only — below lg the existing sticky bottom
             bar and two-step sheet already cover this. --}}
        @auth
            @if(!$isOwner)
                <template x-teleport="body">
                    <div x-show="inquireOpen" x-cloak class="hidden lg:block fixed inset-0 z-[200] overflow-y-auto bg-black/40 backdrop-blur-sm"
                        x-on:keydown.escape.window="inquireOpen = false"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0">
                        {{-- items-center on a min-h-full track inside an
                             overflow-y-auto backdrop: centred when the dialog
                             fits, and still scrollable from the top when it
                             doesn't, which fixed positioning alone would clip. --}}
                        <div class="min-h-full flex items-center justify-center p-6">
                            <div x-on:click.outside="inquireOpen = false" role="dialog" aria-modal="true"
                                aria-labelledby="inquire-modal-title"
                                class="w-full max-w-lg rounded-2xl bg-white shadow-xl overflow-hidden"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95 motion-reduce:scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-end="opacity-0 scale-95 motion-reduce:scale-100">

                                <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h2 id="inquire-modal-title" class="text-[16px] font-bold text-[#1F2937]">Inquire / Reserve</h2>
                                        <template x-if="selected">
                                            <p class="mt-0.5 text-[12.5px] text-[#64748B] truncate">
                                                <span x-text="selected.label"></span> &middot;
                                                &#8369;<span x-text="selected.price"></span> / month
                                            </p>
                                        </template>
                                    </div>
                                    <button type="button" x-on:click="inquireOpen = false"
                                        class="shrink-0 -mr-1 w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#EEF8F8] hover:text-[#1F2937] transition-colors">
                                        <span class="sr-only">Close</span>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="px-6 py-5">
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

    <form action="{{ route('reservations.store') }}" method="POST" class="space-y-4">
                                @csrf
                                <input type="hidden" name="unit_id" :value="selected ? selected.id : ''">
                                {{-- The toggle used to be presentational only, so the
                                     server couldn't tell the two apart and demanded a
                                     move-in date either way. Posting it is what lets
                                     an inquiry be a question rather than a booking. --}}
                                <input type="hidden" name="mode" :value="mode">

                                {{-- Reserve only: a tenant asking a question hasn't
                                     decided when they'd move in, and guessing here
                                     used to set the escrow escalation clock. --}}
                                <div class="grid grid-cols-2 gap-3 items-start" x-show="mode === 'reserve'" x-cloak>
                                    <div>
                                        <div class="rounded-xl bg-white border px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10 @error('target_move_in_date') border-[#EF4444] @else border-[#E2E8F0] @enderror">
                                            <label for="target_move_in_date"
                                                class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Move
                                                In</label>
                                            <input type="date" id="target_move_in_date" name="target_move_in_date"
                                                :required="mode === 'reserve'"
                                                x-model="moveIn" x-on:change="onMoveInChange()"
                                                :min="minMoveIn" :max="maxMoveIn"
                                                @error('target_move_in_date') aria-invalid="true" aria-describedby="target_move_in_date_error" @enderror
                                                class="w-full bg-transparent border-0 p-0 text-sm font-semibold text-[#1F2937] focus:outline-none focus:ring-0 [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer">
                                        </div>
                                        @error('target_move_in_date')
                                            <p id="target_move_in_date_error" class="mt-1 text-[11px] font-semibold text-[#EF4444]">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <div class="rounded-xl bg-white border px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10 @error('target_move_out_date') border-[#EF4444] @else border-[#E2E8F0] @enderror">
                                            <label for="target_move_out_date"
                                                class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Move
                                                Out <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                                            <input type="date" id="target_move_out_date" name="target_move_out_date"
                                                x-model="moveOut" :min="moveIn || minMoveIn" :disabled="!moveIn"
                                                @error('target_move_out_date') aria-invalid="true" aria-describedby="target_move_out_date_error" @enderror
                                                class="w-full bg-transparent border-0 p-0 text-sm font-semibold text-[#1F2937] focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:text-[#64748B]/50 [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer">
                                        </div>
                                        @error('target_move_out_date')
                                            <p id="target_move_out_date_error" class="mt-1 text-[11px] font-semibold text-[#EF4444]">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl bg-white border border-[#E2E8F0] px-3.5 pt-2.5 pb-2 transition-all focus-within:border-[#2AA7A1]/60 focus-within:ring-4 focus-within:ring-[#2AA7A1]/10">
                                    <label for="inquiry_message"
                                        class="block text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-0.5">Message
                                        <span class="normal-case tracking-normal font-semibold">· optional</span></label>
                                    <textarea id="inquiry_message" name="message" rows="3" maxlength="300" x-model="msg"
                                        placeholder="Hi! I'm interested in this unit..."
                                        class="w-full bg-transparent border-0 p-0 text-sm font-medium text-[#1F2937] placeholder:text-[#64748B]/60 focus:outline-none focus:ring-0 resize-none"></textarea>
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
                                    <button type="submit" :disabled="!canSubmit"
                                        class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]"
                                        x-text="!selected ? 'Select a unit' : (mode === 'reserve' ? (!moveIn ? 'Choose a move-in date' : 'Send Reservation Request') : 'Send Inquiry to Landlord')">
                                    </button>
                                </template>
                            </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        @endauth

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

                                    {{-- Same two modes as the desktop sidebar. The sheet
                                         previously offered only "Send Inquiry" yet still
                                         demanded a move-in date, so a tenant on a phone had
                                         no way to just ask a question. --}}
                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <button type="button" x-on:click="mode = 'inquiry'"
                                            :class="mode === 'inquiry' ? 'border-[#2AA7A1] text-[#156F8C] bg-[#EEF8F8]/60' : 'border-[#E2E8F0] text-[#64748B]'"
                                            class="h-10 rounded-xl border text-sm font-bold bg-white cursor-pointer transition-all duration-200">
                                            Inquiry
                                        </button>
                                        <button type="button" x-on:click="mode = 'reserve'"
                                            :class="mode === 'reserve' ? 'border-[#2AA7A1] text-[#156F8C] bg-[#EEF8F8]/60' : 'border-[#E2E8F0] text-[#64748B]'"
                                            class="h-10 rounded-xl border text-sm font-bold bg-white cursor-pointer transition-all duration-200">
                                            Reserve
                                        </button>
                                    </div>

                                    <form action="{{ route('reservations.store') }}" method="POST" class="space-y-3.5">
                                        @csrf
                                        <input type="hidden" name="unit_id" :value="selected ? selected.id : ''">
                                        <input type="hidden" name="mode" :value="mode">

                                        <div x-show="mode === 'reserve'" x-cloak>
                                            <label for="m_move_in" class="block text-[11px] font-bold text-[#64748B] mb-1">Target Move In</label>
                                            <input type="date" id="m_move_in" name="target_move_in_date"
                                                :required="mode === 'reserve'"
                                                x-model="moveIn" x-on:change="onMoveInChange()"
                                                :min="minMoveIn" :max="maxMoveIn"
                                                @error('target_move_in_date') aria-invalid="true" aria-describedby="m_move_in_error" @enderror
                                                class="w-full h-11 rounded-xl border px-3.5 text-sm text-[#1F2937] focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 transition-all @error('target_move_in_date') border-[#EF4444] @else border-[#E2E8F0] @enderror">
                                            @error('target_move_in_date')
                                                <p id="m_move_in_error" class="mt-1 text-[11px] font-semibold text-[#EF4444]">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div x-show="mode === 'reserve'" x-cloak>
                                            <label for="m_move_out" class="block text-[11px] font-bold text-[#64748B] mb-1">Target Move Out <span class="font-semibold">(Optional)</span></label>
                                            <input type="date" id="m_move_out" name="target_move_out_date"
                                                x-model="moveOut" :min="moveIn || minMoveIn" :disabled="!moveIn"
                                                @error('target_move_out_date') aria-invalid="true" aria-describedby="m_move_out_error" @enderror
                                                class="w-full h-11 rounded-xl border px-3.5 text-sm text-[#1F2937] focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 transition-all disabled:cursor-not-allowed disabled:bg-[#F7FCFC] disabled:text-[#64748B]/50 @error('target_move_out_date') border-[#EF4444] @else border-[#E2E8F0] @enderror">
                                            @error('target_move_out_date')
                                                <p id="m_move_out_error" class="mt-1 text-[11px] font-semibold text-[#EF4444]">{{ $message }}</p>
                                            @enderror
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
                                            <button type="submit" :disabled="!canSubmit"
                                                class="w-full py-3 rounded-xl bg-[#FF8A65] hover:brightness-95 text-white text-sm font-bold shadow-sm transition-all disabled:cursor-not-allowed disabled:bg-[#E2E8F0] disabled:text-[#64748B]"
                                                x-text="!selected ? 'Select a unit' : (mode === 'reserve' ? (!moveIn ? 'Choose a move-in date' : 'Send Reservation Request') : 'Send Inquiry')">
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