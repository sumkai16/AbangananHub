@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-6 pb-10" x-data="{
            modalOpen: false,
            selected: null,
            openModal(reservation) {
                this.selected = reservation;
                this.modalOpen = true;
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
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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

            <div class="bg-white rounded-2xl ring-1 ring-[#2AA7A1]/30 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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

            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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

            <div class="bg-white rounded-2xl ring-1 ring-red-100 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
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
        <div class="flex items-center gap-1 bg-white ring-1 ring-[#64748B]/10 rounded-2xl p-1.5 mb-4 w-fit max-w-full overflow-x-auto scrollbar-thin-light shadow-[0_2px_12px_rgba(15,23,42,0.05)]">
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
                <a href="{{ route('landlord.reservations.index', $key === 'all' ? [] : ['status' => $key]) }}"
                    class="px-4 py-2 rounded-xl text-[13px] font-semibold transition-all duration-150 whitespace-nowrap inline-flex items-center gap-1.5
                              {{ $status === $key ? 'bg-[#2AA7A1] text-white shadow-sm' : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                    {{ $label }}
                    <span class="text-[11px] {{ $status === $key ? 'text-white/80' : 'text-[#64748B]/70' }}">
                        {{ $key === 'all' ? $counts['all'] : $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Reservation cards --}}
        @if($reservations->isEmpty())
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#1F2937] mb-1">No reservations
                    {{ $status !== 'all' ? 'with this status' : 'yet' }}</p>
                <p class="text-[13px] text-[#64748B]">Reservation requests from tenants will show up here.</p>
            </div>
        @else
            <div class="space-y-4">
                @php
                    $stages = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied'];
                    $stageLabels = ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Occupied'];
                @endphp
                @foreach($reservations as $reservation)
                    @continue(!$reservation->property)
                    @php
                        $modalData = [
                            'reservation_id' => $reservation->reservation_id,
                            'reservation_date' => $reservation->reservation_date?->format('M d, Y'),
                            'duration_of_stay' => $reservation->duration_of_stay,
                            'occupants_count' => $reservation->occupants_count,
                            'remarks' => $reservation->remarks,
                            'rental_status' => $reservation->rental_status,
                            'tenant_name' => trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')),
                            'tenant_contact' => $reservation->tenant->contact_number ?? '—',
                            'property_title' => $reservation->property->title,
                            'unit_label' => $reservation->unit->unit_label ?? 'No unit',
                        ];
                        $isTerminal = in_array($reservation->rental_status, ['Rejected', 'Cancelled']);
                        $stageIndex = array_search($reservation->rental_status, $stages);
                        $initials = strtoupper(substr($reservation->tenant->first_name ?? '?', 0, 1) . substr($reservation->tenant->last_name ?? '', 0, 1));
                    @endphp

                    <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:shadow-[0_6px_20px_rgba(15,23,42,0.08)] transition-shadow duration-200 overflow-hidden">
                        <div class="p-5 flex flex-col lg:flex-row lg:items-center gap-5">

                            {{-- Tenant + property --}}
                            <div class="flex items-start gap-3 lg:w-[280px] shrink-0">
                                <div class="w-10 h-10 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[13px] font-bold flex items-center justify-center shrink-0">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-bold text-[#1F2937] truncate">
                                        {{ $reservation->tenant->first_name ?? 'Unknown' }} {{ $reservation->tenant->last_name ?? '' }}
                                    </p>
                                    <p class="text-[12px] text-[#64748B]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                    <div class="flex items-center gap-1.5 mt-1.5">
                                        <div class="w-6 h-6 rounded-md bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($photo = $reservation->property->media->first())
                                                <img src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <p class="text-[12px] text-[#64748B] truncate">
                                            {{ $reservation->property->title }} &middot; {{ $reservation->unit->unit_label ?? 'No unit' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Lifecycle stepper / terminal banner --}}
                            <div class="flex-1 min-w-0">
                                @if($isTerminal)
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-50 text-red-600 text-[12px] font-semibold">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                        {{ $reservation->rental_status }}
                                    </div>
                                @else
                                    <div class="flex items-center">
                                        @foreach($stageLabels as $i => $label)
                                            <div class="flex items-center {{ $i < count($stageLabels) - 1 ? 'flex-1' : '' }}">
                                                <div class="flex flex-col items-center shrink-0">
                                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0
                                                        {{ $i < $stageIndex ? 'bg-emerald-500 text-white' : ($i === $stageIndex ? 'bg-[#2AA7A1] text-white ring-4 ring-[#2AA7A1]/20' : 'bg-[#E2E8F0] text-[#64748B]') }}">
                                                        @if($i < $stageIndex)
                                                            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                            </svg>
                                                        @else
                                                            {{ $i + 1 }}
                                                        @endif
                                                    </div>
                                                    <span class="text-[9.5px] mt-1 whitespace-nowrap {{ $i === $stageIndex ? 'font-bold text-[#1F2937]' : 'text-[#64748B]' }}">{{ $label }}</span>
                                                </div>
                                                @if($i < count($stageLabels) - 1)
                                                    <div class="flex-1 h-0.5 mx-1.5 -mt-4 {{ $i < $stageIndex ? 'bg-emerald-500' : 'bg-[#E2E8F0]' }}"></div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center gap-4 mt-3 text-[11.5px] text-[#64748B]">
                                    <span class="flex items-center gap-1">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                        </svg>
                                        Move-in: {{ $reservation->reservation_date?->format('M d, Y') ?? '—' }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                                            <circle cx="12" cy="12" r="9" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        {{ $reservation->duration_of_stay ?? '—' }}
                                    </span>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center flex-wrap gap-2 lg:justify-end shrink-0 lg:w-[280px]">
                                <button @click="openModal({{ Js::from($modalData) }})"
                                    class="h-9 px-3.5 inline-flex items-center gap-1 rounded-full border border-[#64748B]/25 text-[#1F2937] text-[12px] font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                                    Details
                                </button>

                                @if($reservation->rental_status === 'Inquiry')
                                    <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-9 px-3.5 rounded-full bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                            Accept &amp; negotiate
                                        </button>
                                    </form>
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-9 px-3.5 rounded-full bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                            Reject
                                        </button>
                                    </form>
                                @elseif($reservation->rental_status === 'Under Negotiation')
                                    <div x-data="{ showTc: false }">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="showTc = !showTc"
                                                class="h-9 px-3.5 rounded-full bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                                Send agreement
                                            </button>
                                            <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="h-9 px-3.5 rounded-full bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                        <div x-show="showTc" x-transition class="mt-3 w-full lg:w-auto">
                                            <form action="{{ route('landlord.reservations.advanceAgreement', $reservation) }}" method="POST">
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
                                                        class="w-full h-9 rounded-lg bg-[#2AA7A1] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                                        Confirm &amp; send agreement
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @elseif(in_array($reservation->rental_status, ['Pending Rental Agreement', 'Rental Agreement Signed']))
                                    <form action="{{ route('landlord.reservations.cancel', $reservation) }}" method="POST"
                                        data-confirm="Cancel this reservation?"
                                        data-confirm-type="warning"
                                        data-confirm-message="The unit will be marked Available again."
                                        data-confirm-button="Cancel reservation"
                                        data-confirm-cancel="Keep it">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            class="h-9 px-3.5 rounded-full bg-red-500 text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                            Cancel
                                        </button>
                                    </form>
                                @endif
                                @if($reservation->rental_status === 'Occupied')
                                    @if($reservation->tenantRating)
                                        <span class="h-9 px-3.5 inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-600 text-[12px] font-semibold">
                                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                            </svg>
                                            Rated
                                        </span>
                                    @else
                                        <a href="{{ route('landlord.reservations.rateTenant', $reservation) }}"
                                            class="h-9 px-3.5 inline-flex items-center rounded-full bg-[#FF8A65] text-white text-[12px] font-semibold hover:brightness-95 transition-all duration-200">
                                            Rate Tenant
                                        </a>
                                    @endif
                                @endif
                                <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                    class="h-9 w-9 flex items-center justify-center rounded-full border border-[#64748B]/25 text-[#1F2937] hover:bg-[#EEF8F8] transition-colors duration-200">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($reservations->hasPages())
            <div class="mt-5">
                {{ $reservations->links() }}
            </div>
        @endif

        {{-- Details modal --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div @click="modalOpen = false" class="absolute inset-0 bg-black/40"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" x-show="modalOpen" x-transition>
                <div class="flex items-start justify-between mb-4">
                    <h2 class="text-lg font-bold text-[#1F2937]">Reservation details</h2>
                    <button @click="modalOpen = false" class="text-[#64748B] hover:text-[#1F2937]">
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
                            <span class="text-[#64748B]">Move-in date</span>
                            <span class="text-[#1F2937]" x-text="selected.reservation_date"></span>
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
    </div>
@endsection
