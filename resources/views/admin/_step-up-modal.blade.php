{{-- Global step-up modal: "Confirm your password" before a risky admin action.
     The SERVER is the gate (RequireRecentPassword). It blocks a stale request and flashes `step_up_required`;
     this modal opens on that flash. It POSTs to admin.stepUp.confirm, then tells the admin to repeat the action.

     Fetch-based admin UIs can open it themselves when a request answers HTTP 423:
         window.dispatchEvent(new CustomEvent('step-up-required'))
     No global fetch patching on purpose. --}}
@php($hasTwoFactor = auth()->user()?->hasTwoFactor() ?? false)
<div x-data="{
        open: @json((bool) session('step_up_required')),
        useCode: false,
        hasTwoFactor: @json($hasTwoFactor),
        password: '',
        code: '',
        error: '',
        busy: false,
        show() {
            this.error = '';
            this.password = '';
            this.code = '';
            this.useCode = false;
            this.open = true;
            this.$nextTick(() => this.focusField());
        },
        toggle() {
            this.useCode = !this.useCode;
            this.error = '';
            this.$nextTick(() => this.focusField());
        },
        focusField() {
            const el = this.useCode ? this.$refs.codeField : this.$refs.pwField;
            if (el) el.focus();
        },
        close() { this.open = false; },
        async submit() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const res = await fetch(@js(route('admin.stepUp.confirm')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.useCode ? { code: this.code } : { password: this.password }),
                });
                if (res.ok) {
                    this.open = false;
                    this.password = '';
                    this.code = '';
                    window.dispatchEvent(new CustomEvent('show-modal', {
                        detail: { type: 'success', title: 'Confirmed', message: 'Confirmed. Repeat the action.' }
                    }));
                    return;
                }
                let body = {};
                try { body = await res.json(); } catch (e) {}
                this.error = body.errors?.password?.[0] || body.errors?.code?.[0] || body.message
                    || (res.status === 429 ? 'Too many tries. Wait a minute and try again.' : 'Could not confirm. Try again.');
            } catch (e) {
                this.error = 'Could not reach the server. Check your connection and try again.';
            } finally {
                this.busy = false;
            }
        }
    }"
    x-init="if (open) $nextTick(() => focusField())"
    x-on:step-up-required.window="show()"
    x-on:keydown.escape.window="close()">

    <div x-show="open" x-cloak
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-sm flex items-center justify-center"
        role="dialog" aria-modal="true" aria-labelledby="step-up-title">

        <form x-show="open"
            x-on:submit.prevent="submit()"
            x-on:click.outside="close()"
            x-transition:enter="ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2 motion-reduce:scale-100 motion-reduce:translate-y-0"
            class="bg-white rounded-2xl max-w-[400px] w-full mx-4 p-7 shadow-lg">

            <div class="w-14 h-14 rounded-full mx-auto mb-4 flex items-center justify-center bg-[#ECEEF6] shadow-[0_0_0_6px_rgba(6,13,38,0.08)]">
                {{-- lock-closed --}}
                <svg class="w-7 h-7 text-[#060D26]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>

            <h3 id="step-up-title" class="text-base font-semibold text-[#060D26] text-center mb-1.5">Confirm your password</h3>
            <p class="text-sm text-[#5B6A8E] leading-relaxed text-center mb-5">
                You are about to change something sensitive. Enter your password to continue for the next 15 minutes.
            </p>

            <div x-show="!useCode">
                <label for="step-up-password" class="block text-xs font-semibold text-[#060D26] mb-1.5">Password</label>
                <input id="step-up-password" type="password" autocomplete="current-password" x-model="password"
                    x-ref="pwField"
                    class="w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-sm text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]"
                    x-bind:class="error ? 'border-[#EF4444]' : ''">
            </div>

            @if ($hasTwoFactor)
                <div x-show="useCode" x-cloak>
                    <label for="step-up-code" class="block text-xs font-semibold text-[#060D26] mb-1.5">6-digit authenticator code</label>
                    <input id="step-up-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="12" x-model="code"
                        x-ref="codeField"
                        class="w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-sm tracking-widest text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]"
                        x-bind:class="error ? 'border-[#EF4444]' : ''">
                </div>
            @endif

            <p x-show="error" x-cloak x-text="error" role="alert" class="text-xs text-[#EF4444] mt-2"></p>

            @if ($hasTwoFactor)
                <button type="button" x-on:click="toggle()"
                    class="mt-3 text-xs font-semibold text-[#5B6A8E] hover:text-[#060D26] underline cursor-pointer"
                    x-text="useCode ? 'Use my password instead' : 'Use my authenticator code instead'"></button>
            @endif

            <div class="flex gap-3 mt-6">
                <button type="button" x-on:click="close()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold border border-[#E2E4EC] text-[#5B6A8E] bg-white hover:bg-[#F7F8FC] transition-colors cursor-pointer">Cancel</button>
                <button type="submit" x-bind:disabled="busy"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-[#FF8A66] text-[#060D26] transition-all hover:brightness-95 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer">
                    <span x-text="busy ? 'Checking...' : 'Confirm'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
