@extends('layouts.landlord')

@section('page-title', 'Dashboard')

@section('content')
    @php
        $business = auth()->user()->rentalBusiness;
        $greetingName = $business->business_name ?? auth()->user()->first_name;

        // Guard divide-by-zero when the landlord has no units yet
        $occupiedPct = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

        $attentionCount = $actionQueue->count() + $actionQueueOverflow;
        $unreadNotes = $notifications->where('is_read', false)->count();
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

        {{-- Setup alerts: only what blocks earning (unpaid-out, unlisted, rejected). Absent when all is well.
             A tinted row per alert: an icon for the level, the sentence, and a button. Blockers (danger, warn) get the
             coral button; a plain status like "waiting for admin approval" gets a quiet outline one. --}}
        @if($setupAlerts->isNotEmpty())
            <ul class="mb-5 space-y-2" aria-label="Setup alerts">
                @foreach($setupAlerts as $alert)
                    @php
                        [$box, $iconBox, $iconPath, $button] = match($alert['level']) {
                            'danger' => [
                                'border-[#EF4444]/25 bg-[#EF4444]/[0.06]',
                                'bg-[#EF4444]/12 text-[#B91C1C]',
                                'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                                'bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F]',
                            ],
                            'warn' => [
                                'border-[#FBBF24]/40 bg-[#FBBF24]/10',
                                'bg-[#FBBF24]/20 text-[#92400E]',
                                'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
                                'bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F]',
                            ],
                            default => [
                                'border-[#E2E4EC] bg-white',
                                'bg-[#ECEEF6] text-[#060D26]',
                                'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                                'border border-[#060D26]/20 bg-white text-[#060D26] hover:border-[#FF8A66]',
                            ],
                        };
                    @endphp
                    <li>
                        <a href="{{ $alert['url'] }}"
                            class="group flex items-center gap-3 rounded-2xl border px-3.5 py-2.5 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/60 sm:px-4 {{ $box }}">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $iconBox }}" aria-hidden="true">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
                                </svg>
                            </span>
                            <p class="min-w-0 flex-1 text-[14px] font-medium text-[#060D26]">{{ $alert['text'] }}</p>
                            <span class="inline-flex min-h-[36px] shrink-0 items-center gap-1 rounded-full px-3.5 text-[12.5px] font-semibold transition-colors duration-200 {{ $button }}">
                                {{ $alert['cta'] }}
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- 2. The numbers, in one navy band. Rent this month leads; overdue, occupancy, vacant units and inquiries follow.
             Phones: rent full width, the other four two-up beneath. --}}
        <div class="mb-5 grid grid-cols-2 gap-y-4 rounded-2xl bg-[#060D26] py-4 text-white lg:grid-cols-[1.6fr_1fr_1fr_1fr_1fr] lg:gap-y-0">

            {{-- Rent this month --}}
            <div class="col-span-2 min-w-0 border-b border-white/10 px-4 pb-4 sm:px-5 lg:col-span-1 lg:border-b-0 lg:pb-0">
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
                    <p class="mt-1.5 text-[16px] font-semibold">No tenants yet</p>
                    <p class="text-[12.5px] text-white/65">Rent tracking starts once a tenant moves in.</p>
                @elseif($rent['due'] <= 0)
                    <p class="mt-1.5 text-[16px] font-semibold">No rent due this month</p>
                    <p class="text-[12.5px] text-white/65">{{ $rent['tenancies'] }} {{ Str::plural('tenancy', $rent['tenancies']) }} running.</p>
                @else
                    <p class="mt-1 text-[24px] font-bold leading-tight tabular-nums">
                        &#8369;{{ number_format($rent['collected']) }}
                        <span class="text-[12.5px] font-medium text-white/65">of &#8369;{{ number_format($rent['due']) }} collected</span>
                    </p>
                    <div class="mt-2 flex items-center gap-2.5">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/15" role="progressbar"
                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rent['percent'] }}" aria-label="Rent collected this month">
                            <div class="h-full rounded-full bg-[#22C55E]" style="width: {{ $rent['percent'] }}%"></div>
                        </div>
                        <span class="text-[12px] tabular-nums text-white/70">{{ $rent['percent'] }}%</span>
                    </div>
                @endif

                @if(auth()->user()->showsPayoutTools())
                    <a href="{{ route('landlord.payouts.index') }}" class="mt-2 block text-[12px] text-white/65 hover:text-white transition-colors duration-200">
                        Payout from AbangananHub:
                        <span class="font-semibold {{ $pendingPayout > 0 ? 'text-white' : '' }}">{{ $pendingPayout > 0 ? '₱' . number_format($pendingPayout) . ' pending' : 'none pending' }}</span>
                    </a>
                @endif
            </div>

            {{-- Overdue --}}
            <a href="{{ route('landlord.payments.index') }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                <p class="text-[12px] font-semibold text-white/70">Overdue</p>
                @if($rent['overdueCount'] > 0)
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums text-[#FF8A66]">&#8369;{{ number_format($rent['overdueAmount']) }}</p>
                    <p class="text-[12px] text-white/65">{{ $rent['overdueCount'] }} {{ Str::plural('tenant', $rent['overdueCount']) }}</p>
                @else
                    <p class="mt-0.5 text-[22px] font-bold leading-tight">None</p>
                    <p class="text-[12px] text-white/65">Everyone is paid up</p>
                @endif
            </a>

            @if($totalUnits > 0)
                <a href="{{ route('landlord.units.index', ['status' => 'Occupied']) }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                    <p class="text-[12px] font-semibold text-white/70">Occupancy</p>
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums">{{ $occupiedPct }}%</p>
                    <p class="text-[12px] text-white/65">{{ $occupiedUnits }} of {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }} rented</p>
                </a>
                <a href="{{ route('landlord.units.index', ['status' => 'Available']) }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                    <p class="text-[12px] font-semibold text-white/70">Vacant units</p>
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums">{{ $availableUnits }}</p>
                    <p class="text-[12px] text-white/65">{{ $availableUnits > 0 ? '₱' . number_format($vacantRoll) . ' a month unrented' : 'Nothing sitting empty' }}</p>
                </a>
                <a href="{{ route('landlord.reservations.index') }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                    <p class="text-[12px] font-semibold text-white/70">Open inquiries</p>
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums">{{ $openInquiries }}</p>
                    <p class="text-[12px] text-white/65">{{ $openInquiries > 0 ? 'Waiting for your reply' : 'All answered' }}</p>
                </a>
            @else
                <div class="col-span-2 min-w-0 px-4 sm:px-5 lg:col-span-3 lg:border-l lg:border-white/10">
                    <p class="text-[14px] font-semibold">No units yet</p>
                    <p class="mt-0.5 text-[12.5px] text-white/65">Add units to a property to start tracking occupancy and income.</p>
                </div>
            @endif
        </div>

        {{-- 3. Work and properties, in two columns that end level. Left: what needs you, then the latest notifications.
             Right: the properties, then viewings. Phones: one column in that order. --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.45fr_1fr] lg:items-stretch">

            <div class="flex min-w-0 flex-col gap-4">

                {{-- Needs your attention: overdue rent, key handovers, inquiries, unread messages. --}}
                <x-card flush>
                    <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-2 sm:px-5">
                        <div class="flex items-baseline gap-2">
                            <h2 class="font-sans text-[15px] font-semibold text-[#060D26]">Needs your attention</h2>
                            @if($attentionCount > 0)
                                <span class="text-[12px] text-[#5B6A8E]">{{ $attentionCount }} open</span>
                            @endif
                        </div>
                    </div>

                    @if($actionQueue->isEmpty())
                        <div class="flex items-center gap-3 border-t border-[#5B6A8E]/10 px-4 py-4 sm:px-5" role="status">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#22C55E]/10">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#15803D" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            <p class="text-[13.5px] text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">You're all caught up.</span> No overdue rent, key handovers, inquiries or unread messages.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-[#5B6A8E]/10 border-t border-[#5B6A8E]/10">
                            @foreach($actionQueue as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" title="{{ $item['cta'] }}"
                                        class="group flex items-center gap-3 px-4 py-3 hover:bg-[#F7F8FC] active:bg-[#ECEEF6] transition-colors duration-200 sm:px-5">
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
                    @endif
                </x-card>

                {{-- Notifications: the bell's own rows. Each opens the page it is about and is marked read on the way.
                     On desktop the card takes the height left over and the list scrolls inside it. --}}
                <x-card flush class="flex min-h-0 flex-col lg:flex-1">
                    <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-2 sm:px-5">
                        <div class="flex items-baseline gap-2">
                            <h2 class="font-sans text-[15px] font-semibold text-[#060D26]">Notifications</h2>
                            @if($unreadNotes > 0)
                                <span class="text-[12px] text-[#5B6A8E]">{{ $unreadNotes }} new</span>
                            @endif
                        </div>
                        <a href="{{ route('notifications.index') }}"
                            class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                            View all
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>

                    @if($notifications->isEmpty())
                        <div class="flex flex-1 flex-col items-center justify-center border-t border-[#5B6A8E]/10 py-10 px-5 text-center">
                            <div class="w-11 h-11 rounded-xl bg-[#ECEEF6] flex items-center justify-center mb-3">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                            </div>
                            <p class="text-[14px] font-semibold text-[#060D26]">Nothing new</p>
                            <p class="text-[13px] text-[#5B6A8E] mt-1">Payments, inquiries and reviews will show up here.</p>
                        </div>
                    @else
                        <div class="divide-y divide-[#5B6A8E]/10 border-t border-[#5B6A8E]/10 lg:min-h-[150px] lg:flex-1 lg:basis-0 lg:overflow-y-auto">
                            {{-- A phone shows the latest three; "View all" has the rest. --}}
                            @foreach($notifications as $n)
                                <div class="{{ $loop->iteration > 3 ? 'max-lg:hidden' : '' }}">
                                    @include('notifications.partials._row', ['n' => $n, 'wrap' => true])
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            </div>

            <div class="flex min-w-0 flex-col gap-4">

                {{-- Your properties: the emptiest listings first, since those are the ones worth a look. --}}
                <x-card flush class="lg:flex-1">
                    <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-2 sm:px-5">
                        <div class="flex min-w-0 items-baseline gap-2">
                            <h2 class="font-sans text-[15px] font-semibold text-[#060D26]">Your properties</h2>
                            @if($properties->isNotEmpty())
                                <span class="text-[12px] text-[#5B6A8E]">Emptiest first</span>
                            @endif
                        </div>
                        <a href="{{ route('landlord.properties.index') }}"
                            class="inline-flex shrink-0 items-center gap-1 text-[12.5px] font-semibold text-[#B35A3D] hover:text-[#060D26] transition-colors duration-200">
                            Manage all{{ $properties->count() > 8 ? ' ' . $properties->count() : '' }}
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>

                    @if($properties->isEmpty())
                        <div class="border-t border-[#5B6A8E]/10 px-5 pb-6 pt-5 text-center">
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
                        </div>
                    @else
                        <ul class="divide-y divide-[#5B6A8E]/10 border-t border-[#5B6A8E]/10">
                            @foreach($properties->take(8) as $property)
                                @php
                                    $propTotal = max($property['total_units'], 1);
                                    $propPct = $property['total_units'] > 0 ? round(($property['occupied_units'] / $propTotal) * 100) : 0;
                                    $rowReservedPct = round($property['reserved_units'] / $propTotal * 100);
                                    $rowOccupiedPct = round($property['occupied_units'] / $propTotal * 100);
                                @endphp
                                <li class="{{ $loop->iteration > 5 ? 'max-lg:hidden' : '' }}">
                                    <a href="{{ route('landlord.properties.show', $property['property_id']) }}"
                                        class="group flex items-center gap-3 px-4 py-2.5 hover:bg-[#F7F8FC] active:bg-[#ECEEF6] transition-colors duration-200 sm:px-5">
                                        <span class="h-10 w-10 shrink-0 overflow-hidden rounded-[10px] bg-[#ECEEF6]">
                                            @if($property['thumbnail'])
                                                <img loading="lazy" decoding="async" src="{{ $property['thumbnail'] }}" alt="" width="40" height="40" class="h-full w-full object-cover">
                                            @else
                                                <span class="flex h-full w-full items-center justify-center" aria-hidden="true">
                                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[13px] font-semibold text-[#060D26] group-hover:text-[#B35A3D] transition-colors duration-200">{{ $property['title'] }}</span>
                                            {{-- Occupied (navy) then reserved (amber) on a quiet track; the exact split is in the tooltip and on the Units page. --}}
                                            <span class="mt-1.5 flex items-center gap-2">
                                                <span class="flex h-1.5 flex-1 overflow-hidden rounded-full bg-[#ECEEF6]" role="img"
                                                    title="{{ $property['available_units'] }} available, {{ $property['reserved_units'] }} reserved, {{ $property['occupied_units'] }} occupied"
                                                    aria-label="{{ $property['available_units'] }} available, {{ $property['reserved_units'] }} reserved, {{ $property['occupied_units'] }} occupied">
                                                    @if($property['occupied_units'] > 0)
                                                        <span class="h-full bg-[#060D26]" style="width: {{ $rowOccupiedPct }}%"></span>
                                                    @endif
                                                    @if($property['reserved_units'] > 0)
                                                        <span class="h-full bg-[#FBBF24]" style="width: {{ $rowReservedPct }}%"></span>
                                                    @endif
                                                </span>
                                                <span class="shrink-0 text-[11.5px] tabular-nums text-[#5B6A8E]">{{ $property['occupied_units'] }} of {{ $property['total_units'] }}</span>
                                            </span>
                                        </span>
                                        <span class="w-10 shrink-0 text-right text-[13px] font-bold tabular-nums text-[#060D26]">{{ $propPct }}%</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                {{-- Viewings: the next one, the count, and the calendar behind a click. --}}
                <x-card flush>
                    @include('landlord.dashboard._viewings', ['embedded' => true])
                </x-card>
            </div>
        </div>

    </div>
@endsection
