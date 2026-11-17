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
        @push('mobile-title')
            <span class="truncate text-[15px] font-semibold tracking-tight text-[#060D26]">{{ $greeting }}, {{ auth()->user()->first_name }}!</span>
        @endpush
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
                        class="flex-1 sm:flex-none shrink-0 inline-flex items-center justify-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold rounded-xl px-4 min-h-11 hover:bg-[#E96F4F] transition-colors duration-200">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        List a property
                    </a>
                </div>
            </div>
        </div>

        {{-- Setup alerts: what blocks earning, and what is only waiting. Absent when all is well. Desktop groups them
             ("Fix now" with one coral button for the first thing, "Waiting on admin" quiet). A phone gets one summary
             card with the first thing to do and the rest one tap away. Each row links to the page that answers it
             (DashboardController::setupAlerts). --}}
        @if($setupAlerts->isNotEmpty())
            @php
                $fix = $setupAlerts->where('group', 'fix')->values();
                $waiting = $setupAlerts->where('group', 'waiting')->values();
                $total = $setupAlerts->count();
                $lead = $fix->first() ?? $waiting->first();
                $rest = $setupAlerts->reject(fn ($a) => $a === $lead)->values();
                $urgent = $fix->isNotEmpty();
            @endphp
            <section aria-label="Setup alerts" class="mb-5">
                {{-- Desktop and tablet --}}
                {{-- Each group folds from its header. Open by default; a folded group stays folded on the next visit. --}}
                <div class="hidden space-y-4 sm:block"
                    x-data="{
                        shut: {},
                        init() { try { this.shut = JSON.parse(localStorage.getItem('dashAlertsShut') || '{}') || {}; } catch (e) { this.shut = {}; } },
                        toggle(k) { this.shut[k] = ! this.shut[k]; try { localStorage.setItem('dashAlertsShut', JSON.stringify(this.shut)); } catch (e) {} },
                    }">
                    @if($fix->isNotEmpty())
                        <div>
                            <button type="button" @click="toggle('fix')" :aria-expanded="(! shut.fix).toString()" aria-controls="alerts-fix"
                                class="group mb-2 flex w-full cursor-pointer items-center gap-2 rounded-lg py-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/60">
                                <span class="h-2 w-2 rounded-full bg-[#DC2626]" aria-hidden="true"></span>
                                <span class="text-[12px] font-bold uppercase tracking-[0.06em] text-[#991B1B] dark:text-[#FCA5A5]">Fix now</span>
                                <span class="text-[12px] text-[#5B6A8E]">{{ $fix->count() }} {{ Str::plural('thing', $fix->count()) }} holding back your income</span>
                                <svg class="ml-auto h-4 w-4 text-[#5B6A8E] transition-transform duration-200 group-hover:text-[#060D26]" :class="shut.fix ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <ul id="alerts-fix" x-show="! shut.fix" class="divide-y divide-[#E2E4EC] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white">
                                @foreach($fix as $alert)
                                    <li>
                                        <a href="{{ $alert['url'] }}" class="group flex items-center gap-3 px-4 py-3 transition-colors duration-200 hover:bg-[#F7F8FC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#FF8A66]/60">
                                            <p class="min-w-0 flex-1 text-[14px] text-[#060D26]"><span class="font-bold group-hover:underline">{{ $alert['title'] }}</span> <span class="text-[#46537A] dark:text-[#94A3B8]">&middot; {{ $alert['sub'] }}</span></p>
                                            @if($loop->first)
                                                <span class="inline-flex min-h-[34px] shrink-0 items-center rounded-lg bg-[#FF8A66] px-3.5 text-[12.5px] font-bold text-[#060D26] transition-colors duration-200 group-hover:bg-[#E96F4F]">{{ $alert['cta'] }}</span>
                                            @else
                                                <span class="shrink-0 text-[12.5px] font-bold text-[#B35A3D]">{{ $alert['cta'] }} &rsaquo;</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($waiting->isNotEmpty())
                        <div>
                            <button type="button" @click="toggle('waiting')" :aria-expanded="(! shut.waiting).toString()" aria-controls="alerts-waiting"
                                class="group mb-2 flex w-full cursor-pointer items-center gap-2 rounded-lg py-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/60">
                                <span class="h-2 w-2 rounded-full bg-[#9AA6C2]" aria-hidden="true"></span>
                                <span class="text-[12px] font-bold uppercase tracking-[0.06em] text-[#46537A] dark:text-[#94A3B8]">Waiting on admin</span>
                                <span class="text-[12px] text-[#5B6A8E]">nothing to do yet</span>
                                <svg class="ml-auto h-4 w-4 text-[#5B6A8E] transition-transform duration-200 group-hover:text-[#060D26]" :class="shut.waiting ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <ul id="alerts-waiting" x-show="! shut.waiting" class="divide-y divide-[#E2E4EC] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white">
                                @foreach($waiting as $alert)
                                    <li>
                                        <a href="{{ $alert['url'] }}" class="group flex items-center gap-3 px-4 py-3 transition-colors duration-200 hover:bg-[#F7F8FC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#FF8A66]/60">
                                            <p class="min-w-0 flex-1 text-[13.5px] text-[#46537A]"><span class="font-bold text-[#060D26] group-hover:underline">{{ $alert['title'] }}</span></p>
                                            <span class="shrink-0 text-[12.5px] font-bold text-[#B35A3D]">{{ $alert['cta'] }} &rsaquo;</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Phone: the first thing to do, the rest behind one tap. --}}
                <div class="sm:hidden" x-data="{ open: false }">
                    <div class="overflow-hidden rounded-2xl border {{ $urgent ? 'border-[#FCA5A5] bg-[#FEF2F2]' : 'border-[#E2E4EC] bg-white' }}">
                        <div class="flex flex-col gap-2.5 px-3.5 pb-3 pt-3.5">
                            <p class="text-[11.5px] font-bold uppercase tracking-[0.05em] {{ $urgent ? 'text-[#991B1B] dark:text-[#FCA5A5]' : 'text-[#46537A] dark:text-[#94A3B8]' }}">{{ $total }} {{ Str::plural('thing', $total) }} {{ $total === 1 ? 'needs' : 'need' }} you</p>
                            <p class="text-[15px] font-bold leading-snug text-[#060D26]">{{ $lead['title'] }}</p>
                            <p class="-mt-1 text-[12.5px] text-[#46537A]">{{ $lead['sub'] }}</p>
                            <a href="{{ $lead['url'] }}" class="flex h-11 items-center justify-center rounded-xl text-[14px] font-bold text-[#060D26] transition-colors duration-200 {{ $urgent ? 'bg-[#FF8A66] hover:bg-[#E96F4F]' : 'border border-[#060D26]/25 bg-white' }}">{{ $lead['cta'] }}</a>
                        </div>
                        @if($rest->isNotEmpty())
                            <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                                class="flex h-11 w-full items-center justify-center gap-1.5 border-t text-[13px] font-bold transition-colors duration-200 {{ $urgent ? 'border-[#FCA5A5] bg-[#FEE2E2] text-[#7F1D1D]' : 'border-[#E2E4EC] bg-[#F7F8FC] text-[#46537A]' }}">
                                <span x-text="open ? 'Show less' : 'Show the other {{ $rest->count() }}'">Show the other {{ $rest->count() }}</span>
                                <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <ul x-show="open" x-cloak class="divide-y divide-[#E2E4EC] border-t border-[#E2E4EC] bg-white">
                                @foreach($rest as $alert)
                                    <li>
                                        <a href="{{ $alert['url'] }}" class="flex min-h-[56px] items-center gap-3 px-3.5 py-2.5">
                                            <span class="min-w-0 flex-1"><span class="block text-[13.5px] font-bold text-[#060D26]">{{ $alert['title'] }}</span><span class="block text-[12.5px] text-[#46537A]">{{ $alert['sub'] }}</span></span>
                                            <span class="text-[18px] text-[#5B6A8E]" aria-hidden="true">&rsaquo;</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- 2. The numbers, in one navy band. Rent this month leads; overdue, occupancy, vacant units and inquiries follow.
             Phones: rent full width, the other four two-up beneath. --}}
        <div class="mb-5 grid grid-cols-2 gap-y-4 rounded-2xl bg-[#060D26] py-4 text-white lg:grid-cols-[1.6fr_1fr_1fr_1fr_1fr] lg:gap-y-0">

            {{-- Rent this month --}}
            <div class="col-span-2 min-w-0 border-b border-white/10 px-4 pb-4 sm:px-5 lg:col-span-1 lg:border-b-0 lg:pb-0">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12px] font-semibold text-white/70">Rent this month &middot; {{ now()->format('F') }}</p>
                    <a data-page-swap="nav" href="{{ route('landlord.payments.index') }}"
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
                    <a data-page-swap="nav" href="{{ route('landlord.payouts.index') }}" class="mt-2 block text-[12px] text-white/65 hover:text-white transition-colors duration-200">
                        Payout from AbangananHub:
                        <span class="font-semibold {{ $pendingPayout > 0 ? 'text-white' : '' }}">{{ $pendingPayout > 0 ? '₱' . number_format($pendingPayout) . ' pending' : 'none pending' }}</span>
                    </a>
                @endif
            </div>

            {{-- Overdue --}}
            <a data-page-swap="nav" href="{{ route('landlord.payments.index') }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
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
                <a data-page-swap="nav" href="{{ route('landlord.units.index', ['status' => 'Occupied']) }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                    <p class="text-[12px] font-semibold text-white/70">Occupancy</p>
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums">{{ $occupiedPct }}%</p>
                    <p class="text-[12px] text-white/65">{{ $occupiedUnits }} of {{ $totalUnits }} {{ Str::plural('unit', $totalUnits) }} rented</p>
                </a>
                <a data-page-swap="nav" href="{{ route('landlord.units.index', ['status' => 'Available']) }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
                    <p class="text-[12px] font-semibold text-white/70">Vacant units</p>
                    <p class="mt-0.5 text-[22px] font-bold leading-tight tabular-nums">{{ $availableUnits }}</p>
                    <p class="text-[12px] text-white/65">{{ $availableUnits > 0 ? '₱' . number_format($vacantRoll) . ' a month unrented' : 'Nothing sitting empty' }}</p>
                </a>
                <a data-page-swap="nav" href="{{ route('landlord.reservations.index') }}" class="min-w-0 px-4 transition-colors duration-200 hover:bg-white/[0.04] sm:px-5 lg:border-l lg:border-white/10">
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
                                        @if(! empty($item['panel'])) data-page-panel data-page-panel-back="{{ $item['panel'] }}" @endif
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
                                class="inline-flex items-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold rounded-xl px-4 min-h-11 hover:bg-[#E96F4F] transition-colors duration-200">
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
                                                        <span class="h-full bg-[#EF4444]" style="width: {{ $rowOccupiedPct }}%"></span>
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
