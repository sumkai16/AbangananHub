@extends('layouts.admin')

@section('page-title', 'Properties')

@section('content')
@php
    $healthOptions = [
        ''           => 'All health',
        'no-media'   => 'No photos',
        'no-geocode' => 'Never pinned on map',
        'no-units'   => 'No units',
        'stale'      => 'Listed 90+ days ago',
    ];
    $typeOptions = \App\Enums\PropertyType::options('All Types');
    $sortOptions = [
        'newest'     => 'Newest first',
        'oldest'     => 'Oldest first',
        'fee_asc'    => 'Lowest rent',
        'fee_desc'   => 'Highest rent',
        'most_units' => 'Most units',
    ];
    $status = $filters['verification_status'] ?? '';
@endphp

<div class="max-w-[1600px] mx-auto"
    x-data="{
        view: localStorage.getItem('adminPropertiesView') || 'table',
        setView(v) { this.view = v; localStorage.setItem('adminPropertiesView', v); }
    }">

    {{-- Header --}}
    <x-page-header title="Properties" subtitle="Every listing, in every status.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <a href="{{ route('admin.catalogue.properties.export', request()->query()) }}"
                class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 shrink-0">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Status summary, like the Units page: each cell filters the list (click the active one again to clear). It replaces the
         status tabs, which showed the same numbers. --}}
    @php
        $health = $filters['health'] ?? '';
        $pctOf = fn (int $n) => $counts['total'] > 0 ? round($n / $counts['total'] * 100) : 0;
        $cellHref = fn (array $params) => route('admin.catalogue.properties.index', array_filter(array_merge(['search' => $search], $params)));
        $needsAttention = $status === '' && $health === 'no-media';
        $summary = [
            ['label' => 'Total properties', 'value' => $counts['total'], 'note' => number_format($counts['units']).' '.Str::plural('unit', $counts['units']).' in all', 'href' => $cellHref([]), 'active' => $status === '' && ! $needsAttention],
            ['label' => 'Approved', 'value' => $counts['approved'], 'note' => $pctOf($counts['approved']).'% of properties', 'dot' => '#22C55E', 'href' => $status === 'Approved' ? $cellHref([]) : $cellHref(['verification_status' => 'Approved']), 'active' => $status === 'Approved'],
            ['label' => 'Pending', 'value' => $counts['pending'], 'note' => $counts['pending'] > 0 ? 'Waiting for a decision' : 'Nothing waiting', 'dot' => '#FBBF24', 'href' => $status === 'Pending' ? $cellHref([]) : $cellHref(['verification_status' => 'Pending']), 'active' => $status === 'Pending'],
            ['label' => 'Rejected', 'value' => $counts['rejected'], 'note' => $pctOf($counts['rejected']).'% of properties', 'dot' => '#EF4444', 'href' => $status === 'Rejected' ? $cellHref([]) : $cellHref(['verification_status' => 'Rejected']), 'active' => $status === 'Rejected'],
            ['label' => 'Needs attention', 'value' => $counts['needs_attention'], 'note' => 'No photos, pin or units, or 90+ days old', 'dot' => '#94A3B8', 'href' => $needsAttention ? $cellHref([]) : $cellHref(['health' => 'no-media']), 'active' => $needsAttention],
        ];
    @endphp
    <x-stat-strip :cells="$summary" class="mb-6" />


    {{-- Search + filters + view toggle --}}
    <form method="GET" action="{{ route('admin.catalogue.properties.index') }}" class="mb-4">
        <input type="hidden" name="verification_status" value="{{ $status }}">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                    placeholder="Search by title, address, or landlord…" aria-label="Search properties"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="property_type" :options="$typeOptions" :selected="$filters['property_type'] ?? ''"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />

                <x-styled-select name="health" :options="$healthOptions" :selected="$filters['health'] ?? ''"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[200px]" />

                <x-styled-select name="sort" :options="$sortOptions" :selected="$filters['sort'] ?? 'newest'"
                    class="h-10 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />

                <button type="submit" class="h-10 rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Filter</button>

                @if($search || ($filters['property_type'] ?? '') || ($filters['health'] ?? '') || (($filters['sort'] ?? 'newest') !== 'newest'))
                    <a href="{{ route('admin.catalogue.properties.index', ['verification_status' => $status]) }}"
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

    {{-- Empty state --}}
    @if($properties->isEmpty())
        <x-card flush><x-admin.empty title="No properties found" :note="$search || array_filter($filters) ? 'Try a different search or filter.' : null" /></x-card>
    @else
        @php
            $derived = [];
            foreach ($properties as $property) {
                $thumb = $property->media->firstWhere('media_type', 'Image');
                $derived[$property->property_id] = compact('thumb');
            }
        @endphp

        {{-- Grid view --}}
        <div x-show="view === 'grid'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-3">
            @foreach($properties as $property)
                @php extract($derived[$property->property_id]); @endphp
                {{-- The whole card is the link; a separate View Details button said the same thing. --}}
                <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="group flex flex-col rounded-xl overflow-hidden bg-white ring-1 ring-[#5B6A8E]/15 transition-all duration-200 hover:ring-[#5B6A8E]/30 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                    <div class="relative aspect-[4/3] overflow-hidden bg-[#ECEEF6] shrink-0">
                        @if($thumb)
                            <img src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center gap-1">
                                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#5B6A8E]/60" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                                </svg>
                                <span class="text-[11px] text-[#5B6A8E]/70">No photos yet</span>
                            </div>
                        @endif
                        <span class="absolute top-2 left-2 inline-flex rounded-md bg-white shadow-sm"><x-admin.status :status="$property->verification_status" /></span>
                    </div>

                    <div class="flex flex-col flex-1 gap-0.5 px-3 py-2.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="min-w-0 truncate text-[13.5px] font-semibold text-[#060D26] leading-snug group-hover:text-[#B35A3D] transition-colors duration-200">{{ $property->title }}</p>
                            <span class="hidden sm:inline shrink-0 text-[11.5px] text-[#5B6A8E]">{{ $property->property_type }}</span>
                        </div>
                        <p class="truncate text-[12px] text-[#5B6A8E]">{{ $property->address }}</p>
                        <p class="truncate text-[12px] text-[#5B6A8E]">{{ trim(($property->landlord->first_name ?? '') . ' ' . ($property->landlord->last_name ?? '')) ?: '—' }}</p>
                        <p class="mt-1 text-[12px] tabular-nums text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">{{ $property->units_count }} {{ Str::plural('unit', $property->units_count) }}</span> · {{ $property->available_units_count }} free · {{ $property->reserved_units_count }} held · {{ $property->occupied_units_count }} taken</p>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Table view --}}
        <x-card flush x-show="view === 'table'" x-cloak>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Property</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th>Type</x-admin.th>
                            <x-admin.th align="right">Units</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th align="right">Actions</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($properties as $property)
                            @php extract($derived[$property->property_id]); @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0">
                                            @if($thumb)
                                                <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="{{ $property->title }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[#5B6A8E]/60">
                                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                                                class="block text-[13px] font-bold text-[#060D26] truncate max-w-[240px] hover:text-[#B35A3D] transition-colors duration-200">
                                                {{ $property->title }}
                                            </a>
                                            <p class="text-[11.5px] text-[#5B6A8E] truncate max-w-[240px]">{{ $property->address }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[12.5px] font-semibold text-[#060D26] truncate max-w-[160px]">
                                        {{ trim(($property->landlord->first_name ?? '') . ' ' . ($property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[11px] text-[#5B6A8E] truncate max-w-[160px]">{{ $property->landlord->email ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-[13px] whitespace-nowrap text-[#060D26]">{{ $property->property_type }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="text-[13.5px] font-semibold tabular-nums text-[#060D26]">{{ $property->units_count }}</p>
                                    <p class="text-[12px] tabular-nums whitespace-nowrap text-[#5B6A8E]">{{ $property->available_units_count }} free · {{ $property->reserved_units_count }} held · {{ $property->occupied_units_count }} taken</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$property->verification_status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">View</a>
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
                Showing <span class="font-semibold text-[#060D26]">{{ $properties->firstItem() }}–{{ $properties->lastItem() }}</span> of
                <span class="font-semibold text-[#060D26]">{{ $properties->total() }}</span> properties
            </p>
            {{ $properties->links() }}
        </div>
    @endif

</div>
@endsection
