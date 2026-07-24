@extends('layouts.landlord')

@section('page-title', 'Rent & Payments')

@section('content')
    @php
        $standingStyles = [
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Behind'],
            'due'     => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Due'],
            'settled' => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Up to date'],
        ];

        $filters = [
            'all'     => 'All tenancies',
            'overdue' => 'Behind on rent',
            'due'     => 'Due this month',
            'settled' => 'Up to date',
        ];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Rent &amp; Payments</h1>
                    <p class="text-sm text-[#64748B] mt-0.5">Rent collection across every occupied unit, worst standing first.</p>
                </div>
            </div>

            <a href="{{ route('landlord.payments.export', request()->only('property')) }}"
                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E8F0] bg-white hover:bg-[#F7FCFC] text-[#1F2937] text-sm font-semibold transition-all duration-200 shrink-0 cursor-pointer">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Export
            </a>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            @php
                $stats = [
                    ['label' => 'Collected',    'value' => '₱' . number_format($totals['collected'], 2),   'tone' => 'text-[#15803D]', 'sub' => 'All time, this portfolio'],
                    ['label' => 'Outstanding',  'value' => '₱' . number_format($totals['outstanding'], 2), 'tone' => 'text-[#1F2937]', 'sub' => 'Unpaid rent to date'],
                    ['label' => 'Overdue',      'value' => '₱' . number_format($totals['overdue'], 2),     'tone' => $totals['overdue'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]', 'sub' => 'Past its due date'],
                    ['label' => 'Behind',       'value' => (string) $totals['behind'],                     'tone' => $totals['behind'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]', 'sub' => 'Tenancies needing a nudge'],
                ];
            @endphp
            @foreach($stats as $stat)
                <x-card class="!p-4">
                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-extrabold {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                    <p class="text-[11px] text-[#64748B] mt-1">{{ $stat['sub'] }}</p>
                </x-card>
            @endforeach
        </div>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('landlord.payments.index') }}"
            class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-4 mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search tenants by name or email..." aria-label="Search tenants by name or email"
                        class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[#1F2937] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] focus:bg-white transition-all duration-200">
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative">
                        <label for="filter-property" class="sr-only">Filter by property</label>
                        <select id="filter-property" name="property"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer max-w-[200px]">
                            <option value="">All Properties</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->property_id }}" @selected($propertyId == $property->property_id)>
                                    {{ $property->title }}
                                </option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12"
                            height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <div class="relative">
                        <label for="filter-status" class="sr-only">Filter by standing</label>
                        <select id="filter-status" name="status"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] text-[13.5px] text-[#1F2937] focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/30 appearance-none transition cursor-pointer">
                            @foreach($filters as $value => $label)
                                <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#64748B]" width="12"
                            height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
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
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Collections table --}}
        @if($rows->isEmpty())
            <x-card class="flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                </div>
                <p class="text-[14px] font-semibold text-[#1F2937]">Nothing to collect yet</p>
                <p class="text-[13px] text-[#64748B] mt-1 max-w-md">
                    Rent tracking starts once a unit is occupied — either through a platform reservation or a walk-in tenant you add.
                </p>
                <a href="{{ route('landlord.tenants.walkIn.create') }}"
                    class="mt-5 inline-flex items-center justify-center h-11 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                    Add a walk-in tenant
                </a>
            </x-card>
        @else
            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                            <tr>
                                <th scope="col" class="px-5 sm:px-6 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Tenant</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Unit</th>
                                <th scope="col" class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rent</th>
                                <th scope="col" class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Collected</th>
                                <th scope="col" class="px-4 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Outstanding</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Standing</th>
                                <th scope="col" class="px-5 sm:px-6 py-3.5 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach($rows as $row)
                                @php
                                    $reservation = $row['reservation'];
                                    $summary = $row['summary'];
                                    $tenant = $reservation->tenant;
                                    $style = $standingStyles[$row['standing']];
                                    $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1));
                                @endphp
                                <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                    <td class="px-5 sm:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                                {{ $initials ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13.5px] font-semibold text-[#1F2937] truncate">
                                                    {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown' }}
                                                </p>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    @if($tenant?->is_walk_in)
                                                        <span class="inline-flex items-center h-5 px-2 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[10px] font-bold">
                                                            Walk-in
                                                        </span>
                                                    @endif
                                                    @if($reservation->rental_status === 'Completed')
                                                        <span class="inline-flex items-center h-5 px-2 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B] text-[10px] font-bold">
                                                            Ended
                                                        </span>
                                                    @endif
                                                    <span class="text-[11px] text-[#64748B] truncate">
                                                        {{ $tenant->contact_number ?: ($tenant->email ?: '—') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] font-medium text-[#1F2937]">{{ $reservation->unit->unit_label ?? '—' }}</p>
                                        <p class="text-[11px] text-[#64748B] truncate max-w-[180px]">{{ $reservation->property->title ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-[13px] text-[#64748B] text-right whitespace-nowrap">
                                        ₱{{ number_format($summary['monthlyRent'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-[13px] font-semibold text-[#15803D] text-right whitespace-nowrap">
                                        ₱{{ number_format($summary['collected'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-[13px] text-right whitespace-nowrap {{ $summary['outstanding'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#64748B]' }}">
                                        ₱{{ number_format($summary['outstanding'], 2) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">
                                            {{ $style['label'] }}
                                        </span>
                                        @if($summary['overdueCount'] > 0)
                                            <p class="text-[11px] text-[#DC2626] mt-1">
                                                {{ $summary['overdueCount'] }} {{ \Illuminate\Support\Str::plural('month', $summary['overdueCount']) }} behind
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 sm:px-6 py-4 text-right">
                                        <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                                            class="inline-flex items-center h-9 px-4 rounded-full border border-[#2AA7A1] text-[#2AA7A1] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200 cursor-pointer whitespace-nowrap">
                                            Open ledger
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
