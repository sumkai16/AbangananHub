@extends('layouts.landlord')

@section('page-title', 'Rent & Payments')

@section('content')
    @php
<<<<<<< HEAD
        $statusStyles = [
            'Overdue'    => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'Partial'    => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'Upcoming'   => ['pill' => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25', 'label' => 'Upcoming'],
            'Paid'       => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'Paid Ahead' => ['pill' => 'bg-[#8B5CF6]/[0.10] text-[#6D28D9] border-[#8B5CF6]/30', 'label' => 'Paid Ahead'],
        ];

        $filters = [
            'all' => 'All Payment Status',
            'overdue' => 'Overdue',
            'partial' => 'Partial',
            'upcoming' => 'Upcoming',
            'paid' => 'Paid',
            'paid_ahead' => 'Paid Ahead',
=======
        // One map so the dot colour, the pill and the sort/filter values can
        // never drift apart. `paid_ahead` deliberately stays in the teal
        // family — DESIGN.md §6n: a future month covered in advance is
        // credit, not debt, and must never read amber or red.
        $paymentStyles = [
            'overdue'    => ['dot' => 'bg-[#DC2626]', 'text' => 'text-[#DC2626]', 'label' => 'Overdue'],
            'partial'    => ['dot' => 'bg-[#B45309]', 'text' => 'text-[#B45309]', 'label' => 'Partial'],
            'upcoming'   => ['dot' => 'bg-[#94A3B8]', 'text' => 'text-[#64748B]', 'label' => 'Upcoming'],
            'paid'       => ['dot' => 'bg-[#15803D]', 'text' => 'text-[#15803D]', 'label' => 'Paid'],
            'paid_ahead' => ['dot' => 'bg-[#156F8C]', 'text' => 'text-[#156F8C]', 'label' => 'Paid Ahead'],
        ];

        $filters = [
            'all'            => 'All Payment Status',
            'overdue'        => 'Overdue',
            'partial'        => 'Partial',
            'upcoming'       => 'Upcoming',
            'paid'           => 'Paid',
            'paid_ahead'     => 'Paid Ahead',
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
            'due_this_month' => 'Due This Month',
        ];

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
                <a href="{{ route('landlord.payments.export', request()->only('property')) }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E8F0] bg-white hover:bg-[#F7FCFC] text-[#1F2937] text-sm font-semibold transition-all duration-200 shrink-0 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export
                </a>
            </x-slot:actions>
        </x-page-header>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
<<<<<<< HEAD
            <x-stat-card label="Due This Month" :value="'₱' . number_format($totals['dueThisMonth'], 2)" value-color="#1F2937" icon-bg="#EEF8F8"
                :sub="$totals['paymentsDueThisMonth'] . ' ' . \Illuminate\Support\Str::plural('payment', $totals['paymentsDueThisMonth']) . ' due'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Collected This Month" :value="'₱' . number_format($totals['collectedThisMonth'], 2)" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                :sub="$totals['collectedThisMonthPct'] === null ? 'No dues this month' : $totals['collectedThisMonthPct'] . '% collected'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Outstanding" :value="'₱' . number_format($totals['outstanding'], 2)" value-color="#1F2937" icon-bg="#EEF8F8"
                :sub="$totals['outstandingCount'] . ' unpaid/partial'">
=======
            <x-stat-card label="Due This Month" :value="'₱' . number_format($totals['dueThisMonth'], 2)"
                value-color="#1F2937" icon-bg="#EEF8F8"
                :sub="$totals['duePaymentCount'] . ' ' . \Illuminate\Support\Str::plural('payment', $totals['duePaymentCount']) . ' due'">
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

<<<<<<< HEAD
            <x-stat-card label="Overdue" :value="'₱' . number_format($totals['overdue'], 2)"
                :value-color="$totals['overdue'] > 0 ? '#DC2626' : '#1F2937'"
                :icon-bg="$totals['overdue'] > 0 ? 'rgba(239,68,68,0.07)' : '#EEF8F8'"
                :sub="$totals['overdueTenancies'] . ' ' . \Illuminate\Support\Str::plural('tenancy', $totals['overdueTenancies']) . ' overdue'">
=======
            <x-stat-card label="Collected This Month" :value="'₱' . number_format($totals['collectedThisMonth'], 2)"
                value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                :percent="$totals['collectedPercent']" bar-color="#22C55E"
                :sub="$totals['collectedPercent'] === null ? 'No dues this month' : $totals['collectedPercent'] . '% collected'">
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
<<<<<<< HEAD
=======

            <x-stat-card label="Total Unpaid" :value="'₱' . number_format($totals['outstanding'], 2)"
                :value-color="$totals['outstanding'] > 0 ? '#1F2937' : '#1F2937'" icon-bg="#EEF8F8"
                :sub="$totals['unpaidCount'] > 0 ? 'across ' . $totals['unpaidCount'] . ' ' . \Illuminate\Support\Str::plural('tenant', $totals['unpaidCount']) : 'Nothing unpaid'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Overdue" :value="'₱' . number_format($totals['overdue'], 2)"
                :value-color="$totals['overdue'] > 0 ? '#DC2626' : '#1F2937'"
                :icon-bg="$totals['overdue'] > 0 ? 'rgba(239,68,68,0.07)' : '#EEF8F8'"
                :sub="$totals['overdueCount'] > 0 ? $totals['overdueCount'] . ' ' . \Illuminate\Support\Str::plural('tenancy', $totals['overdueCount']) . ' overdue' : 'Nothing overdue'">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $totals['overdue'] > 0 ? '#DC2626' : '#B45309' }}" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('landlord.payments.index') }}"
            class="bg-white rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4 mb-5">
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
                        class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] focus:bg-white transition-all duration-200">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div>
                        <label for="filter-property" class="sr-only">Filter by property</label>
                        @php
                            $paymentPropertyOptions = ['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all();
                        @endphp
                        <x-styled-select name="property" id="filter-property" :options="$paymentPropertyOptions"
                            :selected="(string) ($propertyId ?? '')"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] max-w-[200px]" />
                    </div>

                    <div>
                        <label for="filter-status" class="sr-only">Filter by payment status</label>
                        <x-styled-select name="status" id="filter-status" :options="$filters" :selected="$statusFilter"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937]" />
                    </div>

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#1F2937] text-white text-[13.5px] font-semibold hover:brightness-95 transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['search', 'property']) || $statusFilter !== 'all')
                        <a href="{{ route('landlord.payments.index') }}"
                            class="h-11 px-4 rounded-xl border border-[#64748B]/25 text-[13.5px] text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 inline-flex items-center gap-1.5 cursor-pointer">
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
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </div>
                <p class="text-[14px] font-semibold text-[#1F2937]">Nothing to collect yet</p>
                <p class="text-[13px] text-[#64748B] mt-1 max-w-md">
                    Rent tracking starts once a unit is occupied — either through a platform reservation or a walk-in tenant you
                    add.
                </p>
                <a href="{{ route('landlord.tenants.walkIn.create') }}"
                    class="mt-5 inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                    Add a walk-in tenant
                </a>
            </x-card>
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
                                <div class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                    {{ $item['initials'] }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">{{ $item['name'] }}</p>
                                    <p class="text-[11px] text-[#64748B] truncate">
                                        {{ $reservation->unit->unit_label ?? '—' }} · {{ $reservation->property->title ?? '' }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full border border-[#E2E8F0] shrink-0 text-[11px] font-bold {{ $item['style']['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $item['style']['dot'] }}"></span>
                                {{ $item['style']['label'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center border-t border-[#E2E8F0] pt-3">
                            <div>
                                <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Rent</p>
                                <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5">₱{{ number_format($summary['monthlyRent'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Due</p>
                                <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5">{{ $item['dueOn']?->format('M j') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Balance</p>
                                <p class="text-[13px] font-semibold {{ $summary['outstanding'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]' }} mt-0.5">
                                    ₱{{ number_format($summary['outstanding'], 2) }}
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                            class="mt-3 flex items-center justify-center h-9 rounded-xl border border-[#2AA7A1] text-[#2AA7A1] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200 cursor-pointer">
                            Open Ledger
                        </a>
                    </x-card>
                @endforeach
            </div>

            {{-- Desktop / tablet: table --}}
            <x-card flush class="hidden sm:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                            <tr>
                                <th scope="col"
                                    class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Tenant</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Unit</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Monthly Rent</th>
<<<<<<< HEAD
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide whitespace-nowrap">
                                    Due Date</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Paid</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Balance</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
=======
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Due Date</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Paid</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    Balance</th>
                                <th scope="col"
                                    class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                                    Status</th>
                                <th scope="col"
                                    class="px-5 sm:px-6 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
<<<<<<< HEAD
                            @foreach($rows as $row)
                                @php
                                    $reservation = $row['reservation'];
                                    $summary = $row['summary'];
                                    $tenant = $reservation->tenant;
                                    $style = $statusStyles[$row['status']];
                                    $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1));
                                @endphp
=======
                            @foreach($displayRows as $item)
                                @php $reservation = $item['reservation']; $summary = $item['summary']; @endphp
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                                <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                    <td class="px-5 sm:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                                {{ $item['initials'] }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">
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
                                                            class="inline-flex items-center h-5 px-2 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B] text-[10px] font-bold">
                                                            Ended
                                                        </span>
                                                    @endif
                                                    <span class="text-[11px] text-[#64748B] truncate">
                                                        {{ $item['tenant']->contact_number ?: ($item['tenant']->email ?: '—') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] font-medium text-[#1F2937]">
                                            {{ $reservation->unit->unit_label ?? '—' }}</p>
                                        <p class="text-[11px] text-[#64748B] truncate max-w-[180px]">
                                            {{ $reservation->property->title ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-[13px] text-[#64748B] text-right whitespace-nowrap">
                                        ₱{{ number_format($summary['monthlyRent'], 2) }}
                                    </td>
<<<<<<< HEAD
                                    <td class="px-4 py-4 text-[13px] text-[#64748B] whitespace-nowrap">
                                        {{ $row['dueDate']?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-[13px] font-semibold text-[#15803D] text-right whitespace-nowrap">
                                        ₱{{ number_format($row['paid'], 2) }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-[13px] text-right whitespace-nowrap {{ $row['balance'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#64748B]' }}">
                                        ₱{{ number_format(max(0, $row['balance']), 2) }}
=======
                                    <td class="px-4 py-4 text-[13px] text-[#1F2937] whitespace-nowrap">
                                        {{ $item['dueOn']?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-[13px] font-semibold text-[#15803D] text-right whitespace-nowrap">
                                        ₱{{ number_format($item['paid'], 2) }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-[13px] text-right whitespace-nowrap {{ $summary['outstanding'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#64748B]' }}">
                                        ₱{{ number_format($summary['outstanding'], 2) }}
                                        @if($item['monthsLabel'])
                                            <p class="text-[11px] font-normal text-[#94A3B8]">{{ $item['monthsLabel'] }}</p>
                                        @endif
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold {{ $item['style']['text'] }}">
                                            <span class="w-2 h-2 rounded-full {{ $item['style']['dot'] }}"></span>
                                            {{ $item['style']['label'] }}
                                        </span>
<<<<<<< HEAD
                                        @if($summary['overdueCount'] > 0)
                                            <p class="text-[11px] text-[#DC2626] mt-1">
                                                {{ $summary['overdueCount'] }}
                                                {{ \Illuminate\Support\Str::plural('month', $summary['overdueCount']) }} behind
                                            </p>
                                        @elseif($row['status'] === 'Paid Ahead' && $summary['prepaidThrough'])
                                            <p class="text-[11px] text-[#6D28D9] mt-1">
                                                Paid through {{ $summary['prepaidThrough']->format('M Y') }}
                                            </p>
                                        @endif
=======
>>>>>>> 57c3b1217ebdc2f2da089457b26a2b088308fc58
                                    </td>
                                    <td class="px-5 sm:px-6 py-4 text-right">
                                        <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                                            class="text-[#2AA7A1] text-[12.5px] font-semibold hover:underline transition-colors duration-200 cursor-pointer whitespace-nowrap">
                                            Open Ledger
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
