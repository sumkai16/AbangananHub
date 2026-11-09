@extends('layouts.admin')

@section('page-title', 'Units')

@section('content')
@php
    $healthOptions = [
        ''                  => 'All health',
        'long-vacant'       => 'Vacant 60+ days',
        'stuck-maintenance' => 'Stuck in maintenance',
        'no-media'          => 'No photos',
    ];
    $verificationOptions = ['' => 'All verification', 'Approved' => 'Approved', 'Pending' => 'Pending', 'Rejected' => 'Rejected'];
    $sortOptions = [
        'newest'         => 'Pending first, then newest',
        'oldest'         => 'Oldest first',
        'fee_asc'        => 'Lowest rent',
        'fee_desc'       => 'Highest rent',
        'vacant_longest' => 'Longest vacant',
    ];
    $availability = $filters['availability_status'] ?? '';
@endphp

<div class="max-w-[1600px] mx-auto px-0"
    x-data="{
        view: localStorage.getItem('adminUnitsView') || 'table',
        setView(v) { this.view = v; localStorage.setItem('adminUnitsView', v); },
        rejectTarget: { action: '', label: '' },
        openReject(action, label) { this.rejectTarget = { action, label }; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'reject-unit' })); },
    }">

    <x-page-header title="Units" icon-path="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" :subtitle="'Every unit, in every status. '.$counts['occupancy_rate'].'% occupied.'">
        <x-slot:actions>
            <a href="{{ route('admin.catalogue.units.export', request()->query()) }}" title="Download the units shown here as a spreadsheet (CSV)"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-[#E2E4EC] bg-white px-5 text-sm font-semibold text-[#060D26] transition-all duration-200 hover:bg-[#F7F8FC] cursor-pointer">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Export CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Status summary. Each status cell filters the list (click again to clear); Total clears any filter. --}}
    @php
        $pct = fn (int $n) => $counts['total'] > 0 ? round($n / $counts['total'] * 100) : 0;
        $cellHref = fn (?string $status) => route('admin.catalogue.units.index', array_filter(array_merge(request()->except('page'), [
            'availability_status' => $status && $availability !== $status ? $status : null,
        ])));
        $summary = [['label' => 'Total units', 'value' => $counts['total'], 'note' => 'Across all properties', 'href' => $cellHref(null)]];
        foreach ([['Available', 'available', '#22C55E', '% of units'], ['Reserved', 'reserved', '#FBBF24', '% of units'], ['Occupied', 'occupied', '#EF4444', '% occupancy'], ['Maintenance', 'maintenance', '#94A3B8', '% of units']] as [$label, $key, $dot, $suffix]) {
            if ($key === 'maintenance' && $counts[$key] === 0 && $availability !== $label) {
                continue;
            }
            $summary[] = ['label' => $label, 'value' => $counts[$key], 'note' => $pct($counts[$key]).$suffix, 'dot' => $dot, 'href' => $cellHref($label), 'active' => $availability === $label];
        }
    @endphp
    <x-stat-strip :cells="$summary" class="mb-4" />

    {{-- The approval queue lives here now (it was its own page, Unit Approvals). Highlighted while something waits. --}}
    @php $needs = ($filters['needs'] ?? '') === 'approval'; @endphp
    @if($counts['needs_approval'] > 0 || $needs)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border {{ $counts['needs_approval'] > 0 ? 'border-[#F4D58A] bg-[#FFFBEF]' : 'border-[#E2E4EC] bg-white' }} px-4 py-3">
            <p class="text-[13.5px] text-[#060D26]">
                @if($counts['needs_approval'] > 0)
                    <span class="font-semibold tabular-nums">{{ number_format($counts['needs_approval']) }} {{ Str::plural('unit', $counts['needs_approval']) }}</span> {{ $counts['needs_approval'] === 1 ? 'needs' : 'need' }} your decision.
                    <span class="text-[#5B6A8E]">{{ $needs ? 'Oldest first.' : 'Units a landlord added or changed after their property was approved.' }}</span>
                @else
                    Nothing needs your decision right now.
                @endif
            </p>
            <a href="{{ route('admin.catalogue.units.index', $needs ? array_filter(request()->except(['page', 'needs'])) : array_filter(array_merge(request()->except('page'), ['needs' => 'approval']))) }}"
                class="inline-flex h-9 items-center rounded-lg {{ $needs ? 'border border-[#E2E4EC] bg-white' : 'bg-[#FF8A66] hover:bg-[#E96F4F]' }} px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200">{{ $needs ? 'Show all units' : 'Show them' }}</a>
        </div>
    @endif

    {{-- Search + filters + view toggle --}}
    <form data-live method="GET" action="{{ route('admin.catalogue.units.index') }}" class="mb-6">
        <input type="hidden" name="availability_status" value="{{ $availability }}">
        @if($needs)<input type="hidden" name="needs" value="approval">@endif
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="relative min-w-[200px] flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by unit, property, or landlord..." aria-label="Search units"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] pl-10 pr-4 text-[16px] text-[#060D26] placeholder-[#94A3B8] transition-all focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 sm:text-[13.5px]">
            </div>
            <div class="filter-row flex flex-wrap items-center gap-2.5">
                <x-styled-select name="verification_status" :options="$verificationOptions" :selected="$filters['verification_status'] ?? ''" :autosubmit="true"
                    class="h-11 max-w-[180px] rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] pl-4 pr-9 text-[13.5px] text-[#060D26]" />
                <x-styled-select name="health" :options="$healthOptions" :selected="$filters['health'] ?? ''" :autosubmit="true"
                    class="h-11 max-w-[200px] rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] pl-4 pr-9 text-[13.5px] text-[#060D26]" />
                <x-styled-select name="sort" :options="$sortOptions" :selected="$filters['sort'] ?? 'newest'" :autosubmit="true"
                    class="h-11 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] pl-4 pr-9 text-[13.5px] text-[#060D26]" />
                <button type="submit" class="inline-flex h-11 items-center gap-1.5 rounded-xl bg-[#FF8A66] px-5 text-[13.5px] font-semibold text-[#060D26] transition-all duration-200 hover:bg-[#E96F4F] cursor-pointer">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filter
                </button>
                @if($search || $needs || ($filters['verification_status'] ?? '') || ($filters['health'] ?? '') || (($filters['sort'] ?? 'newest') !== 'newest') || $availability)
                    <a href="{{ route('admin.catalogue.units.index') }}" class="inline-flex h-11 items-center gap-1.5 rounded-xl border border-[#5B6A8E]/25 px-4 text-[13.5px] text-[#5B6A8E] transition-colors duration-200 hover:bg-[#ECEEF6] hover:text-[#060D26]">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </a>
                @endif
                <div class="ml-auto flex h-11 items-center gap-0.5 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] p-1">
                    <button type="button" x-on:click="setView('grid')" aria-label="Grid view" :class="view === 'grid' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'" class="flex h-9 w-9 items-center justify-center rounded-lg transition-all duration-200 cursor-pointer">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" /></svg>
                    </button>
                    <button type="button" x-on:click="setView('table')" aria-label="Table view" :class="view === 'table' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'" class="flex h-9 w-9 items-center justify-center rounded-lg transition-all duration-200 cursor-pointer">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if($units->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#5B6A8E]/30 bg-white py-16 text-center">
            <p class="text-sm font-semibold text-[#060D26]">{{ $needs ? 'No units need a decision' : 'No units found' }}</p>
            <p class="mt-1 text-xs text-[#5B6A8E]">{{ $needs ? 'Units a landlord adds or changes after approval show here.' : 'Try adjusting your search or filters.' }}</p>
        </div>
    @else
        @php
            // Derived per-unit data shared by the card grid and the table
            $derived = [];
            foreach ($units as $unit) {
                $thumb = $unit->media->firstWhere('media_type', 'Image');
                $avBg = match ($unit->availability_status) {
                    'Available' => 'bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25',
                    'Reserved' => 'bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35',
                    'Occupied' => 'bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25',
                    default => 'bg-[#ECEEF6] text-[#5B6A8E] ring-[#5B6A8E]/20',
                };
                $tenant = $unit->activeReservation?->tenant;
                $tenantName = $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : null;
                $href = route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]);
                // The popup is the landlord's; its two buttons point at the admin pages, and the admin also sees verification.
                $payload = $unit->detailPayload($unit->property, 'units');
                $payload['property_url'] = route('admin.catalogue.properties.show', $unit->property_id);
                $payload['edit_url'] = $href;
                $payload['verification'] = $unit->verification_status;
                if ($tenantName && ! collect($payload['facts'])->contains(fn ($row) => $row[0] === 'Tenant')) {
                    array_unshift($payload['facts'], ['Tenant', $tenantName]);
                }
                // Needs its own decision: Pending, on an approved property.
                $actionable = $unit->verification_status === 'Pending' && ($unit->property->verification_status ?? null) === 'Approved';
                $decisionLabel = $unit->unit_label.' in '.($unit->property->title ?? 'this property');
                $derived[$unit->unit_id] = compact('thumb', 'avBg', 'tenant', 'tenantName', 'href', 'payload', 'actionable', 'decisionLabel');
            }
        @endphp

        {{-- Card grid: photo left, details right. The whole card opens the unit popup. --}}
        <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 gap-3 sm:gap-5 xl:grid-cols-2 2xl:grid-cols-3">
            @foreach($units as $unit)
                @php extract($derived[$unit->unit_id]); @endphp
                <div role="button" tabindex="0" aria-label="View {{ $unit->unit_label }}"
                    x-on:click="$dispatch('open-unit-detail', @js($payload))"
                    x-on:keydown.enter.prevent="$dispatch('open-unit-detail', @js($payload))"
                    x-on:keydown.space.prevent="$dispatch('open-unit-detail', @js($payload))"
                    class="group flex cursor-pointer flex-row overflow-hidden rounded-2xl bg-white shadow-[0_2px_12px_rgba(6,13,38,0.05)] ring-1 ring-[#5B6A8E]/10 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_8px_28px_rgba(6,13,38,0.1)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                    <div class="relative min-h-[104px] w-24 shrink-0 self-stretch overflow-hidden bg-[#ECEEF6] sm:min-h-[132px] sm:w-44">
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/60" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" /></svg>
                            <span class="text-[11px] text-[#5B6A8E]/70">No photo</span>
                        </div>
                        @if($thumb)
                            <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]">
                        @endif
                        <span class="absolute left-2 top-2 rounded-full bg-white">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold ring-1 {{ $avBg }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $unit->availability_status }}</span>
                        </span>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 p-3.5 sm:gap-3 sm:p-4">
                        <div class="min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-[15px] font-bold leading-snug text-[#060D26]">{{ $unit->unit_label }}</p>
                                <x-admin.status :status="$unit->verification_status" class="shrink-0" />
                            </div>
                            <p class="mt-0.5 line-clamp-1 text-[12px] text-[#5B6A8E]">{{ $unit->property->title ?? '—' }}</p>
                            @if($unit->rejection_reason)
                                <p class="mt-0.5 line-clamp-1 text-[11.5px] text-[#DC2626]" title="{{ $unit->rejection_reason }}">{{ $unit->rejection_reason }}</p>
                            @endif
                        </div>
                        <p class="mt-auto text-[13px] tabular-nums text-[#5B6A8E]">
                            <span class="text-[16px] font-semibold text-[#060D26]">₱{{ number_format($unit->rental_fee, 0) }}</span> /month
                            <span class="mx-1.5 text-[#5B6A8E]/50">·</span>Fits {{ $unit->occupancy_limit }}
                            @if($tenantName)<span class="mt-0.5 block truncate text-[12px]">Tenant: {{ $tenantName }}</span>@endif
                        </p>
                        @if($actionable)
                            <div class="flex items-center gap-1.5" x-on:click.stop x-on:keydown.stop>
                                <form method="POST" action="{{ route('admin.units.approve', [$unit->property_id, $unit->unit_id]) }}"
                                    data-confirm="Approve this unit?" data-confirm-message="It can be booked by tenants and the landlord is told. Check the photos first." data-confirm-button="Approve unit">
                                    @csrf
                                    <button type="submit" class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-[#15803D] px-2.5 text-[12.5px] font-semibold text-white transition-all duration-200 hover:brightness-95">Approve</button>
                                </form>
                                <button type="button" x-on:click="openReject(@js(route('admin.units.reject', [$unit->property_id, $unit->unit_id])), @js($decisionLabel))"
                                    class="inline-flex h-8 cursor-pointer items-center rounded-lg border border-[#EF4444]/30 px-2.5 text-[12.5px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06]">Reject</button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Table: one per property, under a tinted label bar --}}
        <div x-show="view === 'table'" x-cloak class="space-y-8">
            @foreach($units->groupBy('property_id') as $groupUnits)
                @php $groupProperty = $groupUnits->first()->property; @endphp
                <section>
                    <x-card flush class="!rounded-none">
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-0.5 border-b border-l-4 border-[#E2E4EC] border-l-[#FF8A66] bg-[#ECEEF6] px-5 py-2.5">
                            <div class="flex min-w-0 items-baseline gap-2">
                                <h2 class="truncate text-[13.5px] font-bold text-[#060D26]">{{ $groupProperty->title ?? '—' }}</h2>
                                <span class="whitespace-nowrap text-[11.5px] text-[#5B6A8E]">{{ $groupUnits->count() }} {{ Str::plural('unit', $groupUnits->count()) }}@if($groupProperty?->landlord) · {{ trim($groupProperty->landlord->first_name.' '.$groupProperty->landlord->last_name) }}@endif</span>
                            </div>
                            @if($groupProperty)
                                <a href="{{ route('admin.catalogue.properties.show', $groupProperty) }}" class="text-[12px] font-semibold text-[#B35A3D] hover:underline">View property</a>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                            {{-- Fixed layout and shared widths: every property has its own table, and automatic widths made the
                                 columns drift from one table to the next. --}}
                            <table class="w-full min-w-[1060px] text-left" style="table-layout: fixed">
                                <colgroup>
                                    <col style="width: 24%"><col style="width: 10%"><col style="width: 11%"><col style="width: 12%">
                                    <col style="width: 14%"><col style="width: 10%"><col style="width: 19%">
                                </colgroup>
                                <thead>
                                    <tr class="border-b border-[#E2E4EC]">
                                        <th class="px-5 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Unit</th>
                                        <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Monthly rent</th>
                                        <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Status</th>
                                        <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Verification</th>
                                        <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Tenant</th>
                                        <th class="px-4 py-3.5 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Last updated</th>
                                        <th class="px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($groupUnits as $unit)
                                        @php extract($derived[$unit->unit_id]); @endphp
                                        <tr class="cursor-pointer transition-colors duration-200 hover:bg-[#F7F8FC]/70"
                                            x-on:click="if (! $event.target.closest('a, button, form, input') && ! window.getSelection().toString()) $el.querySelector('[data-view-unit]').click()">
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6]">
                                                        @if($thumb)
                                                            <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="" class="h-full w-full object-cover">
                                                        @else
                                                            <div class="flex h-full w-full items-center justify-center text-[#5B6A8E]/60"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" /></svg></div>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-[13px] font-bold text-[#060D26]">{{ $unit->unit_label }}</p>
                                                        <p class="max-w-[180px] truncate text-[11.5px] text-[#5B6A8E]">{{ $unit->property->title ?? '—' }}</p>
                                                        <p class="text-[11px] text-[#5B6A8E]">{{ collect([$unit->floor, $unit->occupancy_limit ? $unit->occupancy_limit.' '.Str::plural('person', $unit->occupancy_limit) : null])->filter()->implode(' · ') ?: '—' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5"><p class="whitespace-nowrap text-[13px] font-bold tabular-nums text-[#060D26]">₱{{ number_format($unit->rental_fee, 0) }}</p><p class="text-[11px] text-[#5B6A8E]">per month</p></td>
                                            <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $avBg }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $unit->availability_status }}</span></td>
                                            <td class="px-4 py-3.5"><x-admin.status :status="$unit->verification_status" /></td>
                                            <td class="px-4 py-3.5">
                                                @if($tenantName)
                                                    <div class="flex items-center gap-2">
                                                        @if($tenant->profile_picture)
                                                            <img loading="lazy" decoding="async" src="{{ $tenant->profile_picture }}" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">
                                                        @else
                                                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#ECEEF6] text-[10px] font-bold text-[#060D26]">{{ strtoupper(substr($tenant->first_name, 0, 1).substr($tenant->last_name ?? '', 0, 1)) }}</div>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <p class="max-w-[140px] truncate text-[12.5px] font-semibold text-[#060D26]">{{ $tenantName }}</p>
                                                            <p class="text-[11px] text-[#5B6A8E]">{{ $tenant->contact_number ?? '—' }}</p>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-[12px] text-[#5B6A8E]">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5"><p class="whitespace-nowrap text-[13px] font-medium text-[#060D26]">{{ $unit->updated_at->format('M d, Y') }}</p><p class="text-[11px] text-[#5B6A8E]">{{ $unit->updated_at->format('h:i A') }}</p></td>
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    @if($actionable)
                                                        <form method="POST" action="{{ route('admin.units.approve', [$unit->property_id, $unit->unit_id]) }}"
                                                            data-confirm="Approve this unit?" data-confirm-message="It can be booked by tenants and the landlord is told. Check the photos first." data-confirm-button="Approve unit">
                                                            @csrf
                                                            <button type="submit" class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-[#15803D] px-2.5 text-[12.5px] font-semibold text-white transition-all duration-200 hover:brightness-95">Approve</button>
                                                        </form>
                                                        <button type="button" x-on:click="openReject(@js(route('admin.units.reject', [$unit->property_id, $unit->unit_id])), @js($decisionLabel))"
                                                            class="inline-flex h-8 cursor-pointer items-center rounded-lg border border-[#EF4444]/30 px-2.5 text-[12.5px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06]">Reject</button>
                                                    @endif
                                                    <button type="button" x-on:click="$dispatch('open-unit-detail', @js($payload))" data-view-unit aria-label="View unit" title="View unit"
                                                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-[#5B6A8E]/25 text-[#060D26] transition-colors duration-200 hover:border-[#FF8A66] hover:bg-[#ECEEF6] hover:text-[#B35A3D]">
                                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    </button>
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

        <div class="mt-8 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            @if($units->hasPages())
                <div class="w-full">{{ $units->links() }}</div>
            @else
                <p class="text-[12.5px] text-[#5B6A8E]">Showing <span class="font-semibold text-[#060D26]">{{ $units->firstItem() }}–{{ $units->lastItem() }}</span> of <span class="font-semibold text-[#060D26]">{{ $units->total() }}</span> units</p>
            @endif
        </div>
    @endif

    {{-- One reason dialog for every Reject button; the landlord sees the reason. --}}
    <x-modal name="reject-unit" focusable>
        <form method="POST" x-bind:action="rejectTarget.action" class="p-6">
            @csrf
            <h2 class="text-[15px] font-bold text-[#060D26]">Reject unit</h2>
            <p class="mt-1 text-[13px] text-[#5B6A8E]"><span x-text="rejectTarget.label"></span> will be rejected and the landlord notified with your reason below.</p>
            <label for="reject_unit_reason" class="mb-1.5 mt-4 block text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Reason for rejection</label>
            <textarea name="rejection_reason" id="reject_unit_reason" rows="4" required maxlength="500"
                class="w-full resize-none rounded-lg border border-[#E2E4EC] px-3 py-2.5 text-[13px] text-[#060D26] transition-all focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20"
                placeholder="Explain what needs to change. The landlord will see this and can resubmit."></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" x-on:click="show = false" class="h-10 rounded-lg border border-[#E2E4EC] px-4 text-[13px] font-semibold text-[#5B6A8E] transition-colors hover:text-[#060D26]">Cancel</button>
                <button type="submit" class="h-10 rounded-lg bg-[#EF4444] px-4 text-[13px] font-bold text-white transition-all hover:brightness-95">Confirm rejection</button>
            </div>
        </form>
    </x-modal>

    {{-- One popup for every card and row; each dispatches `open-unit-detail`. Same as the landlord's Units page. --}}
    @include('landlord.units.partials._detail-modal', ['primaryLabel' => 'Review unit'])
</div>
@endsection
