@extends('layouts.landlord')

@section('page-title', 'Dashboard')

@section('content')
    @php
        $business = auth()->user()->rentalBusiness;
        $greetingName = $business->business_name ?? auth()->user()->first_name;

        // Donut math — guard divide-by-zero when the landlord has no units yet
        $occupancyTotal = max($totalUnits, 1);
        $occupiedPct = $totalUnits > 0 ? round(($occupiedUnits / $occupancyTotal) * 100) : 0;

        $radius = 36;
        $circumference = 2 * M_PI * $radius;
        $occupiedLen = $circumference * ($occupiedUnits / $occupancyTotal);
        $availableLen = $circumference * ($availableUnits / $occupancyTotal);
        $reservedLen = $circumference * ($reservedUnits / $occupancyTotal);
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- 1. Greeting row --}}
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-[20px] sm:text-[22px] font-bold text-[#156F8C] truncate">
                        <span class="sm:hidden">{{ $greeting }}, {{ auth()->user()->first_name }}!</span>
                        <span class="hidden sm:inline">{{ $greeting }}, {{ $greetingName }}!</span>
                    </h1>

                    <div class="flex flex-wrap items-center gap-2 mt-2.5">
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#1F2937] bg-[#EEF8F8] rounded-full pl-2 pr-3 py-1">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                            </svg>
                            {{ $totalProperties }} {{ Str::plural('property', $totalProperties) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#1F2937] bg-[#EEF8F8] rounded-full pl-2 pr-3 py-1">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                            </svg>
                            {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#1F2937] bg-[#EEF8F8] rounded-full pl-2 pr-3 py-1">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            {{ $totalTenants }} {{ Str::plural('tenant', $totalTenants) }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('properties.create') }}"
                    class="shrink-0 inline-flex items-center gap-1.5 bg-[#2AA7A1] text-white text-[13px] font-semibold rounded-xl px-3 sm:px-4 py-2.5 shadow-sm shadow-[#2AA7A1]/30 hover:brightness-95 hover:-translate-y-0.5 transition-all duration-200">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="hidden sm:inline">List a property</span>
                    <span class="sm:hidden">List</span>
                </a>
            </div>
        </div>

        {{-- 2. Donut + attention tiles --}}
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-4 mb-6">

            {{-- Occupancy donut --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                <p class="text-[12px] font-bold text-[#64748B] uppercase tracking-wide mb-4">Unit Status</p>
                <div class="flex md:flex-col items-center gap-5 md:gap-4">
                    <div class="relative shrink-0">
                        <svg width="112" height="112" viewBox="0 0 96 96" class="-rotate-90">
                            <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#EEF8F8" stroke-width="11" />
                            @if($occupiedUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#EF4444" stroke-width="11"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $occupiedLen }} {{ $circumference }}" stroke-dashoffset="0" />
                            @endif
                            @if($availableUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#22C55E" stroke-width="11"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $availableLen }} {{ $circumference }}"
                                    stroke-dashoffset="-{{ $occupiedLen }}" />
                            @endif
                            @if($reservedUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#F59E0B" stroke-width="11"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $reservedLen }} {{ $circumference }}"
                                    stroke-dashoffset="-{{ $occupiedLen + $availableLen }}" />
                            @endif
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-[20px] font-bold text-[#1F2937] leading-none">{{ $occupiedPct }}%</p>
                            <p class="text-[10px] text-[#64748B] mt-1">occupied</p>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-[12.5px] w-full">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#EF4444] shrink-0"></span>Occupied</span>
                            <span class="font-bold text-[#1F2937]">{{ $occupiedUnits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#22C55E] shrink-0"></span>Available</span>
                            <span class="font-bold text-[#1F2937]">{{ $availableUnits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#FBBF24] shrink-0"></span>Reserved</span>
                            <span class="font-bold text-[#1F2937]">{{ $reservedUnits }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attention tiles --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="{{ route('landlord.reservations.index') }}"
                    class="group bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 flex items-start justify-between hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(15,23,42,0.1)] transition-all duration-200">
                    <div>
                        <p class="text-[26px] font-extrabold text-[#1F2937] leading-none">{{ $pendingReservations }}</p>
                        <p class="text-[12.5px] text-[#64748B] mt-2 font-medium">Pending reservations</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#EF4444]/[0.07] flex items-center justify-center shrink-0 group-hover:bg-[#EF4444] transition-colors duration-200">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#EF4444" stroke-width="1.75"
                            class="group-hover:stroke-white transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </a>

                <a href="{{ route('conversations.index') }}"
                    class="group bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 flex items-start justify-between hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(15,23,42,0.1)] transition-all duration-200">
                    <div>
                        <p class="text-[26px] font-extrabold text-[#1F2937] leading-none">{{ $unreadMessages }}</p>
                        <p class="text-[12.5px] text-[#64748B] mt-2 font-medium">Unread messages</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0 group-hover:bg-[#2AA7A1] transition-colors duration-200">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.75"
                            class="group-hover:stroke-white transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                    </div>
                </a>

                <a href="{{ route('landlord.reviews.index') }}"
                    class="group bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 flex items-start justify-between hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(15,23,42,0.1)] transition-all duration-200">
                    <div>
                        <p class="text-[26px] font-extrabold text-[#1F2937] leading-none">{{ $newReviews }}</p>
                        <p class="text-[12.5px] text-[#64748B] mt-2 font-medium">New reviews this week</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#FBBF24]/[0.10] flex items-center justify-center shrink-0 group-hover:bg-[#FBBF24] transition-colors duration-200">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#92400E" stroke-width="1.75"
                            class="group-hover:stroke-white transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                </a>

                <div class="group bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5 flex items-start justify-between hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(15,23,42,0.1)] transition-all duration-200">
                    <div>
                        <p class="text-[26px] font-extrabold text-[#1F2937] leading-none">{{ $openComplaints }}</p>
                        <p class="text-[12.5px] text-[#64748B] mt-2 font-medium">Open complaints</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-[#22C55E]/[0.07] flex items-center justify-center shrink-0 group-hover:bg-[#22C55E] transition-colors duration-200">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#166534" stroke-width="1.75"
                            class="group-hover:stroke-white transition-colors duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Properties + Recent activity row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Properties section --}}
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                        </div>
                        <h2 class="text-[15px] font-bold text-[#1F2937]">Your properties</h2>
                    </div>
                    <a href="{{ route('landlord.properties.index') }}"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#2AA7A1] hover:text-[#156F8C] transition-colors duration-200">
                        Manage all
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                @if($properties->isEmpty())
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-8 text-center">
                        <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center mx-auto mb-3">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                        </div>
                        <p class="text-[14px] font-semibold text-[#1F2937]">List your first property</p>
                        <p class="text-[13px] text-[#64748B] mt-1 mb-4">Approved properties will show up here with live
                            occupancy stats.</p>
                        <a href="{{ route('properties.create') }}"
                            class="inline-flex items-center gap-1.5 bg-[#2AA7A1] text-white text-[13px] font-semibold rounded-xl px-4 py-2.5 hover:brightness-95 transition">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            List a property
                        </a>
                    </div>
                @else
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] divide-y divide-[#64748B]/10 overflow-hidden">
                        @foreach($properties->take(5) as $property)
                            @php
                                $propTotal = max($property['total_units'], 1);
                                $propPct = $property['total_units'] > 0
                                    ? round(($property['occupied_units'] / $propTotal) * 100)
                                    : 0;
                                $availPct = round($property['available_units'] / $propTotal * 100);
                                $reservedPct = round($property['reserved_units'] / $propTotal * 100);
                                $occupiedPct = round($property['occupied_units'] / $propTotal * 100);
                            @endphp
                            <a href="{{ route('landlord.properties.show', $property['property_id']) }}"
                                class="group flex items-center gap-4 p-4 hover:bg-[#F7FCFC] transition-colors duration-150">
                                <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] overflow-hidden shrink-0 ring-1 ring-[#64748B]/10">
                                    @if($property['thumbnail'])
                                        <img src="{{ $property['thumbnail'] }}" alt="{{ $property['title'] }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13.5px] font-bold text-[#1F2937] truncate group-hover:text-[#156F8C] transition-colors duration-150">{{ $property['title'] }}</p>
                                    <p class="text-[12px] text-[#64748B] truncate mt-0.5">
                                        {{ $property['address'] }} &middot; {{ $property['property_type'] }}
                                    </p>

                                    {{-- Segmented availability bar --}}
                                    <div class="flex h-1.5 rounded-full bg-[#E2E8F0] overflow-hidden mt-2 max-w-[220px]">
                                        @if($property['available_units'] > 0)
                                            <div class="h-full bg-[#22C55E]" style="width: {{ $availPct }}%"></div>
                                        @endif
                                        @if($property['reserved_units'] > 0)
                                            <div class="h-full bg-[#FBBF24]" style="width: {{ $reservedPct }}%"></div>
                                        @endif
                                        @if($property['occupied_units'] > 0)
                                            <div class="h-full bg-[#EF4444]" style="width: {{ $occupiedPct }}%"></div>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-[#64748B] mt-1.5 flex items-center gap-2.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>{{ $property['available_units'] }} available</span>
                                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#FBBF24]"></span>{{ $property['reserved_units'] }} reserved</span>
                                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#EF4444]"></span>{{ $property['occupied_units'] }} occupied</span>
                                    </p>
                                </div>
                                <div class="hidden sm:flex flex-col items-end shrink-0">
                                    <p class="text-[15px] font-bold text-[#1F2937]">{{ $propPct }}%</p>
                                    <p class="text-[11px] text-[#64748B] mt-0.5">occupancy</p>
                                </div>
                                <div class="w-7 h-7 rounded-full bg-[#EEF8F8] group-hover:bg-[#2AA7A1] flex items-center justify-center shrink-0 transition-colors duration-150">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5"
                                        class="group-hover:stroke-white transition-colors duration-150">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </a>
                        @endforeach
                        @if($properties->count() > 5)
                            <a href="{{ route('landlord.properties.index') }}"
                                class="block text-center py-3 text-[12.5px] font-semibold text-[#2AA7A1] hover:text-[#156F8C] hover:bg-[#F7FCFC] transition-colors duration-150">
                                and {{ $properties->count() - 5 }} more
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Recent activity --}}
            <div class="lg:col-span-1">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                            </svg>
                        </div>
                        <h2 class="text-[15px] font-bold text-[#1F2937]">Recent activity</h2>
                    </div>
                    <a href="{{ route('landlord.reservations.index') }}"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#2AA7A1] hover:text-[#156F8C] transition-colors duration-200">
                        View all
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                    @if($recentActivity->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12 px-5 text-center">
                            <div class="w-11 h-11 rounded-xl bg-[#EEF8F8] flex items-center justify-center mb-3">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <p class="text-[13px] font-semibold text-[#1F2937]">No recent activity yet</p>
                            <p class="text-[12px] text-[#64748B] mt-1">Updates to your units and reservations will show up here.</p>
                        </div>
                    @else
                        <div class="divide-y divide-[#64748B]/10">
                            @foreach($recentActivity as $activity)
                                @php
                                    $isReservation = $activity['type'] === 'reservation';
                                    $statusDot = match($activity['status']) {
                                        'Available' => 'bg-[#22C55E]',
                                        'Reserved' => 'bg-[#FBBF24]',
                                        'Occupied' => 'bg-[#EF4444]',
                                        'Rejected', 'Cancelled' => 'bg-[#EF4444]',
                                        default => 'bg-[#2AA7A1]',
                                    };
                                @endphp
                                <div class="flex items-start gap-3 p-4 hover:bg-[#F7FCFC] transition-colors duration-150">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 {{ $isReservation ? 'bg-[#EEF8F8]' : 'bg-[#22C55E]/[0.07]' }}">
                                        @if($isReservation)
                                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                            </svg>
                                        @else
                                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#22C55E" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V9.75M9 15l2.25 2.25L15 12.75" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[13px] text-[#1F2937] leading-snug">{{ $activity['description'] }}</p>
                                        <div class="flex items-center gap-1.5 mt-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $statusDot }}"></span>
                                            <span class="text-[11px] font-medium text-[#64748B]">{{ $activity['status'] }}</span>
                                            <span class="text-[#64748B]/40">&middot;</span>
                                            <span class="text-[11px] text-[#64748B] whitespace-nowrap">
                                                {{ $activity['timestamp']->diffForHumans(['short' => true, 'parts' => 1]) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>
@endsection