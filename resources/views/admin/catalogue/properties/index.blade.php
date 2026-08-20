@extends('layouts.admin')

@section('page-title', 'Properties')

@section('content')
@php
    $verificationBadge = [
        'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D] ring-[#22C55E]/25',
        'Pending'  => 'bg-[#FBBF24]/[0.10] text-[#B45309] ring-[#FBBF24]/35',
        'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626] ring-[#EF4444]/25',
    ];
    $healthOptions = [
        ''           => 'All health',
        'no-media'   => 'No photos',
        'no-geocode' => 'Never pinned on map',
        'no-units'   => 'No units',
        'stale'      => 'Listed 90+ days ago',
    ];
    $typeOptions = ['' => 'All Types', 'Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House'];
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
        view: localStorage.getItem('adminPropertiesView') || 'grid',
        setView(v) { this.view = v; localStorage.setItem('adminPropertiesView', v); }
    }">

    {{-- Header --}}
    <x-page-header title="Properties" subtitle="Full catalogue of every listing on the platform, across all statuses.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <a href="{{ route('admin.catalogue.properties.export', request()->query()) }}"
                class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full border border-[#E2E8F0] bg-white hover:bg-[#F7FCFC] text-[#1F2937] text-sm font-semibold transition-all duration-200 shrink-0">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-stat-card label="Total" :value="number_format($counts['total'])" sub="All properties">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Approved" :value="number_format($counts['approved'])" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)" sub="Live to tenants">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Pending" :value="number_format($counts['pending'])" value-color="#B45309" icon-bg="rgba(251,191,36,0.10)" sub="Awaiting review">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Needs Attention" :value="number_format($counts['needs_attention'])" value-color="#DC2626" icon-bg="rgba(239,68,68,0.07)"
            sub="Missing photos, pin, or units"
            :href="route('admin.catalogue.properties.index', array_merge(request()->query(), ['health' => 'no-media']))">
            <x-slot:icon>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    {{-- Search + filters + view toggle --}}
    <form method="GET" action="{{ route('admin.catalogue.properties.index') }}"
        class="bg-white rounded-2xl p-4 mb-5 shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
        <input type="hidden" name="verification_status" value="{{ $status }}">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" value="{{ $search }}"
                    placeholder="Search by title, address, or landlord…" aria-label="Search properties"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <x-styled-select name="property_type" :options="$typeOptions" :selected="$filters['property_type'] ?? ''"
                    class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937]" />

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

                @if($search || ($filters['property_type'] ?? '') || ($filters['health'] ?? '') || (($filters['sort'] ?? 'newest') !== 'newest'))
                    <a href="{{ route('admin.catalogue.properties.index', ['verification_status' => $status]) }}"
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

    {{-- Status tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-6 overflow-x-auto">
        <a href="{{ route('admin.catalogue.properties.index', array_filter(['search' => $search])) }}"
            class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                {{ $status === '' ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
            All
            <span class="ml-1 text-[11px] {{ $status === '' ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts['total'] }}</span>
        </a>
        @foreach(['Approved' => $counts['approved'], 'Pending' => $counts['pending'], 'Rejected' => $counts['rejected']] as $key => $count)
            <a href="{{ route('admin.catalogue.properties.index', array_filter(['verification_status' => $key, 'search' => $search])) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ $status === $key ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                {{ $key }}
                <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    {{-- Empty state --}}
    @if($properties->isEmpty())
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-[#64748B]">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                </svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1F2937]">No properties found</p>
            <p class="text-sm text-[#64748B] mt-1 max-w-xs">{{ $search || array_filter($filters) ? 'Try adjusting your search or filters.' : 'No properties have been listed yet.' }}</p>
        </div>
    @else
        @php
            $derived = [];
            foreach ($properties as $property) {
                $thumb = $property->media->firstWhere('media_type', 'Image');
                $derived[$property->property_id] = compact('thumb');
            }
        @endphp

        {{-- Grid view --}}
        <div x-show="view === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($properties as $property)
                @php extract($derived[$property->property_id]); @endphp
                <article class="group flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/15 hover:ring-[#64748B]/30 hover:shadow-lg transition-all duration-300">
                    <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="relative block aspect-[16/10] overflow-hidden bg-[#EEF8F8] shrink-0">
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="{{ $property->title }}" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500 ease-out">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" class="text-[#64748B]/60">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                                </svg>
                                <span class="text-xs text-[#64748B]/70">No photos yet</span>
                            </div>
                        @endif
                        <span class="absolute top-3 left-3 inline-flex items-center text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $verificationBadge[$property->verification_status] ?? 'bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20' }}">
                            {{ $property->verification_status }}
                        </span>
                    </a>

                    <div class="flex flex-col flex-1 p-4 gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                                    class="block text-[15px] font-bold text-[#1F2937] leading-snug line-clamp-1 hover:text-[#2AA7A1] transition-colors duration-200">
                                    {{ $property->title }}
                                </a>
                                <p class="text-[12px] text-[#64748B] mt-0.5 line-clamp-1">{{ $property->address }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] font-medium text-[#2AA7A1] border border-[#2AA7A1]/40 rounded-full px-2.5 py-0.5 mt-0.5">
                                {{ $property->property_type }}
                            </span>
                        </div>

                        <p class="text-[12px] text-[#64748B]">
                            {{ trim(($property->landlord->first_name ?? '') . ' ' . ($property->landlord->last_name ?? '')) ?: '—' }}
                        </p>

                        <div class="grid grid-cols-4 divide-x divide-[#64748B]/10 rounded-xl bg-[#EEF8F8]/60 py-2.5">
                            <div class="flex flex-col items-center gap-0.5">
                                <span class="text-[14px] font-bold text-[#1F2937]">{{ $property->units_count }}</span>
                                <span class="text-[10px] text-[#64748B] font-medium">Total</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <span class="text-[14px] font-bold text-[#15803D]">{{ $property->available_units_count }}</span>
                                <span class="text-[10px] text-[#64748B] font-medium">Available</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <span class="text-[14px] font-bold text-[#B45309]">{{ $property->reserved_units_count }}</span>
                                <span class="text-[10px] text-[#64748B] font-medium">Reserved</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <span class="text-[14px] font-bold text-[#EF4444]">{{ $property->occupied_units_count }}</span>
                                <span class="text-[10px] text-[#64748B] font-medium">Occupied</span>
                            </div>
                        </div>

                        <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                            class="mt-auto h-9 flex items-center justify-center gap-1.5 rounded-full border border-[#64748B]/30 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                            View Details
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Table view --}}
        <x-card flush x-show="view === 'table'" x-cloak>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left">
                    <thead>
                        <tr class="border-b border-[#E2E8F0]">
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Property</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Landlord</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Type</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Units</th>
                            <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Status</th>
                            <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach($properties as $property)
                            @php extract($derived[$property->property_id]); @endphp
                            <tr class="hover:bg-[#F7FCFC]/70 transition-colors duration-200">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($thumb)
                                                <img src="{{ $thumb->media_url }}" alt="{{ $property->title }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[#64748B]/60">
                                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                                                class="block text-[13px] font-bold text-[#1F2937] truncate max-w-[240px] hover:text-[#2AA7A1] transition-colors duration-200">
                                                {{ $property->title }}
                                            </a>
                                            <p class="text-[11.5px] text-[#64748B] truncate max-w-[240px]">{{ $property->address }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937] truncate max-w-[160px]">
                                        {{ trim(($property->landlord->first_name ?? '') . ' ' . ($property->landlord->last_name ?? '')) ?: '—' }}
                                    </p>
                                    <p class="text-[11px] text-[#64748B] truncate max-w-[160px]">{{ $property->landlord->email ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2.5 py-1 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[11px] font-semibold whitespace-nowrap">
                                        {{ $property->property_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3 text-[12px] whitespace-nowrap">
                                        <span class="font-bold text-[#1F2937]">{{ $property->units_count }} total</span>
                                        <span class="text-[#15803D] font-semibold">{{ $property->available_units_count }} avail</span>
                                        <span class="text-[#B45309] font-semibold">{{ $property->reserved_units_count }} rsvd</span>
                                        <span class="text-[#EF4444] font-semibold">{{ $property->occupied_units_count }} occ</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2.5 py-1 rounded-full ring-1 text-[11px] font-semibold whitespace-nowrap {{ $verificationBadge[$property->verification_status] ?? 'bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20' }}">
                                        {{ $property->verification_status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('admin.catalogue.properties.show', $property) }}"
                                        class="h-8 px-3 inline-flex items-center rounded-lg border border-[#64748B]/25 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200 whitespace-nowrap">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Pagination --}}
        <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-[12.5px] text-[#64748B]">
                Showing <span class="font-semibold text-[#1F2937]">{{ $properties->firstItem() }}–{{ $properties->lastItem() }}</span> of
                <span class="font-semibold text-[#1F2937]">{{ $properties->total() }}</span> properties
            </p>
            {{ $properties->links() }}
        </div>
    @endif

</div>
@endsection
