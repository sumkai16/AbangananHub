@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Occupancy Monitoring</h1>
            <p class="text-sm text-[#64748B] mt-1">Track unit availability and occupancy rates across all your
                properties.</p>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <span class="text-[11px] font-medium text-[#64748B]">Total Units</span>
                <p class="text-xl font-bold text-[#1F2937] mt-1">{{ $totalUnits }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <span class="text-[11px] font-medium text-[#64748B]">Available</span>
                <p class="text-xl font-bold text-emerald-600 mt-1">{{ $availableUnits }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <span class="text-[11px] font-medium text-[#64748B]">Reserved</span>
                <p class="text-xl font-bold text-amber-500 mt-1">{{ $reservedUnits }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#64748B]/15 p-4">
                <span class="text-[11px] font-medium text-[#64748B]">Occupied</span>
                <p class="text-xl font-bold text-[#EF4444] mt-1">{{ $occupiedUnits }}</p>
            </div>
            <div class="bg-white rounded-xl ring-1 ring-[#2AA7A1]/30 p-4 col-span-2 sm:col-span-1">
                <span class="text-[11px] font-medium text-[#64748B]">Occupancy Rate</span>
                <p class="text-xl font-bold text-[#2AA7A1] mt-1">{{ $aggregateRate }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

            {{-- Status split donut --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-5">
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Status Split</h2>
                <div class="relative h-40">
                    <canvas id="occupancyStatusChart"></canvas>
                </div>
                <div class="space-y-2 text-[12px] mt-4">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Available</span>
                        <span class="font-semibold text-[#1F2937]">{{ $availableUnits }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Reserved</span>
                        <span class="font-semibold text-[#1F2937]">{{ $reservedUnits }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-[#EF4444]"></span>Occupied</span>
                        <span class="font-semibold text-[#1F2937]">{{ $occupiedUnits }}</span>
                    </div>
                </div>
            </div>

            {{-- Per-property occupancy rate --}}
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-5 lg:col-span-2">
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-4">Occupancy Rate by Property</h2>
                @if($propertyBreakdown->isEmpty())
                    <p class="text-sm text-[#64748B] text-center py-10">No properties yet.</p>
                @else
                    <div class="relative" style="height: {{ max(160, $propertyBreakdown->count() * 44) }}px">
                        <canvas id="propertyRateChart"></canvas>
                    </div>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-[#64748B]/15 text-left">
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Property</th>
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Total</th>
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Available</th>
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Reserved</th>
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Occupied</th>
                            <th class="px-5 py-3 text-[11px] font-semibold text-[#64748B] uppercase tracking-wider">Rate</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#64748B]/10">
                        @forelse($propertyBreakdown as $row)
                            <tr class="hover:bg-[#EEF8F8]/30 transition-colors duration-150">
                                <td class="px-5 py-3.5 font-semibold text-[#1F2937]">{{ $row['title'] }}</td>
                                <td class="px-5 py-3.5 text-[#1F2937]">{{ $row['total'] }}</td>
                                <td class="px-5 py-3.5 text-emerald-600 font-medium">{{ $row['available'] }}</td>
                                <td class="px-5 py-3.5 text-amber-500 font-medium">{{ $row['reserved'] }}</td>
                                <td class="px-5 py-3.5 text-[#EF4444] font-medium">{{ $row['occupied'] }}</td>
                                <td class="px-5 py-3.5 font-semibold text-[#2AA7A1]">{{ $row['rate'] }}%</td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('landlord.properties.units.index', $row['property_id']) }}"
                                        class="text-[12px] font-semibold text-[#2AA7A1] hover:underline">View units</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-[#64748B]">No properties yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                            hoverOffset: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: { legend: { display: false } },
                    },
                });
            }

            const rateCtx = document.getElementById('propertyRateChart');
            if (rateCtx) {
                new Chart(rateCtx, {
                    type: 'bar',
                    data: {
                        labels: {!! $propertyBreakdown->pluck('title')->toJson() !!},
                        datasets: [{
                            label: 'Occupancy Rate (%)',
                            data: {!! $propertyBreakdown->pluck('rate')->toJson() !!},
                            backgroundColor: '#2AA7A1',
                            borderRadius: 6,
                            barThickness: 18,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: v => v + '%' } },
                            y: { grid: { display: false } },
                        },
                    },
                });
            }
        });
    </script>
@endpush
