@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-5 min-h-[calc(100vh-72px)]" x-data="inboxApp()" x-cloak>

        <div class="flex flex-col overflow-hidden rounded-[28px] border border-[#E2E8F0] bg-white shadow-[0_20px_50px_rgba(15,23,42,0.06)]"
            style="height: calc(100vh - 110px); min-height: 560px;">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] p-4 flex-shrink-0">
            <div class="flex items-center gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#1F2937]">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#1F2937]">
                        {{ $isLandlord ? 'Inquiries' : 'Messages' }}
                    </h1>
                    <p class="mt-0.5 text-[13px] text-[#64748B]">
                        {{ $isLandlord ? 'Respond to tenant inquiries across your properties.' : 'Manage your active inquiries and conversation threads.' }}
                    </p>
                </div>
            </div>

            @if($isLandlord && $landlordProperties->isNotEmpty())
                <div class="relative" x-data="{ filterOpen: false }">
                    <button @click="filterOpen = !filterOpen" type="button"
                        class="flex h-10 items-center gap-2 rounded-xl border border-[#CBD5E1] bg-white px-4 text-[13px] font-medium text-[#1F2937] shadow-[0_6px_18px_rgba(15,23,42,0.04)] transition hover:border-[#2AA7A1]/50 hover:bg-[#F7FCFC] focus:outline-none">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                        </svg>
                        {{ $propertyId ? $landlordProperties->firstWhere('property_id', $propertyId)?->title ?? 'All Properties' : 'All Properties' }}
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="filterOpen" @click.away="filterOpen = false" x-cloak
                        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="absolute right-0 top-[calc(100%+6px)] z-50 w-[240px] rounded-2xl border border-[#E2E8F0] bg-white py-1 shadow-[0_20px_45px_rgba(15,23,42,0.12)]">
                        <a href="{{ route('conversations.index', array_filter(['status' => $status !== 'all' ? $status : null])) }}"
                            class="block px-4 py-2.5 text-[13px] font-medium text-[#1F2937] transition hover:bg-[#F7FCFC] {{ !$propertyId ? 'bg-[#EEF8F8]' : '' }}">
                            All Properties
                        </a>
                        @foreach($landlordProperties as $prop)
                            <a href="{{ route('conversations.index', array_filter(['status' => $status !== 'all' ? $status : null, 'property_id' => $prop->property_id])) }}"
                                class="block truncate px-4 py-2.5 text-[13px] font-medium text-[#1F2937] transition hover:bg-[#F7FCFC] {{ $propertyId == $prop->property_id ? 'bg-[#EEF8F8]' : '' }}">
                                {{ $prop->title }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Split panel --}}
        <div class="flex flex-1 overflow-hidden">

            {{-- LEFT: Conversation list --}}
            <div class="w-full lg:w-[340px] flex-shrink-0 lg:border-r border-[#64748B]/10 flex-col"
                :class="activeId ? 'hidden lg:flex' : 'flex'">

                {{-- Tabs --}}
                <div class="flex flex-shrink-0 items-center gap-0.5 border-b border-[#E2E8F0] bg-[#F8FAFC] px-2 pb-1 pt-2">
                    @php
                        $tabs = [
                            'all' => ['label' => 'Active', 'count' => $activeCount],
                            'unread' => ['label' => 'Unread', 'count' => $unreadCount],
                            'resolved' => ['label' => 'Resolved', 'count' => $resolvedCount],
                            'cancelled' => ['label' => 'Cancelled', 'count' => $cancelledCount],
                        ];
                    @endphp
                    @foreach ($tabs as $key => $tab)
                                <a href="{{ route('conversations.index', array_filter(['status' => $key, 'search' => request('search'), 'property_id' => $propertyId])) }}"
                                    class="flex-1 rounded-t-xl border-b-2 py-2.5 text-center text-[12px] font-semibold transition-all
                                        {{ $status === $key ? 'border-[#2AA7A1] bg-white text-[#1F2937] shadow-[0_-2px_0_rgba(42,167,161,0.1)]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                                    {{ $tab['label'] }}
                                    @if($tab['count'] > 0)
                                        <span class="ml-1 text-[11px] {{ $status === $key ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $tab['count'] }}</span>
                                    @endif
                                </a>
                    @endforeach
                </div>

                {{-- Search --}}
                <div class="flex-shrink-0 border-b border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3">
                    <form method="GET" action="{{ route('conversations.index') }}" class="relative">
                        <input type="hidden" name="status" value="{{ $status }}">
                        @if($propertyId)<input type="hidden" name="property_id" value="{{ $propertyId }}">@endif
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#94A3B8]"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Search by person or property..." aria-label="Search by person or property"
                            x-on:input.debounce.400ms="$el.form.requestSubmit()"
                            class="w-full rounded-xl border border-[#E2E8F0] bg-white py-2.5 pl-8 pr-3 text-[12px] text-[#1F2937] placeholder-[#94A3B8] shadow-[0_4px_10px_rgba(15,23,42,0.02)] transition focus:border-[#2AA7A1] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20" />
                    </form>
                </div>

                {{-- Conversation items --}}
                <div class="flex-1 overflow-y-auto">
                    @forelse ($conversations as $conversation)
                        @php
                            $otherParty = auth()->id() === $conversation->tenant_id
                                ? $conversation->landlord
                                : $conversation->tenant;
                            $hasUnread = $conversation->latestMessage
                                && $conversation->latestMessage->sender_id !== auth()->id()
                                && !$conversation->latestMessage->is_read;

                            $rowReservation = $conversation->activeReservation;
                            $rowStatus = $rowReservation?->rental_status;
                            $rowLabels = [
                                'Inquiry' => 'Inquiry',
                                'Under Negotiation' => 'Negotiation',
                                'Pending Rental Agreement' => 'Agreement',
                                'Rental Agreement Signed' => 'Signed',
                                'Occupied' => 'Occupied',
                                'Completed' => 'Completed',
                            ];
                            $rowTerminal = in_array($rowStatus, ['Cancelled', 'Rejected', 'Completed'], true);
                            // Same derived stage as the chat panel: money held or
                            // released while still on 'Signed' reads as "Paid".
                            $rowPaid = $rowStatus === 'Rental Agreement Signed'
                                && $rowReservation?->payments->whereIn('status', ['Held', 'Released'])->isNotEmpty();
                            $rowLabel = $rowPaid ? 'Paid' : ($rowLabels[$rowStatus] ?? $rowStatus);

                            $previewText = 'No messages yet';
                            if ($conversation->latestMessage) {
                                $previewText = $conversation->latestMessage->is_inquiry_summary && trim($conversation->latestMessage->message ?? '') === ''
                                    ? 'Sent an inquiry'
                                    : $conversation->latestMessage->message;
                            }
                        @endphp

                        <button type="button" @click="loadConversation({{ $conversation->conversation_id }})"
                            class="group flex w-full items-start gap-3 border-b border-[#E2E8F0] px-4 py-3.5 text-left transition-all duration-200 hover:bg-[#F8FAFC]"
                            :class="activeId === {{ $conversation->conversation_id }} ? 'bg-[#EEF8F8] shadow-[inset_2px_0_0_#2AA7A1]' : 'bg-white'"
                            data-conversation-id="{{ $conversation->conversation_id }}">

                            <div
                                class="mt-0.5 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#DBF6F4] to-[#C7F0EF] text-[11px] font-bold text-[#156F8C] shadow-[0_8px_18px_rgba(42,167,161,0.12)]">
                                {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="truncate text-[13px] font-bold text-[#1F2937]">
                                        {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                                    </h3>
                                    <span class="shrink-0 whitespace-nowrap text-[10px] text-[#64748B]">
                                        {{ $conversation->latestMessage ? $conversation->latestMessage->sent_at->diffForHumans(null, true) : '' }}
                                    </span>
                                </div>
                                <p data-preview
                                    class="mt-0.5 truncate text-[12px] {{ $hasUnread ? 'font-semibold text-[#1F2937]' : 'text-[#64748B]' }}">
                                    {{ $previewText }}
                                </p>
                                <p class="mt-1 flex items-center gap-1 truncate text-[11px] text-[#64748B]">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                    </svg>
                                    {{ $conversation->property->title }}
                                    @if($conversation->unit) &middot; {{ $conversation->unit->unit_label }} @endif
                                </p>

                                @if($rowStatus)
                                    <div class="mt-1.5">
                                        <span data-stage-pill="{{ $rowStatus }}"
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $rowTerminal ? 'bg-[#E2E8F0] text-[#EF4444]' : 'bg-[#EEF8F8] text-[#156F8C]' }}">
                                            {{ $rowLabel }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            @if($hasUnread)
                                <div data-unread-dot class="mt-2 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-[#2AA7A1] shadow-[0_0_0_3px_rgba(42,167,161,0.15)]"></div>
                            @endif
                        </button>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#DCF7F5] to-[#D7F0F7] shadow-[0_12px_24px_rgba(42,167,161,0.12)]">
                                <svg class="h-6 w-6 text-[#156F8C]" fill="none" stroke="currentColor"
                                    stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <p class="text-[13px] font-bold text-[#1F2937]">No conversations yet</p>
                            <p class="mx-auto mt-1 max-w-[220px] text-[12px] leading-5 text-[#64748B]">
                                {{ $isLandlord ? 'Inquiries from tenants will appear here.' : 'When you send inquiries, your conversations will appear here.' }}
                            </p>
                            @if(!$isLandlord)
                                <a href="{{ route('properties.index') }}"
                                    class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-[#2AA7A1] px-4 py-2 text-[12.5px] font-semibold text-white shadow-[0_10px_20px_rgba(42,167,161,0.25)] transition hover:brightness-95">
                                    Browse properties
                                </a>
                            @endif
                        </div>
                    @endforelse
                </div>

                @if($conversations->isNotEmpty())
                    <div class="px-4 py-2 border-t border-[#64748B]/10 flex-shrink-0">
                        <p class="text-[11px] text-[#64748B] text-center">
                            Showing 1 to {{ $conversations->count() }} of {{ $conversations->count() }} {{ $isLandlord ? Str::plural('inquiry', $conversations->count()) : Str::plural('conversation', $conversations->count()) }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- RIGHT: Chat panel --}}
            <div class="flex-1 flex-col min-w-0" id="chat-container"
                :class="activeId ? 'flex' : 'hidden lg:flex'">
                {{-- Mobile back to list --}}
                <button type="button" x-show="activeId" x-cloak @click="activeId = null; window.activeConversationId = null"
                    class="lg:hidden flex items-center gap-1.5 px-4 py-2.5 border-b border-[#64748B]/10 text-[12px] font-bold text-[#156F8C] bg-white text-left">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    All conversations
                </button>
                {{-- Empty state --}}
                <div x-show="!activeId" class="flex flex-1 items-center justify-center bg-gradient-to-br from-[#F8FBFC] via-white to-[#F5FBFB]">
                    <div class="text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-[22px] bg-gradient-to-br from-[#DCF7F5] to-[#D7F0F7] shadow-[0_12px_24px_rgba(42,167,161,0.12)]">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <p class="text-[14px] font-bold text-[#1F2937]">Select a conversation</p>
                        <p class="mt-1 text-[12px] leading-5 text-[#64748B]">Choose from your conversations on the left to start
                            messaging.</p>
                    </div>
                </div>

                {{-- Loading state --}}
                <div x-show="loading" class="flex-1 flex items-center justify-center">
                    <div class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin"></div>
                </div>

                {{-- Chat content injected here --}}
                <div x-show="activeId && !loading" class="flex-1 flex flex-col min-h-0" id="chat-panel-wrapper"></div>
            </div>

        </div>
        </div>
    </div>

    @push('scripts')
        {{-- Loaded here, not from the component: the chat panel arrives by AJAX
             with no layout, so anything the component pushes never renders. --}}
        <script src="{{ asset('js/datetime-picker.js') }}"></script>
        <script>
            function inboxApp() {
                return {
                    activeId: null,
                    loading: false,
                    echoListener: null,

                    init() {
                        const active = {{ request('active', 'null') }};
                        if (active) this.loadConversation(active);

                        // Bound once for the page lifetime, not per-conversation:
                        // this is how a thread you are NOT currently viewing gets
                        // its list row updated. The per-conversation channel only
                        // covers the open thread.
                        // Guarded: if Reverb is unreachable the inbox must still
                        // work as a normal page rather than dying in init().
                        if (!window.Echo) return;

                        window.Echo.private('user.{{ auth()->id() }}')
                            .listen('.ReservationStatusUpdated', (e) => {
                                if (e.conversation_id !== this.activeId) {
                                    this.updateListStage(e.conversation_id, e.status);
                                }
                            })
                            .listen('.MessageSent', (e) => {
                                if (e.conversation_id === this.activeId) return;
                                this.updateListPreview(e.conversation_id, e.message, e.is_system);
                            })
                            .listen('.PaymentStatusUpdated', (e) => {
                                // Same split as ReservationStatusUpdated above: this
                                // channel owns the list rows for threads you are not
                                // looking at, the conversation channel owns the open
                                // panel. Paying never changes rental_status — "Paid"
                                // is derived from payment state — so without this the
                                // row's stage pill has nothing to move it.
                                if (e.conversation_id !== this.activeId) {
                                    this.updateListStage(e.conversation_id, e.stage);
                                }
                            });
                    },

                    updateListPreview(conversationId, message, isSystem) {
                        const btn = document.querySelector(`button[data-conversation-id="${conversationId}"]`);
                        if (!btn) return;

                        const preview = btn.querySelector('[data-preview]');
                        if (preview) {
                            preview.textContent = message;
                            // System messages are status narration, not someone
                            // waiting on a reply — don't mark them unread.
                            if (!isSystem) preview.classList.add('font-semibold', 'text-[#1F2937]');
                        }

                        if (!isSystem && !btn.querySelector('[data-unread-dot]')) {
                            const dot = document.createElement('div');
                            dot.className = 'w-2.5 h-2.5 rounded-full bg-[#2AA7A1] flex-shrink-0 mt-2';
                            dot.setAttribute('data-unread-dot', '');
                            btn.appendChild(dot);
                        }
                    },

                    async loadConversation(id) {
                        if (this.activeId === id) return;
                        this.activeId = id;
                        window.activeConversationId = id;

                        // Keep the address bar in sync with what's actually open.
                        // Without this, clicking through the list never touches
                        // the URL, so a later full-page action (a form POST that
                        // redirects back(), or resolveConversation()'s reload)
                        // lands on a bare /conversations with no ?active= and the
                        // panel comes back empty even though nothing failed.
                        const url = new URL(window.location.href);
                        url.searchParams.set('active', id);
                        window.history.replaceState({}, '', url);

                        // Clean up previous Echo listener
                        if (this.echoListener) {
                            window.Echo.leave('conversation.' + this.echoListener);
                            this.echoListener = null;
                        }

                        try {
                            const res = await fetch('/conversations/' + id, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'text/html'
                                }
                            });
                            const html = await res.text();
                            const wrapper = document.getElementById('chat-panel-wrapper');
                            this.replacePanelMarkup(wrapper, html);

                            this.loading = false;

                            // Format times
                            wrapper.querySelectorAll('.message-time').forEach(el => {
                                if (el.dataset.sentAt) el.textContent = new Date(el.dataset.sentAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                            });

                            // Scroll to bottom
                            const msgList = wrapper.querySelector('#message-list');
                            if (msgList) msgList.scrollTop = msgList.scrollHeight;

                            // Wire up message form
                            this.wireMessageForm(id);

                            // Wire up Echo for real-time
                            this.wireEcho(id);

                        } catch (e) {
                            document.getElementById('chat-panel-wrapper').innerHTML =
                                '<div class="flex-1 flex items-center justify-center"><p class="text-[13px] text-[#EF4444]">Failed to load conversation.</p></div>';
                            this.loading = false;
                        }
                    },

                    wireMessageForm(conversationId) {
                        const form = document.getElementById('message-form');
                        const input = document.getElementById('message-input');
                        if (!form || !input) return;

                        form.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            const text = input.value.trim();
                            if (!text) return;

                            const headers = {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            };
                            const socketId = window.Echo?.socketId?.();
                            if (socketId) headers['X-Socket-ID'] = socketId;

                            try {
                                const res = await fetch('/conversations/' + conversationId + '/messages', {
                                    method: 'POST',
                                    headers,
                                    body: JSON.stringify({ message: text }),
                                });
                                if (!res.ok) return;
                                const data = await res.json();
                                this.appendMessage(data, true);
                                input.value = '';
                                input.focus();
                            } catch (err) {
                                console.error('Send failed', err);
                            }
                        });
                    },

                    wireEcho(conversationId) {
                        if (!window.Echo) return;
                        this.echoListener = conversationId;
                        window.Echo.private('conversation.' + conversationId)
                            .listen('.MessageSent', (e) => {
                                this.appendMessage({
                                    sender_id: e.sender_id,
                                    sender_name: e.sender_name,
                                    message: e.message,
                                    sent_at: e.sent_at,
                                    is_system: e.is_system
                                }, false);
                            })
                            .listen('.ReservationStatusUpdated', (e) => {
                                // The panel is role-dependent (landlord sees
                                // "Accept & negotiate", tenant sees "Waiting
                                // for landlord"), so we refetch our own render
                                // rather than trusting a broadcast payload.
                                this.refreshPanel();
                                this.updateListStage(e.conversation_id, e.status);
                            })
                            .listen('.PaymentStatusUpdated', (e) => {
                                // Money landing moves the stepper to "Paid" and
                                // swaps the tenant's CTA from "Proceed to payment"
                                // to "Confirm move-in", but rental_status doesn't
                                // change — so ReservationStatusUpdated never fires
                                // and this is the only signal the panel gets.
                                this.refreshPanel();
                                this.updateListStage(e.conversation_id, e.stage);
                            })
                            .listen('.HandoverScheduleUpdated', () => {
                                // Same shape again: proposing or confirming a
                                // handover slot changes neither rental_status nor
                                // payment status, so without this the other party
                                // sees the system message arrive while the strip
                                // that lets them answer it stays stale.
                                this.refreshPanel();
                            });
                    },

                    // Re-render the open panel in place. Anything the user has
                    // already typed and their scroll position both survive —
                    // losing a half-written message because the other party
                    // clicked a button would be worse than the stale panel.
                    async refreshPanel() {
                        const id = this.activeId;
                        if (!id) return;

                        const wrapper = document.getElementById('chat-panel-wrapper');
                        if (!wrapper) return;

                        const oldList = wrapper.querySelector('#message-list');
                        const oldInput = document.getElementById('message-input');
                        const draft = oldInput ? oldInput.value : '';
                        const hadFocus = document.activeElement === oldInput;
                        // Only pin to the bottom if they were already there;
                        // someone reading back through history shouldn't get
                        // yanked down by the other party's status change.
                        const wasAtBottom = oldList
                            ? (oldList.scrollHeight - oldList.scrollTop - oldList.clientHeight) < 40
                            : true;
                        const oldScroll = oldList ? oldList.scrollTop : 0;

                        let markup;
                        try {
                            const res = await fetch('/conversations/' + id, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'text/html'
                                }
                            });
                            if (!res.ok) return;
                            markup = await res.text();
                        } catch (e) {
                            return; // Leave the stale panel up rather than blanking it.
                        }

                        // Trusted first-party markup: this is the chat-panel
                        // Blade partial for the authenticated viewer, served by
                        // a Gate-authorized route, with all interpolation
                        // escaped by Blade. Same source as loadConversation().
                        this.replacePanelMarkup(wrapper, markup);

                        wrapper.querySelectorAll('.message-time').forEach(el => {
                            if (el.dataset.sentAt) el.textContent = new Date(el.dataset.sentAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        });

                        const newList = wrapper.querySelector('#message-list');
                        if (newList) {
                            newList.scrollTop = wasAtBottom ? newList.scrollHeight : oldScroll;
                        }

                        const newInput = document.getElementById('message-input');
                        if (newInput && draft) {
                            newInput.value = draft;
                            if (hadFocus) {
                                newInput.focus();
                                newInput.setSelectionRange(draft.length, draft.length);
                            }
                        }

                        // The form element was replaced, so its listener went
                        // with it. Echo is bound to the channel, not the DOM,
                        // so it must NOT be rewired here or every refresh
                        // stacks another duplicate listener.
                        this.wireMessageForm(id);
                    },

                    replacePanelMarkup(wrapper, markup) {
                        const parsed = new DOMParser().parseFromString(markup, 'text/html');
                        wrapper.replaceChildren(...parsed.body.childNodes);
                    },

                    updateListStage(conversationId, status) {
                        const btn = document.querySelector(`button[data-conversation-id="${conversationId}"]`);
                        if (!btn) return;

                        const pill = btn.querySelector('[data-stage-pill]');
                        if (!pill) return;

                        const labels = {
                            'Inquiry': 'Inquiry',
                            'Under Negotiation': 'Negotiation',
                            'Pending Rental Agreement': 'Agreement',
                            'Rental Agreement Signed': 'Signed',
                            // Derived, not a rental_status — sent by
                            // PaymentStatusUpdated once escrow is funded.
                            'Paid': 'Paid',
                            'Occupied': 'Occupied',
                            'Cancelled': 'Cancelled',
                            'Rejected': 'Rejected',
                        };
                        const terminal = status === 'Cancelled' || status === 'Rejected';

                        pill.textContent = labels[status] ?? status;
                        pill.className = terminal
                            ? 'shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#E2E8F0] text-[#EF4444]'
                            : 'shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EEF8F8] text-[#156F8C]';
                        pill.setAttribute('data-stage-pill', status);
                    },

                    appendMessage(data, isSelf) {
                        const msgList = document.getElementById('message-list');
                        if (!msgList) return;

                        if (data.is_system) {
                            const divider = document.createElement('div');
                            divider.className = 'self-stretch flex items-center gap-3 my-2 px-2';
                            divider.innerHTML = `
                                <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                                <p class="text-xs text-[#64748B] text-center max-w-[70%] leading-relaxed">${this.escapeHtml(data.message)}</p>
                                <div class="flex-1 h-px bg-[#E2E8F0]"></div>
                            `;
                            msgList.appendChild(divider);
                            msgList.scrollTop = msgList.scrollHeight;
                            return;
                        }

                        const currentUserId = {{ auth()->id() }};
                        const self = isSelf || data.sender_id === currentUserId;
                        const time = new Date(data.sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        const senderFirst = (data.sender_name || '').split(' ')[0];

                        // Mirrors the Blade markup in chat-panel.blade.php. If you
                        // restyle a bubble there, restyle it here too — these two
                        // renderers have to agree or a message changes appearance
                        // the moment the page is reloaded.
                        if (self) {
                            const wrap = document.createElement('div');
                            wrap.className = 'group relative max-w-[75%] self-end mb-1.5';
                            wrap.setAttribute('data-bubble', '');

                            const bubble = document.createElement('div');
                            bubble.className = 'bg-[#1F2937] text-white rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-sm cursor-default';
                            bubble.appendChild(this.buildBody(data.message));

                            wrap.appendChild(bubble);
                            wrap.appendChild(this.buildTime(time, true));
                            msgList.appendChild(wrap);
                        } else {
                            // The previous message in this run loses its avatar —
                            // Messenger shows it only on the last of a run.
                            const prevRow = msgList.lastElementChild;
                            if (prevRow && prevRow.dataset.msgSender === String(data.sender_id)) {
                                const slot = prevRow.querySelector('[data-avatar-slot]');
                                if (slot) slot.replaceChildren(this.buildSpacer());
                                prevRow.classList.remove('mb-1.5');
                            }

                            const row = document.createElement('div');
                            row.className = 'flex items-end gap-2 self-start max-w-[85%] mb-1.5';
                            row.dataset.msgSender = String(data.sender_id);

                            const slot = document.createElement('span');
                            slot.setAttribute('data-avatar-slot', '');
                            slot.className = 'contents';
                            slot.appendChild(this.buildAvatar(data.sender_avatar, senderFirst, data.sender_name));
                            row.appendChild(slot);

                            const wrap = document.createElement('div');
                            wrap.className = 'group relative min-w-0';
                            wrap.setAttribute('data-bubble', '');

                            const bubble = document.createElement('div');
                            bubble.className = 'bg-white text-[#1F2937] border border-[#E2E8F0] rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm cursor-default';

                            const name = document.createElement('p');
                            name.className = 'text-[11px] font-bold text-[#156F8C] mb-1';
                            name.textContent = senderFirst;
                            bubble.appendChild(name);
                            bubble.appendChild(this.buildBody(data.message));

                            wrap.appendChild(bubble);
                            wrap.appendChild(this.buildTime(time, false));
                            row.appendChild(wrap);
                            msgList.appendChild(row);
                        }

                        msgList.scrollTop = msgList.scrollHeight;

                        // Update sidebar preview
                        const btn = document.querySelector(`button[data-conversation-id="${this.activeId}"]`);
                        if (btn) {
                            // Target the preview explicitly — 'p.truncate' also
                            // matches the property-title line below it.
                            const preview = btn.querySelector('[data-preview]');
                            if (preview) preview.textContent = data.message;
                        }
                    },

                    buildBody(message) {
                        const text = document.createElement('p');
                        text.className = 'text-[13px] leading-relaxed whitespace-pre-wrap';
                        text.textContent = message;
                        return text;
                    },

                    // Mirrors the .message-time element in chat-panel.blade.php:
                    // outside the bubble so hiding it costs no height, opacity-only
                    // so it stays readable to screen readers.
                    buildTime(time, self) {
                        const stamp = document.createElement('p');
                        stamp.className = 'message-time absolute top-1/2 -translate-y-1/2 whitespace-nowrap text-[10px] tracking-wide text-[#64748B] opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none '
                            + (self ? 'right-full mr-2' : 'left-full ml-2');
                        stamp.textContent = time;
                        return stamp;
                    },

                    buildAvatar(url, firstName, fullName) {
                        if (url) {
                            const img = document.createElement('img');
                            img.src = url;
                            img.alt = fullName || firstName || '';
                            img.className = 'w-7 h-7 rounded-full object-cover shrink-0 mb-0.5';
                            return img;
                        }
                        const fallback = document.createElement('div');
                        fallback.className = 'w-7 h-7 rounded-full bg-[#2AA7A1] text-white flex items-center justify-center text-[11px] font-bold shrink-0 mb-0.5';
                        fallback.setAttribute('aria-hidden', 'true');
                        fallback.textContent = (firstName || '?').charAt(0).toUpperCase();
                        return fallback;
                    },

                    buildSpacer() {
                        const spacer = document.createElement('div');
                        spacer.className = 'w-7 shrink-0';
                        spacer.setAttribute('aria-hidden', 'true');
                        return spacer;
                    },

                    escapeHtml(str) {
                        const div = document.createElement('div');
                        div.textContent = str;
                        return div.innerHTML;
                    }
                }
            }

            // Timestamps are hover-revealed on pointer devices. Touch has no
            // hover, so tapping a bubble pins its time instead. Delegated from
            // document because the panel markup is replaced wholesale whenever
            // a conversation loads or a status change refreshes it.
            document.addEventListener('click', (e) => {
                const bubble = e.target.closest('[data-bubble]');
                const list = e.target.closest('#message-list');
                if (!list) return;

                // Tapping empty space in the thread clears any pinned time.
                list.querySelectorAll('[data-bubble] .message-time.\\!opacity-100').forEach((el) => {
                    if (!bubble || !bubble.contains(el)) el.classList.remove('!opacity-100');
                });

                if (!bubble) return;
                const time = bubble.querySelector('.message-time');
                if (time) time.classList.toggle('!opacity-100');
            });

            async function resolveConversation(id) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                try {
                    const res = await fetch('/conversations/' + id + '/resolve', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (res.ok) window.location.reload();
                } catch (e) { }
            }
        </script>
    @endpush
@endsection