@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Header --}}
        <div class="flex items-center gap-3.5 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Occupancy Monitoring</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Track unit availability and occupancy rates across all your
                    properties.</p>
            </div>
        </div>

        {{-- Stat cards --}}
        @php
            $availPctAll = $totalUnits > 0 ? round($availableUnits / $totalUnits * 100) : 0;
            $reservedPctAll = $totalUnits > 0 ? round($reservedUnits / $totalUnits * 100) : 0;
            $occupiedPctAll = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100) : 0;
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total Units</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF2F5] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $totalUnits }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Across all properties</p>
            </div>

            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Available</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-emerald-600">{{ $availableUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $availPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $availPctAll }}% of total units</p>
            </div>

            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Reserved</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-amber-500">{{ $reservedUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $reservedPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $reservedPctAll }}% of total units</p>
            </div>

            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-red-500">{{ $occupiedUnits }}</span>
                <div class="w-full h-1.5 rounded-full bg-[#E2E8F0] mt-2.5 overflow-hidden">
                    <div class="h-full rounded-full bg-red-500" style="width: {{ $occupiedPctAll }}%"></div>
                </div>
                <p class="text-[11px] text-[#64748B] mt-1.5">{{ $occupiedPctAll }}% of total units</p>
            </div>

            <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupancy Rate</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#2AA7A1]">{{ $aggregateRate }}%</span>
                <p class="text-[11px] text-[#64748B] mt-1">Approved units occupied</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

            {{-- Status split donut --}}
            <div class="lg:col-span-2 bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-5">
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Status Split</h2>
                <div class="relative h-44">
                    <canvas id="occupancyStatusChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <p class="text-[22px] font-bold text-[#1F2937] leading-none">{{ $totalUnits }}</p>
                        <p class="text-[10px] text-[#64748B] mt-1">total units</p>
                    </div>
                </div>
                <div class="space-y-2.5 text-[12.5px] mt-5">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-[#64748B]"><span
                                class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Available</span>
                        <span class="font-bold text-[#1F2937]">{{ $availableUnits }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-[#64748B]"><span
                                class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Reserved</span>
                        <span class="font-bold text-[#1F2937]">{{ $reservedUnits }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-[#64748B]"><span
                                class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Occupied</span>
                        <span class="font-bold text-[#1F2937]">{{ $occupiedUnits }}</span>
                    </div>
                </div>
            </div>

            {{-- Per-property occupancy breakdown --}}
            <div class="lg:col-span-3 bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between p-5 pb-0">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Occupancy by Property</h2>
                    <span class="text-[11px] font-medium text-[#64748B]">{{ $propertyBreakdown->count() }} {{ Str::plural('property', $propertyBreakdown->count()) }}</span>
                </div>

                @if($propertyBreakdown->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 px-5 text-center">
                        <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center mb-3">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                            </svg>
                        </div>
                        <p class="text-[13px] font-semibold text-[#1F2937]">No approved properties yet</p>
                        <p class="text-[12px] text-[#64748B] mt-1">Occupancy breakdown will appear here once a property is approved.</p>
                    </div>
                @else
                    <div class="divide-y divide-[#64748B]/10 max-h-[420px] overflow-y-auto scrollbar-thin-light mt-3">
                        @foreach($propertyBreakdown as $row)
                            @php
                                $rowTotal = max($row['total'], 1);
                                $rAvail = round($row['available'] / $rowTotal * 100);
                                $rReserved = round($row['reserved'] / $rowTotal * 100);
                                $rOccupied = round($row['occupied'] / $rowTotal * 100);
                                $rateAccent = $row['rate'] >= 60 ? '#2AA7A1' : ($row['rate'] > 0 ? '#B45309' : '#94A3B8');
                            @endphp
                            <a href="{{ route('landlord.properties.units.index', $row['property_id']) }}"
                                class="group flex items-center gap-4 px-5 py-3.5 hover:bg-[#F7FCFC] transition-colors duration-150">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <p class="text-[13px] font-bold text-[#1F2937] truncate group-hover:text-[#156F8C] transition-colors duration-150">{{ $row['title'] }}</p>
                                        <span class="text-[12.5px] font-bold shrink-0" style="color: {{ $rateAccent }}">{{ $row['rate'] }}%</span>
                                    </div>
                                    <div class="flex h-1.5 rounded-full bg-[#E2E8F0] overflow-hidden">
                                        @if($row['available'] > 0)
                                            <div class="h-full bg-emerald-500" style="width: {{ $rAvail }}%"></div>
                                        @endif
                                        @if($row['reserved'] > 0)
                                            <div class="h-full bg-amber-500" style="width: {{ $rReserved }}%"></div>
                                        @endif
                                        @if($row['occupied'] > 0)
                                            <div class="h-full bg-red-500" style="width: {{ $rOccupied }}%"></div>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-[#64748B] mt-1.5">
                                        {{ $row['total'] }} {{ Str::plural('unit', $row['total']) }} &middot;
                                        {{ $row['available'] }} available &middot; {{ $row['reserved'] }} reserved &middot; {{ $row['occupied'] }} occupied
                                    </p>
                                </div>
                                <div class="w-7 h-7 rounded-full bg-[#EEF8F8] group-hover:bg-[#2AA7A1] flex items-center justify-center shrink-0 transition-colors duration-150">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5"
                                        class="group-hover:stroke-white transition-colors duration-150">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusCtx = document.getElementById('occupancyStatusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available', 'Reserved', 'Occupied'],
                        datasets: [{
                            data: [{{ $availableUnits }}, {{ $reservedUnits }}, {{ $occupiedUnits }}],
                            backgroundColor: ['#10b981', '#f59e0b', '#EF4444'],
                            borderWidth: 0,
                            borderRadius: 6,
                            hoverOffset: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '74%',
                        plugins: { legend: { display: false } },
                    },
                });
            }
        });
    </script>
@endpush
