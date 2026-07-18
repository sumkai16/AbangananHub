@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-1.5 text-[12.5px] text-[#64748B] mb-3">
            <a href="{{ route('landlord.properties.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Properties</a>
            <svg width="11" height="11" class="text-[#64748B]/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C] font-semibold">Units</span>
        </div>

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Units</h1>
                    <p class="text-sm text-[#64748B] mt-0.5">Manage all units and their availability across your properties.</p>
                </div>
            </div>
            @if(request('property'))
                <a href="{{ route('landlord.properties.units.create', request('property')) }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-full bg-[#2AA7A1] hover:brightness-95 text-white text-sm font-semibold shadow-sm transition-all duration-200 shrink-0">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Unit
                </a>
            @endif
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-medium flex items-center gap-2">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    class="shrink-0 text-[#2AA7A1]">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm font-medium">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Stat cards --}}
        @php
            $availPct = $stats['total'] > 0 ? round($stats['available'] / $stats['total'] * 100) : 0;
            $reservedPct = $stats['total'] > 0 ? round($stats['reserved'] / $stats['total'] * 100) : 0;
            $occupiedPct = $stats['total'] > 0 ? round($stats['occupied'] / $stats['total'] * 100) : 0;
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Units</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF2F5] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $stats['total'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Across all properties</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Available</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-emerald-600">{{ $stats['available'] }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width: {{ $availPct }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $availPct }}% of total units</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reserved</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-amber-500">{{ $stats['reserved'] }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-amber-500 transition-all duration-300" style="width: {{ $reservedPct }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $reservedPct }}% of total units</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-red-500">{{ $stats['occupied'] }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-red-500 transition-all duration-300" style="width: {{ $occupiedPct }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $occupiedPct }}% occupancy rate</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="15" height="15" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search units by name or property..."
                        class="pl-10 pr-4 h-11 w-full rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative">
                        <select name="property" onchange="this.form.submit()"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer max-w-[180px]">
                            <option value="">All Properties</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->property_id }}" @selected(request('property') == $property->property_id)>
                                    {{ $property->title }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <div class="relative">
                        <select name="status" onchange="this.form.submit()"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                            <option value="">All Status</option>
                            <option value="Available" @selected(request('status') === 'Available')>Available</option>
                            <option value="Reserved" @selected(request('status') === 'Reserved')>Reserved</option>
                            <option value="Occupied" @selected(request('status') === 'Occupied')>Occupied</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['search', 'status', 'property']))
                        <a href="{{ route('landlord.units.index') }}"
                            class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>

            @if(request()->hasAny(['search', 'status', 'property']))
                <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-[#64748B]/10 text-[12.5px] text-[#64748B]">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span class="font-semibold text-[#1F2937]">{{ $units->total() }}</span>
                    {{ Str::plural('unit', $units->total()) }} match{{ $units->total() === 1 ? 'es' : '' }} your filters
                </div>
            @endif
        </form>

        {{-- Empty state --}}
        @if($units->isEmpty())
            <div
                class="rounded-2xl border border-dashed border-[#64748B]/30 bg-white/70 backdrop-blur-xl flex flex-col items-center justify-center py-16 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#1F2937]">No units found</p>
                <p class="text-xs text-[#64748B] mt-1">Try adjusting your search or filters.</p>
            </div>

            {{-- Card grid --}}
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($units as $unit)
                    @php
                        $thumb = $unit->media->first() ?? $unit->property->media->first() ?? null;
                        [$avBg] = match ($unit->availability_status) {
                            'Available' => ['bg-emerald-50 text-emerald-700 ring-emerald-200'],
                            'Reserved' => ['bg-amber-50 text-amber-600 ring-amber-200'],
                            'Occupied' => ['bg-red-50 text-red-600 ring-red-200'],
                            default => ['bg-[#EEF8F8] text-[#64748B] ring-[#64748B]/20'],
                        };
                        [$vrBg] = match ($unit->verification_status) {
                            'Approved' => ['bg-emerald-50 text-emerald-700'],
                            'Pending' => ['bg-amber-50 text-amber-600'],
                            'Rejected' => ['bg-red-50 text-red-600'],
                            default => ['bg-[#EEF8F8] text-[#64748B]'],
                        };
                    @endphp

                    <article
                        class="group flex flex-col rounded-2xl overflow-hidden bg-white ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:shadow-[0_8px_28px_rgba(15,23,42,0.1)] hover:-translate-y-0.5 transition-all duration-300">

                        {{-- Photo --}}
                        <div class="relative aspect-[16/10] overflow-hidden bg-[#EEF8F8] shrink-0">
                            @if($thumb)
                                <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}"
                                    class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-500 ease-out">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center gap-2">
                                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"
                                        class="text-[#64748B]/60">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159" />
                                    </svg>
                                    <span class="text-[11px] text-[#64748B]/70">No photo</span>
                                </div>
                            @endif

                            {{-- Status chip --}}
                            <span
                                class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ $avBg }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ $unit->availability_status }}
                            </span>

                            {{-- Verification chip --}}
                            <span
                                class="absolute top-3 right-3 inline-flex items-center text-[10.5px] font-semibold px-2 py-1 rounded-full {{ $vrBg }}">
                                {{ $unit->verification_status }}
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="flex flex-col flex-1 p-4 gap-3">
                            <div>
                                <p class="text-[15px] font-bold text-[#1F2937] leading-snug">{{ $unit->unit_label }}</p>
                                <a href="{{ route('landlord.properties.show', $unit->property) }}"
                                    class="text-[12px] text-[#64748B] hover:text-[#2AA7A1] transition-colors duration-200 mt-0.5 line-clamp-1 flex items-center gap-1 w-fit">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    {{ $unit->property->title }}
                                </a>
                            </div>

                            <div class="flex items-center justify-between rounded-xl bg-[#EEF8F8]/60 px-3.5 py-2.5">
                                <div>
                                    <p class="text-[15px] font-bold text-[#1F2937]">₱{{ number_format($unit->rental_fee, 0) }}</p>
                                    <p class="text-[10px] text-[#64748B]">per month</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[13px] font-semibold text-[#1F2937]">{{ $unit->occupancy_limit }}</p>
                                    <p class="text-[10px] text-[#64748B]">{{ Str::plural('person', $unit->occupancy_limit) }}</p>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 pt-1 mt-auto">
                                <a href="{{ route('landlord.properties.units.edit', [$unit->property, $unit]) }}"
                                    class="flex-1 h-9 flex items-center justify-center gap-1.5 rounded-full border border-[#2AA7A1] text-[#2AA7A1] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
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
                                    <button type="submit"
                                        class="h-9 w-9 flex items-center justify-center rounded-full border border-red-200 text-red-500 hover:bg-red-50 transition-colors duration-200">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

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
