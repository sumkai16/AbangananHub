@php
    $reservation = $conversation->activeReservation;
    $rentalStatus = $reservation?->rental_status;
    $isLandlord = auth()->id() === $conversation->landlord_id;
    $isTenant = auth()->id() === $conversation->tenant_id;

    $isTerminal = in_array($rentalStatus, ['Cancelled', 'Rejected']);

    // Payment state, read from the payments table rather than mirrored into
    // rental_status — one fact, one home. "Paid" is a *derived* stage: the
    // reservation sits on 'Rental Agreement Signed' the whole time money is
    // held, and only moves to 'Occupied' when the tenant confirms move-in
    // (which is also what releases the escrow).
    $heldPayment = $reservation?->payments->firstWhere('status', 'Held');
    $releasedPayment = $reservation?->payments->firstWhere('status', 'Released');
    $pendingPayment = $reservation?->payments->firstWhere('status', 'Pending');
    $hasSettledPayment = $heldPayment || $releasedPayment;

    $stageLabels = ['Inquiry', 'Negotiation', 'Agreement', 'Signed', 'Paid', 'Occupied'];
    $currentStageIndex = match ($rentalStatus) {
        'Inquiry' => 0,
        'Under Negotiation' => 1,
        'Pending Rental Agreement' => 2,
        'Rental Agreement Signed' => $hasSettledPayment ? 4 : 3,
        'Occupied' => 5,
        default => false,
    };

    // What this viewer is expected to do right now. Drives the action bar so
    // the role/status branching is stated once instead of being re-derived
    // in four places further down.
    $waitingOnMe = ($isLandlord && in_array($rentalStatus, ['Inquiry', 'Under Negotiation']))
        || ($isTenant && in_array($rentalStatus, ['Pending Rental Agreement', 'Rental Agreement Signed']));
@endphp

<div class="flex flex-col h-full" id="chat-panel-root" data-conversation-id="{{ $conversation->conversation_id }}" x-data="{ detailsOpen: false }">

    {{-- Chat header --}}
    <div class="px-5 py-3.5 border-b border-[#E2E8F0] flex items-center justify-between gap-3 flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-full bg-[#1F2937] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <h2 class="text-[13px] font-bold text-[#1F2937] truncate">
                    {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                </h2>
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
            {{-- Details toggle now carries only secondary context (property,
                 dates) — the stage and the actions moved out into the always
                 visible strip below, so nothing actionable hides in here. --}}
            <button type="button" @click="detailsOpen = !detailsOpen"
                :aria-expanded="detailsOpen ? 'true' : 'false'"
                class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-[#E2E8F0] text-[11px] font-bold text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC] cursor-pointer transition-all duration-200">
                Details
                <svg class="w-3 h-3 transition-transform duration-200" :class="detailsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            @if($isLandlord && !$conversation->isResolved())
                <button type="button" onclick="resolveConversation({{ $conversation->conversation_id }})"
                    class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-bold text-[#1F2937] bg-[#EEF8F8] border border-[#2AA7A1]/20 rounded-lg hover:brightness-95 cursor-pointer transition-all duration-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Mark as Resolved
                </button>
            @elseif($conversation->isResolved())
                <span class="inline-flex items-center h-8 px-3 text-[11px] font-bold text-[#15803D] bg-[#22C55E]/[0.07] border border-[#22C55E]/20 rounded-lg">
                    Resolved
                </span>
            @endif
        </div>
    </div>

    {{-- ===== Rental progress — always visible ===== --}}
    @if($reservation)
        @if($isTerminal)
            <div class="px-5 py-3 border-b border-[#E2E8F0] bg-[#EF4444]/[0.04] flex items-center gap-2.5 flex-shrink-0">
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-[#E2E8F0] text-[#EF4444]">{{ $rentalStatus }}</span>
                <p class="text-[11.5px] text-[#64748B] truncate">
                    @if($rentalStatus === 'Rejected' && $reservation->rejection_reason)
                        {{ $reservation->rejection_reason }}
                    @else
                        This rental process has ended.
                    @endif
                </p>
            </div>
        @else
            <div class="px-5 pt-3.5 pb-3 border-b border-[#E2E8F0] bg-[#F7FCFC] flex-shrink-0">
                <ol class="flex items-start" aria-label="Rental progress">
                    @foreach($stageLabels as $i => $stage)
                        @php
                            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
                            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
                            $isLast = $i === count($stageLabels) - 1;
                        @endphp
                        <li class="flex items-start {{ !$isLast ? 'flex-1' : '' }} min-w-0"
                            @if($isCurrent) aria-current="step" @endif>
                            <div class="flex flex-col items-center gap-1.5 {{ !$isLast ? 'w-[22px]' : '' }} flex-shrink-0">
                                @if($isDone)
                                    <div class="w-[18px] h-[18px] rounded-full bg-[#2AA7A1] flex items-center justify-center">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                @elseif($isCurrent)
                                    <div class="w-[18px] h-[18px] rounded-full bg-[#2AA7A1] flex items-center justify-center ring-4 ring-[#2AA7A1]/15">
                                        <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                    </div>
                                @else
                                    <div class="w-[18px] h-[18px] rounded-full border-2 border-[#E2E8F0] bg-white"></div>
                                @endif
                            </div>

                            @if(!$isLast)
                                <div class="flex-1 h-0.5 rounded-full mt-[8px] mx-1 {{ $isDone ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]' }}"></div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <div class="flex items-start mt-1.5">
                    @foreach($stageLabels as $i => $label)
                        @php
                            $isDone = $currentStageIndex !== false && $i < $currentStageIndex;
                            $isCurrent = $currentStageIndex !== false && $i === $currentStageIndex;
                            $isLast = $i === count($stageLabels) - 1;
                        @endphp
                        <p class="{{ !$isLast ? 'flex-1' : '' }} text-[9.5px] leading-tight tracking-wide {{ $isLast ? 'text-right' : '' }} {{ $isCurrent ? 'font-bold text-[#156F8C]' : ($isDone ? 'font-semibold text-[#1F2937]' : 'text-[#64748B]') }}">
                            {{ $label }}
                        </p>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ===== Action bar — always visible, never behind a toggle ===== --}}
    @if($reservation && !$isTerminal)
        <div class="px-5 py-3 border-b border-[#E2E8F0] flex-shrink-0 {{ $waitingOnMe ? 'bg-[#EEF8F8]/50' : 'bg-white' }}"
            x-data="{ showReject: false, showCancel: false, showTc: false }">

            {{-- LANDLORD --}}
            @if($isLandlord)
                @if($rentalStatus === 'Inquiry')
                    <div class="flex items-center gap-2">
                        <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST" class="flex-1">
                            @csrf @method('PATCH')
                            <button type="submit" class="w-full bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl cursor-pointer transition-all duration-200 flex items-center justify-center gap-1.5">
                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                Accept &amp; negotiate
                            </button>
                        </form>
                        <button type="button" @click="showReject = !showReject"
                            class="px-4 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
                            Reject
                        </button>
                    </div>
                    <div x-show="showReject" x-cloak class="mt-2">
                        <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST" class="flex gap-2">
                            @csrf @method('PATCH')
                            <label for="reject_reason_{{ $reservation->reservation_id }}" class="sr-only">Rejection reason (optional)</label>
                            <input id="reject_reason_{{ $reservation->reservation_id }}" name="rejection_reason" placeholder="Reason (optional)"
                                class="flex-1 text-[12px] border border-[#E2E8F0] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none">
                            <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Confirm</button>
                        </form>
                    </div>

                @elseif($rentalStatus === 'Under Negotiation')
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showTc = !showTc" class="flex-1 bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl cursor-pointer transition-all duration-200 flex items-center justify-center gap-1.5">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Send agreement
                        </button>
                        <button type="button" @click="showReject = !showReject"
                            class="px-4 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
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
                                    class="w-full bg-[#2AA7A1] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-lg cursor-pointer transition-all duration-200">
                                    Confirm &amp; send agreement
                                </button>
                            </div>
                        </form>
                    </div>
                    <div x-show="showReject" x-cloak class="mt-2">
                        <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST" class="flex gap-2">
                            @csrf @method('PATCH')
                            <label for="reject_reason_neg_{{ $reservation->reservation_id }}" class="sr-only">Rejection reason (optional)</label>
                            <input id="reject_reason_neg_{{ $reservation->reservation_id }}" name="rejection_reason" placeholder="Reason (optional)"
                                class="flex-1 text-[12px] border border-[#E2E8F0] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none">
                            <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Confirm</button>
                        </form>
                    </div>

                @elseif($rentalStatus === 'Pending Rental Agreement')
                    <p class="text-[12px] text-[#64748B] font-medium text-center py-1">Waiting for {{ $otherParty->first_name }} to sign the agreement</p>

                @elseif($rentalStatus === 'Rental Agreement Signed')
                    @if($heldPayment)
                        <p class="text-[12px] text-[#1F2937] font-medium text-center py-1">
                            Payment received and held — waiting for {{ $otherParty->first_name }} to confirm move-in
                        </p>
                    @elseif($pendingPayment)
                        <p class="text-[12px] text-[#64748B] font-medium text-center py-1">{{ $otherParty->first_name }}'s payment is processing</p>
                    @else
                        <p class="text-[12px] text-[#64748B] font-medium text-center py-1">Agreement signed — waiting for payment</p>
                    @endif

                @elseif($rentalStatus === 'Occupied')
                    {{-- Build the sentence in PHP. A Blade directive placed
                         immediately after a word character, with no space, is
                         not matched by Blade's `\B@` regex — it renders as
                         literal text and leaves the opening @if unclosed. --}}
                    @php
                        $occupiedNote = $releasedPayment
                            ? $otherParty->first_name . ' is occupying this unit — payment released to you'
                            : $otherParty->first_name . ' is occupying this unit';
                    @endphp
                    <p class="text-[12px] text-[#1F2937] font-medium text-center py-1">{{ $occupiedNote }}</p>
                @endif
            @endif

            {{-- TENANT --}}
            @if($isTenant)
                @if($rentalStatus === 'Inquiry')
                    <div class="flex items-center gap-2">
                        <p class="flex-1 text-[12px] text-[#64748B] font-medium text-center py-1">Waiting for the landlord to respond</p>
                        <button type="button" @click="showCancel = !showCancel"
                            class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
                            Cancel
                        </button>
                    </div>
                    <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                        <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Confirm cancellation</button>
                        </form>
                    </div>

                @elseif($rentalStatus === 'Under Negotiation')
                    <div class="flex items-center gap-2">
                        <p class="flex-1 text-[12px] text-[#64748B] font-medium text-center py-1">Discuss the terms with the landlord</p>
                        <button type="button" @click="showCancel = !showCancel"
                            class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
                            Cancel
                        </button>
                    </div>
                    <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                        <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Confirm cancellation</button>
                        </form>
                    </div>

                @elseif($rentalStatus === 'Pending Rental Agreement')
                    <div class="flex items-center gap-2">
                        {{-- New tab: signing is a long read-and-confirm task, and
                             losing the live chat thread (and its websocket) to
                             navigate away mid-negotiation is the wrong trade. --}}
                        <a href="{{ route('agreements.show', $reservation) }}" target="_blank" rel="noopener"
                            class="flex-1 bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl cursor-pointer transition-all duration-200 flex items-center justify-center gap-1.5">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            View &amp; sign agreement
                            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="opacity-60">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                        <button type="button" @click="showCancel = !showCancel"
                            class="px-3 py-2 border border-[#EF4444] text-[#EF4444] text-[12px] font-semibold rounded-xl hover:bg-[#EF4444]/5 cursor-pointer transition-all duration-200">
                            Cancel
                        </button>
                    </div>
                    <div x-show="showCancel" x-cloak class="mt-2 flex justify-end">
                        <form action="{{ route('reservations.cancel', $reservation) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="bg-[#EF4444] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-lg cursor-pointer transition-all duration-200">Confirm cancellation</button>
                        </form>
                    </div>

                @elseif($rentalStatus === 'Rental Agreement Signed')
                    @if($heldPayment)
                        {{-- Already paid. The remaining action is confirming
                             move-in, which is what releases the escrow — not
                             paying again, which is what this branch used to
                             offer regardless of payment state. --}}
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-[12px] font-bold text-[#1F2937]">Payment held by AbangananHub</p>
                                <p class="text-[11px] text-[#64748B]">Confirm move-in to release &#8369;{{ number_format($heldPayment->amount, 2) }} to the landlord.</p>
                            </div>
                            <a href="{{ route('agreements.show', $reservation) }}" target="_blank" rel="noopener"
                                class="shrink-0 bg-[#FF8A65] hover:brightness-95 text-white text-[12px] font-bold px-4 py-2 rounded-xl cursor-pointer transition-all duration-200 flex items-center gap-1.5">
                                Confirm move-in
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="opacity-60">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                        </div>
                    @elseif($pendingPayment)
                        <p class="text-[12px] text-[#64748B] font-medium text-center py-1">Your payment is processing — this updates automatically once it clears.</p>
                    @else
                        <a href="{{ route('agreements.show', $reservation) }}" target="_blank" rel="noopener"
                            class="w-full bg-[#1F2937] hover:brightness-95 text-white text-[12px] font-bold py-2 rounded-xl cursor-pointer transition-all duration-200 flex items-center justify-center gap-1.5">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                            Proceed to payment
                        </a>
                    @endif

                @elseif($rentalStatus === 'Occupied')
                    @php
                        $occupiedNote = $releasedPayment
                            ? 'You are occupying this unit — payment released to the landlord'
                            : 'You are occupying this unit';
                    @endphp
                    <p class="text-[12px] text-[#1F2937] font-medium text-center py-1">{{ $occupiedNote }}</p>
                @endif
            @endif
        </div>
    @endif

    {{-- Expandable details — secondary context only --}}
    <div x-show="detailsOpen" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="border-b border-[#E2E8F0] bg-[#F7FCFC] overflow-hidden flex-shrink-0 motion-reduce:transform-none">

        <div class="px-5 py-4">
            <a href="{{ route('properties.show', $conversation->property) }}" target="_blank"
                class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-[#E2E8F0] hover:brightness-95 transition-all duration-200">
                @php $detailThumb = $conversation->property->media->firstWhere('media_type', 'Image'); @endphp
                @if($detailThumb)
                    <img src="{{ $detailThumb->media_url }}" alt="" class="w-14 h-10 rounded-lg object-cover flex-shrink-0">
                @else
                    <div class="w-14 h-10 rounded-lg bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6"/></svg>
                    </div>
                @endif
                <div class="min-w-0 flex-1">
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

            @if($reservation && ($reservation->target_move_in_date || $reservation->target_move_out_date))
                <div class="flex flex-wrap items-center gap-4 mt-3 px-1">
                    @if($reservation->target_move_in_date)
                        <div class="flex items-center gap-1.5 text-[11px] text-[#64748B]">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Move in: <span class="text-[#1F2937] font-semibold">{{ $reservation->target_move_in_date->format('M j, Y') }}</span></span>
                        </div>
                    @endif
                    @if($reservation->target_move_out_date)
                        <div class="flex items-center gap-1.5 text-[11px] text-[#64748B]">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            <span>Move out: <span class="text-[#1F2937] font-semibold">{{ $reservation->target_move_out_date->format('M j, Y') }}</span></span>
                        </div>
                    @endif
                </div>
            @endif

            @if($reservation?->remarks)
                <div class="mt-3 px-1">
                    <p class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-1">Original message</p>
                    <p class="text-[12px] text-[#1F2937] leading-relaxed whitespace-pre-line">{{ $reservation->remarks }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Messages --}}
    @php $msgs = $conversation->messages->values(); @endphp
    <div id="message-list" class="flex-1 px-5 py-4 overflow-y-auto flex flex-col gap-1 scroll-smooth bg-[#F7FCFC]"
        data-other-avatar="{{ $otherParty->profile_picture }}"
        data-other-initial="{{ strtoupper(substr($otherParty->first_name, 0, 1)) }}">
        @foreach ($msgs as $i => $message)
            @if($message->is_system)
                <div class="self-stretch flex items-center gap-3 my-2 px-2">
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                    <p class="text-[11px] text-[#64748B] text-center max-w-[70%] leading-relaxed">{{ $message->message }}</p>
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                </div>
            @else
                @php
                    $isSelf = $message->sender_id === auth()->id();
                    $next = $msgs[$i + 1] ?? null;
                    // Messenger convention: the avatar sits on the LAST message
                    // of a consecutive run, not every one. A run ends at the
                    // thread end, at a system divider, or at a sender change.
                    $endsRun = !$next || $next->is_system || $next->sender_id !== $message->sender_id;
                    $prev = $msgs[$i - 1] ?? null;
                    $startsRun = !$prev || $prev->is_system || $prev->sender_id !== $message->sender_id;
                @endphp

                @if($isSelf)
                    {{-- The timestamp is absolutely positioned outside the
                         bubble so hiding it costs no vertical space. It stays
                         in the DOM (opacity only) so screen readers keep it. --}}
                    <div class="group relative max-w-[75%] self-end {{ $endsRun ? 'mb-1.5' : '' }}" data-bubble>
                        <div class="bg-[#1F2937] text-white rounded-2xl {{ $startsRun ? 'rounded-tr-sm' : '' }} px-4 py-2.5 shadow-sm cursor-default">
                            <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                        </div>
                        <p class="message-time absolute right-full top-1/2 -translate-y-1/2 mr-2 whitespace-nowrap text-[10px] tracking-wide text-[#64748B] opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none"
                            data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                    </div>
                @else
                    <div class="flex items-end gap-2 self-start max-w-[85%] {{ $endsRun ? 'mb-1.5' : '' }}"
                        data-msg-sender="{{ $message->sender_id }}">
                        {{-- Spacer keeps every bubble in the run on the same
                             left edge as the one that carries the avatar. --}}
                        <span data-avatar-slot class="contents">
                        @if($endsRun)
                            @if($message->sender?->profile_picture)
                                <img src="{{ $message->sender->profile_picture }}"
                                    alt="{{ $message->sender->first_name }} {{ $message->sender->last_name }}"
                                    class="w-7 h-7 rounded-full object-cover shrink-0 mb-0.5">
                            @else
                                <div class="w-7 h-7 rounded-full bg-[#2AA7A1] text-white flex items-center justify-center text-[11px] font-bold shrink-0 mb-0.5"
                                    aria-hidden="true">
                                    {{ strtoupper(substr($message->sender->first_name, 0, 1)) }}
                                </div>
                            @endif
                        @else
                            <div class="w-7 shrink-0" aria-hidden="true"></div>
                        @endif
                        </span>

                        <div class="group relative min-w-0" data-bubble>
                            <div class="bg-white text-[#1F2937] border border-[#E2E8F0] rounded-2xl {{ $startsRun ? 'rounded-tl-sm' : '' }} px-4 py-2.5 shadow-sm cursor-default">
                                @if($startsRun)
                                    <p class="text-[11px] font-bold text-[#156F8C] mb-1">
                                        {{ $message->sender->first_name }}
                                    </p>
                                @endif
                                <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                            </div>
                            <p class="message-time absolute left-full top-1/2 -translate-y-1/2 ml-2 whitespace-nowrap text-[10px] tracking-wide text-[#64748B] opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none"
                                data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                        </div>
                    </div>
                @endif
            @endif
        @endforeach
    </div>

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
        <div class="px-5 py-3 bg-white border-t border-[#E2E8F0] flex-shrink-0">
            <form id="message-form" class="flex items-center gap-2.5">
                <label for="message-input" class="sr-only">Message {{ $otherParty->first_name }}</label>
                <input type="text" id="message-input" name="message" required maxlength="2000" autocomplete="off"
                    class="flex-1 bg-[#F7FCFC] border border-[#E2E8F0] focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/10 rounded-xl px-4 py-2.5 text-[13px] text-[#1F2937] transition-all duration-200 outline-none placeholder-[#64748B]"
                    placeholder="Message {{ $otherParty->first_name }}...">
                <button type="submit"
                    class="bg-[#1F2937] hover:brightness-95 text-white font-bold text-[13px] px-4 py-2.5 rounded-xl shadow-sm cursor-pointer transition-all duration-200 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    Send
                </button>
            </form>
        </div>
    @endif
</div>
