@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10" x-data="{
            view: localStorage.getItem('reservationsView') || 'table',
            setView(v) { this.view = v; localStorage.setItem('reservationsView', v); },
            modalOpen: false,
            selected: null,
            openModal(reservation) {
                this.selected = reservation;
                this.modalOpen = true;
            },
            agreementOpen: false,
            agreementAction: '',
            // The final terms for the Send-agreement modal (_agreement-fields), from Reservation::agreementDefaults().
            agreement: {},
            openAgreement(action, defaults) {
                this.agreementAction = action;
                this.agreement = { ...defaults };
                this.agreementOpen = true;
            },
        }">

        {{-- Header --}}
        <x-page-header title="Reservations" subtitle="Requests from tenants, from the first message to a signed, paid agreement.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </x-slot:icon>
            <x-slot:actions>
                {{-- Export carries the active filters and stage tab --}}
                <a href="{{ route('landlord.reservations.export', request()->only('search', 'property', 'from', 'to', 'stage')) }}"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold transition-all duration-200 shrink-0 cursor-pointer">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export
                </a>
            </x-slot:actions>
        </x-page-header>

        @include('landlord.reservations._section-tabs', ['active' => 'requests', 'pendingViewings' => $pendingViewings])

        @if($errors->any())
            <div class="mb-6 bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Whose turn it is. Each cell narrows the list to what it counts. --}}
        @php $stageUrl = fn ($s) => route('landlord.reservations.index', array_filter(['stage' => $s] + request()->only('search', 'property', 'from', 'to'))); @endphp
        <x-stat-strip class="mb-4" :cells="[
            ['label' => 'Needs your reply', 'value' => $strip['reply'], 'note' => 'New inquiries', 'dot' => $strip['reply'] > 0 ? '#FF8A66' : null, 'href' => $stageUrl('new'), 'active' => $stage === 'new'],
            ['label' => 'Discussing', 'value' => $strip['discussing'], 'note' => 'Send the agreement once agreed', 'href' => $stageUrl('discussing'), 'active' => $stage === 'discussing'],
            ['label' => 'Waiting on tenant', 'value' => $strip['waiting'], 'note' => 'To sign or to pay', 'href' => $stageUrl('agreement'), 'active' => $stage === 'agreement'],
            ['label' => 'Payment to confirm', 'value' => $strip['confirm'], 'note' => $strip['confirm'] > 0 ? '₱'.number_format($strip['confirmAmount'], 2).' reported' : 'Nothing reported', 'dot' => $strip['confirm'] > 0 ? '#FF8A66' : null, 'href' => $stageUrl('payment'), 'active' => $stage === 'payment'],
        ]" />

        {{-- Search + filters --}}
        <form method="GET" action="{{ route('landlord.reservations.index') }}"
            class="bg-white rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-3 mb-4 flex flex-col lg:flex-row gap-2.5">
            @if($stage !== 'open')
                <input type="hidden" name="stage" value="{{ $stage }}">
            @endif
            <div class="relative flex-1 min-w-0">
                <label for="reservation-search" class="sr-only">Search by tenant, unit or property</label>
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="2"
                    class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                </svg>
                <input type="text" id="reservation-search" name="search" value="{{ request('search') }}"
                    placeholder="Search by tenant, unit or property..."
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white transition-all duration-200">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex gap-2.5">
                <div>
                    <label for="filter-property" class="sr-only">Property</label>
                    @php
                        $reservationsPropertyOptions = ['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all();
                    @endphp
                    <x-styled-select name="property" id="filter-property" :options="$reservationsPropertyOptions" :selected="(string) request('property', '')" autosubmit
                        class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[200px]" />
                </div>
                {{-- Requested-date range: one control instead of two always-visible boxes --}}
                <div class="relative"
                    x-data="{
                        rangeOpen: false,
                        from: @js(request('from')),
                        to: @js(request('to')),
                        fmt(iso) {
                            if (!iso) return 'Any';
                            const [y, m, d] = iso.split('-').map(Number);
                            const sameYear = y === new Date().getFullYear();
                            return new Date(y, m - 1, d).toLocaleDateString('en-US', sameYear
                                ? { month: 'short', day: 'numeric' }
                                : { month: 'short', day: 'numeric', year: 'numeric' });
                        },
                        get label() { return (this.from || this.to) ? this.fmt(this.from) + ' – ' + this.fmt(this.to) : 'Requested date'; },
                    }"
                    @click.outside="rangeOpen = false" @keydown.escape.window="rangeOpen = false">
                    <button type="button" @click="rangeOpen = !rangeOpen" :aria-expanded="rangeOpen" aria-haspopup="dialog"
                        class="h-11 lg:h-10 w-full lg:w-auto lg:min-w-[190px] px-3.5 inline-flex items-center gap-2 rounded-xl border bg-[#F7F8FC] text-[13.5px] transition-colors duration-200 cursor-pointer hover:border-[#060D26]/40"
                        :class="(from || to) ? 'border-[#060D26]/40 text-[#060D26] font-semibold' : 'border-[#5B6A8E]/25 text-[#060D26]'">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="2" aria-hidden="true" class="shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span x-text="label" class="truncate"></span>
                    </button>
                    <div x-show="rangeOpen" x-cloak
                        class="absolute right-0 z-30 mt-2 w-[300px] max-w-[calc(100vw-2rem)] rounded-xl bg-white p-4 shadow-[0_4px_24px_rgba(0,0,0,0.12)] ring-1 ring-black/5 space-y-3">
                        <div>
                            <label for="filter-from" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Requested from</label>
                            <x-date-picker name="from" id="filter-from" value="{{ request('from') }}" x-model="from" max-expr="to"
                                x-init="$watch('date', () => $nextTick(() => $el.closest('form').requestSubmit()))" />
                        </div>
                        <div>
                            <label for="filter-to" class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Requested until</label>
                            <x-date-picker name="to" id="filter-to" value="{{ request('to') }}" x-model="to" min-expr="from"
                                x-init="$watch('date', () => $nextTick(() => $el.closest('form').requestSubmit()))" />
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if(request()->hasAny(['search', 'property', 'from', 'to']))
                        <a href="{{ route('landlord.reservations.index', $stage === 'open' ? [] : ['stage' => $stage]) }}"
                            class="h-10 px-3 inline-flex items-center rounded-xl text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#F7F8FC] transition-all duration-200">
                            Clear
                        </a>
                    @endif
                </div>

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-10 p-1 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] ml-auto">
                    <button type="button" x-on:click="setView('table')" aria-label="Table view"
                        :class="view === 'table' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="hidden lg:flex h-8 w-8 items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                    <button type="button" x-on:click="setView('grid')" aria-label="Card view"
                        :class="view === 'grid' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-8 w-8 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </button>
                </div>
            </div>
        </form>

        {{-- Stage tabs: the request pipeline only. Signed and paid requests continue on the Contracts page. --}}
        <div class="flex items-center gap-0.5 border-b border-[#E2E4EC] mb-5 overflow-x-auto overflow-y-hidden">
            @foreach([
                'open' => 'Open',
                'new' => 'New',
                'discussing' => 'Discussing',
                'agreement' => 'Agreement sent',
                'payment' => 'Awaiting payment',
                'closed' => 'Closed',
                'history' => 'Moved in',
            ] as $key => $label)
                <a href="{{ route('landlord.reservations.index', array_filter([
                        'stage' => $key === 'open' ? null : $key,
                        'search' => request('search'),
                        'property' => request('property'),
                        'from' => request('from'),
                        'to' => request('to'),
                    ])) }}"
                    class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                        {{ $stage === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#94A3B8] hover:text-[#060D26]' }}">
                    {{ $label }}
                    @if($counts[$key] > 0)
                        <span class="ml-1 text-[11px] {{ $stage === $key ? 'text-[#060D26]' : 'text-[#94A3B8]' }}">{{ $counts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Reservations table --}}
        @if($reservations->isEmpty())
            <div class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#060D26] mb-1">
                    {{ $stage !== 'open' || request()->hasAny(['search', 'property', 'from', 'to']) ? 'No requests match' : 'No open requests' }}
                </p>
                <p class="text-[13px] text-[#5B6A8E]">New inquiries from tenants show up here. Signed and paid ones are on <a href="{{ route('landlord.contracts.index') }}" class="font-semibold text-[#060D26] underline underline-offset-2">Contracts</a>.</p>
            </div>
        @else
            @php
                $stageTones = [
                    'warn' => 'bg-[#FBBF24]/[0.12] text-[#B45309]',
                    'info' => 'bg-[#ECEEF6] text-[#060D26]',
                    'muted' => 'bg-[#F7F8FC] text-[#5B6A8E]',
                ];

                // Derived per-reservation data shared by the table and card views
                $derived = [];
                foreach ($reservations as $reservation) {
                    if (!$reservation->property) {
                        continue;
                    }
                    $requestStage = $reservation->requestStage();
                    $modalData = [
                        'reservation_id' => $reservation->reservation_id,
                        'reservation_date' => $reservation->reservation_date?->format('M d, Y'),
                        'move_in' => $reservation->target_move_in_date?->format('M d, Y'),
                        'move_out' => $reservation->target_move_out_date?->format('M d, Y'),
                        'duration_of_stay' => $reservation->duration_of_stay,
                        'occupants_count' => $reservation->occupants_count,
                        'remarks' => $reservation->remarks,
                        'rental_status' => $requestStage['label'],
                        'stage_next' => $requestStage['next'],
                        'tenant_name' => trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')),
                        'tenant_contact' => $reservation->tenant->contact_number ?? '—',
                        'property_title' => $reservation->property->title,
                        'unit_label' => $reservation->unit->unit_label ?? 'No unit',
                        'initials' => mb_strtoupper(mb_substr($reservation->tenant->first_name ?? '?', 0, 1) . mb_substr($reservation->tenant->last_name ?? '', 0, 1)),
                        'status_class' => $stageTones[$requestStage['tone']],
                        'rent' => $reservation->agreed_monthly_rent ? '₱' . number_format($reservation->agreed_monthly_rent, 0) . '/mo' : null,
                        'cancel_reservation_url' => route('landlord.reservations.cancel', $reservation),
                    ];
                    $initials = mb_strtoupper(mb_substr($reservation->tenant->first_name ?? '?', 0, 1) . mb_substr($reservation->tenant->last_name ?? '', 0, 1));
                    $moveIn = $reservation->target_move_in_date ?? $reservation->reservation_date;
                    $photo = $reservation->property->media->firstWhere('media_type', 'Image');
                    $derived[$reservation->reservation_id] = compact('modalData', 'initials', 'moveIn', 'photo', 'requestStage');
                }
            @endphp
            {{-- max-lg:!hidden guards against 'table' being the default (or a
                 stale localStorage) preference on a phone — !important beats
                 Alpine's inline x-show style at that breakpoint, so mobile
                 always gets the card grid below regardless of stored view. --}}
            <div x-show="view === 'table'" class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden max-lg:!hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E4EC]">
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Asked for</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Stage</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Requested On</th>
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide text-right">Next step</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($reservations as $reservation)
                                @continue(!$reservation->property)
                                @php extract($derived[$reservation->reservation_id]); @endphp
                                <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                    {{-- Tenant --}}
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            @if($reservation->tenant?->profile_picture)
                                                <img loading="lazy" decoding="async" src="{{ $reservation->tenant->profile_picture }}" alt="{{ $modalData['tenant_name'] }}"
                                                    class="w-9 h-9 rounded-full object-cover shrink-0">
                                            @else
                                                <div class="w-9 h-9 rounded-full bg-[#ECEEF6] text-[#060D26] text-[12px] font-bold flex items-center justify-center shrink-0">
                                                    {{ $initials ?: '?' }}
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-bold text-[#060D26] truncate">
                                                    {{ $reservation->tenant->first_name ?? 'Unknown' }} {{ $reservation->tenant->last_name ?? '' }}
                                                </p>
                                                <p class="text-[11.5px] text-[#5B6A8E]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Property / Unit --}}
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0">
                                                @if($photo)
                                                    <img loading="lazy" decoding="async" src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-semibold text-[#060D26] truncate max-w-[260px]">{{ $reservation->property->title }}</p>
                                                <p class="text-[11.5px] text-[#5B6A8E]">{{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- What the tenant asked for: move-in → move-out (or open), and how many people --}}
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] text-[#060D26] font-medium whitespace-nowrap">
                                            {{ $moveIn?->format('M d, Y') ?? '—' }} <span class="text-[#5B6A8E]">&rarr;</span>
                                            {{ $reservation->target_move_out_date?->format('M d, Y') ?? 'open' }}
                                        </p>
                                        <p class="text-[11.5px] text-[#5B6A8E]">{{ $reservation->occupants_count ? $reservation->occupants_count.' '.\Illuminate\Support\Str::plural('person', $reservation->occupants_count) : 'Occupants not given' }}</p>
                                    </td>

                                    {{-- Stage and whose turn --}}
                                    <td class="px-4 py-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap {{ $stageTones[$requestStage['tone']] }}">{{ $requestStage['label'] }}</span>
                                        <p class="mt-1 text-[11.5px] text-[#5B6A8E]">{{ $requestStage['next'] }}</p>
                                    </td>

                                    {{-- Requested on --}}
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] text-[#060D26] font-medium whitespace-nowrap">{{ $reservation->created_at->format('M d, Y') }}</p>
                                        <p class="text-[11.5px] text-[#5B6A8E]">{{ $reservation->created_at->diffForHumans() }}</p>
                                    </td>

                                    {{-- Next step --}}
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            @include('landlord.reservations._row-actions')
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Table footer: showing text + pagination --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3.5 border-t border-[#E2E4EC]">
                    <p class="text-[12px] text-[#5B6A8E]">
                        Showing {{ $reservations->firstItem() }} to {{ $reservations->lastItem() }} of {{ $reservations->total() }} requests &middot; <a href="{{ route('landlord.contracts.index') }}" class="font-semibold text-[#060D26] hover:underline">Leases of moved-in tenants are on Contracts &rarr;</a>
                    </p>
                    @if($reservations->hasPages())
                        <div>
                            {{ $reservations->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card view — max-lg:!block forces this on for phones regardless
                 of the stored 'view' preference (see the table block above).
                 No x-cloak here: this must paint immediately on mobile, not
                 wait for Alpine's init to remove a cloak attribute. --}}
            <div x-show="view === 'grid'" class="max-lg:!block">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($reservations as $reservation)
                        @continue(!$reservation->property)
                        @php extract($derived[$reservation->reservation_id]); @endphp
                        <article class="flex flex-col bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-4 gap-3">
                            {{-- Tenant + status --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if($reservation->tenant?->profile_picture)
                                        <img loading="lazy" decoding="async" src="{{ $reservation->tenant->profile_picture }}" alt="{{ $modalData['tenant_name'] }}"
                                            class="w-9 h-9 rounded-full object-cover shrink-0">
                                    @else
                                        <div class="w-9 h-9 rounded-full bg-[#ECEEF6] text-[#060D26] text-[12px] font-bold flex items-center justify-center shrink-0">
                                            {{ $initials ?: '?' }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-bold text-[#060D26] truncate">
                                            {{ $reservation->tenant->first_name ?? 'Unknown' }} {{ $reservation->tenant->last_name ?? '' }}
                                        </p>
                                        <p class="text-[11.5px] text-[#5B6A8E]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 inline-flex px-2.5 py-1 rounded-full text-[10.5px] font-semibold whitespace-nowrap {{ $stageTones[$requestStage['tone']] }}">
                                    {{ $requestStage['label'] }}
                                </span>
                            </div>

                            {{-- Property / unit --}}
                            <div class="flex items-center gap-2.5 rounded-xl bg-[#ECEEF6]/60 px-3 py-2.5">
                                <div class="w-9 h-9 rounded-lg bg-white overflow-hidden shrink-0">
                                    @if($photo)
                                        <img loading="lazy" decoding="async" src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12.5px] font-semibold text-[#060D26] truncate">{{ $reservation->property->title }}</p>
                                    <p class="text-[11px] text-[#5B6A8E]">{{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                </div>
                            </div>

                            {{-- What they asked for --}}
                            <div class="grid grid-cols-3 gap-2 text-[11.5px]">
                                <div class="rounded-lg border border-[#E2E4EC] px-2.5 py-1.5">
                                    <p class="text-[10px] uppercase tracking-wide text-[#5B6A8E]">Move in</p>
                                    <p class="font-semibold text-[#060D26] mt-0.5">{{ $moveIn?->format('M d, Y') ?? '—' }}</p>
                                </div>
                                <div class="rounded-lg border border-[#E2E4EC] px-2.5 py-1.5">
                                    <p class="text-[10px] uppercase tracking-wide text-[#5B6A8E]">Move out</p>
                                    <p class="font-semibold text-[#060D26] mt-0.5">{{ $reservation->target_move_out_date?->format('M d, Y') ?? 'Open' }}</p>
                                </div>
                                <div class="rounded-lg border border-[#E2E4EC] px-2.5 py-1.5">
                                    <p class="text-[10px] uppercase tracking-wide text-[#5B6A8E]">People</p>
                                    <p class="font-semibold text-[#060D26] mt-0.5">{{ $reservation->occupants_count ?? '—' }}</p>
                                </div>
                            </div>
                            <p class="text-[12px] font-medium text-[#060D26]">{{ $requestStage['next'] }}</p>
                            <p class="text-[11px] text-[#5B6A8E]">Requested {{ $reservation->created_at->format('M d, Y h:i A') }} &middot; {{ $reservation->created_at->diffForHumans() }}</p>

                            {{-- Next step --}}
                            <div class="flex items-center gap-1.5 flex-wrap mt-auto border-t border-[#E2E4EC] -mx-4 px-4 pt-3">
                                @include('landlord.reservations._row-actions')
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Card footer: showing text + pagination --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-5">
                    <p class="text-[12px] text-[#5B6A8E]">
                        Showing {{ $reservations->firstItem() }} to {{ $reservations->lastItem() }} of {{ $reservations->total() }} requests &middot; <a href="{{ route('landlord.contracts.index') }}" class="font-semibold text-[#060D26] hover:underline">Leases of moved-in tenants are on Contracts &rarr;</a>
                    </p>
                    @if($reservations->hasPages())
                        <div>
                            {{ $reservations->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Details modal --}}
        <template x-teleport="body">
            <div x-show="modalOpen" x-cloak @keydown.escape.window="modalOpen = false"
                class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center sm:p-4">
                <div @click="modalOpen = false" class="absolute inset-0 bg-[#060D26]/50"></div>
                <div class="relative bg-white rounded-t-2xl sm:rounded-2xl shadow-xl w-full sm:max-w-md max-h-[90vh] overflow-y-auto"
                    role="dialog" aria-modal="true" aria-label="Reservation details" x-show="modalOpen" x-transition>
                    <template x-if="selected">
                        <div>
                            {{-- Who: identity + status --}}
                            <div class="flex items-start gap-3.5 p-5 sm:p-6 border-b border-[#E2E4EC]">
                                <div class="h-12 w-12 shrink-0 rounded-full bg-[#060D26] text-white flex items-center justify-center text-[14px] font-semibold"
                                    x-text="selected.initials"></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[16px] font-semibold text-[#060D26] truncate" x-text="selected.tenant_name"></p>
                                    <a :href="'tel:' + selected.tenant_contact"
                                        class="inline-flex items-center gap-1 mt-0.5 text-[13px] text-[#5B6A8E] hover:text-[#060D26] transition-colors duration-200">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                        </svg>
                                        <span x-text="selected.tenant_contact"></span>
                                    </a>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold"
                                            :class="selected.status_class" x-text="selected.rental_status"></span>
                                        <span class="text-[11px] text-[#5B6A8E]" x-text="selected.stage_next"></span>
                                        <template x-if="selected.reservation_date">
                                            <span class="text-[11px] text-[#5B6A8E]">Requested <span x-text="selected.reservation_date"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <button @click="modalOpen = false" aria-label="Close"
                                    class="h-9 w-9 -mr-1.5 -mt-1.5 flex items-center justify-center rounded-lg text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="p-5 sm:p-6 space-y-5">
                                {{-- Where --}}
                                <div>
                                    <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Place</p>
                                    <p class="mt-1 text-[14px] font-semibold text-[#060D26]" x-text="selected.property_title"></p>
                                    <p class="text-[13px] text-[#5B6A8E]" x-text="selected.unit_label"></p>
                                </div>

                                {{-- When: the stay as a range, facts beneath — one unified card
                                     instead of boxed dates + a separate plain stat row, so the
                                     section reads as one fact block, not two container styles. --}}
                                <div>
                                    <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Stay</p>
                                    <div class="mt-2 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] overflow-hidden">
                                        <div class="grid grid-cols-2 divide-x divide-[#E2E4EC]">
                                            <div class="px-3.5 py-3">
                                                <p class="text-[11px] text-[#5B6A8E]">Move-in</p>
                                                <p class="mt-0.5 text-[13.5px] font-semibold text-[#060D26]" x-text="selected.move_in || selected.reservation_date || '—'"></p>
                                            </div>
                                            <div class="px-3.5 py-3">
                                                <p class="text-[11px] text-[#5B6A8E]">Move-out</p>
                                                <p class="mt-0.5 text-[13.5px] font-semibold text-[#060D26]" x-text="selected.move_out || '—'"></p>
                                            </div>
                                        </div>
                                        <dl class="flex flex-wrap gap-x-6 gap-y-2.5 px-3.5 py-3 border-t border-[#E2E4EC] text-[13px]">
                                            <div>
                                                <dt class="text-[11px] text-[#5B6A8E]">Duration</dt>
                                                <dd class="font-medium text-[#060D26]" x-text="selected.duration_of_stay || '—'"></dd>
                                            </div>
                                            {{-- Only shown when actually recorded — an em dash here
                                                 reads as broken data, not "not applicable". --}}
                                            <template x-if="selected.occupants_count">
                                                <div>
                                                    <dt class="text-[11px] text-[#5B6A8E]">Occupants</dt>
                                                    <dd class="font-medium text-[#060D26]" x-text="selected.occupants_count"></dd>
                                                </div>
                                            </template>
                                            <div class="ml-auto text-right">
                                                <dt class="text-[11px] text-[#5B6A8E]">Rent</dt>
                                                <dd class="font-medium text-[#060D26]" x-text="selected.rent || '—'"></dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <template x-if="selected.remarks">
                                    <div>
                                        <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Tenant's remarks</p>
                                        <p class="mt-1 text-[13.5px] leading-relaxed text-[#060D26]" x-text="selected.remarks"></p>
                                    </div>
                                </template>
                            </div>

                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Send agreement modal --}}
        <template x-teleport="body">
            <div x-show="agreementOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div @click="agreementOpen = false" class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[92vh] overflow-y-auto p-6" x-show="agreementOpen" x-transition>
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-normal text-[#060D26]">Send rental agreement</h2>
                        <button @click="agreementOpen = false" aria-label="Close" class="text-[#5B6A8E] hover:text-[#060D26] cursor-pointer">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form :action="agreementAction" method="POST">
                        @csrf @method('PATCH')
                        <div class="p-3 bg-[#ECEEF6] rounded-xl border border-[#FF8A66]/20">
                            @include('landlord.reservations._agreement-fields', ['idPrefix' => 'agreement_modal'])
                            <label class="flex items-start gap-2.5 cursor-pointer group mb-3">
                                <input type="checkbox" name="accept_tc" required
                                    class="mt-0.5 w-4 h-4 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66] focus:ring-offset-0 transition">
                                <span class="text-xs text-[#060D26] leading-relaxed">
                                    <x-payment-consent role="landlord" />
                                </span>
                            </label>
                            <button type="submit"
                                class="w-full h-9 rounded-lg bg-[#FF8A66] text-[#060D26] text-[12px] font-semibold hover:bg-[#E96F4F] cursor-pointer transition-all duration-200">
                                Confirm &amp; send agreement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

    <script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
