@extends('layouts.admin')

@section('page-title', 'Units')

@section('content')
@php
    $availabilityBadge = [
        'Available'   => 'bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25',
        'Reserved'    => 'bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35',
        'Occupied'    => 'bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25',
        'Maintenance' => 'bg-[#EEF8F8] text-[#156F8C] ring-[#2AA7A1]/25',
    ];
    $verificationBadge = [
        'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
        'Pending'  => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
        'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
    ];
    $healthOptions = [
        ''                  => 'All health',
        'long-vacant'       => 'Vacant 60+ days',
        'stuck-maintenance' => 'Stuck in maintenance',
        'no-media'          => 'No photos',
    ];
    $verificationOptions = ['' => 'All verification', 'Approved' => 'Approved', 'Pending' => 'Pending', 'Rejected' => 'Rejected'];
    $sortOptions = [
        'newest'         => 'Newest first',
        'oldest'         => 'Oldest first',
        'fee_asc'        => 'Lowest rent',
        'fee_desc'       => 'Highest rent',
        'vacant_longest' => 'Longest vacant',
    ];
    $availability = $filters['availability_status'] ?? '';
@endphp

<div class="max-w-[1600px] mx-auto"
    x-data="{
        view: localStorage.getItem('adminUnitsView') || 'grid',
        setView(v) { this.view = v; localStorage.setItem('adminUnitsView', v); }
    }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Units</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Full inventory of every unit on the platform, across all statuses.</p>
            </div>
        </div>
        <a href="{{ route('admin.catalogue.units.export', request()->query()) }}"
            class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full border border-[#E2E8F0] bg-white hover:bg-[#F7FCFC] text-[#1F2937] text-sm font-semibold transition-all duration-200 shrink-0">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export CSV
        </a>
    </div>

    {{-- Occupancy stat cards --}}
    @php
        $availPct = $counts['total'] > 0 ? round($counts['available'] / $counts['total'] * 100) : 0;
        $reservedPct = $counts['total'] > 0 ? round($counts['reserved'] / $counts['total'] * 100) : 0;
        $occupiedPct = $counts['total'] > 0 ? round($counts['occupied'] / $counts['total'] * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Units</span>
                <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z" />
                    </svg>
                </div>
            </div>
            <span class="text-2xl font-extrabold text-[#1F2937]">{{ number_format($counts['total']) }}</span>
            <p class="text-[11px] text-[#64748B] mt-1">{{ $counts['occupancy_rate'] }}% occupied</p>
        </div>

        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Available</span>
                <div class="w-8 h-8 rounded-lg bg-[#22C55E]/[0.07] flex items-center justify-center shrink-0">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </div>
            </div>
            <span class="text-2xl font-extrabold text-[#15803D]">{{ number_format($counts['available']) }}</span>
            <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                <div class="h-full rounded-full bg-[#22C55E] transition-all duration-300" style="width: {{ $availPct }}%"></div>
            </div>
            <p class="text-[11px] text-[#64748B] mt-1.5">{{ $availPct }}% of total units</p>
        </div>

        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reserved</span>
                <div class="w-8 h-8 rounded-lg bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </div>
            </div>
            <span class="text-2xl font-extrabold text-[#B45309]">{{ number_format($counts['reserved']) }}</span>
            <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                <div class="h-full rounded-full bg-[#FBBF24] transition-all duration-300" style="width: {{ $reservedPct }}%"></div>
            </div>
            <p class="text-[11px] text-[#64748B] mt-1.5">{{ $reservedPct }}% of total units</p>
        </div>

        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                <div class="w-8 h-8 rounded-lg bg-[#EF4444]/[0.07] flex items-center justify-center shrink-0">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
            </div>
            <span class="text-2xl font-extrabold text-[#DC2626]">{{ number_format($counts['occupied']) }}</span>
            <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                <div class="h-full rounded-full bg-[#EF4444] transition-all duration-300" style="width: {{ $occupiedPct }}%"></div>
            </div>
            <p class="text-[11px] text-[#64748B] mt-1.5">{{ $occupiedPct }}% occupancy rate</p>
        </div>
    </div>

    {{-- Search + filters + view toggle --}}
    <form method="GET" action="{{ route('admin.catalogue.units.index') }}"
        class="bg-white rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
        <input type="hidden" name="availability_status" value="{{ $availability }}">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                    placeholder="Search by unit, property, or landlord…" aria-label="Search units"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="verification_status" :options="$verificationOptions" :selected="$filters['verification_status'] ?? ''"
                    class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] max-w-[180px]" />

                <x-styled-select name="health" :options="$healthOptions" :selected="$filters['health'] ?? ''"
                    class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] max-w-[200px]" />

                <x-styled-select name="sort" :options="$sortOptions" :selected="$filters['sort'] ?? 'newest'"
                    class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937]" />

                <button type="submit"
                    class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    Filter
                </button>

                @if($search || ($filters['verification_status'] ?? '') || ($filters['health'] ?? '') || (($filters['sort'] ?? 'newest') !== 'newest'))
                    <a href="{{ route('admin.catalogue.units.index', ['availability_status' => $availability]) }}"
                        class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Clear
                    </a>
                @endif

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-11 p-1 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC]">
                    <button type="button" x-on:click="setView('grid')" aria-label="Grid view"
                        :class="view === 'grid' ? 'bg-white text-[#156F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'"
                        class="h-9 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </button>
                    <button type="button" x-on:click="setView('table')" aria-label="Table view"
                        :class="view === 'table' ? 'bg-white text-[#156F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'"
                        class="h-9 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Availability tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-6 overflow-x-auto">
        <a href="{{ route('admin.catalogue.units.index', array_filter(['search' => $search])) }}"
            class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                {{ $availability === '' ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
            All
            <span class="ml-1 text-[11px] {{ $availability === '' ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts['total'] }}</span>
        </a>
        @foreach(['Available' => $counts['available'], 'Reserved' => $counts['reserved'], 'Occupied' => $counts['occupied'], 'Maintenance' => $counts['maintenance']] as $key => $count)
            <a href="{{ route('admin.catalogue.units.index', array_filter(['availability_status' => $key, 'search' => $search])) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ $availability === $key ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                {{ $key }}
                <span class="ml-1 text-[11px] {{ $availability === $key ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    {{-- Empty state --}}
    @if($units->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#64748B]/30 bg-white flex flex-col items-center justify-center py-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-[#1F2937]">No units found</p>
            <p class="text-xs text-[#64748B] mt-1">{{ $search || array_filter($filters) ? 'Try adjusting your search or filters.' : 'No units have been created yet.' }}</p>
        </div>
    @else
        @php
            $derived = [];
            foreach ($units as $unit) {
                $thumb = $unit->media->first();
                $tenantName = $unit->activeReservation?->tenant
                    ? trim($unit->activeReservation->tenant->first_name . ' ' . $unit->activeReservation->tenant->last_name)
                    : null;
                $derived[$unit->unit_id] = compact('thumb', 'tenantName');
            }
        @endphp

        {{-- Grid view --}}
        <div x-show="view === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($units as $unit)
                @php extract($derived[$unit->unit_id]); @endphp
                <article class="group flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:shadow-[0_8px_28px_rgba(15,23,42,0.1)] hover:-translate-y-0.5 transition-all duration-300">
                    <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}"
                        class="relative block aspect-[16/10] overflow-hidden bg-[#EEF8F8] shrink-0">
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500 ease-out">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/60">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                </svg>
                                <span class="text-[11px] text-[#64748B]/70">No photo</span>
                            </div>
                        @endif
                        <span class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $availabilityBadge[$unit->availability_status] ?? 'bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20' }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            {{ $unit->availability_status }}
                        </span>
                        <span class="absolute top-3 right-3 inline-flex items-center text-[10.5px] font-semibold px-2 py-1 rounded-full {{ $verificationBadge[$unit->verification_status] ?? 'bg-[#EEF8F8] text-[#64748B]' }}">
                            {{ $unit->verification_status }}
                        </span>
                    </a>

                    <div class="flex flex-col flex-1 p-4 gap-3">
                        <div>
                            <p class="text-[15px] font-bold text-[#1F2937] leading-snug">{{ $unit->unit_label }}</p>
                            <p class="text-[12px] text-[#64748B] mt-0.5 line-clamp-1">{{ $unit->property->title ?? '—' }}</p>
                            @if($unit->rejection_reason)
                                <p class="text-[11px] text-[#DC2626] mt-0.5 line-clamp-1" title="{{ $unit->rejection_reason }}">{{ $unit->rejection_reason }}</p>
                            @endif
                        </div>

                        <div class="flex items-center justify-between rounded-xl bg-[#EEF8F8]/60 px-3.5 py-2.5">
                            <div>
                                <p class="text-[15px] font-bold text-[#1F2937]">₱{{ number_format($unit->rental_fee, 0) }}</p>
                                <p class="text-[10px] text-[#64748B]">per month</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[13px] font-semibold text-[#1F2937]">{{ $tenantName ?? '—' }}</p>
                                <p class="text-[10px] text-[#64748B]">tenant</p>
                            </div>
                        </div>

                        <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}"
                            class="mt-auto h-9 flex items-center justify-center gap-1.5 rounded-full border border-[#64748B]/30 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                            View Details
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Table view --}}
        <div x-show="view === 'table'" x-cloak class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead>
                        <tr class="border-b border-[#E2E8F0]">
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Unit</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Property / Landlord</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rent</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Tenant</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Availability</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Verification</th>
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach($units as $unit)
                            @php extract($derived[$unit->unit_id]); @endphp
                            <tr class="hover:bg-[#F7FCFC]/70 transition-colors duration-200">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($thumb)
                                                <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[#64748B]/60">
                                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-bold text-[#1F2937] truncate">{{ $unit->unit_label }}</p>
                                            @if($unit->rejection_reason)
                                                <p class="text-[11px] text-[#DC2626] truncate max-w-[180px]" title="{{ $unit->rejection_reason }}">{{ $unit->rejection_reason }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937] truncate max-w-[180px]">{{ $unit->property->title ?? '—' }}</p>
                                    <p class="text-[11px] text-[#64748B] truncate max-w-[180px]">
                                        {{ trim(($unit->property->landlord->first_name ?? '') . ' ' . ($unit->property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-bold text-[#1F2937] whitespace-nowrap">₱{{ number_format($unit->rental_fee, 0) }}</p>
                                    <p class="text-[11px] text-[#64748B]">per month</p>
                                </td>
                                <td class="px-4 py-3.5 text-[12.5px] text-[#1F2937]">{{ $tenantName ?? '—' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full ring-1 text-[11px] font-semibold whitespace-nowrap {{ $availabilityBadge[$unit->availability_status] ?? 'bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20' }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        {{ $unit->availability_status }}
                                    </span>
                                    @if($unit->availability_status === 'Available' && $unit->vacated_at && $unit->vacated_at->lt(now()->subDays(60)))
                                        <p class="mt-1 text-[11px] text-[#B45309]">Vacant {{ $unit->vacated_at->diffForHumans() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap {{ $verificationBadge[$unit->verification_status] ?? 'bg-[#EEF8F8] text-[#64748B]' }}">
                                        {{ $unit->verification_status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}"
                                        class="h-8 px-3 inline-flex items-center rounded-lg border border-[#64748B]/25 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200 whitespace-nowrap">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-[12.5px] text-[#64748B]">
                Showing <span class="font-semibold text-[#1F2937]">{{ $units->firstItem() }}–{{ $units->lastItem() }}</span> of
                <span class="font-semibold text-[#1F2937]">{{ $units->total() }}</span> units
            </p>
            {{ $units->links() }}
        </div>
    @endif

</div>
@endsection
