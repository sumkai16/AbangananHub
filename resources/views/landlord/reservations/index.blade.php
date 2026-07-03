@extends('layouts.landlord')

@section('content')
    <div class="px-4 sm:px-8 lg:px-[50px] py-8" x-data="{
            modalOpen: false,
            selected: null,
            openModal(reservation) {
                this.selected = reservation;
                this.modalOpen = true;
            }
        }">

        {{-- Header --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#2A2523]">Reservations</h1>
                <p class="text-sm text-[#9B9F98] mt-1">Manage and respond to reservation requests from tenants.</p>
            </div>
        </div>

        {{-- Flash --}}
        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#9B9F98]/15 rounded-2xl p-4">
                <p class="text-[12px] font-semibold text-[#9B9F98] mb-1">Total reservations</p>
                <p class="text-2xl font-bold text-[#2A2523]">{{ $counts['all'] }}</p>
                <p class="text-[11px] text-[#9B9F98] mt-1">All time</p>
            </div>
            <div class="bg-amber-50 border border-amber-200/60 rounded-2xl p-4">
                <p class="text-[12px] font-semibold text-amber-700 mb-1">In progress</p>
                <p class="text-2xl font-bold text-amber-800">
                    {{ $counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed'] }}
                </p>
                <p class="text-[11px] text-amber-700 mt-1">Awaiting action</p>
            </div>
            <div class="bg-green-50 border border-green-200/60 rounded-2xl p-4">
                <p class="text-[12px] font-semibold text-green-700 mb-1">Occupied</p>
                <p class="text-2xl font-bold text-green-800">{{ $counts['Occupied'] }}</p>
                <p class="text-[11px] text-green-700 mt-1">All time</p>
            </div>
            <div class="bg-red-50 border border-red-200/60 rounded-2xl p-4">
                <p class="text-[12px] font-semibold text-red-700 mb-1">Rejected / Cancelled</p>
                <p class="text-2xl font-bold text-red-800">{{ $counts['Rejected'] + $counts['Cancelled'] }}</p>
                <p class="text-[11px] text-red-700 mt-1">All time</p>
            </div>
        </div>

        {{-- Status tabs --}}
        <div class="flex items-center gap-1 border-b border-[#9B9F98]/15 mb-5 overflow-x-auto">
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
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors duration-150 whitespace-nowrap
                              {{ $status === $key ? 'border-[#61B2F0] text-[#2A2523]' : 'border-transparent text-[#9B9F98] hover:text-[#2A2523]' }}">
                    {{ $label }}
                    <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#61B2F0]' : 'text-[#9B9F98]' }}">
                        {{ $key === 'all' ? $counts['all'] : $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Table --}}
        <div class="bg-white border border-[#9B9F98]/15 rounded-2xl overflow-hidden">
            @if($reservations->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#9B9F98" stroke-width="1.5"
                        class="mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <p class="text-sm font-semibold text-[#2A2523] mb-1">No reservations
                        {{ $status !== 'all' ? 'with this status' : 'yet' }}</p>
                    <p class="text-[13px] text-[#9B9F98]">Reservation requests from tenants will show up here.</p>
                </div>
            @else
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[#F0EDE8] text-[11px] font-bold text-[#9B9F98] uppercase tracking-wider">
                            <th class="px-5 py-3">Tenant</th>
                            <th class="px-5 py-3">Property / Unit</th>
                            <th class="px-5 py-3">Move-in date</th>
                            <th class="px-5 py-3">Duration</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservations as $reservation)
                            @continue(!$reservation->property)
                            @php
                                $statusStyles = [
                                    'Inquiry' => 'bg-amber-100 text-amber-700',
                                    'Under Negotiation' => 'bg-amber-100 text-amber-700',
                                    'Pending Rental Agreement' => 'bg-blue-100 text-blue-700',
                                    'Rental Agreement Signed' => 'bg-blue-100 text-blue-700',
                                    'Occupied' => 'bg-green-100 text-green-700',
                                    'Rejected' => 'bg-red-100 text-red-700',
                                    'Cancelled' => 'bg-[#9B9F98]/15 text-[#9B9F98]',
                                ];
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
                            @endphp
                            <tr class="border-t border-[#9B9F98]/10 hover:bg-[#F0EDE8]/40 transition-colors duration-150">
                                <td class="px-5 py-4">
                                    <p class="text-sm font-semibold text-[#2A2523]">
                                        {{ $reservation->tenant->first_name ?? 'Unknown' }}
                                        {{ $reservation->tenant->last_name ?? '' }}
                                    </p>
                                    <p class="text-[12px] text-[#9B9F98]">{{ $reservation->tenant->contact_number ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-lg bg-[#F0EDE8] overflow-hidden shrink-0">
                                            @if($photo = $reservation->property->media->first())
                                                <img src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-[#2A2523] truncate">
                                                {{ $reservation->property->title }}</p>
                                            <p class="text-[12px] text-[#9B9F98] truncate">
                                                {{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-[#2A2523]">{{ $reservation->reservation_date?->format('M d, Y') ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-[#2A2523]">{{ $reservation->duration_of_stay ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $statusStyles[$reservation->rental_status] ?? '' }}">
                                        {{ $reservation->rental_status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openModal({{ Js::from($modalData) }})"
                                            class="text-[12px] font-semibold text-[#61B2F0] hover:underline px-2 py-1.5">
                                            View Details
                                        </button>

                                        @if($reservation->rental_status === 'Inquiry')
                                            <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-green-600 hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Accept &amp; negotiate
                                                </button>
                                            </form>
                                            <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-[#BD5434] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Reject
                                                </button>
                                            </form>
                                        @elseif($reservation->rental_status === 'Under Negotiation')
                                            <form action="{{ route('landlord.reservations.advanceAgreement', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-green-600 hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Send agreement
                                                </button>
                                            </form>
                                            <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-[#BD5434] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Reject
                                                </button>
                                            </form>
                                        @elseif(in_array($reservation->rental_status, ['Pending Rental Agreement', 'Rental Agreement Signed']))
                                            <form action="{{ route('landlord.reservations.cancel', $reservation) }}" method="POST"
                                                onsubmit="return confirm('Cancel this reservation? The unit will be marked Available again.')">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-[#BD5434] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Cancel
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                            class="text-[12px] font-semibold text-[#2A2523] border border-[#9B9F98]/20 rounded-lg px-3 py-1.5 hover:bg-[#F0EDE8] transition-all duration-150">
                                            Chat
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

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
                    <h2 class="text-lg font-bold text-[#2A2523]">Reservation details</h2>
                    <button @click="modalOpen = false" class="text-[#9B9F98] hover:text-[#2A2523]">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="selected">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Tenant</span>
                            <span class="font-semibold text-[#2A2523]" x-text="selected.tenant_name"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Contact</span>
                            <span class="text-[#2A2523]" x-text="selected.tenant_contact"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Property</span>
                            <span class="text-[#2A2523]" x-text="selected.property_title"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Unit</span>
                            <span class="text-[#2A2523]" x-text="selected.unit_label"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Move-in date</span>
                            <span class="text-[#2A2523]" x-text="selected.reservation_date"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Duration of stay</span>
                            <span class="text-[#2A2523]" x-text="selected.duration_of_stay || '—'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Occupants</span>
                            <span class="text-[#2A2523]" x-text="selected.occupants_count || '—'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#9B9F98]">Status</span>
                            <span class="font-semibold text-[#2A2523]" x-text="selected.rental_status"></span>
                        </div>
                        <template x-if="selected.remarks">
                            <div class="pt-2 border-t border-[#9B9F98]/15">
                                <p class="text-[#9B9F98] mb-1">Tenant's remarks</p>
                                <p class="text-[#2A2523]" x-text="selected.remarks"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endsection
