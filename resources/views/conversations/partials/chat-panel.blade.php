@php
    $reservation = $conversation->activeReservation;
    $rentalStatus = $reservation?->rental_status;
    $isLandlord = auth()->id() === $conversation->landlord_id;
    $isTenant = auth()->id() === $conversation->tenant_id;

    $stages = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied'];
    $stageLabels = ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Occupied'];
    $currentStageIndex = $rentalStatus ? array_search($rentalStatus, $stages) : false;
    $isTerminal = in_array($rentalStatus, ['Cancelled', 'Rejected']);
@endphp

<div class="flex flex-col h-full" id="chat-panel-root" data-conversation-id="{{ $conversation->conversation_id }}" x-data="{ detailsOpen: false }">

    {{-- Chat header --}}
    <div class="px-5 py-3.5 border-b border-[#E2E8F0] flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-full bg-[#1F2937] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-[13px] font-bold text-[#1F2937] truncate">
                        {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                    </h2>
                    @if($rentalStatus === 'Inquiry')
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]">New Inquiry</span>
                    @endif
                </div>
                <p class="text-[11px] text-[#64748B] truncate">
                    @if($isLandlord)
                        {{ $otherParty->email }}{{ $otherParty->contact_number ? ' · ' . $otherParty->contact_number : '' }}
                    @else
                        {{ $conversation->property->title }}
                        @if($conversation->unit)
                            &middot; {{ $conversation->unit->unit_label }}
                        @endif
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            @if($rentalStatus && !$isTerminal)
                <button type="button" @click="detailsOpen = !detailsOpen"
                    class="bg-[#EEF8F8] rounded-lg px-2.5 py-1 flex items-center gap-1.5 hover:brightness-95 transition cursor-pointer">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1]"></div>
                    <span class="text-[10px] font-bold text-[#1F2937] tracking-wide">{{ $stageLabels[$currentStageIndex] ?? $rentalStatus }}</span>
                    <svg class="w-3 h-3 text-[#64748B] transition-transform" :class="detailsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            @elseif($isTerminal)
                <button type="button" @click="detailsOpen = !detailsOpen"
                    class="bg-[#E2E8F0] rounded-lg px-2.5 py-1 flex items-center gap-1.5 hover:brightness-95 transition cursor-pointer">
                    <span class="text-[10px] font-bold text-[#EF4444] tracking-wide">{{ $rentalStatus }}</span>
                    <svg class="w-3 h-3 text-[#64748B] transition-transform" :class="detailsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            @elseif(!$reservation)
                {{-- No reservation yet — show info toggle for property card --}}
                <button type="button" @click="detailsOpen = !detailsOpen"
                    class="w-8 h-8 rounded-lg border border-[#E2E8F0] flex items-center justify-center text-[#64748B] hover:text-[#1F2937] hover:bg-[#E2E8F0] transition"
                    title="Conversation details">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </button>
            @endif

            @if($isLandlord && !$conversation->isResolved())
                <button type="button" onclick="resolveConversation({{ $conversation->conversation_id }})"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-[#1F2937] bg-[#EEF8F8] border border-[#2AA7A1]/20 rounded-lg hover:brightness-95 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Mark as Resolved
                </button>
            @elseif($conversation->isResolved())
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-[#1F2937] bg-[#EEF8F8] rounded-lg">
                    Resolved
                </span>
            @endif
        </div>
    </div>

    {{-- Expandable details panel --}}
    <div x-show="detailsOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 max-h-0"
        x-transition:enter-end="opacity-100 translate-y-0 max-h-[500px]"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 max-h-[500px]"
        x-transition:leave-end="opacity-0 -translate-y-2 max-h-0"
        class="border-b border-[#E2E8F0] bg-[#E2E8F0]/20 overflow-hidden flex-shrink-0">

        <div class="px-4 sm:px-5 py-4 flex flex-col sm:flex-row gap-5">

            {{-- Left: Property card + inquiry dates --}}
            <div class="flex-1 min-w-0">
                {{-- Property card --}}
                <a href="{{ route('properties.show', $conversation->property) }}" target="_blank"
                    class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-[#E2E8F0] hover:bg-[#E2E8F0]/50 transition">
                    @if($conversation->property->media->count() > 0)
                        <img src="{{ $conversation->property->media->first()->media_url }}" alt="" class="w-14 h-10 rounded-lg object-cover flex-shrink-0">
                    @else
                        <div class="w-14 h-10 rounded-lg bg-[#E2E8F0] flex items-center justify-center flex-shrink-0">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6"/></svg>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[12px] font-bold text-[#1F2937] truncate">{{ $conversation->property->title }}</p>
                        <p class="text-[11px] text-[#64748B] mt-0.5">
                            @if($conversation->unit)
                                {{ $conversation->unit->unit_label }} &middot; &#8369;{{ number_format($conversation->unit?->rental_fee ?? $conversation->property->rental_fee) }}/mo
                            @else
                                &#8369;{{ number_format($conversation->property->rental_fee) }}/mo
                            @endif
                        </p>
                    </div>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2" class="flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>

                {{-- Inquiry dates --}}
                @if($reservation && ($reservation->target_move_in_date || $reservation->target_move_out_date))
                    <div class="flex items-center gap-4 mt-3 px-1">
                        @if($reservation->target_move_in_date)
                            <div class="flex items-center gap-1.5 text-[11px] text-[#64748B]">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Move in: <span class="text-[#1F2937] font-medium">{{ \Carbon\Carbon::parse($reservation->target_move_in_date)->format('M j, Y') }}</span></span>
                            </div>
                        @endif
                        @if($reservation->target_move_out_date)
                            <div class="flex items-center gap-1.5 text-[11px] text-[#64748B]">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                <span>Move out: <span class="text-[#1F2937] font-medium">{{ \Carbon\Carbon::parse($reservation->target_move_out_date)->format('M j, Y') }}</span></span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Actions --}}
                @if($reservation && !$isTerminal)
                    <div class="mt-3" x-data="{ showReject: false, showCancel: false, showTc: false }">
                        @if($isLandlord)
                            @if($rentalStatus === 'Inquiry')
                                <div class="flex items-center gap-2">
                                    <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST" class="flex-1">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="w-full bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                            Accept & negotiate
                                        </button>
                                    </form>
                                    <button type="button" @click="showReject = !showReject"
                                        class="px-4 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 transition">
                                        Reject
                                    </button>
                                </div>
                                <div x-show="showReject" x-cloak class="mt-2">
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST" class="flex gap-2">
                                        @csrf @method('PATCH')
                                        <input name="rejection_reason" placeholder="Reason (optional)" aria-label="Rejection reason (optional)"
                                            class="flex-1 text-[12px] border border-[#E2E8F0] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none">
                                        <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg transition">Confirm</button>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Under Negotiation')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="showTc = !showTc" class="flex-1 bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        Send agreement
                                    </button>
                                    <button type="button" @click="showReject = !showReject"
                                        class="px-4 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 transition">
                                        Reject
                                    </button>
                                </div>
                                <div x-show="showTc" x-transition x-cloak class="mt-2">
                                    <form action="{{ route('landlord.reservations.advanceAgreement', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <div class="p-3 bg-[#EEF8F8] rounded-xl border border-[#2AA7A1]/20">
                                            <label class="flex items-start gap-2.5 cursor-pointer group mb-3">
                                                <input type="checkbox" name="accept_tc" required
                                                    class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                                                <span class="text-[11px] text-[#1F2937] leading-relaxed">
                                                    I agree that the tenant's payment will be held by AbangananHub until the tenant confirms move-in. Funds will be released only after tenant verification.
                                                </span>
                                            </label>
                                            <button type="submit"
                                                class="w-full bg-[#2AA7A1] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-lg transition">
                                                Confirm &amp; send agreement
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div x-show="showReject" x-cloak class="mt-2">
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST" class="flex gap-2">
                                        @csrf @method('PATCH')
                                        <input name="rejection_reason" placeholder="Reason (optional)" aria-label="Rejection reason (optional)"
                                            class="flex-1 text-[12px] border border-[#E2E8F0] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none">
                                        <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg transition">Confirm</button>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Pending Rental Agreement')
                                <p class="text-[12px] text-[#64748B] font-medium text-center bg-[#E2E8F0] rounded-xl py-2">Waiting for tenant to sign</p>

                            @elseif($rentalStatus === 'Rental Agreement Signed')
                                <p class="text-[12px] text-[#64748B] font-medium text-center bg-[#E2E8F0] rounded-xl py-2">Waiting for payment</p>

                            @elseif($rentalStatus === 'Occupied')
                                <p class="text-[12px] text-[#1F2937] font-medium text-center bg-[#EEF8F8] rounded-xl py-2">Tenant is occupying this unit</p>
                            @endif
                        @endif

                        @if($isTenant)
                           @if($rentalStatus === 'Inquiry')
                                <div class="flex items-center gap-2">
                                    <p class="flex-1 text-[12px] text-[#64748B] font-medium text-center bg-[#E2E8F0] rounded-xl py-2">Waiting for landlord to respond</p>
                                    <button type="button" @click="showCancel = !showCancel"
                                        class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 transition">
                                        Cancel
                                    </button>
                                </div>
                                <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                                    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg transition">Confirm cancellation</button>
                                    </form>
                                </div>
                            @elseif($rentalStatus === 'Under Negotiation')
                                <div class="flex items-center gap-2">
                                    <p class="flex-1 text-[12px] text-[#64748B] font-medium bg-[#E2E8F0] rounded-xl py-2 text-center">Discuss terms with the landlord</p>
                                    <button type="button" @click="showCancel = !showCancel"
                                        class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 transition">
                                        Cancel
                                    </button>
                                </div>
                                <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                                    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg transition">Confirm cancellation</button>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Pending Rental Agreement')
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('agreements.show', $reservation) }}"
                                        class="flex-1 bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        View & sign agreement
                                    </a>
                                    <button type="button" @click="showCancel = !showCancel"
                                        class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 transition">
                                        Cancel
                                    </button>
                                </div>
                                <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                                    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg transition">Confirm cancellation</button>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Rental Agreement Signed')
                                <a href="{{ route('agreements.show', $reservation) }}"
                                    class="w-full bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                                    Proceed to payment
                                </a>

                            @elseif($rentalStatus === 'Occupied')
                                <p class="text-[12px] text-[#1F2937] font-medium text-center bg-[#EEF8F8] rounded-xl py-2">You are occupying this unit</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            {{-- Right: Rental progress stepper --}}
            @if($reservation)
                <div class="w-full sm:w-[160px] flex-shrink-0">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-2.5">Rental progress</p>

                    @if($isTerminal)
                        <div class="bg-[#E2E8F0] rounded-xl p-3 text-center">
                            <span class="text-[12px] font-bold text-[#EF4444]">{{ $rentalStatus }}</span>
                            @if($rentalStatus === 'Rejected' && $reservation->rejection_reason)
                                <p class="text-[11px] text-[#64748B] mt-1">{{ $reservation->rejection_reason }}</p>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-col ml-0.5">
                            @foreach($stages as $i => $stage)
                                @php
                                    $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
                                    $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
                                    $isLast = $i === count($stages) - 1;
                                @endphp
                                <div class="relative flex gap-2.5 {{ !$isLast ? 'pb-4' : '' }}">
                                    @if(!$isLast)
                                        <div class="absolute left-[7px] top-[18px] bottom-0 w-0.5 rounded-full {{ $isDone ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]' }}"></div>
                                    @endif
                                    <div class="relative z-10 flex-shrink-0">
                                        @if($isDone)
                                            <div class="w-4 h-4 rounded-full bg-[#2AA7A1] flex items-center justify-center">
                                                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                        @elseif($isCurrent)
                                            <div class="w-4 h-4 rounded-full bg-[#2AA7A1] flex items-center justify-center ring-3 ring-[#EEF8F8]">
                                                <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                            </div>
                                        @else
                                            <div class="w-4 h-4 rounded-full border-2 border-[#E2E8F0] bg-white"></div>
                                        @endif
                                    </div>
                                    <div class="pt-px">
                                        <p class="text-[11px] {{ $isCurrent ? 'font-bold text-[#1F2937]' : ($isDone ? 'font-medium text-[#1F2937]' : 'text-[#64748B]') }}">
                                            {{ $stageLabels[$i] }}
                                        </p>
                                        @if($isCurrent)
                                            <p class="text-[10px] text-[#64748B]">In progress</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    {{-- Messages --}}
    <div id="message-list" class="flex-1 px-5 py-4 overflow-y-auto flex flex-col gap-2.5 scroll-smooth" style="background: #F7FCFC;">

        {{-- Pinned inquiry summary --}}
        @if($reservation)
            @php
                $inqThumb = $conversation->unit?->media?->firstWhere('media_type', 'Image')
                    ?? $conversation->property->media->firstWhere('media_type', 'Image');
                $inqFee = $conversation->unit?->rental_fee ?? $conversation->property->rental_fee;
            @endphp
            <div class="self-stretch bg-white border border-[#E2E8F0] rounded-2xl p-4 mb-1">
                <div class="flex items-center gap-3.5 mb-3">
                    @if($inqThumb)
                        <img src="{{ $inqThumb->media_url }}" alt="" class="w-16 h-14 rounded-xl object-cover shrink-0">
                    @else
                        <div class="w-16 h-14 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[14px] font-bold text-[#1F2937] truncate">
                            {{ $conversation->unit->unit_label ?? $conversation->property->title }}
                        </p>
                        @if($conversation->unit)
                            <p class="text-[12px] text-[#64748B] truncate">{{ $conversation->property->title }}</p>
                        @endif
                        <p class="text-[13px] font-bold text-[#156F8C]">₱{{ number_format($inqFee) }}
                            <span class="text-[11px] font-semibold text-[#64748B]">/ month</span>
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-[#E2E8F0] p-3.5">
                    <p class="text-[12px] font-bold text-[#1F2937] mb-2.5">Inquiry Details</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mb-2.5">
                        <div class="flex items-start gap-2">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2" class="shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <p class="text-[11px] text-[#64748B]">Target Move In</p>
                                <p class="text-[12.5px] font-semibold text-[#1F2937]">
                                    {{ $reservation->target_move_in_date?->format('M d, Y') ?? '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2" class="shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <p class="text-[11px] text-[#64748B]">Target Move Out</p>
                                <p class="text-[12.5px] font-semibold text-[#1F2937]">
                                    {{ $reservation->target_move_out_date?->format('M d, Y') ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @if($reservation->remarks)
                        <p class="text-[11px] text-[#64748B]">Message</p>
                        <p class="text-[12.5px] text-[#1F2937] leading-relaxed whitespace-pre-line">{{ $reservation->remarks }}</p>
                    @endif
                    <p class="text-[10.5px] text-[#64748B] mt-2">{{ $reservation->created_at->format('h:i A') }}</p>
                </div>
            </div>
        @endif

        @foreach ($conversation->messages as $message)
            @if($message->is_system)
                <div class="self-stretch flex items-center gap-3 my-1 px-2">
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                    <p class="text-xs text-[#64748B] text-center max-w-[70%] leading-relaxed">{{ $message->message }}</p>
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                </div>
            @else
                @php $isSelf = $message->sender_id === auth()->id(); @endphp
                <div class="max-w-[75%] {{ $isSelf ? 'self-end bg-[#1F2937] text-white rounded-2xl rounded-tr-sm' : 'self-start bg-white text-[#1F2937] border border-[#E2E8F0] rounded-2xl rounded-tl-sm' }} px-4 py-2.5 shadow-sm">
                    @if(!$isSelf)
                        <p class="text-[10px] font-bold text-[#64748B] mb-1 tracking-wide uppercase">
                            {{ $message->sender->first_name }}
                        </p>
                    @endif
                    <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                    <div class="flex items-center justify-end mt-1">
                        <p class="text-[10px] tracking-wide {{ $isSelf ? 'text-white/40' : 'text-[#64748B]' }} message-time"
                            data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Nudge banner for landlord at Inquiry stage --}}
        @if($isLandlord && $rentalStatus === 'Inquiry')
            <div class="px-5 py-2.5 bg-[#EEF8F8]/40 border-t border-[#2AA7A1]/15 flex items-center gap-2.5 flex-shrink-0">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z"/>
                </svg>
                <p class="text-[12px] text-[#1F2937]">
                    New inquiry — <button type="button" @click="detailsOpen = true" class="font-bold text-[#156F8C] hover:underline">review the details above</button> and accept or reject to proceed.
                </p>
            </div>
        @endif

        {{-- Nudge banner for tenant at Inquiry stage --}}
        @if($isTenant && $rentalStatus === 'Inquiry')
            <div class="px-5 py-2.5 bg-[#E2E8F0]/50 border-t border-[#E2E8F0] flex items-center gap-2.5 flex-shrink-0">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2" class="shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-[12px] text-[#64748B]">Your inquiry has been sent. The landlord will review and respond.</p>
            </div>
        @endif
        {{-- Banner for cancelled conversations --}}
        @if($conversation->isCancelled())
            <div class="px-5 py-2.5 bg-[#E2E8F0]/50 border-t border-[#E2E8F0] flex items-center gap-2.5 flex-shrink-0">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#EF4444" stroke-width="2" class="shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <p class="text-[12px] text-[#64748B]">This conversation has been cancelled. You can no longer send messages here.</p>
            </div>
        @endif
    {{-- Message input --}}
        @if($conversation->isCancelled())
            <div class="px-5 py-3 bg-[#E2E8F0]/40 border-t border-[#E2E8F0] flex-shrink-0">
                <p class="text-center text-[12px] text-[#64748B] font-medium">Messaging is disabled for cancelled conversations.</p>
            </div>
        @else
            <div class="px-5 py-3 bg-white border-t border-[#E2E8F0] flex-shrink-0">        <form id="message-form" class="flex items-center gap-2.5">
                <input type="text" id="message-input" name="message" required maxlength="2000" autocomplete="off"
                    class="flex-1 bg-[#E2E8F0] border border-transparent focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/10 rounded-xl px-4 py-2.5 text-[13px] text-[#1F2937] transition outline-none placeholder-[#64748B]"
                    placeholder="Message {{ $otherParty->first_name }}..." aria-label="Message {{ $otherParty->first_name }}">
                <button type="submit"
                    class="bg-[#1F2937] hover:brightness-95 active:scale-[0.98] text-white font-bold text-[13px] px-4 py-2.5 rounded-xl shadow-sm transition inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    Send
                </button>
            </form>
            </div>
        @endif
    </div>
</div>