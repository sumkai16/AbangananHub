@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-14 min-h-[calc(100vh-72px)] flex flex-col" x-data="{
            modalOpen: false,
            selected: null,
            openModal(reservation) {
                this.selected = reservation;
                this.modalOpen = true;
            }
        }">

        {{-- Header --}}
        <div class="flex items-center gap-3.5 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-[#1F2937]">My Reservations</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Track your rental inquiries and reservations.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Total</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $counts['all'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>
            <div class="bg-white rounded-2xl ring-1 ring-[#2AA7A1]/25 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#156F8C] uppercase tracking-wide">In progress</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">
                    {{ $counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed'] }}
                </span>
                <p class="text-[11px] text-[#64748B] mt-1">Awaiting action</p>
            </div>
            <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Occupied</span>
                    <div class="w-7 h-7 rounded-lg bg-[#22C55E]/[0.07] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#16A34A" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#15803D]">{{ $counts['Occupied'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>
            <div class="bg-white rounded-2xl ring-1 ring-[#EF4444]/20 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-bold text-[#DC2626] uppercase tracking-wide">Cancelled/Rejected</span>
                    <div class="w-7 h-7 rounded-lg bg-[#EF4444]/[0.07] flex items-center justify-center shrink-0">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <span class="text-xl font-extrabold text-[#1F2937]">{{ $counts['Cancelled'] + $counts['Rejected'] }}</span>
                <p class="text-[11px] text-[#64748B] mt-1">All time</p>
            </div>
        </div>

        {{-- Status tabs --}}
        <div class="flex items-center gap-1 border-b border-[#64748B]/15 mb-5 overflow-x-auto">
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
                <a href="{{ route('reservations.index', $key === 'all' ? [] : ['status' => $key]) }}"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors duration-150 whitespace-nowrap
                              {{ $status === $key ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#64748B] hover:text-[#1F2937]' }}">
                    {{ $label }}
                    <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#156F8C]' : 'text-[#64748B]' }}">
                        {{ $key === 'all' ? $counts['all'] : $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] overflow-hidden flex-1 flex flex-col">
            @if($reservations->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] flex items-center justify-center mb-4">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <p class="text-[15px] font-bold text-[#1F2937] mb-1">No reservations
                        {{ $status !== 'all' ? 'with this status' : 'yet' }}</p>
                    <p class="text-[13px] text-[#64748B] mb-5 max-w-xs">Your rental inquiries and reservations will show up here once you contact a landlord.</p>
                    @if($status === 'all')
                        <a href="{{ route('properties.index') }}"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-white bg-[#2AA7A1] hover:brightness-95 transition-all shadow-sm">
                            Browse properties
                        </a>
                    @else
                        <a href="{{ route('reservations.index') }}"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-[#1F2937] bg-white ring-1 ring-[#64748B]/15 hover:bg-[#EEF8F8] transition-all">
                            View all reservations
                        </a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[820px] text-left">
                    <thead>
                        <tr class="bg-[#F7FCFC] text-[11px] font-bold text-[#64748B] uppercase tracking-wider">
                            <th class="px-5 py-3">Property / Unit</th>
                            <th class="px-5 py-3">Landlord</th>
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
                                    'Inquiry' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                    'Under Negotiation' => 'bg-[#FBBF24]/[0.10] text-[#B45309]',
                                    'Pending Rental Agreement' => 'bg-[#EEF8F8] text-[#156F8C]',
                                    'Rental Agreement Signed' => 'bg-[#EEF8F8] text-[#156F8C]',
                                    'Occupied' => 'bg-[#22C55E]/[0.07] text-[#15803D]',
                                    'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626]',
                                    'Cancelled' => 'bg-[#64748B]/10 text-[#64748B]',
                                ];
                                $landlordName = trim(($reservation->property->landlord->first_name ?? '') . ' ' . ($reservation->property->landlord->last_name ?? ''));
                                $modalData = [
                                    'reservation_id' => $reservation->reservation_id,
                                    'reservation_date' => $reservation->reservation_date?->format('M d, Y'),
                                    'duration_of_stay' => $reservation->duration_of_stay,
                                    'occupants_count' => $reservation->occupants_count,
                                    'remarks' => $reservation->remarks,
                                    'rental_status' => $reservation->rental_status,
                                    'landlord_name' => $landlordName !== '' ? $landlordName : 'Unknown',
                                    'landlord_contact' => $reservation->property->landlord->contact_number ?? '—',
                                    'property_title' => $reservation->property->title,
                                    'unit_label' => $reservation->unit->unit_label ?? 'No unit',
                                ];
                            @endphp
                            <tr class="border-t border-[#64748B]/10 hover:bg-[#F7FCFC] transition-colors duration-150">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-lg bg-[#EEF8F8] overflow-hidden shrink-0">
                                            @if($photo = $reservation->property->media->first())
                                                <img src="{{ $photo->media_url }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-[#1F2937] truncate">
                                                {{ $reservation->property->title }}</p>
                                            <p class="text-[12px] text-[#64748B] truncate">
                                                {{ $reservation->unit->unit_label ?? 'No unit' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-semibold text-[#1F2937]">
                                        {{ $landlordName !== '' ? $landlordName : 'Unknown' }}
                                    </p>
                                    <p class="text-[12px] text-[#64748B]">{{ $reservation->property->landlord->contact_number ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-[#1F2937]">{{ $reservation->reservation_date?->format('M d, Y') ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm text-[#1F2937]">{{ $reservation->duration_of_stay ?? '—' }}</p>
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
                                            class="text-[12px] font-semibold text-[#156F8C] hover:underline px-2 py-1.5">
                                            View Details
                                        </button>

                                        @if(in_array($reservation->rental_status, ['Inquiry', 'Under Negotiation']))
                                            <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-[#EF4444] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Cancel
                                                </button>
                                            </form>
                                            <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                Chat
                                            </a>
                                        @elseif(in_array($reservation->rental_status, ['Pending Rental Agreement', 'Rental Agreement Signed']))
                                            <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
                                                data-confirm="Cancel this reservation?"
                                                data-confirm-type="warning"
                                                data-confirm-message="This action cannot be undone."
                                                data-confirm-button="Cancel reservation"
                                                data-confirm-cancel="Keep it">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    class="text-[12px] font-semibold text-white bg-[#EF4444] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                    Cancel
                                                </button>
                                            </form>
                                            <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                Chat
                                            </a>
                                        @elseif($reservation->rental_status === 'Occupied')
                                            <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                Chat
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        @if($reservations->hasPages())
            <div class="mt-5">
                {{ $reservations->links() }}
            </div>
        @endif

        {{-- Details modal --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div @click="modalOpen = false" class="absolute inset-0 bg-[#1F2937]/40"></div>
            <div class="relative bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-2xl w-full max-w-md p-6" x-show="modalOpen" x-transition>
                <div class="flex items-start justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                        </div>
                        <h2 class="text-[16px] font-bold text-[#1F2937]">Reservation details</h2>
                    </div>
                    <button @click="modalOpen = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-colors">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <template x-if="selected">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#64748B]">Landlord</span>
                            <span class="font-semibold text-[#1F2937]" x-text="selected.landlord_name"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#64748B]">Contact</span>
                            <span class="text-[#1F2937]" x-text="selected.landlord_contact"></span>
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
                        <div class="flex justify-between items-center pt-2 border-t border-[#E2E8F0]">
                            <span class="text-[#64748B]">Status</span>
                            <span class="font-bold text-[#1F2937]" x-text="selected.rental_status"></span>
                        </div>
                        <template x-if="selected.remarks">
                            <div class="pt-3 border-t border-[#E2E8F0]">
                                <p class="text-[#64748B] mb-1">Your remarks</p>
                                <p class="text-[#1F2937]" x-text="selected.remarks"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endsection
