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
        view: localStorage.getItem('adminUnitsView') || 'table',
        setView(v) { this.view = v; localStorage.setItem('adminUnitsView', v); }
    }">

    {{-- Header --}}
    <x-page-header title="Units" :subtitle="'Every unit, in every status. '.$counts['occupancy_rate'].'% occupied.'">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <a href="{{ route('admin.catalogue.units.export', request()->query()) }}"
                class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 shrink-0">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export CSV
            </a>
        </x-slot:actions>
    </x-page-header>


    {{-- Search + filters + view toggle --}}
    <form method="GET" action="{{ route('admin.catalogue.units.index') }}" class="mb-4">
        <input type="hidden" name="availability_status" value="{{ $availability }}">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                    placeholder="Search by unit, property, or landlord…" aria-label="Search units"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="verification_status" :options="$verificationOptions" :selected="$filters['verification_status'] ?? ''"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[180px]" />

                <x-styled-select name="health" :options="$healthOptions" :selected="$filters['health'] ?? ''"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[200px]" />

                <x-styled-select name="sort" :options="$sortOptions" :selected="$filters['sort'] ?? 'newest'"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />

                <button type="submit" class="h-10 rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Filter</button>

                @if($search || ($filters['verification_status'] ?? '') || ($filters['health'] ?? '') || (($filters['sort'] ?? 'newest') !== 'newest'))
                    <a href="{{ route('admin.catalogue.units.index', ['availability_status' => $availability]) }}"
                        class="h-10 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Clear
                    </a>
                @endif

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-10 p-1 rounded-xl border border-[#5B6A8E]/25 bg-white">
                    <button type="button" x-on:click="setView('grid')" aria-label="Grid view"
                        :class="view === 'grid' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-9 w-9 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </button>
                    <button type="button" x-on:click="setView('table')" aria-label="Table view"
                        :class="view === 'table' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
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
    <x-admin.tabs class="mb-4" :tabs="collect(['' => ['All', $counts['total']], 'Available' => ['Available', $counts['available']], 'Reserved' => ['Reserved', $counts['reserved']], 'Occupied' => ['Occupied', $counts['occupied']], 'Maintenance' => ['Maintenance', $counts['maintenance']]])->map(fn ($tab, $key) => [
        'label' => $tab[0],
        'count' => $tab[1],
        'href' => route('admin.catalogue.units.index', array_filter(['availability_status' => $key, 'search' => $search])),
        'active' => $availability === (string) $key,
    ])->values()->all()" />

    {{-- Empty state --}}
    @if($units->isEmpty())
        <x-card flush><x-admin.empty title="No units found" :note="$search || array_filter($filters) ? 'Try a different search or filter.' : null" /></x-card>
    @else
        @php
            $derived = [];
            foreach ($units as $unit) {
                $thumb = $unit->media->firstWhere('media_type', 'Image');
                $tenantName = $unit->activeReservation?->tenant
                    ? trim($unit->activeReservation->tenant->first_name . ' ' . $unit->activeReservation->tenant->last_name)
                    : null;
                $derived[$unit->unit_id] = compact('thumb', 'tenantName');
            }
        @endphp

        {{-- Grid view --}}
        <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($units as $unit)
                @php extract($derived[$unit->unit_id]); @endphp
                <article class="flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#5B6A8E]/15">
                    <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}"
                        class="relative block aspect-[16/10] overflow-hidden bg-[#ECEEF6] shrink-0">
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" loading="lazy"
                                class="w-full h-full object-cover transition-transform duration-500 ease-out">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/60">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                </svg>
                                <span class="text-[11px] text-[#5B6A8E]/70">No photo</span>
                            </div>
                        @endif
                        <div class="absolute top-3 left-3 flex gap-1.5">
                            <x-admin.status :status="$unit->availability_status" kind="unit" />
                            <x-admin.status :status="$unit->verification_status" />
                        </div>
                    </a>

                    <div class="flex flex-col flex-1 p-4 gap-3">
                        <div>
                            <p class="text-[15px] font-semibold text-[#060D26] leading-snug">{{ $unit->unit_label }}</p>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5 line-clamp-1">{{ $unit->property->title ?? '—' }}</p>
                            @if($unit->rejection_reason)
                                <p class="text-[11px] text-[#DC2626] mt-0.5 line-clamp-1" title="{{ $unit->rejection_reason }}">{{ $unit->rejection_reason }}</p>
                            @endif
                        </div>

                        <div class="flex items-baseline justify-between gap-3 text-[13px]">
                            <p class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($unit->rental_fee, 2) }} <span class="font-normal text-[#5B6A8E]">/ month</span></p>
                            <p class="truncate text-[#5B6A8E]">{{ $tenantName ?? 'No tenant' }}</p>
                        </div>

                        <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}"
                            class="mt-auto h-9 flex items-center justify-center gap-1.5 rounded-lg border border-[#E2E4EC] text-[#060D26] text-[12.5px] font-semibold hover:bg-[#ECEEF6] transition-colors duration-200">
                            View Details
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Table view --}}
        <x-card flush x-show="view === 'table'" x-cloak>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Unit</x-admin.th>
                            <x-admin.th>Property / Landlord</x-admin.th>
                            <x-admin.th align="right">Rent</x-admin.th>
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th>Availability</x-admin.th>
                            <x-admin.th>Verification</x-admin.th>
                            <x-admin.th align="right">Actions</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($units as $unit)
                            @php extract($derived[$unit->unit_id]); @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0">
                                            @if($thumb)
                                                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[#5B6A8E]/60">
                                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-bold text-[#060D26] truncate">{{ $unit->unit_label }}</p>
                                            @if($unit->rejection_reason)
                                                <p class="text-[11px] text-[#DC2626] truncate max-w-[180px]" title="{{ $unit->rejection_reason }}">{{ $unit->rejection_reason }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[12.5px] font-semibold text-[#060D26] truncate max-w-[180px]">{{ $unit->property->title ?? '—' }}</p>
                                    <p class="text-[11px] text-[#5B6A8E] truncate max-w-[180px]">
                                        {{ trim(($unit->property->landlord->first_name ?? '') . ' ' . ($unit->property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right text-[13.5px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">&#8369;{{ number_format($unit->rental_fee, 2) }}</td>
                                <td class="px-4 py-3 text-[12.5px] text-[#060D26]">{{ $tenantName ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$unit->availability_status" kind="unit" />
                                    @if($unit->availability_status === 'Available' && $unit->vacated_at && $unit->vacated_at->lt(now()->subDays(60)))
                                        <p class="mt-1 text-[11px] text-[#B45309]">Vacant {{ $unit->vacated_at->diffForHumans() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$unit->verification_status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.units.show', ['property' => $unit->property_id, 'unit' => $unit->unit_id]) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Pagination --}}
        <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-[12.5px] text-[#5B6A8E]">
                Showing <span class="font-semibold text-[#060D26]">{{ $units->firstItem() }}–{{ $units->lastItem() }}</span> of
                <span class="font-semibold text-[#060D26]">{{ $units->total() }}</span> units
            </p>
            {{ $units->links() }}
        </div>
    @endif

</div>
@endsection
