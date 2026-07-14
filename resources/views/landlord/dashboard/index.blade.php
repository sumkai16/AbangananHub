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

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8">

        {{-- 1. Greeting row --}}
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="min-w-0">
                <h1 class="text-[20px] sm:text-[22px] font-bold text-[#156F8C] truncate">
                    <span class="sm:hidden">{{ $greeting }}, {{ auth()->user()->first_name }}!</span>
                    <span class="hidden sm:inline">{{ $greeting }}, {{ $greetingName }}!</span>
                </h1>
                <p class="text-[13px] text-[#64748B] mt-1">
                    {{ $totalProperties }} {{ Str::plural('property', $totalProperties) }}
                    &middot; {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }}
                    &middot; {{ $totalTenants }} {{ Str::plural('tenant', $totalTenants) }}
                </p>
            </div>
            <a href="{{ route('properties.create') }}"
                class="shrink-0 inline-flex items-center gap-1.5 bg-[#FF8A65] text-white text-[13px] font-semibold rounded-xl px-3 sm:px-4 py-2.5 hover:brightness-95 transition">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span class="hidden sm:inline">List a property</span>
                <span class="sm:hidden">List</span>
            </a>
        </div>

        {{-- 2. Donut + attention tiles --}}
        <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-4 mb-6">

            {{-- Occupancy donut --}}
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-4">
                <div class="flex md:flex-col items-center gap-4 md:gap-3">
                    <div class="relative shrink-0">
                        <svg width="96" height="96" viewBox="0 0 96 96" class="-rotate-90">
                            <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#E2E8F0" stroke-width="10" />
                            @if($occupiedUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#EF4444" stroke-width="10"
                                    stroke-dasharray="{{ $occupiedLen }} {{ $circumference }}" stroke-dashoffset="0" />
                            @endif
                            @if($availableUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#E2E8F0" stroke-width="10"
                                    stroke-dasharray="{{ $availableLen }} {{ $circumference }}"
                                    stroke-dashoffset="-{{ $occupiedLen }}" />
                            @endif
                            @if($reservedUnits > 0)
                                <circle cx="48" cy="48" r="{{ $radius }}" fill="none" stroke="#2AA7A1" stroke-width="10"
                                    stroke-dasharray="{{ $reservedLen }} {{ $circumference }}"
                                    stroke-dashoffset="-{{ $occupiedLen + $availableLen }}" />
                            @endif
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <p class="text-[18px] font-bold text-[#1F2937] leading-none">{{ $occupiedPct }}%</p>
                            <p class="text-[10px] text-[#64748B] mt-0.5">occupied</p>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-[12px] w-full">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#EF4444] shrink-0"></span>Occupied</span>
                            <span class="font-semibold text-[#1F2937]">{{ $occupiedUnits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#E2E8F0] shrink-0"></span>Available</span>
                            <span class="font-semibold text-[#1F2937]">{{ $availableUnits }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-[#64748B]"><span
                                    class="w-2.5 h-2.5 rounded-full bg-[#2AA7A1] shrink-0"></span>Reserved</span>
                            <span class="font-semibold text-[#1F2937]">{{ $reservedUnits }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attention tiles --}}
            <div class="grid grid-cols-2 gap-4">
                <a href="{{ route('landlord.reservations.index') }}"
                    class="bg-[#FEF2F2] rounded-2xl p-4 flex flex-col justify-between hover:brightness-95 transition">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#991B1B" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div class="mt-2">
                        <p class="text-[22px] font-bold text-[#991B1B] leading-none">{{ $pendingReservations }}</p>
                        <p class="text-[12px] text-[#991B1B] mt-1">Pending reservations</p>
                    </div>
                </a>

                <a href="{{ route('conversations.index') }}"
                    class="bg-[#EEF8F8] rounded-2xl p-4 flex flex-col justify-between hover:brightness-95 transition">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                    </svg>
                    <div class="mt-2">
                        <p class="text-[22px] font-bold text-[#156F8C] leading-none">{{ $unreadMessages }}</p>
                        <p class="text-[12px] text-[#156F8C] mt-1">Unread messages</p>
                    </div>
                </a>

                <div class="bg-[#FFFBEB] rounded-2xl p-4 flex flex-col justify-between">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#92400E" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    <div class="mt-2">
                        <p class="text-[22px] font-bold text-[#92400E] leading-none">{{ $newReviews }}</p>
                        <p class="text-[12px] text-[#92400E] mt-1">New reviews this week</p>
                    </div>
                </div>

                <div class="bg-[#F0FDF4] rounded-2xl p-4 flex flex-col justify-between">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#166534" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="mt-2">
                        <p class="text-[22px] font-bold text-[#166534] leading-none">{{ $openComplaints }}</p>
                        <p class="text-[12px] text-[#166534] mt-1">Open complaints</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Properties + Recent activity row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Properties section --}}
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[15px] font-bold text-[#156F8C]">Your properties</h2>
                    <a href="{{ route('landlord.properties.index') }}"
                        class="text-[13px] font-semibold text-[#156F8C] hover:brightness-95">Manage all</a>
                </div>

                @if($properties->isEmpty())
                    <div class="bg-white rounded-2xl border border-[#E2E8F0] p-8 text-center">
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
                            class="inline-flex items-center gap-1.5 bg-[#FF8A65] text-white text-[13px] font-semibold rounded-xl px-4 py-2.5 hover:brightness-95 transition">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            List a property
                        </a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($properties->take(5) as $property)
                            @php
                                $propTotal = max($property['total_units'], 1);
                                $propPct = $property['total_units'] > 0
                                    ? round(($property['occupied_units'] / $propTotal) * 100)
                                    : 0;
                            @endphp
                            <a href="{{ route('landlord.properties.show', $property['property_id']) }}"
                                class="bg-white rounded-2xl border border-[#E2E8F0] p-4 flex items-center gap-4 hover:brightness-95 transition">
                                <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-bold text-[#1F2937] truncate">{{ $property['title'] }}</p>
                                    <p class="text-[12px] text-[#64748B] truncate">
                                        {{ $property['address'] }} &middot; {{ $property['property_type'] }}
                                    </p>
                                    <p class="text-[12px] text-[#64748B] mt-0.5 sm:hidden">
                                        {{ $property['occupied_units'] }}/{{ $property['total_units'] }} occupied
                                    </p>
                                </div>
                                <div class="hidden sm:flex flex-col items-end shrink-0">
                                    <p class="text-[13px] font-bold text-[#1F2937]">
                                        {{ $property['occupied_units'] }}/{{ $property['total_units'] }}
                                    </p>
                                    <svg width="44" height="5" viewBox="0 0 44 5" class="mt-1">
                                        <rect width="44" height="5" rx="2.5" fill="#E2E8F0" />
                                        <rect width="{{ round(44 * $propPct / 100) }}" height="5" rx="2.5"
                                            fill="{{ $propPct >= 60 ? '#2AA7A1' : '#F59E0B' }}" />
                                    </svg>
                                    <p class="text-[11px] text-[#64748B] mt-1">{{ $propPct }}%</p>
                                </div>
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                                    class="shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        @endforeach
                        @if($properties->count() > 5)
                            <p class="text-[12px] text-[#64748B] text-center pt-1">
                                <a href="{{ route('landlord.properties.index') }}" class="hover:brightness-95">
                                    and {{ $properties->count() - 5 }} more
                                </a>
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Recent activity --}}
            <div class="lg:col-span-1">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[15px] font-bold text-[#156F8C]">Recent activity</h2>
                    <a href="{{ route('landlord.reservations.index') }}"
                        class="text-[13px] font-semibold text-[#156F8C] hover:brightness-95">View all</a>
                </div>

                <div class="bg-white rounded-2xl border border-[#E2E8F0] p-5">
                    @if($recentActivity->isEmpty())
                        <p class="text-[13px] text-[#64748B] text-center py-8">No recent activity yet.</p>
                    @else
                        @foreach($recentActivity as $activity)
                            <div class="flex items-start gap-3 py-3 {{ !$loop->last ? 'border-b border-[#E2E8F0]' : '' }}">
                                <div class="w-8 h-8 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 mt-0.5">
                                    @if($activity['type'] === 'reservation')
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                    @else
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22C55E" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V9.75M9 15l2.25 2.25L15 12.75" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] text-[#1F2937] leading-snug">{{ $activity['description'] }}</p>
                                </div>
                                <p class="text-[11px] text-[#64748B] shrink-0 mt-0.5 whitespace-nowrap">
                                    {{ $activity['timestamp']->diffForHumans(['short' => true, 'parts' => 1]) }}
                                </p>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

        </div>

    </div>
@endsection