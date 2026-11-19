{{-- A pop-up for every notification that arrives while the page is open (rent reminder, payment to confirm, ...).
     Listens on the private user channel the bell already uses; without Reverb it simply never shows and the bell
     number is the signal. Messages have their own toast (partials.message-notifications), so they are skipped here. --}}
@auth
<div x-data="noticeToasts()" x-cloak aria-live="polite"
    class="fixed inset-x-3 top-[calc(env(safe-area-inset-top,0px)+72px)] lg:inset-x-auto lg:right-5 lg:top-20 z-[9997] flex flex-col gap-2 pointer-events-none">
    <template x-for="t in toasts" :key="t.id">
        <a :href="t.href" role="status" @click="dismiss(t.id)"
            x-transition:enter="transition ease-out duration-300 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-2 lg:translate-y-0 lg:translate-x-4"
            x-transition:enter-end="opacity-100 translate-y-0 lg:translate-x-0"
            x-transition:leave="transition ease-in duration-200 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="pointer-events-auto block w-full max-w-[360px] mx-auto lg:mx-0 lg:ml-auto overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_8px_24px_rgba(6,13,38,0.14)]">
            <div class="flex items-start gap-3 border-l-4 p-3.5" :class="t.urgent ? 'border-[#FF8A66]' : 'border-[#060D26]'">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="t.urgent ? 'bg-[#FF8A66]/20 text-[#B35A3D]' : 'bg-[#ECEEF6] text-[#060D26]'" aria-hidden="true">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-bold text-[#060D26]" x-text="t.title"></p>
                    <p class="mt-0.5 line-clamp-3 text-[12.5px] leading-snug text-[#5B6A8E]" x-text="t.message"></p>
                </div>
                <button type="button" @click.prevent.stop="dismiss(t.id)" aria-label="Dismiss"
                    class="-mr-1 -mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26]">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </a>
    </template>
</div>

<script>
    function noticeToasts() {
        return {
            toasts: [],
            next: 0,
            // Money notices get the coral accent: they are the ones somebody has to act on.
            urgentTypes: ['rent_due', 'payment_to_confirm', 'payment', 'move_in_disputed', 'turnover_overdue'],

            init() {
                if (!window.Echo) return;
                window.Echo.private('user.{{ auth()->id() }}').listen('.NotificationCreated', (e) => this.push(e));
            },

            push(e) {
                if (!e || e.type === 'message') return;
                const id = ++this.next;
                this.toasts.push({
                    id,
                    title: e.title || 'New notification',
                    message: e.message || '',
                    urgent: this.urgentTypes.includes(e.type),
                    // Opening it marks it read and goes to its page.
                    href: e.notification_id ? '{{ url('/notifications') }}/' + e.notification_id + '/open' : '{{ route('notifications.index') }}',
                });
                if (this.toasts.length > 3) this.toasts.shift();
                setTimeout(() => this.dismiss(id), 9000);
            },

            dismiss(id) {
                this.toasts = this.toasts.filter((t) => t.id !== id);
            },
        };
    }
</script>
@endauth
