@extends('layouts.landlord')

@section('page-title', 'Dashboard')

@section('content')
    @php
        $business = auth()->user()->rentalBusiness;
        $greetingName = $business->business_name ?? auth()->user()->first_name;

        // Guard divide-by-zero when the landlord has no units yet
        $occupiedPct = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

        // The four small bento tiles beside the rent card. Two sit on white, two on mist, so the row reads as
        // "money" then "pipeline" without needing borders or colour to say so.
        $tiles = $totalUnits > 0 ? [
            ['label' => 'Occupancy', 'value' => $occupiedPct . '%', 'note' => $occupiedUnits . ' of ' . $totalUnits . ' ' . Str::plural('unit', $totalUnits) . ' rented', 'href' => route('landlord.units.index', ['status' => 'Occupied']), 'mist' => false, 'dot' => null],
            ['label' => 'Monthly rent roll', 'value' => '₱' . number_format($rentRoll), 'note' => $occupiedUnits > 0 ? 'From ' . $occupiedUnits . ' rented ' . Str::plural('unit', $occupiedUnits) : 'No rented units yet', 'href' => route('landlord.payments.index'), 'mist' => false, 'dot' => null],
            ['label' => 'Vacant units', 'value' => $availableUnits, 'note' => $availableUnits > 0 ? '₱' . number_format($vacantRoll) . ' a month unrented' : 'Nothing sitting empty', 'href' => route('landlord.units.index', ['status' => 'Available']), 'mist' => true, 'dot' => '#22C55E'],
            ['label' => 'Open inquiries', 'value' => $openInquiries, 'note' => $openInquiries > 0 ? 'Waiting for your reply' : 'All answered', 'href' => route('landlord.reservations.index'), 'mist' => true, 'dot' => $openInquiries > 0 ? '#FBBF24' : null],
        ] : [];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- 1. Greeting row --}}
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                    {{-- Same icon tile as the other landlord page headers (x-page-header) --}}
                    <div class="w-11 h-11 rounded-xl bg-[#060D26] flex items-center justify-center shrink-0">
                        <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                        </svg>
                    </div>
                    <h1 class="min-w-0 font-sans text-[20px] sm:text-[22px] font-semibold text-[#060D26] truncate">
                        <span class="sm:hidden">{{ $greeting }}, {{ auth()->user()->first_name }}!</span>
                        <span class="hidden sm:inline">{{ $greeting }}, {{ $greetingName }}!</span>
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:shrink-0">
                    {{-- Phones already have the toggle in the top bar --}}
                    <x-theme-toggle class="hidden lg:flex w-10 h-10 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#ECEEF6]" />
                    <a href="{{ route('properties.create') }}"
                        class="flex-1 sm:flex-none shrink-0 inline-flex items-center justify-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold rounded-xl px-4 py-2.5 hover:bg-[#E96F4F] transition-colors duration-200">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        List a property
                    </a>
                </div>
            </div>
        </div>

        {{-- Setup alerts — only what blocks earning (unpaid-out, unlisted, rejected). Absent when all is well. --}}
        @if($setupAlerts->isNotEmpty())
            <ul class="mb-6 overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] divide-y divide-[#5B6A8E]/10" aria-label="Setup alerts">
                @foreach($setupAlerts as $alert)
                    @php
                        $alertTone = match($alert['level']) {
                            'danger' => 'bg-[#EF4444]',
                            'warn' => 'bg-[#FBBF24]',
                            default => 'bg-[#5B6A8E]',
                        };
                    @endphp
                    <li>
                        <a href="{{ $alert['url'] }}" class="group flex items-center gap-3 px-4 sm:px-5 py-3.5 hover:bg-[#F7F8FC] transition-colors duration-200">
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $alertTone }}" aria-hidden="true"></span>
                            <p class="min-w-0 flex-1 text-[14px] text-[#060D26]">{{ $alert['text'] }}</p>
                            <span class="inline-flex items-center gap-1 shrink-0 text-[13px] font-semibold text-[#B35A3D] group-hover:text-[#060D26] transition-colors duration-200">
                                <span class="hidden sm:inline">{{ $alert['cta'] }}</span>
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- 2. Bento overview. The navy rent card is the one thing that matters most, so it spans both rows;
             four quieter tiles sit beside it. Phones: rent card full width, tiles two-up beneath. --}}
        <div class="grid grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr] lg:grid-rows-2 gap-3.5 mb-8">

            {{-- Rent this month --}}
            <div class="col-span-2 lg:col-span-1 lg:row-span-2 flex flex-col rounded-2xl bg-[#060D26] p-5 sm:p-6 text-white">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12px] font-semibold text-white/70">Rent this month &middot; {{ now()->format('F') }}</p>
                    <a href="{{ route('landlord.payments.index') }}"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#FF8A66] hover:text-white transition-colors duration-200">
                        Ledger
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                @if($rent['tenancies'] === 0)
                    <p class="mt-4 text-[16px] font-semibold">No tenants yet</p>
                    <p class="mt-1 text-[13px] text-white/65">Rent tracking starts once a tenant moves in.</p>
                @elseif($rent['due'] <= 0)
                    <p class="mt-4 text-[16px] font-semibold">No rent due this month</p>
                    <p class="mt-1 text-[13px] text-white/65">{{ $rent['tenancies'] }} {{ Str::plural('tenancy', $rent['tenancies']) }} running.</p>
                @else
                    <p class="mt-3 text-[32px] sm:text-[36px] font-extrabold leading-none tabular-nums">&#8369;{{ number_format($rent['collected']) }}</p>
                    <p class="mt-1.5 text-[13px] text-white/65">of &#8369;{{ number_format($rent['due']) }} collected</p>
                    <div class="mt-4 h-2 rounded-full bg-white/15 overflow-hidden" role="progressbar"
                        aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rent['percent'] }}" aria-label="Rent collected this month">
                        <div class="h-full rounded-full bg-[#22C55E]" style="width: {{ $rent['percent'] }}%"></div>
                    </div>
                    <p class="mt-2 text-[12.5px] text-white/65">{{ $rent['percent'] }}% collected</p>
                @endif

                <div class="mt-auto pt-6 space-y-3">
                    @if($rent['overdueCount'] > 0)
                        <p class="text-[13.5px] font-bold text-[#FF8A66]">
                            &#8369;{{ number_format($rent['overdueAmount']) }} overdue
                            <span class="font-medium text-white/65">&middot; {{ $rent['overdueCount'] }} {{ Str::plural('tenant', $rent['overdueCount']) }}</span>
                        </p>
                    @endif
                    <a href="{{ route('landlord.payouts.index') }}"
                        class="flex items-center justify-between gap-2 border-t border-white/10 pt-3 text-[13px] text-white/70 hover:text-white transition-colors duration-200">
                        <span>Payout from AbangananHub</span>
                        <span class="font-semibold {{ $pendingPayout > 0 ? 'text-white' : '' }}">
                            {{ $pendingPayout > 0 ? '₱' . number_format($pendingPayout) . ' pending' : 'None pending' }}
                        </span>
                    </a>
                </div>
            </div>

            @if($totalUnits > 0)
                @foreach($tiles as $tile)
                    <a href="{{ $tile['href'] }}"
                        class="group rounded-2xl border p-4 sm:p-[18px] hover:border-[#5B6A8E]/40 active:bg-[#ECEEF6] transition-colors duration-200
                            {{ $tile['mist'] ? 'bg-[#ECEEF6] border-transparent' : 'bg-white border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)]' }}">
                        <p class="flex items-center gap-1.5 text-[12px] font-semibold text-[#5B6A8E]">
                            @if($tile['dot'])
                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $tile['dot'] }}" aria-hidden="true"></span>
                            @endif
                            {{ $tile['label'] }}
                        </p>
                        <p class="mt-2 text-[24px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $tile['value'] }}</p>
                        <p class="mt-1.5 text-[12px] text-[#5B6A8E]">{{ $tile['note'] }}</p>
                    </a>
                @endforeach
            @else
                <x-card class="col-span-2 lg:row-span-2 flex items-center">
                    <div>
                        <p class="text-[15px] font-semibold text-[#060D26]">No units yet</p>
                        <p class="mt-1 text-[13px] text-[#5B6A8E]">Add units to a property to start tracking occupancy and income.</p>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- 3. Your properties — a photo row: the emptiest listings first, since those are the ones worth a look. --}}
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="font-sans text-[17px] font-semibold text-[#060D26]">Your properties</h2>
                    @if($properties->isNotEmpty())
                        <span class="text-[12px] text-[#5B6A8E]">Emptiest first</span>
                    @endif
                </div>
                <a href="{{ route('landlord.properties.index') }}"
                    class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                    Manage all{{ $properties->count() > 5 ? ' ' . $properties->count() : '' }}
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>

            @if($properties->isEmpty())
                <x-card class="!p-8 text-center">
                    <div class="w-12 h-12 rounded-xl bg-[#ECEEF6] flex items-center justify-center mx-auto mb-3">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                        </svg>
                    </div>
                    <p class="text-[14px] font-semibold text-[#060D26]">List your first property</p>
                    <p class="text-[13px] text-[#5B6A8E] mt-1 mb-4">Approved properties will show up here with live occupancy stats.</p>
                    <a href="{{ route('properties.create') }}"
                        class="inline-flex items-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold rounded-xl px-4 py-2.5 hover:bg-[#E96F4F] transition-colors duration-200">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        List a property
                    </a>
                </x-card>
            @else
                <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($properties->take(5) as $property)
                        @php
                            $propTotal = max($property['total_units'], 1);
                            $propPct = $property['total_units'] > 0
                                ? round(($property['occupied_units'] / $propTotal) * 100)
                                : 0;
                            $rowAvailPct = round($property['available_units'] / $propTotal * 100);
                            $rowReservedPct = round($property['reserved_units'] / $propTotal * 100);
                            $rowOccupiedPct = round($property['occupied_units'] / $propTotal * 100);
                        @endphp
                        <li>
                            <a href="{{ route('landlord.properties.show', $property['property_id']) }}"
                                class="group block h-full overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] hover:border-[#5B6A8E]/40 active:bg-[#ECEEF6] transition-colors duration-200">
                                <div class="h-24 sm:h-28 overflow-hidden bg-[#ECEEF6]">
                                    @if($property['thumbnail'])
                                        <img loading="lazy" decoding="async" src="{{ $property['thumbnail'] }}" alt=""
                                            class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300 motion-reduce:transition-none">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center" aria-hidden="true">
                                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <p class="text-[13px] font-bold text-[#060D26] truncate group-hover:text-[#B35A3D] transition-colors duration-200">{{ $property['title'] }}</p>
                                    <p class="mt-1 text-[12px] text-[#5B6A8E]"><span class="font-bold text-[#060D26] tabular-nums">{{ $propPct }}%</span> occupied</p>

                                    {{-- One thin bar per property (Available → Reserved → Occupied); the exact split is in the tooltip and on the Units page. --}}
                                    <div class="mt-2 flex h-1.5 rounded-full bg-[#E2E4EC] overflow-hidden" role="img"
                                        title="{{ $property['available_units'] }} available, {{ $property['reserved_units'] }} reserved, {{ $property['occupied_units'] }} occupied"
                                        aria-label="{{ $property['available_units'] }} available, {{ $property['reserved_units'] }} reserved, {{ $property['occupied_units'] }} occupied">
                                        @if($property['available_units'] > 0)
                                            <div class="h-full bg-[#22C55E]" style="width: {{ $rowAvailPct }}%"></div>
                                        @endif
                                        @if($property['reserved_units'] > 0)
                                            <div class="h-full bg-[#FBBF24]" style="width: {{ $rowReservedPct }}%"></div>
                                        @endif
                                        @if($property['occupied_units'] > 0)
                                            <div class="h-full bg-[#EF4444]" style="width: {{ $rowOccupiedPct }}%"></div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- 4. Needs your attention · Recent activity, side by side. With nothing to do, the left column is a calm
             "all caught up" card instead of an empty list. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

            {{-- Needs your attention --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-sans text-[17px] font-semibold text-[#060D26]">Needs your attention</h2>
                    @if($actionQueue->isNotEmpty())
                        <span class="text-[12.5px] text-[#5B6A8E]">{{ $actionQueue->count() + $actionQueueOverflow }} open</span>
                    @endif
                </div>

                @if($actionQueue->isEmpty())
                    <x-card class="flex items-center gap-3 !py-4" role="status">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#22C55E]/10">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#15803D" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </span>
                        <p class="text-[13.5px] text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">You're all caught up.</span> No overdue rent, key handovers, inquiries or unread messages.</p>
                    </x-card>
                @else
                    <x-card flush>
                        <ul class="divide-y divide-[#5B6A8E]/10">
                            @foreach($actionQueue as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" title="{{ $item['cta'] }}"
                                        class="group flex items-center gap-3 px-4 py-3.5 hover:bg-[#F7F8FC] active:bg-[#ECEEF6] transition-colors duration-200">
                                        <span class="w-2 h-2 rounded-full shrink-0 {{ $item['level'] === 'danger' ? 'bg-[#EF4444]' : 'bg-[#FBBF24]' }}"></span>
                                        <span class="sr-only">{{ $item['level'] === 'danger' ? 'Urgent:' : 'Needs action:' }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[14px] font-semibold text-[#060D26] truncate">{{ $item['title'] }}</p>
                                            <p class="text-[12.5px] text-[#5B6A8E] truncate mt-0.5">{{ $item['meta'] }}</p>
                                        </div>
                                        <svg class="shrink-0 text-[#B35A3D] group-hover:text-[#060D26] transition-colors duration-200" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                        <span class="sr-only">{{ $item['cta'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if($actionQueueOverflow > 0)
                            <p class="border-t border-[#5B6A8E]/10 px-5 py-3 text-center text-[12.5px] text-[#5B6A8E]">and {{ $actionQueueOverflow }} more</p>
                        @endif
                    </x-card>
                @endif
            </div>

            {{-- Recent activity --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-sans text-[17px] font-semibold text-[#060D26]">Recent activity</h2>
                    <a href="{{ route('landlord.reservations.index') }}"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                        View all
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                <x-card flush>
                    @if($recentActivity->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12 px-5 text-center">
                            <div class="w-11 h-11 rounded-xl bg-[#ECEEF6] flex items-center justify-center mb-3">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <p class="text-[14px] font-semibold text-[#060D26]">No activity yet</p>
                            <p class="text-[13px] text-[#5B6A8E] mt-1">Payments, inquiries and reviews will show up here.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-[#5B6A8E]/10">
                            @foreach($recentActivity as $activity)
                                @php
                                    // A distinct icon per kind so the feed can be scanned by shape, not just read.
                                    [$kindLabel, $kindBg, $kindIcon] = match($activity['kind']) {
                                        'payment' => ['Payment', 'bg-[#22C55E]/10 text-[#15803D]', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
                                        'review' => ['Review', 'bg-[#FBBF24]/15 text-[#B45309]', 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z'],
                                        default => ['Reservation', 'bg-[#ECEEF6] text-[#060D26]', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                                    };
                                @endphp
                                <li>
                                    <a href="{{ $activity['url'] }}" class="flex items-start gap-3 px-4 py-3.5 hover:bg-[#F7F8FC] active:bg-[#ECEEF6] transition-colors duration-200">
                                        <span class="mt-0.5 w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $kindBg }}">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $kindIcon }}" />
                                            </svg>
                                            <span class="sr-only">{{ $kindLabel }}:</span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[14px] font-medium text-[#060D26] leading-snug">{{ $activity['text'] }}</p>
                                            @if($activity['meta'])
                                                <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] truncate">{{ $activity['meta'] }}</p>
                                            @endif
                                        </div>
                                        <span class="text-[12px] text-[#5B6A8E] whitespace-nowrap mt-0.5">
                                            {{ $activity['timestamp']->diffForHumans(['short' => true, 'parts' => 1]) }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        </div>

    </div>
@endsection
