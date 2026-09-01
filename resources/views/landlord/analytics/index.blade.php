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

        // Recent Activity's dot colour and sentence verb, moved here with the
        // panel when landlord/occupancy was deleted.
        $statusStyles = [
            'Available'   => ['tile' => 'border-[#22C55E]/25 bg-[#22C55E]/[0.07]', 'dot' => 'bg-[#22C55E]', 'verb' => 'was made available'],
            'Reserved'    => ['tile' => 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]', 'dot' => 'bg-[#FBBF24]', 'verb' => 'was reserved'],
            'Occupied'    => ['tile' => 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]', 'dot' => 'bg-[#EF4444]', 'verb' => 'was occupied'],
            'Maintenance' => ['tile' => 'border-[#E2E8F0] bg-[#F7FCFC]',           'dot' => 'bg-[#94A3B8]', 'verb' => 'is now under maintenance'],
        ];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <x-page-header title="Analytics" subtitle="Overview of your rental business performance.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                <form method="GET" action="{{ route('landlord.analytics.index') }}">
                    <label for="range" class="sr-only">Date range</label>
                    <div class="relative">
                        <x-styled-select name="range" id="range" :options="$ranges" :selected="$rangeKey" :autosubmit="true"
                            class="h-10 pl-9 pr-4 rounded-xl border border-[#E2E8F0] bg-white text-[13px] font-medium text-[#1F2937]" />
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B] z-10" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
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
            </x-slot:actions>
        </x-page-header>

        {{-- ===== Stat cards ===== --}}
        @php
            $cards = [
                ['label' => 'Total Properties', 'value' => number_format($stats['properties']), 'sub' => 'Active properties', 'tint' => '#156F8C', 'box' => 'bg-[#156F8C]/10', 'delta' => null,
                 'icon' => 'M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6'],
                ['label' => 'Total Units', 'value' => number_format($stats['units']), 'sub' => 'All rental units', 'tint' => '#2AA7A1', 'box' => 'bg-[#2AA7A1]/10', 'delta' => null,
                 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
                ['label' => 'Occupied Units', 'value' => number_format($stats['occupied']), 'sub' => $stats['occupancyRate'] . '% occupancy rate', 'tint' => '#22C55E', 'box' => 'bg-[#22C55E]/10', 'delta' => null,
                 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
                ['label' => 'Revenue', 'value' => '₱' . number_format($stats['revenue'], 2), 'sub' => 'Collected this period', 'tint' => '#FF8A65', 'box' => 'bg-[#FF8A65]/10', 'delta' => $stats['revenueDelta'],
                 'icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Active Reservations', 'value' => number_format($stats['reservations']), 'sub' => 'Currently in progress', 'tint' => '#FBBF24', 'box' => 'bg-[#FBBF24]/10', 'delta' => $stats['reservationsDelta'],
                 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-4">
            @foreach($cards as $card)
                @php
                    $deltaSub = $card['delta'] !== null
                        ? ($card['delta'] >= 0 ? '▲ ' : '▼ ') . abs($card['delta']) . '% vs previous period'
                        : null;
                @endphp
                <x-stat-card :label="$card['label']" :value="$card['value']" :value-color="$card['tint']" :icon-bg="$card['tint'].'1A'"
                    :sub="$deltaSub ?? $card['sub']">
                    <x-slot:icon>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $card['tint'] }}" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                        </svg>
                    </x-slot:icon>
                </x-stat-card>
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
                <h2 class="text-[14px] font-bold text-[#1F2937] mb-3">Occupancy by Property</h2>
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

        {{-- ===== Row: Vacancy watch · Recent activity ===== =========
             Both are point-in-time, not range-filtered — hence the "As of
             today" chips. The range footer below must not read as covering
             them. Occupancy Overview and Occupancy by Property above are
             already point-in-time too, so this is the page's existing mix,
             not a new one. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-3">
            {{-- Vacancy Watch — moved here when landlord/occupancy was deleted.
                 That page's numbers duplicated this one's; this panel did not,
                 so it came across rather than going with the page. --}}
            <x-card flush class="p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Vacancy Watch</h2>
                    <span class="shrink-0 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#64748B]">As of today</span>
                </div>

                @if($vacancy['count'] === 0)
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="w-11 h-11 rounded-xl bg-[#22C55E]/[0.10] flex items-center justify-center mb-3">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#15803D" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                            </svg>
                        </div>
                        <p class="text-[13px] font-semibold text-[#1F2937]">No vacant units</p>
                        <p class="text-[11.5px] text-[#64748B] mt-1 max-w-[230px]">Every unit is reserved, occupied, or under maintenance.</p>
                    </div>
                @else
                    {{-- The headline: rent the portfolio is not earning. This is
                         the one figure that makes an empty unit feel like a cost
                         rather than a status. --}}
                    <div class="rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] px-4 py-3.5 mb-4">
                        <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rent not being earned</p>
                        <p class="text-[22px] font-extrabold text-[#B45309] mt-1 leading-none">
                            ₱{{ number_format($vacancy['idle_rent']) }}<span class="text-[12px] font-semibold text-[#64748B]"> / month</span>
                        </p>
                        <p class="text-[11.5px] text-[#64748B] mt-1.5">
                            Across {{ $vacancy['count'] }} vacant {{ Str::plural('unit', $vacancy['count']) }}
                        </p>
                    </div>

                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2.5">Empty longest</p>
                    <div class="space-y-2">
                        @foreach($vacancy['units'] as $unit)
                            @php
                                // Same 60-day threshold the admin unit catalogue uses for
                                // "stale", so one number defines a long vacancy app-wide.
                                $days = $unit['days'];
                                $tone = match (true) {
                                    $days >= 60 => ['bg-[#EF4444]/[0.07]', 'text-[#DC2626]'],
                                    $days >= 30 => ['bg-[#FBBF24]/[0.12]', 'text-[#B45309]'],
                                    default     => ['bg-[#F7FCFC]',        'text-[#64748B]'],
                                };
                            @endphp
                            <a href="{{ $unit['edit_url'] }}"
                                class="flex items-center gap-3 rounded-xl border border-[#E2E8F0] px-3 py-2.5 hover:bg-[#F7FCFC] transition-colors duration-150">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937] truncate">{{ $unit['label'] }}</p>
                                    <p class="text-[11px] text-[#64748B] truncate">{{ $unit['property'] }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone[0] }} {{ $tone[1] }}">
                                        {{ $days }}{{ $days === 1 ? ' day' : ' days' }}
                                    </span>
                                    <p class="text-[11px] text-[#64748B] mt-1">₱{{ number_format($unit['rent']) }}/mo</p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <p class="text-[11px] text-[#64748B] mt-3">
                        Counted from when the unit was last vacated, or from when it was listed if it has never been let.
                    </p>
                @endif
            </x-card>

            {{-- Recent Activity — a timeline: the connecting rule ties the
                 status dots into one sequence rather than four loose rows. --}}
            <x-card flush class="p-5">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Recent Activity</h2>
                    <span class="shrink-0 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#64748B]">As of today</span>
                </div>
                @if($recentActivities->isEmpty())
                    <p class="text-[12px] text-[#64748B] py-4 text-center">No occupancy changes recorded yet.</p>
                @else
                    <div class="relative">
                        {{-- Timeline rule, stopped short of the last dot's centre. --}}
                        <span class="absolute left-[13px] top-3 bottom-6 w-px bg-[#E2E8F0]" aria-hidden="true"></span>
                        <div class="space-y-3.5">
                            @foreach($recentActivities as $activity)
                                @php
                                    $st = $statusStyles[$activity->to_status] ?? $statusStyles['Available'];
                                    $person = $activity->tenant?->first_name
                                        ? trim($activity->tenant->first_name . ' ' . $activity->tenant->last_name)
                                        : ($activity->actor?->first_name ? trim($activity->actor->first_name . ' ' . $activity->actor->last_name) : null);
                                @endphp
                                <div class="relative flex items-start gap-3">
                                    <span class="w-[27px] h-[27px] rounded-full border flex items-center justify-center shrink-0 bg-white z-10 {{ $st['tile'] }}">
                                        <span class="w-2 h-2 rounded-full {{ $st['dot'] }}"></span>
                                    </span>
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <p class="text-[12.5px] text-[#1F2937] leading-snug">
                                            <span class="font-semibold">{{ $activity->unit?->unit_label ?? 'A unit' }}</span>
                                            in {{ $activity->property?->title ?? 'a property' }} {{ $st['verb'] }}
                                        </p>
                                        <p class="text-[11px] text-[#64748B] mt-0.5">
                                            @if($person)by {{ $person }} &middot; @endif{{ $activity->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
