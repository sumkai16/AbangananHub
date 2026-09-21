@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16"
        x-data="{
            view: {!! "localStorage.getItem('unitsView') || 'table'" !!},
            setView(v) { this.view = v; localStorage.setItem('unitsView', v); },
            modal: null,
            show: false,
            peso(v) { return v ? '₱' + Number(v).toLocaleString('en-PH') : null; },
            openModal(u) { this.modal = u; this.$nextTick(() => this.show = true); },
            closeModal() { this.show = false; setTimeout(() => this.modal = null, 200); },
        }"
        x-on:keydown.escape.window="closeModal()">

        {{-- One layout only: ?property= (from a property card) just narrows the list via the dropdown. It no longer switches to a separate single-property layout. --}}
        @php
            $scopedProperty = null;
        @endphp

        {{-- Header --}}
        <x-page-header :title="$scopedProperty ? $scopedProperty->title : 'Units'"
            :subtitle="$scopedProperty ? 'Units in this property.' : 'Manage all units and their availability across your properties.'">
            <x-slot:actions>
                {{-- Export carries the active filters so the CSV matches the page --}}
                <a href="{{ route('landlord.units.export', request()->only('search', 'property', 'status')) }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export
                </a>

                @if(request('property'))
                    <a href="{{ route('landlord.properties.units.create', request('property')) }}"
                        class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-sm font-semibold shadow-sm transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Unit
                    </a>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] text-[#DC2626] text-sm font-medium">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Status summary. Each status cell filters the list (click again to clear); Total clears any filter. --}}
        @php
            $pctOfTotal = fn (int $n) => $stats['total'] > 0 ? round($n / $stats['total'] * 100) : 0;
            $cellHref = fn (?string $status) => route('landlord.units.index', array_filter([
                'property' => request('property'),
                'search' => request('search'),
                'status' => $status && request('status') !== $status ? $status : null,
            ]));
            $summary = [
                ['label' => 'Total units', 'value' => $stats['total'], 'note' => request('property') ? 'In the selected property' : 'Across all properties', 'href' => $cellHref(null)],
            ];
            foreach ([
                ['Available', 'available', '#22C55E', '% of units'],
                ['Reserved', 'reserved', '#FBBF24', '% of units'],
                ['Occupied', 'occupied', '#EF4444', '% occupancy'],
                ['Maintenance', 'maintenance', '#94A3B8', '% of units'],
            ] as [$label, $key, $dot, $suffix]) {
                if ($key === 'maintenance' && $stats[$key] === 0 && request('status') !== $label) {
                    continue;
                }
                $summary[] = ['label' => $label, 'value' => $stats[$key], 'note' => $pctOfTotal($stats[$key]) . $suffix, 'dot' => $dot,
                    'href' => $cellHref($label), 'active' => request('status') === $label];
            }
        @endphp
        <x-stat-strip :cells="$summary" class="mb-6" />

        {{-- Filters --}}
        <form method="GET" class="mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ $scopedProperty ? 'Search units by name...' : 'Search units by name or property...' }}" aria-label="Search units"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @php
                        $unitsPropertyOptions = ['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all();
                        $unitsStatusOptions = ['' => 'All Status', 'Available' => 'Available', 'Reserved' => 'Reserved', 'Occupied' => 'Occupied', 'Maintenance' => 'Maintenance'];
                    @endphp
                    @if($scopedProperty)
                        <input type="hidden" name="property" value="{{ $scopedProperty->property_id }}">
                    @else
                        <x-styled-select name="property" :options="$unitsPropertyOptions" :selected="(string) request('property', '')" :autosubmit="true"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[180px]" />
                    @endif

                    @if($scopedProperty)
                        {{-- Status is chosen with the tabs above; keep it through a search --}}
                        @if(request('status'))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                    @else
                    <x-styled-select name="status" :options="$unitsStatusOptions" :selected="request('status', '')" :autosubmit="true"
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26]" />

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13.5px] font-semibold hover:bg-[#E96F4F] transition-all duration-200 inline-flex items-center gap-1.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>
                    @endif

                    @if(request()->hasAny(['search', 'status']) || (! $scopedProperty && request()->has('property')))
                        <a href="{{ route('landlord.units.index', $scopedProperty ? ['property' => $scopedProperty->property_id] : []) }}"
                            class="h-11 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif

                    {{-- View toggle (one property is always a table on desktop, cards on phones) --}}
                    @unless($scopedProperty)
                    <div class="flex items-center gap-0.5 h-11 p-1 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] ml-auto">
                        <button type="button" x-on:click="setView('grid')" aria-label="Grid view"
                            :class="view === 'grid' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="h-9 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                            </svg>
                        </button>
                        <button type="button" x-on:click="setView('table')" aria-label="Table view"
                            :class="view === 'table' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="h-9 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>
                    </div>
                    @endunless
                </div>
            </div>

            @if(request()->hasAny(['search', 'status']) || (! $scopedProperty && request()->has('property')))
                <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-[#5B6A8E]/10 text-[12.5px] text-[#5B6A8E]">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span class="font-semibold text-[#060D26]">{{ $units->total() }}</span>
                    {{ Str::plural('unit', $units->total()) }} match{{ $units->total() === 1 ? 'es' : '' }} your filters
                </div>
            @endif
        </form>

        {{-- Empty state --}}
        @if($units->isEmpty())
            <div
                class="rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-white flex flex-col items-center justify-center py-16 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#060D26]">No units found</p>
                <p class="text-xs text-[#5B6A8E] mt-1">Try adjusting your search or filters.</p>
            </div>

            {{-- Unit list (grid / table toggle) --}}
        @else
            @php
                // Derived per-unit data shared by both the card grid and the table view
                $derived = [];
                foreach ($units as $unit) {
                    $thumb = $unit->media->firstWhere('media_type', 'Image') ?? $unit->property->media->firstWhere('media_type', 'Image') ?? null;
                    [$avBg] = match ($unit->availability_status) {
                        'Available' => ['bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25'],
                        'Reserved' => ['bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35'],
                        'Occupied' => ['bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25'],
                        default => ['bg-[#ECEEF6] text-[#5B6A8E] ring-[#5B6A8E]/20'],
                    };
                    [$vrBg] = match ($unit->verification_status) {
                        'Approved' => ['bg-[#22C55E]/[0.07] text-[#15803D]'],
                        'Pending' => ['bg-[#FBBF24]/[0.10] text-[#B45309]'],
                        'Rejected' => ['bg-[#EF4444]/[0.07] text-[#DC2626]'],
                        default => ['bg-[#ECEEF6] text-[#5B6A8E]'],
                    };

                    // Payload for the unit detail modal (Live Preview style)
                    $modalStyles = match ($unit->availability_status) {
                        'Available' => ['tile' => 'border-[#22C55E]/25 bg-[#22C55E]/[0.07]', 'text' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                        'Reserved' => ['tile' => 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]', 'text' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
                        'Occupied' => ['tile' => 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]', 'text' => 'text-[#DC2626]', 'dot' => 'bg-[#EF4444]'],
                        default => ['tile' => 'border-[#E2E4EC] bg-[#F7F8FC]', 'text' => 'text-[#5B6A8E]', 'dot' => 'bg-[#94A3B8]'],
                    };
                    $activeRes = in_array($unit->availability_status, ['Reserved', 'Occupied'], true)
                        ? $unit->reservations->whereNotIn('rental_status', \App\Models\Reservation::TERMINAL_STATUSES)->sortByDesc('reservation_id')->first()
                        : null;
                    $tenantName = $activeRes?->tenant ? trim($activeRes->tenant->first_name . ' ' . $activeRes->tenant->last_name) : null;
                    $unitPayload = [
                        'label'        => $unit->unit_label,
                        'property'     => $unit->property->title,
                        'status'       => $unit->availability_status,
                        'styles'       => $modalStyles,
                        'photo'        => $thumb?->media_url,
                        'rent'         => (float) $unit->rental_fee,
                        'deposit'      => $unit->security_deposit !== null ? (float) $unit->security_deposit : null,
                        'capacity'     => $unit->occupancy_limit,
                        'floorArea'    => $unit->floor_area_label,
                        'floor'        => $unit->floor,
                        'furnishing'   => $unit->furnishing_status,
                        // Only rows that have a value: the modal renders exactly this list, so no row is ever hidden.
                        'facts'        => array_merge($tenantName ? [['Tenant', $tenantName]] : [], $unit->specRows()),
                        'description'  => $unit->description,
                        'photos'       => $unit->media->where('media_type', 'Image')->pluck('media_url')->take(5)->values(),
                        'tenant'       => $tenantName,
                        'amenities'    => $unit->amenitiesBeyondSpecs()->map(fn ($a) => [
                            'name' => $a->amenity_name,
                            'icon' => \App\Support\AmenityIcons::path($a->amenity_name),
                        ])->values(),
                        'property_url' => route('landlord.properties.show', $unit->property),
                        'edit_url'     => route('landlord.properties.units.edit', [$unit->property, $unit]),
                    ];

                    $derived[$unit->unit_id] = compact('thumb', 'avBg', 'vrBg', 'activeRes', 'tenantName', 'unitPayload');
                }
            @endphp

            <div {!! $scopedProperty ? '' : 'x-show="view === \'grid\'"' !!}
                class="grid grid-cols-1 {{ $scopedProperty ? 'lg:hidden' : 'xl:grid-cols-2 2xl:grid-cols-3' }} gap-4">
                @foreach($units as $unit)
                    @php extract($derived[$unit->unit_id]); @endphp

                    {{-- Horizontal card: photo left, details right --}}
                    <article
                        class="group flex flex-row rounded-2xl overflow-hidden bg-white ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)] hover:shadow-[0_8px_28px_rgba(6,13,38,0.1)] hover:-translate-y-0.5 transition-all duration-300">

                        {{-- Photo --}}
                        <div class="relative w-32 sm:w-44 min-h-[148px] self-stretch overflow-hidden bg-[#ECEEF6] shrink-0">
                            {{-- Placeholder sits underneath, so a photo that fails to load falls back to it --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"
                                    class="text-[#5B6A8E]/60">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                </svg>
                                <span class="text-[11px] text-[#5B6A8E]/70">No photo</span>
                            </div>
                            @if($thumb)
                                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" onerror="this.remove()"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500 ease-out">
                            @endif

                            {{-- Status chip --}}
                            {{-- Solid white base under each chip: the tints are translucent and vanish on busy photos --}}
                            <span class="absolute top-2 left-2 rounded-full bg-white">
                                <span class="inline-flex items-center gap-1 text-[10.5px] font-semibold px-2 py-0.5 rounded-full ring-1 {{ $avBg }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $unit->availability_status }}
                                </span>
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="flex flex-col flex-1 min-w-0 p-3.5 gap-2.5">
                            <div class="min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-[15px] font-bold text-[#060D26] leading-snug truncate">{{ $unit->unit_label }}</p>
                                    <span class="shrink-0 inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $vrBg }}">
                                        {{ $unit->verification_status }}
                                    </span>
                                </div>
                                @unless($scopedProperty)
                                <a href="{{ route('landlord.properties.show', $unit->property) }}"
                                    class="text-[12px] text-[#5B6A8E] hover:text-[#B35A3D] transition-colors duration-200 mt-0.5 line-clamp-1 flex items-center gap-1 w-fit">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    {{ $unit->property->title }}
                                </a>
                                @endunless
                            </div>

                            <p class="text-[13px] text-[#5B6A8E] tabular-nums">
                                <span class="text-[16px] font-semibold text-[#060D26]">₱{{ number_format($unit->rental_fee, 0) }}</span>
                                /month
                                <span class="mx-1.5 text-[#5B6A8E]/50">·</span>
                                Fits {{ $unit->occupancy_limit }}
                            </p>

                            {{-- Actions --}}
                            <div class="flex items-center gap-1.5 mt-auto">
                                <button type="button" x-on:click="openModal(@js($unitPayload))"
                                    class="flex-1 h-9 flex items-center justify-center gap-1.5 rounded-full border border-[#5B6A8E]/30 text-[#060D26] text-[12px] font-semibold hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    View
                                </button>
                                <a href="{{ route('landlord.properties.units.edit', [$unit->property, $unit]) }}"
                                    class="flex-1 h-9 flex items-center justify-center gap-1.5 rounded-full text-[#5B6A8E] text-[12px] font-semibold hover:bg-[#ECEEF6] hover:text-[#B35A3D] transition-colors duration-200">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z" />
                                    </svg>
                                    Edit
                                </a>
                                <form method="POST"
                                    action="{{ route('landlord.properties.units.destroy', [$unit->property, $unit]) }}"
                                    data-confirm="Remove {{ $unit->unit_label }}?"
                                    data-confirm-type="error"
                                    data-confirm-message="The unit will be permanently removed. This cannot be undone."
                                    data-confirm-button="Remove unit">
                                    @csrf @method('DELETE')
                                    <button type="submit" aria-label="Delete unit" title="Delete unit"
                                        class="h-9 w-9 flex items-center justify-center rounded-full text-[#5B6A8E] hover:text-[#DC2626] hover:bg-[#EF4444]/[0.07] transition-colors duration-200 cursor-pointer">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Table view --}}
            <div @if($scopedProperty) class="hidden lg:block" @else x-show="view === 'table'" x-cloak @endif>
            <div class="space-y-8">
            {{-- One table per property. Scoped to a single property the heading is redundant (it's the page title). --}}
            @foreach($units->groupBy('property_id') as $groupUnits)
            @php
                $groupProperty = $groupUnits->first()->property;
            @endphp
            <section>
            <x-card flush class="!rounded-none">
                {{-- Property label: a soft tinted bar on top of its table --}}
                @unless($scopedProperty)
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-0.5 px-5 py-2.5 bg-[#ECEEF6] border-b border-[#E2E4EC] border-l-4 border-l-[#FF8A66]">
                        <div class="min-w-0 flex items-baseline gap-2">
                            <h2 class="text-[13.5px] font-bold text-[#060D26] truncate">{{ $groupProperty->title }}</h2>
                            <span class="text-[11.5px] text-[#5B6A8E] whitespace-nowrap">{{ $groupUnits->count() }} {{ Str::plural('unit', $groupUnits->count()) }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-[12px] font-semibold">
                            <a href="{{ route('landlord.properties.show', $groupProperty) }}" class="text-[#B35A3D] hover:underline">View property</a>
                            <a href="{{ route('landlord.properties.units.create', $groupProperty) }}" class="text-[#B35A3D] hover:underline">+ Add unit</a>
                        </div>
                    </div>
                @endunless
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E4EC]">
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Monthly Rent</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Last Updated</th>
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($groupUnits as $unit)
                                @php extract($derived[$unit->unit_id]); @endphp
                                <tr class="hover:bg-[#F7F8FC]/70 transition-colors duration-200 cursor-pointer"
                                    x-on:click="if (! $event.target.closest('a, button, form, input') && ! window.getSelection().toString()) $el.querySelector('[data-view-unit]').click()">
                                    {{-- Unit --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0">
                                                @if($thumb)
                                                    <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-[#5B6A8E]/60">
                                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-bold text-[#060D26] truncate">{{ $unit->unit_label }}</p>
                                                @unless($scopedProperty)
                                                <p class="text-[11.5px] text-[#5B6A8E] truncate max-w-[180px]">{{ $unit->property->title }}</p>
                                                @endunless
                                                <p class="text-[11px] text-[#5B6A8E]">
                                                    {{ collect([$unit->floor, $unit->occupancy_limit ? $unit->occupancy_limit . ' ' . Str::plural('person', $unit->occupancy_limit) : null])->filter()->implode(' · ') ?: '—' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Rent --}}
                                    <td class="px-4 py-3.5">
                                        <p class="text-[13px] font-bold text-[#060D26] whitespace-nowrap">₱{{ number_format($unit->rental_fee, 0) }}</p>
                                        <p class="text-[11px] text-[#5B6A8E]">per month</p>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full ring-1 text-[11px] font-semibold whitespace-nowrap {{ $avBg }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ $unit->availability_status }}
                                        </span>
                                    </td>

                                    {{-- Tenant --}}
                                    <td class="px-4 py-3.5">
                                        @if($tenantName)
                                            <div class="flex items-center gap-2">
                                                @if($activeRes->tenant->profile_picture)
                                                    <img loading="lazy" decoding="async" src="{{ $activeRes->tenant->profile_picture }}" alt="{{ $tenantName }}"
                                                        class="w-7 h-7 rounded-full object-cover shrink-0">
                                                @else
                                                    <div class="w-7 h-7 rounded-full bg-[#ECEEF6] text-[#060D26] text-[10px] font-bold flex items-center justify-center shrink-0">
                                                        {{ strtoupper(substr($activeRes->tenant->first_name, 0, 1) . substr($activeRes->tenant->last_name ?? '', 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div class="min-w-0">
                                                    <p class="text-[12.5px] font-semibold text-[#060D26] truncate max-w-[140px]">{{ $tenantName }}</p>
                                                    <p class="text-[11px] text-[#5B6A8E]">{{ $activeRes->tenant->contact_number ?? '—' }}</p>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-[12px] text-[#5B6A8E]">—</span>
                                        @endif
                                    </td>

                                    {{-- Last updated --}}
                                    <td class="px-4 py-3.5">
                                        <p class="text-[13px] text-[#060D26] font-medium whitespace-nowrap">{{ $unit->updated_at->format('M d, Y') }}</p>
                                        <p class="text-[11px] text-[#5B6A8E]">{{ $unit->updated_at->format('h:i A') }}</p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" x-on:click="openModal(@js($unitPayload))" aria-label="View unit" title="View unit" data-view-unit
                                                class="h-8 w-8 flex items-center justify-center rounded-lg border border-[#5B6A8E]/25 text-[#060D26] hover:border-[#FF8A66] hover:text-[#B35A3D] hover:bg-[#ECEEF6] cursor-pointer transition-colors duration-200">
                                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </button>
                                            <a href="{{ route('landlord.properties.units.edit', [$unit->property, $unit]) }}" aria-label="Edit unit" title="Edit unit"
                                                class="h-8 w-8 flex items-center justify-center rounded-lg border border-[#5B6A8E]/25 text-[#060D26] hover:border-[#FF8A66] hover:text-[#B35A3D] hover:bg-[#ECEEF6] transition-colors duration-200">
                                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931z" />
                                                </svg>
                                            </a>
                                            <form method="POST" class="ml-1.5"
                                                action="{{ route('landlord.properties.units.destroy', [$unit->property, $unit]) }}"
                                                data-confirm="Remove {{ $unit->unit_label }}?"
                                                data-confirm-type="error"
                                                data-confirm-message="The unit will be permanently removed. This cannot be undone."
                                                data-confirm-button="Remove unit">
                                                @csrf @method('DELETE')
                                                <button type="submit" aria-label="Delete unit" title="Delete unit"
                                                    class="h-8 w-8 flex items-center justify-center rounded-lg border border-[#EF4444]/25 text-[#DC2626] hover:bg-[#EF4444]/[0.07] cursor-pointer transition-colors duration-200">
                                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            </section>
            @endforeach
            </div>
            </div>

            <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-[12.5px] text-[#5B6A8E]">
                    Showing <span class="font-semibold text-[#060D26]">{{ $units->firstItem() }}–{{ $units->lastItem() }}</span> of
                    <span class="font-semibold text-[#060D26]">{{ $units->total() }}</span> units
                </p>
                {{ $units->links() }}
            </div>
        @endif

        {{-- Unit detail modal (Live Preview style). Teleported to <body> so ancestor
             backdrop-blur/transform styles can never trap the fixed overlay. --}}
        <template x-teleport="body">
            <div x-show="modal" x-cloak>
            <template x-if="modal">
            <div class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm motion-reduce:transition-none" x-on:click="closeModal()"
                    x-show="show"
                    x-transition:enter="transition-opacity ease-out duration-250"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"></div>
                <div class="relative w-full max-w-md md:max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto md:grid md:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_4px_24px_rgba(0,0,0,0.12)] motion-reduce:transition-none"
                    role="dialog" aria-modal="true" aria-labelledby="unit-modal-title"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 scale-95">

                    <button type="button" x-on:click="closeModal()" aria-label="Close"
                        class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 hover:bg-white border border-[#E2E4EC] flex items-center justify-center text-[#060D26] transition-colors duration-200 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>

                    {{-- Photo panel: fills the full height of the details beside it on desktop. --}}
                    <div class="relative aspect-[16/10] md:aspect-auto md:min-h-[380px] bg-[#ECEEF6] border-b md:border-b-0 md:border-r border-[#E2E4EC]">
                        <template x-if="modal.photo">
                            <img :src="modal.photo" :alt="modal.label" class="absolute inset-0 w-full h-full object-cover">
                        </template>
                        <template x-if="!modal.photo">
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-[#5B6A8E]">
                                <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-[12px] mt-1.5">No photos on this unit</p>
                            </div>
                        </template>

                        {{-- Thumbnails: only when the unit has more than one photo. --}}
                        <div x-show="modal.photos && modal.photos.length > 1" x-cloak class="absolute bottom-3 left-3 right-3 flex gap-2 overflow-x-auto">
                            <template x-for="(p, i) in (modal.photos || [])" :key="p">
                                <button type="button" x-on:click="modal.photo = p" :aria-label="'Show photo ' + (i + 1)"
                                    :class="modal.photo === p ? 'ring-2 ring-[#FF8A66]' : 'ring-1 ring-white/70 opacity-90 hover:opacity-100'"
                                    class="h-11 w-14 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6] shadow-sm transition-opacity duration-200 cursor-pointer">
                                    <img :src="p" alt="" class="h-full w-full object-cover">
                                </button>
                            </template>
                        </div>
                    </div>
                    {{-- Body --}}
                    <div class="p-5 sm:p-6 space-y-5">
                        <div class="flex items-start justify-between gap-3 md:pr-10">
                            <div class="min-w-0">
                                <h2 id="unit-modal-title" class="text-[18px] font-semibold leading-tight text-[#060D26] truncate" x-text="modal.label"></h2>
                                <p class="mt-1 text-[13px] text-[#5B6A8E] line-clamp-2" x-text="[modal.property, modal.floor].filter(Boolean).join(' · ')"></p>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[12px] font-semibold"
                                :class="modal.styles.tile + ' ' + modal.styles.text">
                                <span class="w-1.5 h-1.5 rounded-full" :class="modal.styles.dot"></span>
                                <span x-text="modal.status"></span>
                            </span>
                        </div>

                        <p class="flex items-baseline gap-1.5">
                            <span class="text-[26px] font-semibold leading-none tabular-nums text-[#060D26]" x-text="peso(modal.rent) || '₱—'"></span>
                            <span class="text-[13px] text-[#5B6A8E]">/ month</span>
                        </p>

                        <p x-show="modal.description" x-cloak class="text-[13px] leading-relaxed text-[#5B6A8E] line-clamp-3" x-text="modal.description"></p>

                        {{-- One list instead of a tile grid: any number of facts lines up, so a missing floor
                             area or furnishing can't strand an orphan tile. --}}
                        <dl class="rounded-xl border border-[#E2E4EC] text-[13px] [&>div~div]:border-t [&>div~div]:border-[#E2E4EC]">
                            <template x-for="row in modal.facts" :key="row[0]">
                                <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                                    <dt class="text-[#5B6A8E]" x-text="row[0]"></dt>
                                    <dd class="font-semibold text-[#060D26] text-right tabular-nums" x-text="row[1]"></dd>
                                </div>
                            </template>
                        </dl>

                        <div x-show="modal.amenities && modal.amenities.length">
                            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Unit amenities</h3>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="a in modal.amenities" :key="a.name">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E2E4EC] bg-[#ECEEF6] px-2.5 py-1 text-[12px] font-medium text-[#060D26]">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
                                        </svg>
                                        <span x-text="a.name"></span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-1">
                            <a :href="modal.property_url"
                                class="flex-1 h-11 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#060D26] text-[13px] font-semibold hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200">
                                View property
                            </a>
                            <a :href="modal.edit_url"
                                class="flex-1 h-11 inline-flex items-center justify-center rounded-full bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                                Edit unit
                            </a>
                        </div>
                    </div>

                </div>
            </div>
            </template>
            </div>
        </template>

    </div>
@endsection
