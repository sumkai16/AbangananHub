@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
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

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 min-h-[calc(100vh-72px)]">
        {{-- Back nav --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('conversations.index') }}"
                class="inline-flex items-center text-sm font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors group">
                <svg class="w-4 h-4 mr-2 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Back to Messages
            </a>

            @if($isLandlord && !$conversation->isResolved())
                <form action="{{ route('conversations.resolve', $conversation) }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-[#1F2937] bg-[#EEF8F8] border border-[#2AA7A1]/20 rounded-xl hover:brightness-95 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Mark as Resolved
                    </button>
                </form>
            @elseif($conversation->isResolved())
                <span class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-xl">
                    Resolved
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- ===== CHAT PANEL ===== --}}
            <div class="lg:col-span-2 bg-white/70 backdrop-blur-xl border border-white/30 shadow-lg rounded-2xl flex flex-col overflow-hidden h-[600px]">

                {{-- Chat header with property context + stage pill --}}
                <div class="p-4 border-b border-[#EEF8F8] flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#2AA7A1] text-white flex items-center justify-center font-bold text-sm">
                            {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <h1 class="text-sm font-bold text-[#1F2937] leading-tight">
                                {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                            </h1>
                            <p class="text-xs text-[#64748B] mt-0.5">
                                {{ $conversation->property->title }}
                                @if($conversation->unit)
                                    &middot; {{ $conversation->unit->unit_label }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($rentalStatus && !$isTerminal)
                        <div class="bg-[#EEF8F8] rounded-lg px-3 py-1.5 flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1]"></div>
                            <span class="text-[10px] font-bold text-[#1F2937] tracking-wide">{{ $stageLabels[$currentStageIndex] ?? $rentalStatus }}</span>
                        </div>
                    @elseif($isTerminal)
                        <div class="bg-[#E2E8F0] rounded-lg px-3 py-1.5">
                            <span class="text-[10px] font-bold text-[#EF4444] tracking-wide">{{ $rentalStatus }}</span>
                        </div>
                    @endif
                </div>

                {{-- Messages --}}
                <div id="message-list" class="flex-1 p-6 overflow-y-auto flex flex-col gap-3 scroll-smooth" style="background: #F7FCFC;">
                    @foreach ($conversation->messages as $message)
                        @if($message->is_system)
                            <div class="self-stretch flex items-center gap-3 my-1 px-2">
                                <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                                <p class="text-xs text-[#64748B] text-center max-w-[70%] leading-relaxed">{{ $message->message }}</p>
                                <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                            </div>
                        @else
                            @php $isSelf = $message->sender_id === auth()->id(); @endphp
                            <div class="max-w-[75%] {{ $isSelf ? 'self-end bg-[#1F2937] text-white rounded-2xl rounded-tr-sm' : 'self-start bg-white text-[#1F2937] border border-[#EEF8F8] rounded-2xl rounded-tl-sm' }} px-4 py-2.5 shadow-sm">
                                @if(!$isSelf)
                                    <p class="text-[10px] font-bold text-[#64748B] mb-1 tracking-wide uppercase">
                                        {{ $message->sender->first_name }}
                                    </p>
                                @endif
                                <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                                <div class="flex items-center justify-end mt-1">
                                    <p class="text-[10px] tracking-wide {{ $isSelf ? 'text-white/40' : 'text-[#64748B]' }} message-time"
                                        data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Input --}}
                <div class="p-4 bg-white/50 backdrop-blur-lg border-t border-white/30">
                    <form id="message-form" class="flex items-center gap-3">
                        <input type="text" id="message-input" name="message" required maxlength="2000" autofocus
                            class="flex-1 bg-[#E2E8F0] border border-[#EEF8F8] focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/10 rounded-xl px-4 py-3 text-sm text-[#1F2937] transition outline-none placeholder-[#64748B]"
                            placeholder="Message {{ $otherParty->first_name }}...">
                        <button type="submit"
                            class="bg-[#1F2937] hover:brightness-95 active:scale-[0.98] text-white font-semibold text-sm px-5 py-3 rounded-xl shadow-sm transition inline-flex items-center gap-1.5">
                            <span>Send</span>
                            <svg class="w-4 h-4 rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            {{-- ===== RIGHT SIDEBAR ===== --}}
            <div class="lg:col-span-1 space-y-3">

                {{-- RENTAL PROGRESS --}}
                @if($reservation)
                    <div class="bg-white/70 backdrop-blur-xl border border-white/30 shadow-lg rounded-2xl p-4">
                        <div class="text-[10px] font-bold text-[#64748B] uppercase tracking-wider mb-3">Rental Progress</div>

                        @if($isTerminal)
                            <div class="bg-[#E2E8F0] rounded-xl p-3 text-center">
                                <span class="text-sm font-bold text-[#EF4444]">{{ $rentalStatus }}</span>
                                @if($rentalStatus === 'Rejected' && $reservation->rejection_reason)
                                    <p class="text-xs text-[#64748B] mt-1">{{ $reservation->rejection_reason }}</p>
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
                                    <div class="relative flex gap-3 {{ !$isLast ? 'pb-5' : '' }}">
                                        {{-- Connector (drawn first, behind the dot) --}}
                                        @if(!$isLast)
                                            <div class="absolute left-[9px] top-5 bottom-0 w-0.5 rounded-full {{ $isDone ? 'bg-[#2AA7A1]' : 'bg-[#EEF8F8]' }} transition-colors duration-300"></div>
                                        @endif
                                        {{-- Dot --}}
                                        <div class="relative z-10 flex-shrink-0">
                                            @if($isDone)
                                                <div class="w-5 h-5 rounded-full bg-[#2AA7A1] flex items-center justify-center shadow-sm">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </div>
                                            @elseif($isCurrent)
                                                <div class="w-5 h-5 rounded-full bg-[#2AA7A1] flex items-center justify-center ring-4 ring-[#EEF8F8]">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                                </div>
                                            @else
                                                <div class="w-5 h-5 rounded-full border-2 border-[#EEF8F8] bg-white"></div>
                                            @endif
                                        </div>
                                        {{-- Label --}}
                                        <div class="pt-0.5">
                                            <div class="text-xs {{ $isCurrent ? 'font-bold text-[#1F2937]' : ($isDone ? 'font-medium text-[#1F2937]' : 'text-[#64748B]') }}">
                                                {{ $stageLabels[$i] }}
                                            </div>
                                            @if($isCurrent)
                                                <div class="text-[10px] text-[#64748B] mt-0.5">In progress</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ACTIONS --}}
                @if($reservation && !$isTerminal)
                    <div class="bg-white/70 backdrop-blur-xl border border-white/30 shadow-lg rounded-2xl p-4">

                        {{-- LANDLORD ACTIONS --}}
                        @if($isLandlord)
                            @if($rentalStatus === 'Inquiry')
                                <div class="space-y-2">
                                    <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full bg-[#1F2937] hover:brightness-95 text-white text-xs font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-1.5">
                                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                            Accept &amp; negotiate
                                        </button>
                                    </form>
                                    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
                                        x-data="{ open: false }">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" @click="open = !open"
                                            class="w-full border border-[#EF4444] text-[#EF4444] text-xs font-semibold py-2.5 rounded-xl transition-colors duration-150 hover:bg-[#EF4444]/5">
                                            Reject
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2 space-y-2">
                                            <textarea name="rejection_reason" rows="2" placeholder="Reason (optional)"
                                                class="w-full text-xs border border-[#EEF8F8] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none resize-none"></textarea>
                                            <button type="submit" class="w-full bg-[#EF4444] hover:brightness-95 active:scale-[0.98] text-white text-xs font-bold py-2 rounded-lg transition-all duration-150">
                                                Confirm rejection
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Under Negotiation')
                            <div class="space-y-2" x-data="{ showTc: false }">
                                <button type="button" @click="showTc = !showTc"
                                    class="w-full bg-[#1F2937] hover:brightness-95 text-white text-xs font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-1.5">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    Send agreement
                                </button>
                                <div x-show="showTc" x-transition x-cloak>
                                    <form action="{{ route('landlord.reservations.advanceAgreement', $reservation) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <div class="p-3 bg-[#EEF8F8] rounded-xl border border-[#2AA7A1]/20">
                                            <label class="flex items-start gap-2.5 cursor-pointer group mb-3">
                                                <input type="checkbox" name="accept_tc" required
                                                    class="mt-0.5 w-4 h-4 rounded border-[#64748B]/40 text-[#156F8C] focus:ring-[#2AA7A1] focus:ring-offset-0 transition">
                                                <span class="text-[11px] text-[#1F2937] leading-relaxed">
                                                    I agree that the tenant's payment will be held by AbangananHub until the tenant confirms move-in. Funds will be released only after tenant verification.
                                                </span>
                                            </label>
                                            <button type="submit"
                                                class="w-full bg-[#2AA7A1] hover:brightness-95 text-white text-xs font-bold py-2 rounded-lg transition">
                                                Confirm &amp; send agreement
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
                                    x-data="{ open: false }">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button" @click="open = !open"
                                        class="w-full border border-[#EF4444] text-[#EF4444] text-xs font-semibold py-2.5 rounded-xl transition-colors duration-150 hover:bg-[#EF4444]/5">
                                        Reject
                                    </button>
                                    <div x-show="open" x-cloak class="mt-2 space-y-2">
                                        <textarea name="rejection_reason" rows="2" placeholder="Reason (optional)"
                                            class="w-full text-xs border border-[#EEF8F8] rounded-lg px-3 py-2 text-[#1F2937] placeholder-[#64748B] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1]/10 outline-none resize-none"></textarea>
                                        <button type="submit" class="w-full bg-[#EF4444] hover:brightness-95 active:scale-[0.98] text-white text-xs font-bold py-2 rounded-lg transition-all duration-150">
                                            Confirm rejection
                                        </button>
                                    </div>
                                </form>
                            </div>

                            @elseif($rentalStatus === 'Pending Rental Agreement')
                                <div class="bg-[#EEF8F8] rounded-xl p-3 text-center">
                                    <p class="text-xs text-[#1F2937] font-medium">Waiting for tenant to sign the agreement</p>
                                </div>

                            @elseif($rentalStatus === 'Rental Agreement Signed')
                                <div class="bg-[#EEF8F8] rounded-xl p-3 text-center">
                                    <p class="text-xs text-[#1F2937] font-medium">Agreement signed — waiting for payment</p>
                                </div>

                            @elseif($rentalStatus === 'Occupied')
                                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                                    <p class="text-xs text-emerald-700 font-medium">Tenant is now occupying this unit</p>
                                </div>
                            @endif
                        @endif

                        {{-- TENANT ACTIONS --}}
                        @if($isTenant)
                            @if($rentalStatus === 'Inquiry')
                                <div class="bg-[#EEF8F8] rounded-xl p-3 text-center">
                                    <p class="text-xs text-[#1F2937] font-medium">Waiting for landlord to respond</p>
                                </div>

                            @elseif($rentalStatus === 'Under Negotiation')
                                <div class="space-y-2">
                                    <div class="bg-[#EEF8F8] rounded-xl p-3 text-center">
                                        <p class="text-xs text-[#1F2937] font-medium">Discuss terms with the landlord</p>
                                    </div>
                                    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
                                        x-data="{ open: false }">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" @click="open = !open"
                                            class="w-full border border-[#EF4444] text-[#EF4444] text-xs font-semibold py-2.5 rounded-xl transition-colors duration-150 hover:bg-[#EF4444]/5">
                                            Cancel
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2">
                                            <button type="submit" class="w-full bg-[#EF4444] hover:brightness-95 active:scale-[0.98] text-white text-xs font-bold py-2 rounded-lg transition-all duration-150">
                                                Confirm cancellation
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Pending Rental Agreement')
                                <div class="space-y-2">
                                    <a href="{{ route('agreements.show', $reservation) }}"
                                        class="w-full bg-[#1F2937] hover:brightness-95 text-white text-xs font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-1.5">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        View &amp; sign agreement
                                    </a>
                                    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
                                        x-data="{ open: false }">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" @click="open = !open"
                                            class="w-full border border-[#EF4444] text-[#EF4444] text-xs font-semibold py-2.5 rounded-xl transition-colors duration-150 hover:bg-[#EF4444]/5">
                                            Cancel
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2">
                                            <button type="submit" class="w-full bg-[#EF4444] hover:brightness-95 active:scale-[0.98] text-white text-xs font-bold py-2 rounded-lg transition-all duration-150">
                                                Confirm cancellation
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            @elseif($rentalStatus === 'Rental Agreement Signed')
                                <a href="{{ route('agreements.show', $reservation) }}"
                                    class="w-full bg-[#1F2937] hover:brightness-95 text-white text-xs font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-1.5">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                                    Proceed to payment
                                </a>

                            @elseif($rentalStatus === 'Occupied')
                                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                                    <p class="text-xs text-emerald-700 font-medium">You are now occupying this unit</p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                {{-- PROPERTY CARD --}}
                <a href="{{ route('properties.show', $conversation->property) }}" target="_blank"
                    class="bg-white/70 backdrop-blur-xl border border-white/30 shadow-lg rounded-2xl p-4 flex items-center gap-3 hover:bg-[#E2E8F0] transition block">
                    <div class="w-12 h-12 rounded-xl bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                        @if($conversation->property->media->count() > 0)
                            <img src="{{ $conversation->property->media->first()->media_url }}" alt="" class="w-12 h-12 rounded-xl object-cover">
                        @else
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-[#1F2937] truncate">{{ $conversation->property->title }}</div>
                        <div class="text-[11px] text-[#64748B] mt-0.5">
                            @if($conversation->unit)
                                {{ $conversation->unit->unit_label }} &middot; ₱{{ number_format($conversation->unit->rental_fee) }}/mo
                            @else
                                {{ $conversation->property->address }}
                            @endif
                        </div>
                    </div>
                </a>

            </div>
        </div>
    </div>

    <script type="module">
        document.querySelectorAll('.message-time').forEach((el) => {
            el.textContent = new Date(el.dataset.sentAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        });

        const conversationId = {{ $conversation->conversation_id }};
        const currentUserId = {{ auth()->id() }};
        const messageList = document.getElementById('message-list');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');

        messageList.scrollTop = messageList.scrollHeight;

        function appendMessage({ sender_id, sender_name, message, sent_at, is_system }) {
            if (is_system) {
                const divider = document.createElement('div');
                divider.className = 'self-stretch flex items-center gap-3 my-1 px-2';
                divider.innerHTML = `
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                    <p class="text-xs text-[#64748B] text-center max-w-[70%] leading-relaxed"></p>
                    <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                `;
                divider.querySelector('p').textContent = message;
                messageList.appendChild(divider);
                messageList.scrollTop = messageList.scrollHeight;
                return;
            }

            const isSelf = sender_id === currentUserId;
            const bubble = document.createElement('div');

            bubble.className = `max-w-[75%] ${isSelf
                ? 'self-end bg-[#1F2937] text-white rounded-2xl rounded-tr-sm'
                : 'self-start bg-white text-[#1F2937] border border-[#EEF8F8] rounded-2xl rounded-tl-sm'
            } px-4 py-2.5 shadow-sm`;

            bubble.innerHTML = `
                ${!isSelf ? `<p class="text-[10px] font-bold text-[#64748B] mb-1 tracking-wide uppercase">${sender_name.split(' ')[0]}</p>` : ''}
                <p class="text-sm leading-relaxed whitespace-pre-wrap">${message}</p>
                <div class="flex items-center justify-end mt-1">
                    <p class="text-[10px] tracking-wide ${isSelf ? 'text-white/40' : 'text-[#64748B]'}">${new Date(sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</p>
                </div>
            `;
            messageList.appendChild(bubble);
            messageList.scrollTop = messageList.scrollHeight;
        }

        messageForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;

          const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
            const socketId = window.Echo?.socketId?.();
            if (socketId) {
                headers['X-Socket-ID'] = socketId;
            }

            const response = await fetch(`/conversations/${conversationId}/messages`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ message: text }),
            });

            if (!response.ok) {
                console.error('Failed to send message');
                return;
            }

            const data = await response.json();
            appendMessage(data);
            messageInput.value = '';
        });

        window.Echo.private(`conversation.${conversationId}`)
            .listen('.MessageSent', (e) => {
                appendMessage({
                    sender_id: e.sender_id,
                    sender_name: e.sender_name,
                    message: e.message,
                    sent_at: e.sent_at,
                    is_system: e.is_system
                });
            });
    </script>
@endsection