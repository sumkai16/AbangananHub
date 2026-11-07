@extends('layouts.landlord')

@section('page-title', 'Tenants')

@php
    // Pill tones, shared with Reservation::contractStatus().
    $pill = [
        'ok' => 'bg-[#22C55E]/[0.10] text-[#15803D] border-[#22C55E]/25',
        'warn' => 'bg-[#FBBF24]/[0.12] text-[#B45309] border-[#FBBF24]/35',
        'bad' => 'bg-[#EF4444]/[0.08] text-[#B91C1C] border-[#EF4444]/25',
        'muted' => 'bg-[#ECEEF6] text-[#5B6A8E] border-[#E2E4EC]',
    ];
    $propertyOptions = ['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all();
    $iconBtn = 'h-9 w-9 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[#060D26] hover:bg-white transition-colors duration-200 cursor-pointer';
    $iconOff = 'h-9 w-9 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#CBD5E1] cursor-not-allowed';
    $smallBtn = 'inline-flex items-center justify-center h-9 px-3.5 rounded-full border border-[#5B6A8E]/30 bg-white text-[12px] font-bold text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer whitespace-nowrap';
    $coralBtn = 'inline-flex items-center justify-center h-9 px-3.5 rounded-full bg-[#FF8A66] text-[12px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer whitespace-nowrap';
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
    $editValues = fn ($r) => [
        'action' => route('landlord.tenants.update', $r['row']),
        'first_name' => $r['tenant']->first_name, 'last_name' => $r['tenant']->last_name,
        'email' => $r['tenant']->email, 'contact_number' => $r['tenant']->contact_number,
    ];
    // Why the Message icon is off, when it is.
    $messageOff = fn ($r) => $r['tenant']->is_walk_in
        ? ($r['tenant']->email ? 'Invite pending: they have not set up their account' : 'Add an email to invite them')
        : 'Available once they move in';

    // Chip links keep the search and property filter.
    $keep = array_filter(['search' => request('search'), 'property' => request('property')]);
    $chipUrl = fn ($key) => route('landlord.tenants.index', $keep + ($key === '' ? [] : ['status' => $key]));
    $filtered = $stage !== '' || $keep !== [];
    // Chips that only matter when something is in them stay out of the way the rest of the time.
    $chips = ['' => 'All'] + collect(\App\Http\Controllers\Landlord\TenantController::STAGES)
        ->reject(fn ($label, $key) => in_array($key, ['ending', 'no-agreement'], true) && ($counts[$key] ?? 0) === 0 && $stage !== $key)
        ->all();
    $alertChips = ['moving', 'ending', 'no-agreement', 'none'];

    // One row's status pill, the line under the name, and the one action it needs.
    $statusOf = fn ($r) => $r['contractStatus']
        ? ['label' => $r['contractStatus']['label'], 'tone' => $r['contractStatus']['tone']]
        : ['label' => 'No contract', 'tone' => 'muted'];
    $whenOf = function ($r) use ($fmt) {
        $c = $r['shown'];

        return match ($r['stage']) {
            'living' => 'Until '.$fmt($c->target_move_out_date),
            'moving' => 'Moving in '.$fmt($c->target_move_in_date),
            'former' => 'Ended '.$fmt($c->target_move_out_date),
            default => 'No unit yet',
        };
    };
@endphp

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10"
        x-data="{}"
        x-init="
            @if($openUrl) $nextTick(() => $dispatch('open-contract', { url: @js($openUrl) })); @endif
            @if(request()->boolean('new') || ($errors->any() && old('_form') === 'contract')) $nextTick(() => $dispatch('open-add-contract')); @endif
            @if($errors->any() && old('_form') === 'tenant') $nextTick(() => $dispatch('open-tenant-form', @js(old('_action') ? ['action' => old('_action')] : []))); @endif
        ">

        <x-page-header title="Tenants" subtitle="Everyone you rent to, with their contract and agreement. Add a tenant first, then give them a contract.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                <a href="{{ route('landlord.leases.index') }}"
                    @click.prevent="$dispatch('open-lease-policies', { url: @js(route('landlord.leases.panel')) })"
                    class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                    Lease policies
                </a>
                <a href="{{ route('landlord.tenants.export') }}"
                    title="Download your tenants as a spreadsheet (CSV)"
                    class="hidden sm:inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </a>
                <button type="button" @click="$dispatch('open-tenant-form', {})"
                    class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" /></svg>
                    Add tenant
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Tenants with no contract yet: the next thing to do for each is one tap away. --}}
        @if(! $filtered && $waiting->isNotEmpty())
            <section class="mb-4 rounded-2xl border border-dashed border-[#B45309]/40 bg-[#FBBF24]/[0.10] p-4" aria-labelledby="waiting-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="waiting-title" class="text-[11px] font-bold uppercase tracking-wide text-[#B45309]">Waiting for a contract · {{ $waiting->count() }}</h2>
                    @if($waiting->count() > 3)
                        <a href="{{ $chipUrl('none') }}" class="text-[12px] font-bold text-[#060D26] underline underline-offset-2">See all {{ $waiting->count() }}</a>
                    @endif
                </div>
                <ul class="mt-2 divide-y divide-[#B45309]/15">
                    @foreach($waiting->take(3) as $w)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <p class="min-w-0 truncate text-[13.5px] font-bold text-[#060D26]">{{ $w['name'] }} <span class="font-normal text-[12px] text-[#5B6A8E]">{{ $w['code'] }}</span></p>
                            <button type="button" class="{{ $coralBtn }}" @click="$dispatch('open-add-contract', { tenant: {{ $w['row']->landlord_tenant_id }} })">Give contract</button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Search, property and the stage chips. The chips are links, so they work without script and can be shared. --}}
        <form data-live method="GET" action="{{ route('landlord.tenants.index') }}" class="mb-5">
            @if($stage !== '')<input type="hidden" name="status" value="{{ $stage }}">@endif
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, Tenant ID, unit or email..." aria-label="Search tenants"
                        x-on:input.debounce.400ms="$el.form.requestSubmit()"
                        class="w-full h-11 pl-10 pr-4 text-[16px] sm:text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
                </div>
                <div class="filter-row flex flex-wrap items-center gap-2.5">
                    <x-styled-select name="property" :options="$propertyOptions" :selected="(string) request('property', '')" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[220px]" />

                    <button type="submit"
                        class="h-11 px-5 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13.5px] font-semibold hover:bg-[#E96F4F] transition-all duration-200 inline-flex items-center gap-1.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filter
                    </button>

                    @if($keep !== [])
                        <a href="{{ route('landlord.tenants.index', $stage !== '' ? ['status' => $stage] : []) }}"
                            class="h-11 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 inline-flex items-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    @endif
                </div>
            </div>

            <nav class="mt-4 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Filter tenants by stage">
                @foreach($chips as $key => $label)
                    @php $count = $key === '' ? $counts['all'] : $counts[$key]; @endphp
                    <a href="{{ $chipUrl($key) }}" @if($stage === $key) aria-current="true" @endif
                        class="shrink-0 inline-flex items-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-full border text-[12.5px] font-semibold transition-colors duration-200
                            {{ $stage === $key ? 'bg-[#060D26] border-[#060D26] text-white' : 'bg-white border-[#E2E4EC] text-[#060D26] hover:bg-[#ECEEF6]' }}">
                        {{ $label }}
                        <span class="tabular-nums font-bold {{ $stage === $key ? 'text-[#FF8A66]' : ($count > 0 && in_array($key, $alertChips, true) ? 'text-[#B45309]' : 'text-[#5B6A8E]') }}">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
        </form>

        @if($unsignedAgreements > 0)
            <p class="mb-4 text-[12.5px] text-[#5B6A8E]">
                {{ $unsignedAgreements }} {{ Str::plural('agreement', $unsignedAgreements) }} sent and waiting for a signature.
                <a href="{{ route('landlord.reservations.index', ['stage' => 'agreement']) }}" class="font-semibold text-[#060D26] underline underline-offset-2">See them on Reservations</a>
            </p>
        @endif

        @if($rows->isEmpty())
            <x-empty-state title="{{ $filtered ? 'No tenants match' : 'No tenants yet' }}"
                message="{{ $filtered ? 'Try another chip, or clear the search.' : 'Add a tenant with their contact and emergency details, then give them a contract.' }}">
                <x-slot:icon>
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                </x-slot:icon>
            </x-empty-state>
        @else
            <x-card flush>
                {{-- Phone and tablet: one card per tenant --}}
                <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach($rows as $r)
                        @php $st = $statusOf($r); @endphp
                        <li class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <button type="button" class="min-w-0 text-left cursor-pointer" @click="$dispatch('open-contract', { url: @js(route('landlord.tenants.show', $r['row'])) })">
                                    <p class="text-[14px] font-bold text-[#060D26] truncate">{{ $r['name'] }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $r['code'] }}@if($r['shown']) · {{ $r['shown']->property->title }} · {{ $r['shown']->unit->unit_label ?? 'No unit' }}@else · {{ $r['tenant']->contact_number ?: ($r['tenant']->email ?: 'No contact') }}@endif</p>
                                </button>
                                <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $pill[$st['tone']] }}">{{ $st['label'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3 text-[12px] text-[#5B6A8E]">
                                <span>{{ $whenOf($r) }}@if($r['noAgreement']) · <span class="font-semibold text-[#B45309]">No agreement</span>@endif</span>
                                @if($r['shown'])<span class="font-semibold text-[#060D26] tabular-nums">₱{{ number_format($r['shown']->currentRent()) }}/mo</span>@endif
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @include('landlord.tenants.partials._row-actions', ['r' => $r])
                                @if($r['stage'] === 'none')
                                    <button type="button" class="{{ $coralBtn }} ml-auto" @click="$dispatch('open-add-contract', { tenant: {{ $r['row']->landlord_tenant_id }} })">Give contract</button>
                                @elseif($r['ending'])
                                    <button type="button" class="{{ $coralBtn }} ml-auto" @click="$dispatch('open-contract', { url: @js(route('landlord.tenants.show', ['landlordTenant' => $r['row'], 'renew' => 1])) })">Renew</button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop: table --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E4EC]">
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Lease</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Rent</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Agreement</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($rows as $r)
                                @php
                                    $st = $statusOf($r);
                                    $c = $r['shown'];
                                    $lease = $c?->leaseStatus();
                                    $docs = $c?->documents ?? collect();
                                @endphp
                                <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                    <td class="px-5 py-3.5">
                                        <button type="button" class="text-left cursor-pointer" @click="$dispatch('open-contract', { url: @js(route('landlord.tenants.show', $r['row'])) })">
                                            <span class="block text-[13px] font-bold text-[#060D26] truncate max-w-[220px]">{{ $r['name'] }}</span>
                                            <span class="block text-[11.5px] text-[#5B6A8E]">{{ $r['code'] }}@if($r['tenant']->is_walk_in) · Added by you{{ $r['tenant']->email ? ', invite pending' : '' }}@endif</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-[13px] text-[#060D26]">
                                        @if($c)
                                            {{ $c->property->title }}<span class="text-[#5B6A8E]"> · {{ $c->unit->unit_label ?? '' }}</span>
                                        @else
                                            <span class="text-[#5B6A8E]">None</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap tabular-nums">
                                        {{ $c ? $fmt($c->target_move_in_date).' → '.$fmt($c->target_move_out_date) : '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right tabular-nums">{{ $c ? '₱'.number_format($c->currentRent()) : '—' }}</td>
                                    <td class="px-4 py-3.5 text-[13px]">
                                        @if(! $c)
                                            <span class="text-[#5B6A8E]">—</span>
                                        @else
                                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold whitespace-nowrap {{ $lease === 'missing' ? $pill['warn'] : $pill['ok'] }}">{{ ['uploaded' => 'Uploaded', 'signed_online' => 'Signed online', 'missing' => 'Missing'][$lease] }}</span>
                                            @if($docs->isNotEmpty())
                                                <span class="block mt-0.5 text-[11.5px] text-[#5B6A8E]">Docs {{ $docs->reject->isOutstanding()->count() }}/{{ $docs->count() }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold whitespace-nowrap {{ $pill[$st['tone']] }}">{{ $st['label'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($r['stage'] === 'none')
                                                <button type="button" class="{{ $coralBtn }}" @click="$dispatch('open-add-contract', { tenant: {{ $r['row']->landlord_tenant_id }} })">Give contract</button>
                                            @elseif($r['ending'])
                                                <button type="button" class="{{ $coralBtn }}" @click="$dispatch('open-contract', { url: @js(route('landlord.tenants.show', ['landlordTenant' => $r['row'], 'renew' => 1])) })">Renew</button>
                                            @endif
                                            @include('landlord.tenants.partials._row-actions', ['r' => $r])
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if($rows->hasPages())
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        @endif
        </div>

        @include('landlord.contracts.partials._view-modal', ['event' => 'open-contract', 'label' => 'Tenant and contract details'])
        @include('landlord.contracts.partials._add-modal')
        @include('landlord.tenants.partials._form-modal')
        @include('landlord.leases.partials._modal')
    </div>

    <script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
