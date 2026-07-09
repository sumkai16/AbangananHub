{{-- Floating message bubble + panel + toast notifications --}}
@auth
@php
    $onConversationsPage = request()->routeIs('conversations.*');
    $unreadMsgCount = \App\Models\Message::whereHas('conversation', function ($q) {
        $q->where('tenant_id', auth()->id())
          ->orWhere('landlord_id', auth()->id());
    })
    ->where('sender_id', '!=', auth()->id())
    ->where('is_read', false)
    ->count();
@endphp

<div x-data="messageNotifications()" x-cloak>

    {{-- Toast container --}}
    <div style="position: fixed; bottom: 80px; right: 20px; z-index: 9998; display: flex; flex-direction: column-reverse; gap: 8px; pointer-events: none;">
        <template x-for="toast in toasts" :key="toast.id">
            <div @click="openConversation(toast.conversation_id)"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                class="pointer-events-auto cursor-pointer w-[calc(100vw-2.5rem)] max-w-[340px] bg-white border border-[#64748B]/20 rounded-2xl p-3.5 shadow-[0_8px_24px_rgba(0,0,0,0.12)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.16)] transition-shadow duration-200">
                <div class="flex gap-3 items-start">
                    <div class="w-10 h-10 rounded-full bg-[#1F2937] flex items-center justify-center shrink-0">
                        <span class="text-white text-[14px] font-bold" x-text="toast.sender_initial"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="text-[13px] font-bold text-[#1F2937] truncate" x-text="toast.sender_name"></span>
                            <span class="text-[11px] text-[#64748B] shrink-0">now</span>
                        </div>
                        <div class="text-[11px] text-[#64748B] truncate mb-1" x-show="toast.property_title">
                            <span x-text="toast.property_title"></span>
                            <template x-if="toast.unit_label">
                                <span> · <span x-text="toast.unit_label"></span></span>
                            </template>
                        </div>
                        <div class="text-[13px] text-[#1F2937]/80 truncate" x-text="toast.message"></div>
                    </div>
                    <button @click.stop="dismissToast(toast.id)"
                        class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full hover:bg-[#E2E8F0] text-[#64748B] transition-colors">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
    </div>

    @unless($onConversationsPage)

    {{-- Message panel (hidden by default, shown when bubble clicked) --}}
    <div x-show="panelOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="fixed bottom-5 right-5 z-[9997] w-[calc(100vw-2.5rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_8px_30px_rgba(0,0,0,0.15)] border border-[#64748B]/15 overflow-hidden"
        :style="footerOffset ? { bottom: footerOffset + 'px' } : {}"
        @click.away="closeBubblePanel()">
        <div x-ref="bubblePanelBody">
            <div class="px-4 py-8 text-center">
                <div class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin mx-auto"></div>
            </div>
        </div>
    </div>

    {{-- Floating bubble (hidden when panel is open) --}}
    <button x-show="!panelOpen" @click="openBubblePanel()"
        class="fixed bottom-5 right-5 z-[9997] w-[52px] h-[52px] rounded-full bg-[#2AA7A1] flex items-center justify-center shadow-[0_4px_16px_rgba(97,178,240,0.35)] hover:brightness-95 transition-all duration-200 focus:outline-none"
        :style="footerOffset ? { bottom: footerOffset + 'px' } : {}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-75"
        x-transition:enter-end="opacity-100 scale-100">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <div x-show="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 min-w-[20px] h-[20px] rounded-full bg-[#EF4444] text-white text-[11px] font-bold flex items-center justify-center px-1 border-2 border-white"
            x-text="unreadCount > 99 ? '99+' : unreadCount">
        </div>
    </button>

    @endunless

</div>

<script>
    function messageNotifications() {
        return {
            toasts: [],
            unreadCount: {{ $unreadMsgCount }},
            nextId: 0,
            maxToasts: 3,
            panelOpen: false,
            panelLoaded: false,
            footerOffset: 0,

            init() {
                this.watchFooter();

                if (typeof window.Echo === 'undefined') return;

                window.Echo.private('user.{{ auth()->id() }}')
                    .listen('.MessageSent', (e) => {
                        if (this.isViewingConversation(e.conversation_id)) return;

                        this.unreadCount++;
                        this.panelLoaded = false;
                        this.addToast(e);
                    });
            },

            watchFooter() {
                const footer = document.querySelector('footer');
                if (!footer || !('IntersectionObserver' in window)) return;

                new IntersectionObserver((entries) => {
                    // Lift the bubble above the footer while any part of it is visible
                    this.footerOffset = entries[0].isIntersecting ? footer.offsetHeight + 16 : 0;
                }).observe(footer);
            },

            isViewingConversation(conversationId) {
                if (window.activeConversationId && parseInt(window.activeConversationId) === parseInt(conversationId)) return true;

                const activeParam = new URLSearchParams(window.location.search).get('active');
                if (activeParam && parseInt(activeParam) === parseInt(conversationId)) return true;

                return window.location.pathname.includes('/conversations/' + conversationId);
            },

            addToast(data) {
                const id = this.nextId++;
                this.toasts.push({
                    id,
                    conversation_id: data.conversation_id,
                    sender_name: data.sender_name,
                    sender_initial: data.sender_initial || data.sender_name.charAt(0).toUpperCase(),
                    message: data.message,
                    property_title: data.property_title || '',
                    unit_label: data.unit_label || '',
                });

                if (this.toasts.length > this.maxToasts) {
                    this.toasts.shift();
                }

                setTimeout(() => this.dismissToast(id), 5000);
            },

            dismissToast(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            },

            openConversation(conversationId) {
                window.location.href = '/conversations?active=' + conversationId;
            },

            async openBubblePanel() {
                this.panelOpen = true;
                if (!this.panelLoaded) {
                    await this.fetchBubblePanel();
                }
            },

            closeBubblePanel() {
                this.panelOpen = false;
            },

            async fetchBubblePanel() {
                try {
                    const res = await fetch('{{ route("conversations.recentMessages") }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.$refs.bubblePanelBody.innerHTML = await res.text();
                    this.panelLoaded = true;
                } catch (e) {
                    this.$refs.bubblePanelBody.innerHTML = '<div class="px-4 py-6 text-center text-[13px] text-[#64748B]">Failed to load messages.</div>';
                }
            }
        }
    }
</script>
@endauth