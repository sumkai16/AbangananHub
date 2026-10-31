@extends('layouts.landlord')

@section('page-title', 'My Tenants')

@php
    $pill = [
        'Active' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'Pending' => 'bg-[#FBBF24]/[0.12] text-[#B45309] border-[#FBBF24]/35',
        'Former' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ];
    $statusOptions = ['' => 'All statuses', 'active' => 'Active', 'pending' => 'Pending', 'former' => 'Former'];
    $propertyOptions = ['' => 'All properties'] + $properties->pluck('title', 'property_id')->all();
    $iconBtn = 'h-9 w-9 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[#060D26] hover:bg-white transition-colors duration-200 cursor-pointer';
    $iconOff = 'h-9 w-9 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#CBD5E1] cursor-not-allowed';
    $editValues = fn ($r) => [
        'action' => route('landlord.tenants.update', $r['row']),
        'first_name' => $r['tenant']->first_name, 'last_name' => $r['tenant']->last_name,
        'email' => $r['tenant']->email, 'contact_number' => $r['tenant']->contact_number,
    ];
    // Why the Message icon is off, when it is.
    $messageOff = fn ($r) => $r['tenant']->is_walk_in
        ? ($r['tenant']->email ? 'Invite pending: they have not set up their account' : 'Add an email to invite them')
        : 'Available once they move in';
@endphp

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10"
        x-data="{}"
        x-init="@if($errors->any() && old('_form') === 'tenant') $nextTick(() => $dispatch('open-tenant-form', @js(old('_action') ? ['action' => old('_action')] : []))); @endif">

        <x-page-header title="My Tenants" subtitle="Everyone you rent to. Add a tenant first, then give them a contract.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                <a href="{{ route('landlord.tenants.export') }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export
                </a>
                <button type="button" @click="$dispatch('open-tenant-form', {})"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" /></svg>
                    Add Tenant
                </button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-3 gap-3 mb-4">
            <x-stat-card label="Total" :value="$stats['total']" :href="route('landlord.tenants.index')" sub="On your list" />
            <x-stat-card label="Active" :value="$stats['active']" value-color="#15803D" :href="route('landlord.tenants.index', ['status' => 'active'])" sub="Have a contract" />
            <x-stat-card label="Pending" :value="$stats['pending']" value-color="#B45309" :href="route('landlord.tenants.index', ['status' => 'pending'])" sub="No contract yet" />
        </div>

        <form method="GET" action="{{ route('landlord.tenants.index') }}" class="bg-white rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-4 mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email or Tenant ID..." aria-label="Search tenants"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-styled-select name="property" :options="$propertyOptions" :selected="(string) request('property', '')" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[220px]" />
                    <x-styled-select name="status" :options="$statusOptions" :selected="(string) request('status', '')" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[180px]" />
                </div>
            </div>
        </form>

        @if($rows->isEmpty())
            <x-empty-state title="{{ request()->hasAny(['search', 'status', 'property']) ? 'No tenants match' : 'No tenants yet' }}"
                message="Add a tenant with their contact and emergency details. They show as Pending until you give them a contract.">
                <x-slot:icon>
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                </x-slot:icon>
            </x-empty-state>
        @else
            <x-card flush>
                {{-- Phone and tablet: one card per tenant --}}
                <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach($rows as $r)
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <button type="button" class="min-w-0 text-left cursor-pointer" @click="$dispatch('open-tenant', { url: @js(route('landlord.tenants.show', $r['row'])) })">
                                    <p class="text-[14px] font-bold text-[#060D26] truncate">{{ $r['name'] }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $r['code'] }} · {{ $r['tenant']->contact_number ?: ($r['tenant']->email ?: 'No contact') }}</p>
                                    <p class="text-[12px] text-[#5B6A8E] mt-0.5">{{ $r['current'] ? $r['current']->property->title.' · '.($r['current']->unit->unit_label ?? '') : 'No current unit' }}</p>
                                </button>
                                <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill[$r['status']] }}">{{ $r['status'] }}</span>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                @include('landlord.tenants.partials._row-actions', ['r' => $r])
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop: table --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E4EC]">
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant ID</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Name</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Contact</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Current unit</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($rows as $r)
                                <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                    <td class="px-5 py-3.5 text-[13px] font-semibold text-[#060D26] tabular-nums">{{ $r['code'] }}</td>
                                    <td class="px-4 py-3.5">
                                        <p class="text-[13px] font-bold text-[#060D26] truncate max-w-[220px]">{{ $r['name'] }}</p>
                                        @if($r['tenant']->is_walk_in)
                                            <p class="text-[11.5px] text-[#5B6A8E]">Added by you{{ $r['tenant']->email ? ' · invite pending' : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="text-[13px] text-[#060D26]">{{ $r['tenant']->contact_number ?: '—' }}</p>
                                        <p class="text-[11.5px] text-[#5B6A8E] truncate max-w-[200px]">{{ $r['tenant']->email ?: 'No email' }}</p>
                                    </td>
                                    <td class="px-4 py-3.5 text-[13px] text-[#060D26]">
                                        @if($r['current'])
                                            {{ $r['current']->property->title }}<span class="text-[#5B6A8E]"> · {{ $r['current']->unit->unit_label ?? '' }}</span>
                                        @else
                                            <span class="text-[#5B6A8E]">None</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill[$r['status']] }}">{{ $r['status'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            @include('landlord.tenants.partials._row-actions', ['r' => $r])
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        @include('landlord.contracts.partials._view-modal', ['event' => 'open-tenant', 'label' => 'Tenant details'])
        @include('landlord.tenants.partials._form-modal')
    </div>
@endsection
