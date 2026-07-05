@extends('layouts.landlord')

@section('content')
    @php
        $business = auth()->user()->rentalBusiness;
        $greetingName = $business->business_name ?? auth()->user()->first_name;

        $occupancyTotal = $totalUnits > 0 ? $totalUnits : 1; // guard divide-by-zero for percentages
        $occupiedPct = round(($occupiedUnits / $occupancyTotal) * 100);
        $availablePct = round(($availableUnits / $occupancyTotal) * 100);
        $reservedPct = round(($reservedUnits / $occupancyTotal) * 100);
        $maintenancePct = round(($maintenanceUnits / $occupancyTotal) * 100);

        // SVG donut math — circumference-based stroke-dasharray segments
        $radius = 60;
        $circumference = 2 * M_PI * $radius;
        $occupiedLen = $circumference * ($occupiedUnits / $occupancyTotal);
        $availableLen = $circumference * ($availableUnits / $occupancyTotal);
        $reservedLen = $circumference * ($reservedUnits / $occupancyTotal);
        $maintenanceLen = $circumference * ($maintenanceUnits / $occupancyTotal);
    @endphp

    <div class="px-4 sm:px-8 lg:px-[50px] py-6">

        <div class="mb-6">
            <h1 class="text-[22px] font-bold text-[#0F172A]">Good morning, {{ $greetingName }}!</h1>
            <p class="text-[13px] text-[#9B9F98] mt-1">Here's what's happening with your properties today.</p>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-4">
                <p class="text-[12px] text-[#9B9F98] font-medium mb-1">Total Properties</p>
                <p class="text-[24px] font-bold text-[#0F172A]">{{ $totalProperties }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-4">
                <p class="text-[12px] text-[#9B9F98] font-medium mb-1">Total Units</p>
                <p class="text-[24px] font-bold text-[#0F172A]">{{ $totalUnits }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-4">
                <p class="text-[12px] text-[#9B9F98] font-medium mb-1">Occupied Units</p>
                <p class="text-[24px] font-bold text-[#0F172A]">{{ $occupiedUnits }}</p>
                <p class="text-[11px] text-[#9B9F98] mt-0.5">{{ $occupiedPct }}% occupancy</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-4">
                <p class="text-[12px] text-[#9B9F98] font-medium mb-1">Available Units</p>
                <p class="text-[24px] font-bold text-[#0F172A]">{{ $availableUnits }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-4 col-span-2 md:col-span-1">
                <p class="text-[12px] text-[#9B9F98] font-medium mb-1">Total Tenants</p>
                <p class="text-[24px] font-bold text-[#0F172A]">{{ $totalTenants }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

            {{-- Occupancy donut (real data) --}}
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-5">
                <h2 class="text-[14px] font-bold text-[#0F172A] mb-4">Occupancy Overview</h2>

                <div class="relative flex items-center justify-center mb-4">
                    <svg width="160" height="160" viewBox="0 0 160 160" class="-rotate-90">
                        <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#F1F5F9" stroke-width="16" />

                        @if($occupiedUnits > 0)
                            <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#DC2626" stroke-width="16"
                                stroke-dasharray="{{ $occupiedLen }} {{ $circumference }}" stroke-dashoffset="0" />
                        @endif
                        @if($availableUnits > 0)
                            <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#9B9F98" stroke-width="16"
                                stroke-dasharray="{{ $availableLen }} {{ $circumference }}"
                                stroke-dashoffset="-{{ $occupiedLen }}" />
                        @endif
                        @if($reservedUnits > 0)
                            <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#3B82F6" stroke-width="16"
                                stroke-dasharray="{{ $reservedLen }} {{ $circumference }}"
                                stroke-dashoffset="-{{ $occupiedLen + $availableLen }}" />
                        @endif
                        @if($maintenanceUnits > 0)
                            <circle cx="80" cy="80" r="{{ $radius }}" fill="none" stroke="#0F172A" stroke-width="16"
                                stroke-dasharray="{{ $maintenanceLen }} {{ $circumference }}"
                                stroke-dashoffset="-{{ $occupiedLen + $availableLen + $reservedLen }}" />
                        @endif
                    </svg>
                    <div class="absolute text-center">
                        <p class="text-[26px] font-bold text-[#0F172A]">{{ $occupiedPct }}%</p>
                        <p class="text-[11px] text-[#9B9F98]">Occupied</p>
                    </div>
                </div>

                <div class="space-y-2 text-[12px]">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-[#DC2626]"></span>Occupied</span>
                        <span class="font-semibold text-[#0F172A]">{{ $occupiedUnits }} ({{ $occupiedPct }}%)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-[#9B9F98]"></span>Available</span>
                        <span class="font-semibold text-[#0F172A]">{{ $availableUnits }} ({{ $availablePct }}%)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-[#3B82F6]"></span>Reserved</span>
                        <span class="font-semibold text-[#0F172A]">{{ $reservedUnits }} ({{ $reservedPct }}%)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-[#0F172A]"></span>Maintenance</span>
                        <span class="font-semibold text-[#0F172A]">{{ $maintenanceUnits }} ({{ $maintenancePct }}%)</span>
                    </div>
                </div>
            </div>

            {{-- Occupancy trend — stubbed, no historical data exists --}}
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-5 lg:col-span-2 flex flex-col">
                <h2 class="text-[14px] font-bold text-[#0F172A] mb-4">Occupancy Trend</h2>
                <div class="flex-1 flex flex-col items-center justify-center text-center py-10">
                    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                        class="text-[#9B9F98]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                    <p class="text-[13px] font-semibold text-[#0F172A]">Coming soon</p>
                    <p class="text-[12px] text-[#9B9F98] mt-1 max-w-[260px]">
                        Historical occupancy tracking will appear here once trend data starts accumulating.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Monthly Revenue — stubbed, no payment processing in scope --}}
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-5">
                <h2 class="text-[14px] font-bold text-[#0F172A] mb-4">Monthly Revenue</h2>
                <div class="flex flex-col items-center justify-center text-center py-6">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                        class="text-[#9B9F98]/50 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182.553-.439 1.278-.659 2.003-.659.725 0 1.45.22 2.003.659l.359.278M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <p class="text-[13px] font-semibold text-[#0F172A]">Coming soon</p>
                    <p class="text-[12px] text-[#9B9F98] mt-1">Payment tracking is not yet part of this platform.</p>
                </div>
            </div>

            {{-- Recent Activity — real data --}}
            <div class="bg-white rounded-2xl border border-[#9B9F98]/15 p-5 lg:col-span-2">
                <h2 class="text-[14px] font-bold text-[#0F172A] mb-4">Recent Activity</h2>

                @if($recentActivity->isEmpty())
                    <p class="text-[13px] text-[#9B9F98] text-center py-8">No recent activity yet.</p>
                @else
                    <div class="space-y-4">
                        @foreach($recentActivity as $activity)
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-[#DBEAFE] flex items-center justify-center shrink-0 mt-0.5">
                                    @if($activity['type'] === 'unit')
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#3B82F6" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6z" />
                                        </svg>
                                    @else
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#3B82F6" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] text-[#0F172A] leading-snug">{{ $activity['description'] }}</p>
                                    <p class="text-[11px] text-[#9B9F98] mt-0.5">{{ $activity['timestamp']->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection