@extends('layouts.admin')

@section('page-title', 'Dashboard')

@section('content')
    <div class="max-w-[1600px] mx-auto space-y-5">

        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-[#060D26]">Dashboard</h1>
            <p class="text-[13px] text-[#5B6A8E]">{{ \App\Support\BusinessTime::now()->format('l, F j, Y') }}</p>
        </div>

        {{-- What only the admin can clear. Rows keep their order and stay when empty, so the eye learns where each one sits. --}}
        <x-card flush>
            <div class="flex items-baseline justify-between gap-3 border-b border-[#E2E4EC] px-5 py-3.5">
                <h2 class="text-[15px] font-semibold text-[#060D26]">Needs you</h2>
                <p class="text-[13px] tabular-nums {{ $openItems > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">
                    {{ $openItems > 0 ? number_format($openItems).' open' : 'Nothing waiting' }}
                </p>
            </div>

            <ul class="divide-y divide-[#E2E4EC]">
                @foreach($queue as $row)
                    <li>
                        <a href="{{ $row['href'] }}"
                            class="group grid grid-cols-[1fr_3rem_auto] sm:grid-cols-[1fr_4rem_9rem_1rem] items-center gap-x-4 px-5 py-2.5 transition-colors duration-200 hover:bg-[#F7F8FC]">
                            <span class="min-w-0 truncate text-[14px] {{ $row['count'] > 0 ? 'font-semibold text-[#FF8A66]' : 'text-[#5B6A8E]' }}">{{ $row['label'] }}</span>
                            <span class="justify-self-end text-right text-[16px] tabular-nums {{ $row['count'] > 0 ? 'font-bold text-[#FF8A66]' : 'text-[#5B6A8E]' }}">{{ number_format($row['count']) }}</span>
                            <span class="hidden sm:block text-right text-[13px] {{ $row['count'] > 0 ? 'text-[#FF8A66]' : 'text-[#5B6A8E]' }}">
                                @if($row['oldest'])
                                    oldest {{ $row['oldest']->diffForHumans(null, true, true) }}
                                @endif
                            </span>
                            <svg class="h-4 w-4 text-[#5B6A8E] transition-transform duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-wrap items-center gap-x-8 gap-y-1 border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3 text-[13px] text-[#5B6A8E]">
                <a href="{{ route('admin.payments.index', ['status' => 'Held']) }}" class="transition-colors duration-200 hover:text-[#060D26]">
                    Money held <span class="ml-1 font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($moneyHeld, 2) }}</span>
                </a>
                <a href="{{ route('admin.payouts.index') }}" class="transition-colors duration-200 hover:text-[#060D26]">
                    Owed to landlords <span class="ml-1 font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($moneyOwed, 2) }}</span>
                </a>
            </div>
        </x-card>

        @php
            $queueCount = collect($queue)->pluck('count', 'label');
            $shortcuts = [
                ['Review landlords', $queueCount['Landlord verifications'] ?? 0, true, route('admin.verifications.index', ['status' => 'Pending']),
                    '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.5-1.5 1.6-2.2 3-2.2s2.5.7 3 2.2"/><path d="M14 11.5l1.6 1.6 3-3.2"/>'],
                ['Review properties', $queueCount['Property verifications'] ?? 0, true, route('admin.listings.approval', ['status' => 'Pending']),
                    '<path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v6"/><path d="M3 21h9"/><path d="M8 8h1M8 12h1M8 16h1M11 8h.5M11 12h.5"/><circle cx="17" cy="17" r="4"/><path d="M15.4 17l1.2 1.2 2-2.2"/>'],
                ['Review units', $queueCount['Unit approvals'] ?? 0, true, route('admin.units.index', ['status' => 'Pending']),
                    '<path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><path d="M3 21h18"/><path d="M14.5 12.5v.01"/>'],
                ['Manage users', $totalUsers, false, route('admin.users.index'),
                    '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c.6-3 2.7-4.7 5.5-4.7s4.9 1.7 5.5 4.7"/><path d="M15.5 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M17.5 14.9c1.6.6 2.6 2.2 3 4.6"/>'],
            ];
            $meterColours = ['In progress' => 'bg-[#FF8A66]', 'Occupied' => 'bg-[#060D26]'];
        @endphp
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

            {{-- Reservations by where they stand. Every row opens that list. --}}
            <x-card flush>
                <div class="flex items-baseline justify-between gap-3 px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Reservations</h2>
                    <p class="text-[13px] tabular-nums text-[#5B6A8E]">{{ number_format($reservationLedger['total']) }} total</p>
                </div>
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-y border-[#E2E4EC] bg-[#F7F8FC]">
                                <x-admin.th>Status</x-admin.th>
                                <x-admin.th align="right">Count</x-admin.th>
                                <x-admin.th align="right">Share</x-admin.th>
                                <th class="w-[30%] px-4 py-2.5"><span class="sr-only">Share of total, as a bar</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($reservationLedger['rows'] as $row)
                                <tr class="transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <td class="px-4 py-2.5 text-[13.5px]">
                                        <a href="{{ $row['href'] }}" class="{{ $row['count'] > 0 ? 'font-medium text-[#060D26]' : 'text-[#5B6A8E]' }} underline-offset-2 hover:underline">{{ $row['label'] }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-[13.5px] tabular-nums {{ $row['count'] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ number_format($row['count']) }}</td>
                                    <td class="px-4 py-2.5 text-right text-[13.5px] tabular-nums text-[#5B6A8E]">{{ $row['share'] }}%</td>
                                    <td class="px-4 py-2.5">
                                        <span class="block h-1.5 min-w-[60px] overflow-hidden rounded-sm bg-[#ECEEF6]">
                                            <span class="block h-full {{ $meterColours[$row['label']] ?? 'bg-[#5B6A8E]' }}" style="width: {{ $row['share'] }}%"></span>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card flush>
                <div class="px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Quick actions</h2>
                </div>
                <div class="grid grid-cols-2 gap-px border-t border-[#E2E4EC] bg-[#E2E4EC]">
                    @foreach($shortcuts as [$label, $count, $isQueue, $href, $icon])
                        <a href="{{ $href }}" class="bg-white px-5 py-5 transition-colors duration-200 hover:bg-[#F7F8FC]">
                            <span class="block text-[26px] font-bold leading-none tabular-nums {{ $isQueue && $count > 0 ? 'text-[#FF8A66]' : ($isQueue ? 'text-[#5B6A8E]' : 'text-[#060D26]') }}">{{ number_format($count) }}</span>
                            <span class="mt-2.5 flex items-center gap-2 text-[13.5px] font-medium text-[#060D26]">
                                <svg class="h-5 w-5 shrink-0 text-[#5B6A8E]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                {{ $label }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-card>
        </div>

        <x-stat-strip :cells="[
            ['label' => 'Users', 'value' => number_format($totalUsers), 'href' => route('admin.users.index')],
            ['label' => 'Verified landlords', 'value' => number_format($verifiedLandlords), 'href' => route('admin.verifications.index', ['status' => 'Approved'])],
            ['label' => 'Properties', 'value' => number_format($totalProperties), 'href' => route('admin.catalogue.properties.index')],
            ['label' => 'Units', 'value' => number_format($totalUnits), 'href' => route('admin.catalogue.units.index')],
            ['label' => 'Active reservations', 'value' => number_format($activeReservations), 'href' => route('admin.reservations.index')],
        ]" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <x-card class="lg:col-span-7">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <div>
                        <h2 class="text-[15px] font-semibold text-[#060D26]">Activity</h2>
                        <p class="text-[13px] text-[#5B6A8E]">New in the last 7 days</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-[#5B6A8E]">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#060D26]"></span>Users</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#FF8A66]"></span>Properties</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#5B6A8E]"></span>Reservations</span>
                    </div>
                </div>
                <div class="h-56">
                    <canvas id="platformChart"></canvas>
                </div>
            </x-card>

            <x-card flush class="lg:col-span-5">
                <div class="flex items-baseline justify-between gap-3 border-b border-[#E2E4EC] px-5 py-3.5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Recent reservations</h2>
                    <a href="{{ route('admin.reservations.index') }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">All</a>
                </div>
                @if($recentReservations->isEmpty())
                    <x-admin.empty title="No reservations yet" />
                @else
                    <ul class="divide-y divide-[#E2E4EC]">
                        @foreach($recentReservations as $res)
                            <li>
                                <a href="{{ route('admin.reservations.show', $res) }}" class="flex items-center gap-3 px-5 py-2.5 transition-colors duration-200 hover:bg-[#F7F8FC]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $res->tenant?->first_name }} {{ $res->tenant?->last_name }}</span>
                                        <span class="block truncate text-[12.5px] text-[#5B6A8E]">{{ $res->property?->title ?? '—' }}</span>
                                    </span>
                                    <x-admin.status :status="$res->rental_status" class="shrink-0" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/charts.js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const platformCtx = document.getElementById('platformChart');
            if (! platformCtx) return;

            const isDark = document.documentElement.classList.contains('dark');
            const series = (label, data, color) => ({
                label,
                data,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 4,
                tension: 0,
                fill: false,
            });

            new Chart(platformCtx, {
                type: 'line',
                data: {
                    labels: {!! $chartLabels->toJson() !!},
                    datasets: [
                        series('Users', {!! $chartUsers->toJson() !!}, isDark ? '#F1F3FA' : '#060D26'),
                        series('Properties', {!! $chartProperties->toJson() !!}, '#FF8A66'),
                        series('Reservations', {!! $chartReservations->toJson() !!}, isDark ? '#9AA6C6' : '#5B6A8E'),
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#060D26',
                            titleColor: '#fff',
                            bodyColor: 'rgba(255,255,255,0.8)',
                            padding: 10,
                            cornerRadius: 8,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: isDark ? 'rgba(255,255,255,0.25)' : 'rgba(6,13,38,0.25)' },
                            ticks: { color: isDark ? '#E8ECF8' : '#060D26', font: { size: 13, weight: '500' }, maxRotation: 0, padding: 10 },
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: 4,
                            grid: { color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(6,13,38,0.06)' },
                            border: { display: false },
                            ticks: { color: isDark ? '#E8ECF8' : '#060D26', font: { size: 13 }, precision: 0, padding: 8 },
                        },
                    },
                },
            });
        });
    </script>
@endpush
