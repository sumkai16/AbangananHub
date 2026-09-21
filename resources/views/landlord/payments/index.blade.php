@extends('layouts.landlord')

@section('page-title', 'Rent & Payments')

@section('content')
    @php
        // Only statuses that some tenancy actually has (plus the active one, so
        // a bookmarked filter never shows a blank select). Order = urgency.
        $filters = ['all' => 'All Payment Status'];
        foreach ($paymentStyles as $key => $style) {
            if (in_array($key, $usedStatuses, true) || $statusFilter === $key) {
                $filters[$key] = $style['label'];
            }
        }
        if ($hasDueThisMonth || $statusFilter === 'due_this_month') {
            $filters['due_this_month'] = 'Due This Month';
        }

        // Precomputed once and shared by the mobile card list and the desktop
        // table below, so the two views can never render different numbers
        // for the same row (DESIGN.md §6d).
        $displayRows = $rows->map(function ($row) use ($paymentStyles) {
            $reservation = $row['reservation'];
            $summary = $row['summary'];
            $tenant = $reservation->tenant;
            $oldestUnpaid = $summary['oldestUnpaid'];

            return [
                'reservation'  => $reservation,
                'summary'      => $summary,
                'tenant'       => $tenant,
                'style'        => $paymentStyles[$row['paymentStatus']] ?? $paymentStyles['upcoming'],
                'initials'     => strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1)) ?: '?',
                'name'         => trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown',
                'dueOn'        => $oldestUnpaid['due_on'] ?? null,
                'paid'         => $oldestUnpaid['paid'] ?? 0.0,
                'monthsLabel'  => $summary['unpaidMonthCount'] > 1 ? $summary['unpaidMonthCount'] . ' months' : null,
            ];
        });
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <x-page-header title="Rent & Payments" subtitle="Rent collection across all occupied units, with the most urgent payments shown first.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                {{-- List / Calendar: plain links, so the view survives reloads and can be shared --}}
                <div class="inline-flex h-11 items-center rounded-full border border-[#E2E4EC] bg-white p-1 shrink-0" role="group" aria-label="Change view">
                    @foreach(['list' => 'List', 'calendar' => 'Calendar'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['view' => $key, 'month' => null]) }}"
                            @if($view === $key) aria-current="page" @endif
                            class="inline-flex h-9 items-center px-4 rounded-full text-[13px] font-semibold transition-colors duration-200 {{ $view === $key ? 'bg-[#060D26] text-white' : 'text-[#5B6A8E] hover:text-[#060D26]' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('landlord.payments.export', request()->only('property')) }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 shrink-0 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export
                </a>
            </x-slot:actions>
        </x-page-header>

        {{-- Summary: two different surfaces for two different questions.
             Left (navy, the page's one dark moment): how is this month going.
             Right (white): who owes what — a bar cut per tenant, so the
             landlord sees at a glance whether the debt is one person or
             spread out. Replaces four identical icon tiles, two of which
             repeated the same figure. --}}
        @php
            $owing = $displayRows
                ->filter(fn ($r) => $r['summary']['outstanding'] > 0)
                ->sortByDesc(fn ($r) => $r['summary']['outstanding'])
                ->values();
            // One red hue stepping down in strength, so segments are told
            // apart without introducing a second colour.
            $segmentTones = ['bg-[#DC2626]', 'bg-[#DC2626]/75', 'bg-[#DC2626]/55', 'bg-[#DC2626]/40', 'bg-[#DC2626]/25'];
            $shortName = fn ($r) => trim(explode(' ', $r['name'])[0] . ' ' . mb_substr(collect(explode(' ', $r['name']))->last(), 0, 1) . '.');
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-3 mb-5">
            {{-- This month --}}
            <section class="lg:col-span-2 rounded-2xl bg-[#060D26] text-white px-5 sm:px-6 py-5 sm:py-6 flex flex-col justify-between gap-6"
                aria-label="Collected this month">
                <div>
                    <p class="text-[12.5px] font-medium text-white/60">Collected this month</p>
                    <p class="mt-2 text-[34px] sm:text-[38px] font-semibold leading-none tracking-tight tabular-nums">
                        ₱{{ number_format($totals['collectedThisMonth'], 2) }}
                    </p>
                    <p class="mt-1.5 text-[13px] text-white/60 tabular-nums">of ₱{{ number_format($totals['dueThisMonth'], 2) }} due</p>
                </div>

                @if($totals['collectedPercent'] !== null)
                    <div>
                        <div class="h-2 rounded-full bg-white/15 overflow-hidden" role="progressbar"
                            aria-label="Collected this month" aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="{{ min(100, $totals['collectedPercent']) }}">
                            <div class="h-full rounded-full bg-[#FF8A66]" style="width: {{ min(100, $totals['collectedPercent']) }}%"></div>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between text-[12px] text-white/70">
                            <span><span class="font-semibold text-white">{{ $totals['collectedPercent'] }}%</span> collected</span>
                            <span>{{ $totals['duePaymentCount'] }} {{ \Illuminate\Support\Str::plural('payment', $totals['duePaymentCount']) }} due</span>
                        </div>
                    </div>
                @else
                    <p class="text-[12px] text-white/70">No rent falls due this month.</p>
                @endif
            </section>

            {{-- Who owes --}}
            <x-card class="lg:col-span-3 !p-5 sm:!p-6 flex flex-col justify-between gap-6">
                <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                    <div>
                        <p class="text-[12.5px] font-medium text-[#5B6A8E]">Still owed</p>
                        <p class="mt-2 text-[34px] sm:text-[38px] font-semibold leading-none tracking-tight tabular-nums text-[#060D26]">
                            ₱{{ number_format($totals['outstanding'], 2) }}
                        </p>
                    </div>
                    @if($totals['overdue'] > 0)
                        <p class="inline-flex items-center gap-1.5 h-7 px-3 rounded-full border border-[#DC2626]/20 bg-[#EF4444]/[0.06] text-[12px] font-semibold text-[#DC2626]">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#DC2626]"></span>
                            {{ $totals['overdue'] >= $totals['outstanding'] ? 'All of it overdue' : '₱' . number_format($totals['overdue'], 2) . ' overdue' }}
                        </p>
                    @endif
                </div>

                @if($owing->isEmpty())
                    <p class="text-[13px] text-[#5B6A8E]">Nobody owes anything right now.</p>
                @else
                    <div>
                        <div class="flex h-2 gap-0.5 rounded-full overflow-hidden" role="img"
                            aria-label="Amount owed, split by tenant">
                            @foreach($owing as $i => $r)
                                <div class="{{ $segmentTones[min($i, 4)] }}"
                                    style="width: {{ round($r['summary']['outstanding'] / $totals['outstanding'] * 100, 2) }}%"></div>
                            @endforeach
                        </div>
                        <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-[12.5px]">
                            @foreach($owing->take(3) as $i => $r)
                                <li class="inline-flex items-center gap-2 min-w-0">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $segmentTones[$i] }}"></span>
                                    <span class="text-[#060D26] font-medium truncate">{{ $shortName($r) }}</span>
                                    <span class="text-[#5B6A8E] tabular-nums">₱{{ number_format($r['summary']['outstanding'], 0) }}</span>
                                </li>
                            @endforeach
                            @if($owing->count() > 3)
                                <li class="text-[#5B6A8E]">+{{ $owing->count() - 3 }} more</li>
                            @endif
                        </ul>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Filter bar --}}
        {{-- Bare on the page: the inputs already have borders, and a card
             around them stacked a third container above the table card. --}}
        <form method="GET" action="{{ route('landlord.payments.index') }}" class="mb-5">
            @if($view === 'calendar')
                <input type="hidden" name="view" value="calendar">
                @if(request('month')) <input type="hidden" name="month" value="{{ request('month') }}"> @endif
            @endif
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search tenants by name or email..." aria-label="Search tenants by name or email"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-11 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#5B6A8E]/25 bg-white text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all duration-200">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div>
                        <label for="filter-property" class="sr-only">Filter by property</label>
                        @php
                            $paymentPropertyOptions = ['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all();
                        @endphp
                        <x-styled-select name="property" id="filter-property" :options="$paymentPropertyOptions"
                            :selected="(string) ($propertyId ?? '')"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26] max-w-[200px]" />
                    </div>

                    <div>
                        <label for="filter-status" class="sr-only">Filter by payment status</label>
                        <x-styled-select name="status" id="filter-status" :options="$filters" :selected="$statusFilter"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13.5px] text-[#060D26]" />
                    </div>

                    <button type="submit"
                        class="h-11 px-5 rounded-xl border border-[#060D26] bg-white text-[#060D26] text-[13.5px] font-semibold hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5 cursor-pointer">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['search', 'property']) || $statusFilter !== 'all')
                        <a href="{{ route('landlord.payments.index') }}"
                            class="h-11 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5 cursor-pointer">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Collections --}}
        @if($displayRows->isEmpty())
            <x-card class="flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </div>
                <p class="text-[14px] font-semibold text-[#060D26]">Nothing to collect yet</p>
                <p class="text-[13px] text-[#5B6A8E] mt-1 max-w-md">
                    Rent tracking starts once a unit is occupied — either through a platform reservation or a walk-in tenant you
                    add.
                </p>
                <a href="{{ route('landlord.tenants.walkIn.create') }}"
                    class="mt-5 inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                    Add a walk-in tenant
                </a>
            </x-card>
        @elseif($view === 'calendar')
            {{-- Month navigation swaps just the calendar in place (no full page load): the links stay real
                 <a href>s so middle-click, no-JS and the back button all still work; a click fetches the same URL
                 as a fragment (the controller returns only the partial for XHR), fades the old month while it loads,
                 and pushes the URL. Any failure falls back to a normal navigation. --}}
            <div x-data="{
                    busy: false,
                    async load(url, push = true, focusKey = null) {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                            if (!res.ok) throw new Error();
                            this.$refs.cal.innerHTML = await res.text();
                            if (push) history.pushState({ cal: true }, '', url);
                            if (focusKey) this.$nextTick(() => this.$refs.cal.querySelector('[data-cal-nav=' + focusKey + ']')?.focus());
                        } catch (e) { window.location.href = url; return; }
                        this.busy = false;
                    },
                    nav(e) {
                        const a = e.target.closest('a[data-cal-nav]');
                        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                        e.preventDefault();
                        this.load(a.href, true, a.dataset.calNav);
                    }
                }"
                @click="nav($event)"
                @popstate.window="location.search.includes('view=calendar') ? load(location.href, false) : location.reload()">
                <div x-ref="cal" :class="busy ? 'opacity-60 pointer-events-none' : ''" :aria-busy="busy" class="transition-opacity duration-150 motion-reduce:transition-none">
                    @include('landlord.payments._calendar')
                </div>
            </div>
        @else
            {{-- Mobile: stacked cards. Landlord surfaces are mobile-first
                 (CLAUDE.md → Device priority) and this table has grown to 8
                 columns, past what a 375px screen can host even scrolled. --}}
            <div class="sm:hidden space-y-3">
                @foreach($displayRows as $item)
                    @php $reservation = $item['reservation']; $summary = $item['summary']; @endphp
                    <x-card class="!p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-[#ECEEF6] flex items-center justify-center text-[12px] font-bold text-[#060D26] shrink-0">
                                    {{ $item['initials'] }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $item['name'] }}</p>
                                    <p class="text-[11px] text-[#5B6A8E] truncate">
                                        {{ $reservation->unit->unit_label ?? '—' }} · {{ $reservation->property->title ?? '' }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full border border-[#E2E4EC] shrink-0 text-[11px] font-bold {{ $item['style']['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $item['style']['dot'] }}"></span>
                                {{ $item['style']['label'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center border-t border-[#E2E4EC] pt-3">
                            <div>
                                <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Rent</p>
                                <p class="text-[13px] font-semibold text-[#060D26] mt-0.5">₱{{ number_format($summary['monthlyRent'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Due</p>
                                <p class="text-[13px] font-semibold text-[#060D26] mt-0.5">{{ $item['dueOn']?->format('M j') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Balance</p>
                                <p class="text-[13px] font-semibold {{ $summary['outstanding'] > 0 ? 'text-[#DC2626]' : 'text-[#060D26]' }} mt-0.5">
                                    ₱{{ number_format($summary['outstanding'], 2) }}
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                            class="mt-3 flex items-center justify-center h-9 rounded-xl border border-[#FF8A66] text-[#B35A3D] text-[12px] font-semibold hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">
                            Open Ledger
                        </a>
                    </x-card>
                @endforeach
            </div>

            {{-- Desktop / tablet: table --}}
            <x-card flush class="hidden sm:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <tr>
                                <th scope="col"
                                    class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Tenant</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Unit</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Monthly Rent</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Due Date</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Paid</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Balance</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    Status</th>
                                <th scope="col"
                                    class="px-5 sm:px-6 py-3.5 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($displayRows as $item)
                                @php $reservation = $item['reservation']; $summary = $item['summary']; @endphp
                                <tr class="hover:bg-[#F7F8FC] transition-colors duration-150 {{ $item['style']['label'] === 'Overdue' ? 'shadow-[inset_3px_0_0_#DC2626]' : '' }}">
                                    <td class="px-5 sm:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-9 h-9 rounded-full bg-[#ECEEF6] flex items-center justify-center text-[12px] font-bold text-[#060D26] shrink-0">
                                                {{ $item['initials'] }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13.5px] font-semibold text-[#060D26] truncate">
                                                    {{ $item['name'] }}
                                                </p>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    @if($item['tenant']?->is_walk_in)
                                                        <span
                                                            class="inline-flex items-center h-5 px-2 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[10px] font-bold">
                                                            Walk-in
                                                        </span>
                                                    @endif
                                                    @if($reservation->rental_status === 'Completed')
                                                        <span
                                                            class="inline-flex items-center h-5 px-2 rounded-full border border-[#E2E4EC] bg-[#F7F8FC] text-[#5B6A8E] text-[10px] font-bold">
                                                            Ended
                                                        </span>
                                                    @endif
                                                    <span class="text-[11px] text-[#5B6A8E] truncate">
                                                        {{ $item['tenant']->contact_number ?: ($item['tenant']->email ?: '—') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] font-medium text-[#060D26]">
                                            {{ $reservation->unit->unit_label ?? '—' }}</p>
                                        <p class="text-[11px] text-[#5B6A8E] truncate max-w-[180px]">
                                            {{ $reservation->property->title ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-[13px] text-[#5B6A8E] text-right tabular-nums whitespace-nowrap">
                                        ₱{{ number_format($summary['monthlyRent'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-[13px] text-[#060D26] whitespace-nowrap">
                                        {{ $item['dueOn']?->format('M j, Y') ?? '—' }}
                                    </td>
                                    {{-- Green is a status colour (the Paid pill), not a money colour:
                                         ₱0.00 in green read as "paid" on an overdue row. --}}
                                    <td class="px-4 py-4 text-[13px] text-right tabular-nums whitespace-nowrap {{ $item['paid'] > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">
                                        ₱{{ number_format($item['paid'], 2) }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-[13px] text-right tabular-nums whitespace-nowrap {{ $summary['outstanding'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#5B6A8E]' }}">
                                        ₱{{ number_format($summary['outstanding'], 2) }}
                                        @if($item['monthsLabel'])
                                            <p class="text-[11px] font-normal text-[#94A3B8]">{{ $item['monthsLabel'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold {{ $item['style']['text'] }}">
                                            <span class="w-2 h-2 rounded-full {{ $item['style']['dot'] }}"></span>
                                            {{ $item['style']['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 sm:px-6 py-4 text-right">
                                        <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                                            class="inline-flex items-center gap-1 text-[#060D26] text-[12.5px] font-semibold hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer whitespace-nowrap">
                                            Open ledger
                                            <span aria-hidden="true">→</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>
@endsection
