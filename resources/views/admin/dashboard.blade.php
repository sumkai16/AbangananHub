@extends('layouts.admin')

@section('page-title', 'Dashboard')

@section('content')
@php
    $peso = fn (float $amount) => '₱'.number_format($amount, fmod($amount, 1) ? 2 : 0);
    $barColours = ['In progress' => 'bg-[#FBBF24]', 'Occupied' => 'bg-[#060D26]', 'Completed' => 'bg-[#22C55E]', 'Cancelled' => 'bg-[#94A3B8]', 'Rejected' => 'bg-[#EF4444]'];
    $totals = [
        ['Money held', $peso($moneyHeld), route('admin.payments.index', ['status' => 'Held'])],
        ['Owed to landlords', $peso($moneyOwed), route('admin.payouts.index')],
        ['Users', number_format($totalUsers), route('admin.users.index')],
        ['Verified landlords', number_format($verifiedLandlords), route('admin.verifications.index', ['status' => 'Approved'])],
        ['Properties', number_format($totalProperties), route('admin.catalogue.properties.index')],
        ['Units', number_format($totalUnits), route('admin.catalogue.units.index')],
        ['Active reservations', number_format($activeReservations), route('admin.reservations.index')],
    ];
@endphp
    <div class="admin-compact max-w-[1600px] mx-auto space-y-3">

        <div class="flex flex-wrap items-baseline justify-between gap-x-4">
            <h1 class="text-[20px] font-semibold leading-7 tracking-tight text-[#060D26]">Dashboard</h1>
            <p class="text-[13px] text-[#5B6A8E]">{{ \App\Support\BusinessTime::now()->format('l, F j, Y') }}</p>
        </div>

        {{-- What only the admin can clear, one cell per queue. Cells keep their place and stay when empty, so the eye learns where each one sits. --}}
        <x-card flush>
            <div class="flex items-center justify-between gap-3 border-b border-[#E2E4EC] px-4 py-2">
                <h2 class="text-[14px] font-semibold text-[#060D26]">Needs you</h2>
                <p class="text-[13px] tabular-nums {{ $openItems > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">
                    {{ $openItems > 0 ? number_format($openItems).' open' : 'Nothing waiting' }}
                </p>
            </div>
            <ul class="grid grid-cols-2 gap-px bg-[#E2E4EC] sm:grid-cols-3 xl:grid-cols-6">
                @foreach($queue as $row)
                    <li class="bg-white">
                        <a href="{{ $row['href'] }}" class="block h-full px-4 py-2.5 transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC]">
                            <span class="flex items-center gap-1.5 text-[12.5px] {{ $row['count'] > 0 ? 'font-medium text-[#060D26]' : 'text-[#5B6A8E]' }}">
                                @if($row['count'] > 0)<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#FF8A66]" aria-hidden="true"></span>@endif
                                <span class="truncate">{{ $row['label'] }}</span>
                            </span>
                            <span class="mt-0.5 flex items-baseline gap-2">
                                <span class="text-[22px] leading-7 tabular-nums {{ $row['count'] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ number_format($row['count']) }}</span>
                                @if($row['oldest'])
                                    <span class="text-[12px] text-[#B45309]">oldest {{ $row['oldest']->diffForHumans(null, true, true) }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>

        {{-- The platform in one row: money first, then how big it is. Every figure opens its list. --}}
        <x-card flush>
            <ul class="grid grid-cols-2 gap-px bg-[#E2E4EC] sm:grid-cols-4 xl:grid-cols-7">
                @foreach($totals as [$label, $value, $href])
                    <li class="bg-white">
                        <a href="{{ $href }}" class="block h-full px-4 py-2 transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC]">
                            <span class="block truncate text-[12px] text-[#5B6A8E]">{{ $label }}</span>
                            <span class="block text-[16px] font-semibold leading-6 tabular-nums text-[#060D26]">{{ $value }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-12">

            {{-- Activity --}}
            <x-card class="lg:col-span-2 xl:col-span-5">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">New in the last 7 days</h2>
                    <div class="flex flex-wrap items-center gap-x-3 text-[12px] text-[#5B6A8E]">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#060D26]"></span>Users</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#FF8A66]"></span>Properties</span>
                        <span class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-3 bg-[#5B6A8E]"></span>Reservations</span>
                    </div>
                </div>
                <div class="h-52"><canvas id="platformChart" role="img" aria-label="New users, properties and reservations per day for the last 7 days"></canvas></div>
            </x-card>

            {{-- Reservations by where they stand. Every row opens that list. --}}
            <x-card flush class="xl:col-span-3">
                <div class="flex items-baseline justify-between gap-3 px-4 pb-2 pt-3">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Reservations</h2>
                    <p class="text-[12.5px] tabular-nums text-[#5B6A8E]">{{ number_format($reservationLedger['total']) }} total</p>
                </div>
                <div class="mx-4 mb-2 flex h-2 gap-px overflow-hidden rounded-full bg-[#ECEEF6]" role="img" aria-label="Share of reservations by status">
                    @foreach($reservationLedger['rows'] as $row)
                        @if($row['share'] > 0)
                            <span class="block h-full {{ $barColours[$row['label']] }}" style="width: {{ $row['share'] }}%"></span>
                        @endif
                    @endforeach
                </div>
                <ul>
                    @foreach($reservationLedger['rows'] as $row)
                        <li>
                            <a href="{{ $row['href'] }}" class="flex h-[34px] items-center gap-2 px-4 text-[13px] transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC]">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $barColours[$row['label']] }}" aria-hidden="true"></span>
                                <span class="flex-1 truncate {{ $row['count'] > 0 ? 'font-medium text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $row['label'] }}</span>
                                <span class="tabular-nums {{ $row['count'] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ number_format($row['count']) }}</span>
                                <span class="w-9 text-right text-[12px] tabular-nums text-[#5B6A8E]">{{ $row['share'] }}%</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{-- Recent reservations --}}
            <x-card flush class="xl:col-span-4">
                <div class="flex items-baseline justify-between gap-3 px-4 pb-2 pt-3">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Recent reservations</h2>
                    <a href="{{ route('admin.reservations.index') }}" class="text-[12.5px] font-semibold text-[#060D26] underline-offset-2 hover:underline">All</a>
                </div>
                @if($recentReservations->isEmpty())
                    <x-admin.empty title="No reservations yet" />
                @else
                    <ul class="divide-y divide-[#E2E4EC] border-t border-[#E2E4EC]">
                        @foreach($recentReservations->take(5) as $res)
                            <li>
                                <a href="{{ route('admin.reservations.show', $res) }}" class="flex items-center gap-3 px-4 py-1.5 transition-colors duration-200 hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13px] font-medium leading-5 text-[#060D26]">{{ $res->tenant?->first_name }} {{ $res->tenant?->last_name }}</span>
                                        <span class="block truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $res->property?->title ?? '—' }}</span>
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
                pointRadius: 2,
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
                    animation: false,
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
                            ticks: { color: isDark ? '#E8ECF8' : '#060D26', font: { size: 12 }, maxRotation: 0, padding: 6 },
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: 4,
                            grid: { color: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(6,13,38,0.06)' },
                            border: { display: false },
                            ticks: { color: isDark ? '#E8ECF8' : '#060D26', font: { size: 12 }, precision: 0, padding: 6 },
                        },
                    },
                },
            });
        });
    </script>
@endpush
