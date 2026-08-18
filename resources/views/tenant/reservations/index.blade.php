@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)] flex flex-col" x-data="{
            modalOpen: false,
            selected: null,
            openModal(reservation) {
                this.selected = reservation;
                this.modalOpen = true;
            }
        }">

        {{-- Header --}}
        <x-page-header title="My Reservations" subtitle="Track your rental inquiries and reservations.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        @if($errors->any())
            <div class="mb-6 bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <x-stat-card label="Total" :value="$counts['all']" sub="All time">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#1F2937" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="In progress"
                :value="$counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed']"
                sub="Awaiting action">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Occupied" :value="$counts['Occupied']" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)" sub="All time">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#16A34A" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Cancelled/Rejected" :value="$counts['Cancelled'] + $counts['Rejected']" icon-bg="rgba(239,68,68,0.07)" sub="All time">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
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
        <x-card flush class="flex-1 flex flex-col">
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
                                $journeySteps = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied'];
                                $isTerminal = in_array($reservation->rental_status, ['Rejected', 'Cancelled']);
                                $stepIndex = array_search($reservation->rental_status, $journeySteps);
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
                                    'property_photo' => $reservation->property->media->first()?->media_url,
                                    'is_terminal' => $isTerminal,
                                    'step_index' => $isTerminal ? -1 : $stepIndex,
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
                                            @if($reservation->conversation)
                                                <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                    class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                    Chat
                                                </a>
                                            @endif
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
                                            @if($reservation->conversation)
                                                <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                    class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                    Chat
                                                </a>
                                            @endif
                                        @elseif($reservation->rental_status === 'Occupied')
                                            <a href="{{ route('tenancy.show', $reservation) }}"
                                                class="text-[12px] font-semibold text-white bg-[#2AA7A1] hover:brightness-95 rounded-lg px-3 py-1.5 transition-all duration-150">
                                                Rent
                                            </a>
                                            @if($reservation->conversation)
                                                <a href="{{ route('conversations.show', $reservation->conversation) }}"
                                                    class="text-[12px] font-semibold text-[#1F2937] border border-[#E2E8F0] rounded-lg px-3 py-1.5 hover:bg-[#F7FCFC] transition-all duration-150">
                                                    Chat
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </x-card>

        @if($reservations->hasPages())
            <div class="mt-5">
                {{ $reservations->links() }}
            </div>
        @endif

        {{-- Details modal --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4" x-data="{
                journey: ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Occupied']
            }">
            <div @click="modalOpen = false" class="absolute inset-0 bg-[#1F2937]/40 backdrop-blur-sm"></div>

            <template x-if="selected">
                <div class="relative bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-2xl w-full max-w-md overflow-hidden max-h-[90vh] flex flex-col"
                    x-show="modalOpen" x-transition
                    @keydown.escape.window="modalOpen = false">

                    {{-- Photo banner --}}
                    <div class="relative h-32 shrink-0 bg-gradient-to-br from-[#156F8C] to-[#2AA7A1] overflow-hidden">
                        <template x-if="selected.property_photo">
                            <img :src="selected.property_photo" alt="" class="w-full h-full object-cover">
                        </template>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0F172A]/70 via-[#0F172A]/10 to-transparent"></div>
                        <button @click="modalOpen = false" class="absolute top-3 right-3 w-8 h-8 rounded-lg bg-white/15 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/25 transition-colors">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="absolute bottom-0 left-0 right-0 px-5 pb-3">
                            <p class="text-[15px] font-bold text-white truncate" x-text="selected.property_title"></p>
                            <p class="text-[12px] text-white/85 truncate" x-text="selected.unit_label"></p>
                        </div>
                    </div>

                    <div class="overflow-y-auto scrollbar-thin-light px-5 py-5 space-y-5">

                        {{-- Status progress visualization --}}
                        <template x-if="!selected.is_terminal">
                            <div>
                                <div class="flex items-center justify-between mb-2.5">
                                    <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wider">Reservation journey</p>
                                    <span class="text-[11px] font-bold text-[#156F8C]" x-text="selected.rental_status"></span>
                                </div>
                                <div class="flex items-center">
                                    <template x-for="(step, i) in journey" :key="i">
                                        <div class="flex items-center flex-1 last:flex-none">
                                            <div class="flex flex-col items-center gap-1.5 shrink-0">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors duration-300"
                                                    :class="i < selected.step_index ? 'bg-[#2AA7A1] text-white' :
                                                            i === selected.step_index ? 'bg-[#156F8C] text-white ring-4 ring-[#156F8C]/15' :
                                                            'bg-[#F1F5F9] text-[#94A3B8]'">
                                                    <template x-if="i < selected.step_index">
                                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="i >= selected.step_index"><span x-text="i + 1"></span></template>
                                                </div>
                                                <span class="text-[9px] font-semibold text-center leading-tight w-14"
                                                    :class="i <= selected.step_index ? 'text-[#1F2937]' : 'text-[#94A3B8]'"
                                                    x-text="step"></span>
                                            </div>
                                            <div class="flex-1 h-[2px] mx-0.5 -mt-4 rounded-full transition-colors duration-300"
                                                x-show="i < journey.length - 1"
                                                :class="i < selected.step_index ? 'bg-[#2AA7A1]' : 'bg-[#F1F5F9]'"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="selected.is_terminal">
                            <div class="flex items-center gap-3 rounded-xl px-4 py-3"
                                :class="selected.rental_status === 'Rejected' ? 'bg-[#EF4444]/[0.07]' : 'bg-[#64748B]/10'">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    :class="selected.rental_status === 'Rejected' ? 'stroke-[#DC2626]' : 'stroke-[#64748B]'">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <p class="text-[13px] font-bold" :class="selected.rental_status === 'Rejected' ? 'text-[#DC2626]' : 'text-[#64748B]'" x-text="'This reservation was ' + selected.rental_status.toLowerCase()"></p>
                            </div>
                        </template>

                        {{-- Landlord --}}
                        <div class="flex items-center gap-3 rounded-xl ring-1 ring-[#E2E8F0] px-4 py-3">
                            <div class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center shrink-0 text-[#156F8C] font-bold text-[13px]" x-text="selected.landlord_name.charAt(0)"></div>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[#1F2937] truncate" x-text="selected.landlord_name"></p>
                                <p class="text-[12px] text-[#64748B]" x-text="selected.landlord_contact"></p>
                            </div>
                        </div>

                        {{-- Info grid --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-[#F7FCFC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Move-in</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#1F2937]" x-text="selected.reservation_date || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#F7FCFC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Duration</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#1F2937]" x-text="selected.duration_of_stay || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#F7FCFC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Occupants</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#1F2937]" x-text="selected.occupants_count || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#F7FCFC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#64748B] uppercase tracking-wide">Status</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#1F2937]" x-text="selected.rental_status"></p>
                            </div>
                        </div>

                        <template x-if="selected.remarks">
                            <div class="pt-4 border-t border-[#E2E8F0]">
                                <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-1.5">Your remarks</p>
                                <p class="text-[13px] text-[#1F2937] leading-relaxed" x-text="selected.remarks"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endsection
