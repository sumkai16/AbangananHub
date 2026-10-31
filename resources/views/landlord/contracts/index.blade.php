@extends('layouts.landlord')

@section('page-title', 'Contracts')

@php
    $pill = [
        'ok' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'warn' => 'bg-[#FBBF24]/[0.12] text-[#B45309] border-[#FBBF24]/35',
        'bad' => 'bg-[#EF4444]/[0.08] text-[#B91C1C] border-[#EF4444]/25',
        'muted' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ];
    $statusOptions = ['current' => 'Current', 'awaiting' => 'Awaiting move-in', 'active' => 'Living there', 'ended' => 'Ended', 'all' => 'All'];
    $propertyOptions = ['' => 'All properties'] + $properties->pluck('title', 'property_id')->all();
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
@endphp

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10"
        x-data="{}"
        x-init="
            @if(request('open')) $nextTick(() => $dispatch('open-contract', { url: @js(route('landlord.contracts.show', (int) request('open'))) })); @endif
            @if(request()->boolean('new') || ($errors->any() && old('_form') === 'contract')) $nextTick(() => $dispatch('open-add-contract')); @endif
        ">

        <x-page-header title="Contracts" subtitle="Every lease on your properties, whether it started online or you added it yourself.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                <button type="button" @click="$dispatch('open-add-contract')"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add contract
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Stat cards double as filters --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <x-stat-card label="Living there" :value="$stats['active']" :href="route('landlord.contracts.index', ['status' => 'active'])" sub="Occupied units" />
            <x-stat-card label="Awaiting move-in" :value="$stats['awaiting']" value-color="#B45309" :href="route('landlord.contracts.index', ['status' => 'awaiting'])" sub="Signed, not moved in" />
            <x-stat-card label="Ending soon" :value="$stats['ending']" value-color="{{ $stats['ending'] > 0 ? '#B45309' : '#060D26' }}"
                sub="Within {{ config('rentals.renewal_window_days') }} days" :href="route('landlord.contracts.index', ['status' => 'active'])" />
            <x-stat-card label="Lease missing" :value="$stats['lease_missing']" value-color="{{ $stats['lease_missing'] > 0 ? '#B45309' : '#060D26' }}"
                sub="No signed lease on file" :href="route('landlord.contracts.index', ['lease_missing' => 1])" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('landlord.contracts.index') }}" class="bg-white rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-4 mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by tenant name or email..." aria-label="Search contracts by tenant"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-styled-select name="property" :options="$propertyOptions" :selected="(string) request('property', '')" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[220px]" />
                    <x-styled-select name="status" :options="$statusOptions" :selected="$filter" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[200px]" />
                </div>
            </div>
        </form>

        @if($groups->isEmpty())
            <x-empty-state title="No contracts here yet"
                message="Add a contract for a tenant on your list, or accept an online booking and it will show up here once the agreement is signed.">
                <x-slot:icon>
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </x-slot:icon>
            </x-empty-state>
        @endif

        <div class="space-y-5">
            @foreach($groups as $propertyContracts)
                @php $property = $propertyContracts->first()->property; @endphp
                <x-card flush>
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-[#E2E4EC]">
                        <h2 class="text-[14px] font-bold text-[#060D26] truncate">{{ $property->title }}</h2>
                        <span class="text-[12px] text-[#5B6A8E] shrink-0">{{ $propertyContracts->count() }} {{ Str::plural('contract', $propertyContracts->count()) }}</span>
                    </div>

                    {{-- Phone and tablet: one card per contract --}}
                    <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                        @foreach($propertyContracts as $contract)
                            @php $status = $contract->contractStatus(); @endphp
                            <li>
                                <button type="button" @click="$dispatch('open-contract', { url: @js(route('landlord.contracts.show', $contract)) })"
                                    class="w-full text-left px-5 py-3.5 hover:bg-[#ECEEF6] active:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-bold text-[#060D26] truncate">{{ $contract->tenant->first_name }} {{ $contract->tenant->last_name }}</p>
                                            <p class="text-[11.5px] text-[#5B6A8E]">{{ $codes[$contract->tenant_id] ?? '' }} · {{ $contract->unit->unit_label ?? 'No unit' }}</p>
                                        </div>
                                        <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill[$status['tone']] }}">{{ $status['label'] }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[12px] text-[#5B6A8E]">
                                        <span class="tabular-nums">{{ $fmt($contract->target_move_in_date) }} → {{ $fmt($contract->target_move_out_date) }}</span>
                                        <span class="font-semibold text-[#060D26] tabular-nums">₱{{ number_format($contract->currentRent()) }}/mo</span>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Desktop: table --}}
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left">
                            <thead>
                                <tr class="border-b border-[#E2E4EC]">
                                    <th class="px-5 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant</th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Unit</th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Move-in → Move-out</th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Rent</th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Due</th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                    <th class="px-5 py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E4EC]">
                                @foreach($propertyContracts as $contract)
                                    @php $status = $contract->contractStatus(); @endphp
                                    <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                        <td class="px-5 py-3">
                                            <p class="text-[13px] font-bold text-[#060D26] truncate max-w-[220px]">{{ $contract->tenant->first_name }} {{ $contract->tenant->last_name }}</p>
                                            <p class="text-[11.5px] text-[#5B6A8E]">
                                                {{ $codes[$contract->tenant_id] ?? '' }}
                                                @if($contract->leaseStatus() === 'missing') · <span class="text-[#B45309]">No lease on file</span> @endif
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-[13px] text-[#060D26]">{{ $contract->unit->unit_label ?? 'No unit' }}</td>
                                        <td class="px-4 py-3 text-[13px] text-[#5B6A8E] whitespace-nowrap tabular-nums">{{ $fmt($contract->target_move_in_date) }} → {{ $fmt($contract->target_move_out_date) }}</td>
                                        <td class="px-4 py-3 text-[13px] font-semibold text-[#060D26] text-right tabular-nums">₱{{ number_format($contract->currentRent()) }}</td>
                                        <td class="px-4 py-3 text-[13px] text-[#5B6A8E] whitespace-nowrap">Day {{ $contract->rentDueDay() }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold whitespace-nowrap {{ $pill[$status['tone']] }}">{{ $status['label'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <button type="button" title="View contract" aria-label="View contract for {{ $contract->tenant->first_name }}"
                                                @click="$dispatch('open-contract', { url: @js(route('landlord.contracts.show', $contract)) })"
                                                class="h-9 w-9 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[#060D26] hover:bg-white cursor-pointer transition-colors duration-200">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>

        @include('landlord.contracts.partials._view-modal')
        @include('landlord.contracts.partials._add-modal')
    </div>

    <script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
