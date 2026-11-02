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
        <div class="stat-chips grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <x-stat-card label="Total" :value="$counts['all']">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="In progress"
                :value="$counts['Inquiry'] + $counts['Under Negotiation'] + $counts['Pending Rental Agreement'] + $counts['Rental Agreement Signed']"
                sub="Awaiting action">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Occupied" :value="$counts['Occupied']" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#16A34A" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M15.75 21H8.25m6.386-8.818a3.375 3.375 0 11-6.747-.248l-.006.248a3.375 3.375 0 116.747.248z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Cancelled/Rejected" :value="$counts['Cancelled'] + $counts['Rejected']" icon-bg="rgba(239,68,68,0.07)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        {{-- Status tabs --}}
        <div class="flex items-center gap-1 border-b border-[#5B6A8E]/15 mb-5 overflow-x-auto">
            @foreach([
                'all' => 'All',
                'Inquiry' => 'Inquiry',
                'Under Negotiation' => 'Negotiation',
                'Pending Rental Agreement' => 'Pending Agreement',
                'Rental Agreement Signed' => 'Signed',
                'Reserved' => 'Reserved',
                'Occupied' => 'Occupied',
                'Completed' => 'Completed',
                'Rejected' => 'Rejected',
                'Cancelled' => 'Cancelled',
            ] as $key => $label)
                {{-- Empty tabs are noise: keep All, the active tab, and any tab with results. --}}
                @continue($key !== 'all' && $status !== $key && ($counts[$key] ?? 0) === 0)
                <a href="{{ route('reservations.index', $key === 'all' ? [] : ['status' => $key]) }}"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors duration-150 whitespace-nowrap
                              {{ $status === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">
                    {{ $label }}
                    <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#060D26]' : 'text-[#5B6A8E]' }}">
                        {{ $key === 'all' ? $counts['all'] : $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Table --}}
        <x-card flush class="flex-1 flex flex-col">
            @if($reservations->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <p class="text-[15px] font-bold text-[#060D26] mb-1">No reservations
                        {{ $status !== 'all' ? 'with this status' : 'yet' }}</p>
                    <p class="text-[13px] text-[#5B6A8E] mb-5 max-w-xs">Your rental inquiries and reservations will show up here once you contact a landlord.</p>
                    @if($status === 'all')
                        <a href="{{ route('properties.index') }}"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-all shadow-sm">
                            Browse properties
                        </a>
                    @else
                        <a href="{{ route('reservations.index') }}"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-[#060D26] bg-white ring-1 ring-[#5B6A8E]/15 hover:bg-[#ECEEF6] transition-all">
                            View all reservations
                        </a>
                    @endif
                </div>
            @else
                @php
                    // One row model for both layouts, so the phone cards and the desktop table can't drift apart.
                    $statusPill = [
                        'Inquiry' => ['Inquiry sent', 'bg-[#FBBF24]/[0.12] text-[#92400E]'],
                        'Under Negotiation' => ['Negotiating', 'bg-[#FBBF24]/[0.12] text-[#92400E]'],
                        'Pending Rental Agreement' => ['Agreement to sign', 'bg-[#FF8A66]/[0.14] text-[#9A3412]'],
                        'Rental Agreement Signed' => ['Signed', 'bg-[#ECEEF6] text-[#060D26]'],
                        'Reserved' => ['Reserved', 'bg-[#ECEEF6] text-[#060D26]'],
                        'Occupied' => ['Living here', 'bg-[#22C55E]/[0.10] text-[#15803D]'],
                        'Completed' => ['Ended', 'bg-[#5B6A8E]/10 text-[#334155]'],
                        'Rejected' => ['Declined', 'bg-[#EF4444]/[0.08] text-[#B91C1C]'],
                        'Cancelled' => ['Cancelled', 'bg-[#5B6A8E]/10 text-[#5B6A8E]'],
                    ];
                    $journeySteps = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied'];
                    $rows = $reservations->filter(fn ($r) => $r->property)->map(function ($r) use ($statusPill, $journeySteps) {
                        $landlord = $r->property->landlord;
                        $landlordName = trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? ''));
                        $closed = in_array($r->rental_status, ['Rejected', 'Cancelled'], true);
                        $moveIn = $r->target_move_in_date?->format('M j, Y') ?? 'Flexible';

                        return [
                            'r' => $r,
                            'photo' => $r->property->media->first()?->media_url,
                            'unit' => $r->unit->unit_label ?? 'No unit',
                            'landlord' => $landlordName !== '' ? $landlordName : 'Unknown',
                            'moveIn' => $moveIn,
                            'duration' => $r->duration_of_stay ?: 'Open-ended',
                            // A signed lease says where the money stands (the same answer as the primary button), not just "Signed".
                            'pill' => match ($r->paymentState()) {
                                'due' => ['Payment due', 'bg-[#FF8A66]/[0.14] text-[#9A3412]'],
                                'reported' => ['Payment sent', 'bg-[#FBBF24]/[0.12] text-[#92400E]'],
                                'paid' => ['Paid · move in', 'bg-[#22C55E]/[0.10] text-[#15803D]'],
                                default => $statusPill[$r->rental_status] ?? [$r->rental_status, 'bg-[#ECEEF6] text-[#060D26]'],
                            },
                            'closed' => $closed,
                            'canReview' => in_array($r->rental_status, ['Occupied', 'Completed'], true)
                                && \App\Models\Review::canReview(auth()->id(), $r->property_id),
                            'details' => [
                                'reservation_id' => $r->reservation_id,
                                'reservation_date' => $r->reservation_date?->format('M d, Y'),
                                'move_in_date' => $r->target_move_in_date?->format('M d, Y') ?? 'Flexible',
                                'duration_of_stay' => $r->duration_of_stay,
                                'occupants_count' => $r->occupants_count,
                                'remarks' => $r->remarks,
                                'rejection_reason' => $r->rental_status === 'Rejected' ? $r->rejection_reason : null,
                                'rental_status' => $r->rental_status,
                                'landlord_name' => $landlordName !== '' ? $landlordName : 'Unknown',
                                'landlord_contact' => $landlord->contact_number ?? '—',
                                'property_title' => $r->property->title,
                                'unit_label' => $r->unit->unit_label ?? 'No unit',
                                'property_photo' => $r->property->media->first()?->media_url,
                                'is_terminal' => $closed,
                                'step_index' => $closed ? -1 : array_search($r->rental_status, $journeySteps),
                            ],
                        ];
                    });
                @endphp

                {{-- Phones and tablets: one card per reservation --}}
                <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach($rows as $row)
                        <li class="p-4 {{ $row['closed'] ? 'bg-[#F7F8FC]/60' : '' }}">
                            <div class="flex items-start gap-3">
                                <div class="w-14 h-14 rounded-xl bg-[#ECEEF6] overflow-hidden shrink-0 {{ $row['closed'] ? 'opacity-60 grayscale' : '' }}">
                                    @if($row['photo'])
                                        <img loading="lazy" decoding="async" src="{{ $row['photo'] }}" alt="" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 text-[14px] font-semibold leading-snug {{ $row['closed'] ? 'text-[#5B6A8E]' : 'text-[#060D26]' }}">{{ $row['r']->property->title }}</p>
                                        <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $row['pill'][1] }}">{{ $row['pill'][0] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] truncate">{{ $row['unit'] }} · {{ $row['landlord'] }}</p>
                                    <p class="mt-1 text-[12.5px] text-[#060D26] tabular-nums">
                                        Move-in: {{ $row['moveIn'] }} <span class="text-[#5B6A8E]">· {{ $row['duration'] }}</span>
                                    </p>
                                    @if($row['details']['rejection_reason'])
                                        <p class="mt-1 text-[12.5px] text-[#B91C1C]">Reason: {{ $row['details']['rejection_reason'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                @include('tenant.reservations._actions', ['reservation' => $row['r'], 'canReview' => $row['canReview'], 'details' => $row['details'], 'mobile' => true])
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop: four columns, every cell on one line, one coloured button per row --}}
                <div class="hidden lg:block">
                {{-- Rental is capped at 40% and Move-in / Status shrink to their content, so the spare width goes to
                     Actions and the buttons stay on one line instead of the title column sitting half empty. --}}
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[#F7F8FC] text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wider">
                            <th class="w-[40%] px-5 py-3">Rental</th>
                            <th class="w-px whitespace-nowrap px-4 py-3">Move-in</th>
                            <th class="w-px whitespace-nowrap px-4 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach($rows as $row)
                            <tr class="{{ $row['closed'] ? 'bg-[#F7F8FC]/60' : 'hover:bg-[#F7F8FC]' }} transition-colors duration-150">
                                <td class="max-w-0 px-5 py-3.5">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-11 h-11 rounded-lg bg-[#ECEEF6] overflow-hidden shrink-0 {{ $row['closed'] ? 'opacity-60 grayscale' : '' }}">
                                            @if($row['photo'])
                                                <img loading="lazy" decoding="async" src="{{ $row['photo'] }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[14px] font-semibold truncate {{ $row['closed'] ? 'text-[#5B6A8E]' : 'text-[#060D26]' }}" title="{{ $row['r']->property->title }}">{{ $row['r']->property->title }}</p>
                                            <p class="text-[12.5px] text-[#5B6A8E] truncate">{{ $row['unit'] }} · {{ $row['landlord'] }}</p>
                                            @if($row['details']['rejection_reason'])
                                                <p class="text-[12.5px] text-[#B91C1C] truncate" title="{{ $row['details']['rejection_reason'] }}">Reason: {{ $row['details']['rejection_reason'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <p class="text-[13.5px] text-[#060D26] tabular-nums">{{ $row['moveIn'] }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $row['duration'] }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5">
                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $row['pill'][1] }}">{{ $row['pill'][0] }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        @include('tenant.reservations._actions', ['reservation' => $row['r'], 'canReview' => $row['canReview'], 'details' => $row['details']])
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
            <div @click="modalOpen = false" class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            <template x-if="selected">
                <div class="relative bg-white rounded-2xl ring-1 ring-[#5B6A8E]/10 shadow-2xl w-full max-w-md overflow-hidden max-h-[90vh] flex flex-col"
                    x-show="modalOpen" x-transition
                    @keydown.escape.window="modalOpen = false">

                    {{-- Photo banner --}}
                    <div class="relative h-32 shrink-0 bg-gradient-to-br from-[#060D26] to-[#FF8A66] overflow-hidden">
                        <template x-if="selected.property_photo">
                            <img :src="selected.property_photo" alt="" class="w-full h-full object-cover">
                        </template>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/70 via-[#060D26]/10 to-transparent"></div>
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
                                    <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wider">Reservation journey</p>
                                    <span class="text-[11px] font-bold text-[#060D26]" x-text="selected.rental_status"></span>
                                </div>
                                <div class="flex items-center">
                                    <template x-for="(step, i) in journey" :key="i">
                                        <div class="flex items-center flex-1 last:flex-none">
                                            <div class="flex flex-col items-center gap-1.5 shrink-0">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors duration-300"
                                                    :class="i < selected.step_index ? 'bg-[#FF8A66] text-[#060D26]' :
                                                            i === selected.step_index ? 'bg-[#FF8A66] text-[#060D26] ring-4 ring-[#060D26]/15' :
                                                            'bg-[#F1F5F9] text-[#94A3B8]'">
                                                    <template x-if="i < selected.step_index">
                                                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="i >= selected.step_index"><span x-text="i + 1"></span></template>
                                                </div>
                                                <span class="text-[9px] font-semibold text-center leading-tight w-14"
                                                    :class="i <= selected.step_index ? 'text-[#060D26]' : 'text-[#94A3B8]'"
                                                    x-text="step"></span>
                                            </div>
                                            <div class="flex-1 h-[2px] mx-0.5 -mt-4 rounded-full transition-colors duration-300"
                                                x-show="i < journey.length - 1"
                                                :class="i < selected.step_index ? 'bg-[#060D26]' : 'bg-[#F1F5F9]'"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="selected.is_terminal">
                            <div class="rounded-xl px-4 py-3"
                                :class="selected.rental_status === 'Rejected' ? 'bg-[#EF4444]/[0.07]' : 'bg-[#5B6A8E]/10'">
                                <div class="flex items-center gap-3">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="2" class="shrink-0"
                                        :class="selected.rental_status === 'Rejected' ? 'stroke-[#DC2626]' : 'stroke-[#5B6A8E]'">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <p class="text-[13px] font-bold" :class="selected.rental_status === 'Rejected' ? 'text-[#DC2626]' : 'text-[#5B6A8E]'" x-text="'This reservation was ' + selected.rental_status.toLowerCase()"></p>
                                </div>
                                <p x-show="selected.rejection_reason" class="mt-2 pl-[30px] text-[13px] text-[#060D26]">
                                    <span class="font-semibold">Reason:</span> <span x-text="selected.rejection_reason"></span>
                                </p>
                            </div>
                        </template>

                        {{-- Landlord --}}
                        <div class="flex items-center gap-3 rounded-xl ring-1 ring-[#E2E4EC] px-4 py-3">
                            <div class="w-9 h-9 rounded-full bg-[#ECEEF6] flex items-center justify-center shrink-0 text-[#060D26] font-bold text-[13px]" x-text="selected.landlord_name.charAt(0)"></div>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[#060D26] truncate" x-text="selected.landlord_name"></p>
                                <p class="text-[12px] text-[#5B6A8E]" x-text="selected.landlord_contact"></p>
                            </div>
                        </div>

                        {{-- Info grid --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Move-in</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#060D26]" x-text="selected.move_in_date || 'Flexible'"></p>
                                <p class="mt-0.5 text-[11px] text-[#5B6A8E]">Inquired <span x-text="selected.reservation_date || '—'"></span></p>
                            </div>
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Duration</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#060D26]" x-text="selected.duration_of_stay || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Occupants</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#060D26]" x-text="selected.occupants_count || '—'"></p>
                            </div>
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</span>
                                </div>
                                <p class="text-[13px] font-semibold text-[#060D26]" x-text="selected.rental_status"></p>
                            </div>
                        </div>

                        <template x-if="selected.remarks">
                            <div class="pt-4 border-t border-[#E2E4EC]">
                                <p class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide mb-1.5">Your remarks</p>
                                <p class="text-[13px] text-[#060D26] leading-relaxed" x-text="selected.remarks"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endsection
