@extends('layouts.landlord')

@section('page-title', 'Analytics')

@section('content')
    @php
        $ranges = [
            'this_month'    => 'This month',
            'last_month'    => 'Last month',
            'last_3_months' => 'Last 3 months',
            'this_year'     => 'This year',
        ];
        $totalUnits = $stats['units'];
        $occupancyTotal = collect($occupancyBreakdown)->sum('count');
        $reservationTotal = collect($reservationBreakdown)->sum('count');
        $revenueTotal = $stats['revenue'];

        // Colour is carried as Tailwind classes, not inline style attributes —
        // the sets are fixed, so there is no need for dynamic CSS (DESIGN.md §10).
        $sliceDotClasses = ['bg-[#156F8C]', 'bg-[#2AA7A1]', 'bg-[#69D2C6]', 'bg-[#FBBF24]', 'bg-[#94A3B8]'];
        $dotClassFor = [
            '#22C55E' => 'bg-[#22C55E]',
            '#FBBF24' => 'bg-[#FBBF24]',
            '#2AA7A1' => 'bg-[#2AA7A1]',
            '#94A3B8' => 'bg-[#94A3B8]',
            '#EF4444' => 'bg-[#EF4444]',
        ];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-[#1F2937]">Analytics</h1>
                <p class="text-sm text-[#64748B] mt-1">Overview of your rental business performance.</p>
            </div>

            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('landlord.analytics.index') }}">
                    <label for="range" class="sr-only">Date range</label>
                    <div class="relative">
                        <select name="range" id="range" onchange="this.form.submit()"
                            class="h-10 pl-9 pr-9 rounded-xl border border-[#E2E8F0] bg-white text-[13px] font-medium text-[#1F2937] focus:outline-none focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 appearance-none transition cursor-pointer">
                            @foreach($ranges as $key => $label)
                                <option value="{{ $key }}" @selected($rangeKey === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B]" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </form>

                <a href="{{ route('landlord.analytics.export', ['range' => $rangeKey]) }}"
                    class="h-10 px-4 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-bold hover:brightness-95 cursor-pointer transition-all duration-200 inline-flex items-center gap-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export Report
                </a>
            </div>
        </div>

        {{-- ===== Stat cards ===== --}}
        @php
            $cards = [
                ['label' => 'Total Properties', 'value' => number_format($stats['properties']), 'sub' => 'Active properties', 'tint' => '#156F8C', 'box' => 'bg-[#156F8C]/10', 'delta' => null,
                 'icon' => 'M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6'],
                ['label' => 'Total Units', 'value' => number_format($stats['units']), 'sub' => 'All rental units', 'tint' => '#2AA7A1', 'box' => 'bg-[#2AA7A1]/10', 'delta' => null,
                 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
                ['label' => 'Occupied Units', 'value' => number_format($stats['occupied']), 'sub' => $stats['occupancyRate'] . '% occupancy rate', 'tint' => '#22C55E', 'box' => 'bg-[#22C55E]/10', 'delta' => null,
                 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
                ['label' => 'Revenue', 'value' => '&#8369;' . number_format($stats['revenue'], 2), 'sub' => 'Collected this period', 'tint' => '#FF8A65', 'box' => 'bg-[#FF8A65]/10', 'delta' => $stats['revenueDelta'],
                 'icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Active Reservations', 'value' => number_format($stats['reservations']), 'sub' => 'Currently in progress', 'tint' => '#FBBF24', 'box' => 'bg-[#FBBF24]/10', 'delta' => $stats['reservationsDelta'],
                 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-4">
            @foreach($cards as $card)
                <x-card class="!p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $card['box'] }}">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="{{ $card['tint'] }}" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">{{ $card['label'] }}</p>
                            <p class="text-xl font-bold text-[#1F2937] mt-0.5 truncate">{!! $card['value'] !!}</p>
                            <p class="text-[11px] text-[#64748B] mt-0.5 truncate">{{ $card['sub'] }}</p>
                        </div>
                    </div>
                    @if($card['delta'] !== null)
                        <p class="text-[11px] font-semibold mt-2.5 flex items-center gap-1 {{ $card['delta'] >= 0 ? 'text-[#15803D]' : 'text-[#DC2626]' }}">
                            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                class="{{ $card['delta'] >= 0 ? '' : 'rotate-180' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                            </svg>
                            {{ abs($card['delta']) }}% vs previous period
                        </p>
                    @endif
                </x-card>
            @endforeach
        </div>

        {{-- ===== Row: Occupancy donut · Revenue line · Revenue by property ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-3 mb-3">

            {{-- Occupancy Overview --}}
            <x-card>
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-3">Occupancy Overview</h2>
                @if($occupancyTotal === 0)
                    <p class="text-[12.5px] text-[#64748B] py-8 text-center">No units yet.</p>
                @else
                    <div class="relative h-[150px] mb-3">
                        <canvas id="occupancyChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-xl font-bold text-[#1F2937]">{{ $stats['occupancyRate'] }}%</span>
                            <span class="text-[10px] text-[#64748B]">Occupancy</span>
                        </div>
                    </div>
                    <ul class="flex flex-col gap-1.5">
                        @foreach($occupancyBreakdown as $slice)
                            <li class="flex items-center gap-2 text-[11.5px]">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $dotClassFor[$slice['color']] ?? 'bg-[#94A3B8]' }}"></span>
                                <span class="text-[#1F2937] flex-1">{{ $slice['label'] }}</span>
                                <span class="text-[#64748B]">{{ $slice['count'] }}</span>
                                <span class="text-[#1F2937] font-semibold w-11 text-right">
                                    {{ $occupancyTotal > 0 ? round($slice['count'] / $occupancyTotal * 100, 1) : 0 }}%
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Revenue Overview --}}
            <x-card class="lg:col-span-1 xl:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Revenue Overview</h2>
                    <span class="text-[11px] text-[#64748B]">Last 6 months</span>
                </div>
                @if($revenueTrend->sum('value') <= 0)
                    <div class="h-[210px] flex flex-col items-center justify-center text-center">
                        <p class="text-[13px] font-semibold text-[#1F2937]">No revenue recorded yet</p>
                        <p class="text-[12px] text-[#64748B] mt-1 max-w-[260px]">Revenue appears once a tenant completes payment on a signed agreement.</p>
                    </div>
                @else
                    <div class="h-[210px]"><canvas id="revenueChart"></canvas></div>
                @endif
            </x-card>

            {{-- Revenue by Property --}}
            <x-card>
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-3">Revenue by Property</h2>
                @if($revenueTotal <= 0)
                    <p class="text-[12.5px] text-[#64748B] py-8 text-center">No revenue in this period.</p>
                @else
                    <div class="relative h-[150px] mb-3">
                        <canvas id="revenueByPropertyChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-[15px] font-bold text-[#1F2937]">&#8369;{{ number_format($revenueTotal, 0) }}</span>
                            <span class="text-[10px] text-[#64748B]">Total</span>
                        </div>
                    </div>
                    <ul class="flex flex-col gap-1.5">
                        @foreach($topSlices as $i => $slice)
                            <li class="flex items-center gap-2 text-[11.5px]">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $sliceDotClasses[$i] }}"></span>
                                <span class="text-[#1F2937] flex-1 truncate">{{ $slice['title'] }}</span>
                                <span class="text-[#1F2937] font-semibold shrink-0">&#8369;{{ number_format($slice['revenue'], 0) }}</span>
                            </li>
                        @endforeach
                        @if($othersTotal > 0)
                            <li class="flex items-center gap-2 text-[11.5px]">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $sliceDotClasses[4] }}"></span>
                                <span class="text-[#1F2937] flex-1">Others</span>
                                <span class="text-[#1F2937] font-semibold shrink-0">&#8369;{{ number_format($othersTotal, 0) }}</span>
                            </li>
                        @endif
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- ===== Row: Occupancy by property · Reservations · Top performing ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-3">

            {{-- Occupancy by Property --}}
            <x-card>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Occupancy by Property</h2>
                    <a href="{{ route('landlord.occupancy.index') }}"
                        class="text-[11.5px] font-bold text-[#156F8C] hover:underline">View All</a>
                </div>
                @if($perProperty->isEmpty())
                    <p class="text-[12.5px] text-[#64748B] py-6 text-center">No properties yet.</p>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach($perProperty->take(5) as $row)
                            <div>
                                <div class="flex items-baseline justify-between gap-2 mb-1">
                                    <p class="text-[12px] text-[#1F2937] truncate">{{ $row['title'] }}</p>
                                    <p class="text-[11.5px] font-bold text-[#1F2937] shrink-0">{{ $row['rate'] }}%</p>
                                </div>
                                <div class="flex h-2 rounded-full overflow-hidden bg-[#E2E8F0]">
                                    @if($row['total'] > 0)
                                        <div class="bg-[#22C55E]" style="width: {{ $row['occupied'] / $row['total'] * 100 }}%"></div>
                                        <div class="bg-[#FBBF24]" style="width: {{ $row['reserved'] / $row['total'] * 100 }}%"></div>
                                        <div class="bg-[#2AA7A1]" style="width: {{ $row['available'] / $row['total'] * 100 }}%"></div>
                                    @endif
                                </div>
                                <p class="text-[10.5px] text-[#64748B] mt-1">
                                    {{ $row['occupied'] }} occupied &middot; {{ $row['reserved'] }} reserved &middot; {{ $row['available'] }} available
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Reservations Overview --}}
            <x-card>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Reservations Overview</h2>
                    <a href="{{ route('landlord.reservations.index') }}"
                        class="text-[11.5px] font-bold text-[#156F8C] hover:underline">View All</a>
                </div>
                @if($reservationTotal === 0)
                    <p class="text-[12.5px] text-[#64748B] py-8 text-center">No reservations in this period.</p>
                @else
                    <div class="relative h-[150px] mb-3">
                        <canvas id="reservationChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-xl font-bold text-[#1F2937]">{{ $reservationTotal }}</span>
                            <span class="text-[10px] text-[#64748B]">Total</span>
                        </div>
                    </div>
                    <ul class="flex flex-col gap-1.5">
                        @foreach($reservationBreakdown as $slice)
                            <li class="flex items-center gap-2 text-[11.5px]">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $dotClassFor[$slice['color']] ?? 'bg-[#94A3B8]' }}"></span>
                                <span class="text-[#1F2937] flex-1">{{ $slice['label'] }}</span>
                                <span class="text-[#1F2937] font-semibold">
                                    {{ $slice['count'] }} ({{ round($slice['count'] / $reservationTotal * 100, 1) }}%)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Top Performing Properties --}}
            <x-card flush class="lg:col-span-2 xl:col-span-1">
                <div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-3">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Top Performing Properties</h2>
                </div>
                @if($perProperty->isEmpty())
                    <p class="text-[12.5px] text-[#64748B] py-8 text-center">No properties yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[320px]">
                            <thead>
                                <tr class="border-y border-[#E2E8F0] bg-[#F7FCFC]">
                                    <th class="text-left text-[10px] font-bold text-[#64748B] uppercase tracking-wider px-5 sm:px-6 py-2">Property</th>
                                    <th class="text-right text-[10px] font-bold text-[#64748B] uppercase tracking-wider px-3 py-2">Occupancy</th>
                                    <th class="text-right text-[10px] font-bold text-[#64748B] uppercase tracking-wider px-5 sm:px-6 py-2">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach($perProperty->take(6) as $row)
                                    <tr class="hover:bg-[#F7FCFC]/70 transition-colors duration-150">
                                        <td class="px-5 sm:px-6 py-2.5 text-[12px] text-[#1F2937] truncate max-w-[180px]">{{ $row['title'] }}</td>
                                        <td class="px-3 py-2.5 text-[12px] font-semibold text-[#1F2937] text-right">{{ $row['rate'] }}%</td>
                                        <td class="px-5 sm:px-6 py-2.5 text-[12px] font-bold text-[#1F2937] text-right">&#8369;{{ number_format($row['revenue'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <p class="text-[11px] text-[#64748B] text-center mt-5">
            Showing data for {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }}
        </p>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') return;

            const peso = (v) => '₱' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
            const donut = { cutout: '68%', plugins: { legend: { display: false } }, maintainAspectRatio: false };

            const occupancy = @json($occupancyBreakdown);
            const occEl = document.getElementById('occupancyChart');
            if (occEl) {
                new Chart(occEl, {
                    type: 'doughnut',
                    data: {
                        labels: occupancy.map(s => s.label),
                        datasets: [{
                            data: occupancy.map(s => s.count),
                            backgroundColor: occupancy.map(s => s.color),
                            borderWidth: 0,
                        }],
                    },
                    options: donut,
                });
            }

            const trend = @json($revenueTrend);
            const revEl = document.getElementById('revenueChart');
            if (revEl) {
                new Chart(revEl, {
                    type: 'line',
                    data: {
                        labels: trend.map(p => p.label),
                        datasets: [{
                            data: trend.map(p => p.value),
                            borderColor: '#2AA7A1',
                            backgroundColor: 'rgba(42, 167, 161, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#2AA7A1',
                            pointRadius: 4,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => peso(c.parsed.y) } },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: peso, font: { size: 10 }, color: '#64748B' },
                                grid: { color: '#E2E8F0' },
                            },
                            x: {
                                ticks: { font: { size: 10 }, color: '#64748B' },
                                grid: { display: false },
                            },
                        },
                    },
                });
            }

            const slices = @json($topSlices->map(fn ($s) => ['title' => $s['title'], 'revenue' => $s['revenue']])->values());
            const others = @json($othersTotal);
            const rbpEl = document.getElementById('revenueByPropertyChart');
            if (rbpEl) {
                const labels = slices.map(s => s.title);
                const values = slices.map(s => s.revenue);
                if (others > 0) { labels.push('Others'); values.push(others); }

                new Chart(rbpEl, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: ['#156F8C', '#2AA7A1', '#69D2C6', '#FBBF24', '#94A3B8'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        ...donut,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => c.label + ': ' + peso(c.parsed) } },
                        },
                    },
                });
            }

            const reservations = @json($reservationBreakdown);
            const resEl = document.getElementById('reservationChart');
            if (resEl) {
                new Chart(resEl, {
                    type: 'doughnut',
                    data: {
                        labels: reservations.map(s => s.label),
                        datasets: [{
                            data: reservations.map(s => s.count),
                            backgroundColor: reservations.map(s => s.color),
                            borderWidth: 0,
                        }],
                    },
                    options: donut,
                });
            }
        });
    </script>
@endpush
