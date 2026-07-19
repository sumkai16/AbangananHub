@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-6 pb-10" x-data="{
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
            openAgreement(action) {
                this.agreementAction = action;
                this.agreementOpen = true;
            }
        }">

        {{-- Header --}}
        <div class="flex items-start justify-between mb-5">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#1F2937]">Reservations</h1>
                    <p class="text-sm text-[#64748B] mt-0.5">Manage and respond to reservation requests from tenants.</p>
                </div>
            </div>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-6 bg-[#EEF8F8] text-[#1F2937] rounded-xl px-4 py-3 text-[13px] font-medium flex items-center gap-2">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    class="shrink-0 text-[#2AA7A1]">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary cards --}}
        @php
            $inProgressCount = $counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed'];
            $rejectedCount = $counts['Rejected'] + $counts['Cancelled'];
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF2F5] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#1F2937]">{{ $counts['all'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">In Progress</span>
                    <div class="w-8 h-8 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-[#2AA7A1]">{{ $inProgressCount }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">Awaiting action</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-emerald-600">{{ $counts['Occupied'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>

            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Rejected / Cancelled</span>
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-extrabold text-red-500">{{ $rejectedCount }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>
        </div>

        {{-- Status tabs --}}
        <div class="flex items-center gap-1 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-1.5 mb-4 w-fit max-w-full overflow-x-auto scrollbar-thin-light shadow-lg">
            @foreach([
                'all' => 'All',
                'Inquiry' => 'Inquiry',
                'Under Negotiation' => 'Negotiation',
                'Pending Rental Agreement' => 'Pending Agreement',
                'Rental Agreement Signed' => 'Signed',
                'Occupied' => 'Occupied',
                'Rejected' => 'Rejected',
                'Cancelled' => 'Cancelled',
            ] as $key => $label)
                <a href="{{ route('landlord.reservations.index', array_filter([
                        'status' => $key === 'all' ? null : $key,
                        'search' => request('search'),
                        'property' => request('property'),
                        'from' => request('from'),
                        'to' => request('to'),
                    ])) }}"
                    class="px-4 py-2 rounded-xl text-[13px] font-semibold transition-all duration-150 whitespace-nowrap inline-flex items-center gap-1.5
                              {{ $status === $key ? 'bg-[#2AA7A1] text-white shadow-sm' : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                    {{ $label }}
                    <span class="text-[11px] {{ $status === $key ? 'text-white/80' : 'text-[#64748B]/70' }}">
                        {{ $key === 'all' ? $counts['all'] : $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Search + filters --}}
        <form method="GET" action="{{ route('landlord.reservations.index') }}"
            class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-3 mb-4 flex flex-col lg:flex-row gap-2.5">
            @if($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="relative flex-1 min-w-0">
                <label for="reservation-search" class="sr-only">Search by tenant, unit or property</label>
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                    class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                </svg>
                <input type="text" id="reservation-search" name="search" value="{{ request('search') }}"
                    placeholder="Search by tenant, unit or property..."
                    class="w-full h-10 pl-10 pr-4 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] placeholder-[#64748B]/70 focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-all duration-200">
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:flex gap-2.5">
                <div>
                    <label for="filter-property" class="sr-only">Property</label>
                    <select id="filter-property" name="property"
                        class="h-10 w-full lg:w-44 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] cursor-pointer transition-all duration-200">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->property_id }}" @selected(request('property') == $property->property_id)>
                                {{ $property->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-from" class="sr-only">Requested from</label>
                    <input type="date" id="filter-from" name="from" value="{{ request('from') }}"
                        class="h-10 w-full lg:w-36 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] cursor-pointer transition-all duration-200">
                </div>
                <div>
                    <label for="filter-to" class="sr-only">Requested until</label>
                    <input type="date" id="filter-to" name="to" value="{{ request('to') }}"
                        class="h-10 w-full lg:w-36 rounded-xl border border-[#E2E8F0] bg-white text-[13px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] cursor-pointer transition-all duration-200">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit"
                        class="h-10 px-4 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200">
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'property', 'from', 'to']))
                        <a href="{{ route('landlord.reservations.index', $status === 'all' ? [] : ['status' => $status]) }}"
                            class="h-10 px-3 inline-flex items-center rounded-xl text-[13px] font-semibold text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC] transition-all duration-200">
                            Clear
                        </a>
                    @endif
                </div>

                {{-- View toggle --}}
                <div class="flex items-center gap-0.5 h-10 p-1 rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] ml-auto">
                    <button type="button" x-on:click="setView('table')" aria-label="Table view"
                        :class="view === 'table' ? 'bg-white text-[#156F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'"
                        class="h-8 w-8 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                    <button type="button" x-on:click="setView('grid')" aria-label="Card view"
                        :class="view === 'grid' ? 'bg-white text-[#156F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'"
                        class="h-8 w-8 flex items-center justify-center rounded-lg cursor-pointer transition-all duration-200">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                        </svg>
                    </button>
                </div>
            </div>
        </form>

        {{-- Reservations table --}}
        @if($reservations->isEmpty())
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#1F2937] mb-1">
                    No reservations {{ $status !== 'all' || request()->hasAny(['search', 'property', 'from', 'to']) ? 'match your filters' : 'yet' }}
                </p>
                <p class="text-[13px] text-[#64748B]">Reservation requests from tenants will show up here.</p>
            </div>
        @else
            @php
                $statusStyles = [
                    'Inquiry' => 'bg-[#EEF8F8] text-[#156F8C]',
                    'Under Negotiation' => 'bg-amber-50 text-amber-600',
                    'Pending Rental Agreement' => 'bg-amber-50 text-amber-600',
                    'Rental Agreement Signed' => 'bg-[#EEF8F8] text-[#156F8C]',
                    'Occupied' => 'bg-emerald-50 text-emerald-600',
                    'Rejected' => 'bg-red-50 text-red-600',
                    'Cancelled' => 'bg-slate-100 text-[#64748B]',
                ];

                // Derived per-reservation data shared by the table and card views
                $derived = [];
                foreach ($reservations as $reservation) {
                    if (!$reservation->property) {
                        continue;
                    }
                    $modalData = [
                        'reservation_id' => $reservation->reservation_id,
                        'reservation_date' => $reservation->reservation_date?->format('M d, Y'),
                        'move_in' => $reservation->target_move_in_date?->format('M d, Y'),
                        'move_out' => $reservation->target_move_out_date?->format('M d, Y'),
                        'duration_of_stay' => $reservation->duration_of_stay,
                        'occupants_count' => $reservation->occupants_count,
                        'remarks' => $reservation->remarks,
                        'rental_status' => $reservation->rental_status,
                        'tenant_name' => trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')),
                        'tenant_contact' => $reservation->tenant->contact_number ?? '—',
                        'property_title' => $reservation->property->title,
                        'unit_label' => $reservation->unit->unit_label ?? 'No unit',
                    ];
                    $initials = strtoupper(substr($reservation->tenant->first_name ?? '?', 0, 1) . substr($reservation->tenant->last_name ?? '', 0, 1));
                    $moveIn = $reservation->target_move_in_date ?? $reservation->reservation_date;
                    $photo = $reservation->property->media->firstWhere('media_type', 'Image');
                    $derived[$reservation->reservation_id] = compact('modalData', 'initials', 'moveIn', 'photo');
                }
            @endphp
            <div x-show="view === 'table'" class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left">
                        <thead>
                            <tr class="border-b border-[#E2E8F0]">
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Tenant</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Property / Unit</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Target Move In</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Target Move Out</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Requested On</th>
                                <th class="px-5 py-3.5 text-[11px] font-bold text-[#64748B] uppercase tracking-wide text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach($reservations as $reservation)
                                @continue(!$reservation->property)
                                @php extract($derived[$reservation->reservation_id]); @endphp
                                <tr class="hover:bg-[#F7FCFC]/70 transition-colors duration-200">
                                    {{-- Tenant --}}
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            @if($reservation->tenant?->profile_picture)
                                                <img src="{{ $reservation->tenant->profile_picture }}" alt="{{ $modalData['tenant_name'] }}"
                                                    class="w-9 h-9 rounded-full object-cover shrink-0">
                                            @else
                                                <div class="w-9 h-9 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[12px] font-bold flex items-center justify-center shrink-0">
                                                    {{ $initials ?: '?' }}
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-bold text-[#1F2937] truncate">
                                                    {{ $reservation->tenant->first_name ?? 'Unknown' }} {{ $reservation->tenant->last_name ?? '' }}
                                                </p>
                                                <p class="text-[11.5px] text-[#64748B]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Property / Unit --}}
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-lg bg-[#EEF8F8] overflow-hidden shrink-0">
                                                @if($photo)
                                                    <img src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-semibold text-[#1F2937] truncate max-w-[160px]">{{ $reservation->property->title }}</p>
                                                <p class="text-[11.5px] text-[#64748B]">{{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Move in --}}
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] text-[#1F2937] font-medium whitespace-nowrap">{{ $moveIn?->format('M d, Y') ?? '—' }}</p>
                                        <p class="text-[11.5px] text-[#64748B]">{{ $moveIn?->format('l') }}</p>
                                    </td>

                                    {{-- Move out --}}
                                    <td class="px-4 py-4">
                                        @if($reservation->target_move_out_date)
                                            <p class="text-[13px] text-[#1F2937] font-medium whitespace-nowrap">{{ $reservation->target_move_out_date->format('M d, Y') }}</p>
                                            <p class="text-[11.5px] text-[#64748B]">{{ $reservation->target_move_out_date->format('l') }}</p>
                                        @else
                                            <p class="text-[13px] text-[#1F2937] font-medium whitespace-nowrap">{{ $reservation->duration_of_stay ?? '—' }}</p>
                                            <p class="text-[11.5px] text-[#64748B]">Duration</p>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap {{ $statusStyles[$reservation->rental_status] ?? 'bg-slate-100 text-[#64748B]' }}">
                                            {{ $reservation->rental_status }}
                                        </span>
                                    </td>

                                    {{-- Requested on --}}
                                    <td class="px-4 py-4">
                                        <p class="text-[13px] text-[#1F2937] font-medium whitespace-nowrap">{{ $reservation->created_at->format('M d, Y') }}</p>
                                        <p class="text-[11.5px] text-[#64748B]">{{ $reservation->created_at->format('h:i A') }} &middot; {{ $reservation->created_at->diffForHumans() }}</p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            <button @click="openModal({{ Js::from($modalData) }})"
                                                class="h-8 px-3 inline-flex items-center rounded-lg border border-[#64748B]/25 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] cursor-pointer transition-colors duration-200 whitespace-nowrap">
                                                Details
                                            </button>

                                            @if($reservation->rental_status === 'Inquiry')
                                                <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="h-8 px-3 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                                        Accept
                                                    </button>
                                                </form>
                                                <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                                        Reject
                                                    </button>
                                                </form>
                                            @elseif($reservation->rental_status === 'Under Negotiation')
                                                <button type="button"
                                                    @click="openAgreement('{{ route('landlord.reservations.advanceAgreement', $reservation) }}')"
                                                    class="h-8 px-3 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                                    Send agreement
                                                </button>
                                                <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                                        Reject
                                                    </button>
                                                </form>
                                            @elseif(in_array($reservation->rental_status, ['Pending Rental Agreement', 'Rental Agreement Signed']))
                                                <form action="{{ route('landlord.reservations.cancel', $reservation) }}" method="POST"
                                                    data-confirm="Cancel this reservation?"
                                                    data-confirm-type="warning"
                                                    data-confirm-message="The unit will be marked Available again."
                                                    data-confirm-button="Cancel reservation"
                                                    data-confirm-cancel="Keep it">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                                        Cancel
                                                    </button>
                                                </form>
                                            @elseif($reservation->rental_status === 'Occupied')
                                                @if($reservation->tenantRating)
                                                    <span class="h-8 px-3 inline-flex items-center gap-1 rounded-lg bg-emerald-50 text-emerald-600 text-[12px] font-semibold whitespace-nowrap">
                                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                                        </svg>
                                                        Rated
                                                    </span>
                                                @else
                                                    <a href="{{ route('landlord.reservations.rateTenant', $reservation) }}"
                                                        class="h-8 px-3 inline-flex items-center rounded-lg bg-[#FF8A65] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200 whitespace-nowrap">
                                                        Rate Tenant
                                                    </a>
                                                @endif
                                            @endif

                                            <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                aria-label="Open conversation"
                                                class="h-8 w-8 flex items-center justify-center rounded-lg border border-[#64748B]/25 text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 shrink-0">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Table footer: showing text + pagination --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3.5 border-t border-[#E2E8F0]">
                    <p class="text-[12px] text-[#64748B]">
                        Showing {{ $reservations->firstItem() }} to {{ $reservations->lastItem() }} of {{ $reservations->total() }} reservations
                    </p>
                    @if($reservations->hasPages())
                        <div>
                            {{ $reservations->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card view --}}
            <div x-show="view === 'grid'" x-cloak>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($reservations as $reservation)
                        @continue(!$reservation->property)
                        @php extract($derived[$reservation->reservation_id]); @endphp
                        <article class="flex flex-col bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-4 gap-3">
                            {{-- Tenant + status --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if($reservation->tenant?->profile_picture)
                                        <img src="{{ $reservation->tenant->profile_picture }}" alt="{{ $modalData['tenant_name'] }}"
                                            class="w-9 h-9 rounded-full object-cover shrink-0">
                                    @else
                                        <div class="w-9 h-9 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[12px] font-bold flex items-center justify-center shrink-0">
                                            {{ $initials ?: '?' }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-bold text-[#1F2937] truncate">
                                            {{ $reservation->tenant->first_name ?? 'Unknown' }} {{ $reservation->tenant->last_name ?? '' }}
                                        </p>
                                        <p class="text-[11.5px] text-[#64748B]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 inline-flex px-2.5 py-1 rounded-full text-[10.5px] font-semibold whitespace-nowrap {{ $statusStyles[$reservation->rental_status] ?? 'bg-slate-100 text-[#64748B]' }}">
                                    {{ $reservation->rental_status }}
                                </span>
                            </div>

                            {{-- Property / unit --}}
                            <div class="flex items-center gap-2.5 rounded-xl bg-[#EEF8F8]/60 px-3 py-2.5">
                                <div class="w-9 h-9 rounded-lg bg-white overflow-hidden shrink-0">
                                    @if($photo)
                                        <img src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12.5px] font-semibold text-[#1F2937] truncate">{{ $reservation->property->title }}</p>
                                    <p class="text-[11px] text-[#64748B]">{{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                </div>
                            </div>

                            {{-- Dates --}}
                            <div class="grid grid-cols-2 gap-2 text-[11.5px]">
                                <div class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5">
                                    <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Move In</p>
                                    <p class="font-semibold text-[#1F2937] mt-0.5">{{ $moveIn?->format('M d, Y') ?? '—' }}</p>
                                </div>
                                <div class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5">
                                    <p class="text-[10px] uppercase tracking-wide text-[#64748B]">
                                        {{ $reservation->target_move_out_date ? 'Move Out' : 'Duration' }}
                                    </p>
                                    <p class="font-semibold text-[#1F2937] mt-0.5">
                                        {{ $reservation->target_move_out_date?->format('M d, Y') ?? ($reservation->duration_of_stay ?? '—') }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-[11px] text-[#64748B]">Requested {{ $reservation->created_at->format('M d, Y h:i A') }} &middot; {{ $reservation->created_at->diffForHumans() }}</p>

                            {{-- Actions --}}
                            <div class="flex items-center gap-1.5 flex-wrap pt-1 mt-auto border-t border-[#E2E8F0] -mx-4 px-4 pt-3">
                                <button @click="openModal({{ Js::from($modalData) }})"
                                    class="h-8 px-3 inline-flex items-center rounded-lg border border-[#64748B]/25 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] cursor-pointer transition-colors duration-200 whitespace-nowrap">
                                    Details
                                </button>

                                @if($reservation->rental_status === 'Inquiry')
                                    <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-8 px-3 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                            Accept
                                        </button>
                                    </form>
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                            Reject
                                        </button>
                                    </form>
                                @elseif($reservation->rental_status === 'Under Negotiation')
                                    <button type="button"
                                        @click="openAgreement('{{ route('landlord.reservations.advanceAgreement', $reservation) }}')"
                                        class="h-8 px-3 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                        Send agreement
                                    </button>
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                            Reject
                                        </button>
                                    </form>
                                @elseif(in_array($reservation->rental_status, ['Pending Rental Agreement', 'Rental Agreement Signed']))
                                    <form action="{{ route('landlord.reservations.cancel', $reservation) }}" method="POST"
                                        data-confirm="Cancel this reservation?"
                                        data-confirm-type="warning"
                                        data-confirm-message="The unit will be marked Available again."
                                        data-confirm-button="Cancel reservation"
                                        data-confirm-cancel="Keep it">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-8 px-3 rounded-lg bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200 whitespace-nowrap">
                                            Cancel
                                        </button>
                                    </form>
                                @elseif($reservation->rental_status === 'Occupied')
                                    @if($reservation->tenantRating)
                                        <span class="h-8 px-3 inline-flex items-center gap-1 rounded-lg bg-emerald-50 text-emerald-600 text-[12px] font-semibold whitespace-nowrap">
                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                            </svg>
                                            Rated
                                        </span>
                                    @else
                                        <a href="{{ route('landlord.reservations.rateTenant', $reservation) }}"
                                            class="h-8 px-3 inline-flex items-center rounded-lg bg-[#FF8A65] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200 whitespace-nowrap">
                                            Rate Tenant
                                        </a>
                                    @endif
                                @endif

                                <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                    aria-label="Open conversation"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg border border-[#64748B]/25 text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200 shrink-0 ml-auto">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Card footer: showing text + pagination --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-5">
                    <p class="text-[12px] text-[#64748B]">
                        Showing {{ $reservations->firstItem() }} to {{ $reservations->lastItem() }} of {{ $reservations->total() }} reservations
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
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-30 flex items-center justify-center p-4">
                <div @click="modalOpen = false" class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-show="modalOpen" x-transition>
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-bold text-[#1F2937]">Reservation details</h2>
                        <button @click="modalOpen = false" aria-label="Close" class="text-[#64748B] hover:text-[#1F2937] cursor-pointer">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <template x-if="selected">
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Tenant</span>
                                <span class="font-semibold text-[#1F2937]" x-text="selected.tenant_name"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Contact</span>
                                <span class="text-[#1F2937]" x-text="selected.tenant_contact"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Property</span>
                                <span class="text-[#1F2937]" x-text="selected.property_title"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Unit</span>
                                <span class="text-[#1F2937]" x-text="selected.unit_label"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Target move-in</span>
                                <span class="text-[#1F2937]" x-text="selected.move_in || selected.reservation_date || '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Target move-out</span>
                                <span class="text-[#1F2937]" x-text="selected.move_out || '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Duration of stay</span>
                                <span class="text-[#1F2937]" x-text="selected.duration_of_stay || '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Occupants</span>
                                <span class="text-[#1F2937]" x-text="selected.occupants_count || '—'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#64748B]">Status</span>
                                <span class="font-semibold text-[#1F2937]" x-text="selected.rental_status"></span>
                            </div>
                            <template x-if="selected.remarks">
                                <div class="pt-2 border-t border-[#64748B]/15">
                                    <p class="text-[#64748B] mb-1">Tenant's remarks</p>
                                    <p class="text-[#1F2937]" x-text="selected.remarks"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Send agreement modal --}}
        <template x-teleport="body">
            <div x-show="agreementOpen" x-cloak class="fixed inset-0 z-30 flex items-center justify-center p-4">
                <div @click="agreementOpen = false" class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-show="agreementOpen" x-transition>
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-bold text-[#1F2937]">Send rental agreement</h2>
                        <button @click="agreementOpen = false" aria-label="Close" class="text-[#64748B] hover:text-[#1F2937] cursor-pointer">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form :action="agreementAction" method="POST">
                        @csrf @method('PATCH')
                        <div class="p-3 bg-[#EEF8F8] rounded-xl border border-[#2AA7A1]/20">
                            <label class="flex items-start gap-2.5 cursor-pointer group mb-3">
                                <input type="checkbox" name="accept_tc" required
                                    class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                                <span class="text-xs text-[#1F2937] leading-relaxed">
                                    I agree that the tenant's payment will be held by AbangananHub until the tenant confirms move-in. Funds will be released only after tenant verification.
                                </span>
                            </label>
                            <button type="submit"
                                class="w-full h-9 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 cursor-pointer transition-all duration-200">
                                Confirm &amp; send agreement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endsection
