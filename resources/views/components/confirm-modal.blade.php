{{-- Global modal (Soft Icon Ring). Trigger from anywhere:
     window.dispatchEvent(new CustomEvent('show-modal', { detail: { type, title, message, confirmText, cancelText, onConfirm } }))

     A dispatch with no onConfirm and type 'success'/'warning' renders as a
     non-blocking, auto-dismissing toast (top-right) instead of the centered
     backdrop dialog — those are pure notifications with nothing to decide,
     so forcing a click to dismiss them was pure friction. Anything with an
     onConfirm (a real decision, however it's styled) and every 'error' /
     'confirm' notification still blocks, on purpose: a timed-out
     confirmation must never silently fire, and an error is the one
     message worth making sure someone actually reads. --}}
<div x-data="{
        open: false,
        type: 'confirm',
        title: '',
        message: '',
        confirmText: null,
        cancelText: 'Cancel',
        onConfirm: null,
        isToast: false,
        progress: 100,
        toastTimer: null,
        defaults: { confirm: 'Confirm', success: 'OK', warning: 'OK', error: 'OK' },
        show(detail) {
            this.type = detail.type || 'confirm';
            this.title = detail.title || '';
            this.message = detail.message || '';
            this.confirmText = detail.confirmText || this.defaults[this.type];
            this.cancelText = detail.cancelText || 'Cancel';
            this.onConfirm = detail.onConfirm || null;
            this.isToast = !this.onConfirm && (this.type === 'success' || this.type === 'warning');
            this.open = true;

            clearTimeout(this.toastTimer);
            if (this.isToast) {
                this.progress = 100;
                // Double rAF so the bar actually paints at 100% before the
                // transition to 0% starts — same reasoning as the classList
                // double-rAF pattern elsewhere in this app, just needed
                // here because Alpine would otherwise batch both writes
                // into a single frame and skip the animation entirely.
                requestAnimationFrame(() => requestAnimationFrame(() => { this.progress = 0; }));
                this.toastTimer = setTimeout(() => { this.open = false; }, 5000);
            }
        },
        confirm() {
            this.open = false;
            if (typeof this.onConfirm === 'function') this.onConfirm();
        },
        dismiss() {
            clearTimeout(this.toastTimer);
            this.open = false;
        }
    }"
    x-on:show-modal.window="show($event.detail)"
    x-on:keydown.escape.window="open = false">

    {{-- ── Blocking modal: confirm + error + non-toast success/warning ── --}}
    <div x-show="open && !isToast" x-cloak
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9998] bg-black/50 backdrop-blur-sm flex items-center justify-center">

        <div x-show="open && !isToast"
            x-on:click.outside="open = false"
            x-transition:enter="ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2 motion-reduce:scale-100 motion-reduce:translate-y-0"
            class="bg-white rounded-2xl max-w-[370px] w-full mx-4 p-8 pt-7 text-center shadow-lg">

            {{-- Icon --}}
            <div x-show="open"
                x-transition:enter="ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300 delay-100"
                x-transition:enter-start="opacity-0 scale-75 motion-reduce:scale-100"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="w-14 h-14 rounded-full mx-auto mb-5 flex items-center justify-center"
                :class="{
                    'bg-[#EEF8F8] shadow-[0_0_0_6px_rgba(42,167,161,0.08)]': type === 'confirm',
                    'bg-[#ECFDF5] shadow-[0_0_0_6px_rgba(34,197,94,0.08)]': type === 'success',
                    'bg-[#FFFBEB] shadow-[0_0_0_6px_rgba(251,191,36,0.08)]': type === 'warning',
                    'bg-[#FEF2F2] shadow-[0_0_0_6px_rgba(239,68,68,0.08)]': type === 'error'
                }">
                {{-- question-mark-circle --}}
                <svg x-show="type === 'confirm'" class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
                {{-- check-circle --}}
                <svg x-show="type === 'success'" class="w-7 h-7 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{-- exclamation-triangle --}}
                <svg x-show="type === 'warning'" class="w-7 h-7 text-[#FBBF24]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                {{-- x-circle --}}
                <svg x-show="type === 'error'" class="w-7 h-7 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <h3 class="text-base font-semibold text-[#1F2937] mb-1.5" x-text="title"></h3>
            <p class="text-sm text-[#64748B] leading-relaxed mb-6" x-text="message"></p>

            <div class="flex gap-3">
                <button type="button" x-show="typeof onConfirm === 'function'" x-on:click="open = false"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold border border-[#E2E8F0] text-[#64748B] bg-white hover:bg-[#F7FCFC] transition-colors"
                    x-text="cancelText"></button>

                <button type="button" x-on:click="confirm()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all hover:brightness-95"
                    :class="{
                        'bg-[#2AA7A1] text-white': type === 'confirm',
                        'bg-[#22C55E] text-white': type === 'success',
                        'bg-[#FBBF24] text-[#1F2937]': type === 'warning',
                        'bg-[#EF4444] text-white': type === 'error'
                    }"
                    x-text="confirmText"></button>
            </div>
        </div>
    </div>

    {{-- ── Toast: pure success/warning notifications, non-blocking ── --}}
    <div x-show="open && isToast" x-cloak
        x-transition:enter="ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2 motion-reduce:translate-y-0"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2 motion-reduce:translate-y-0"
        class="fixed top-20 right-5 z-[9998] w-[calc(100vw-2.5rem)] max-w-[360px]">

        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_8px_30px_rgba(0,0,0,0.12)] overflow-hidden">
            <div class="flex items-start gap-3 p-4">
                <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                    :class="type === 'success' ? 'bg-[#ECFDF5]' : 'bg-[#FFFBEB]'">
                    <svg x-show="type === 'success'" class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="type === 'warning'" class="w-5 h-5 text-[#FBBF24]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <p class="text-[13.5px] font-bold text-[#1F2937]" x-text="title"></p>
                    <p class="text-[12.5px] text-[#64748B] mt-0.5 leading-snug" x-text="message"></p>
                </div>
                <button type="button" x-on:click="dismiss()"
                    class="shrink-0 -mt-2 -mr-2 w-11 h-11 flex items-center justify-center rounded-full hover:bg-[#F7FCFC] text-[#94A3B8] transition-colors">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="h-1 bg-[#F7FCFC]">
                <template x-if="open && isToast">
                    <div class="h-full motion-reduce:transition-none"
                        :class="type === 'success' ? 'bg-[#22C55E]' : 'bg-[#FBBF24]'"
                        :style="`width:${progress}%; transition-property: width; transition-duration: 5000ms; transition-timing-function: linear;`"></div>
                </template>
            </div>
        </div>
    </div>
</div>
