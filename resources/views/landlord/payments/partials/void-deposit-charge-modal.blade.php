{{--
    Two flags, not one: `show` drives visibility so the leave transition
    actually plays (x-if alone doesn't animate) — RULES.md → Modals & Overlays.

    This modal IS the confirmation step — the reason is a required choice, not
    a yes/no, so it cannot be a plain `data-confirm` form.
--}}
@php
    $voidDepositFields = ['void_reason', 'void_note'];
    $voidDepositReopenId = old('void_deposit_charge_id');
@endphp
<div x-data="{
        show: {{ $errors->hasAny($voidDepositFields) ? 'true' : 'false' }},
        charge: {
            id: @js($voidDepositReopenId),
            action: @js($voidDepositReopenId ? route('landlord.depositCharges.void', $voidDepositReopenId) : ''),
            amount: @js((float) old('void_amount', 0)),
            label: @js(old('void_label', '')),
        },
        reason: @js(old('void_reason', 'wrong_amount')),
        note: @js(old('void_note', '')),
        open(detail) {
            this.charge = detail;
            this.reason = 'wrong_amount';
            this.note = '';
            this.show = true;
        },
        close() { this.show = false; },
        peso(value) {
            return '₱' + (value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
     }"
     x-on:open-void-deposit-charge.window="open($event.detail)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            aria-modal="true" aria-labelledby="void-deposit-charge-title">

            {{-- Backdrop --}}
            <div x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="close()"
                class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            {{-- Panel --}}
            <div x-show="show"
                x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="void-deposit-charge-title" class="text-[17px] font-normal text-[#060D26]">Void this charge</h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5">
                            <span x-text="charge.label"></span> · <span x-text="peso(charge.amount)"></span>
                        </p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="charge.action" class="px-6 pb-6">
                    @csrf
                    {{-- Not read by the controller (the route model binding already
                         knows which charge) — resubmitted purely so a failed
                         validation can reopen this modal on the right row instead
                         of a blank one. --}}
                    <input type="hidden" name="void_deposit_charge_id" :value="charge.id">
                    <input type="hidden" name="void_amount" :value="charge.amount">
                    <input type="hidden" name="void_label" :value="charge.label">

                    <div class="flex items-start gap-2.5 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-3.5 py-3 mb-4">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="2"
                            class="shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p class="text-[12px] text-[#5B6A8E] leading-relaxed">
                            The entry stays on your records with the reason you give. It stops counting against the
                            deposit, and the tenant is notified it was reversed.
                        </p>
                    </div>

                    <div class="mb-4">
                        <label for="void_deposit_reason" class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                            Why is this wrong <span class="text-[#EF4444]">*</span>
                        </label>
                        <x-styled-select name="void_reason" x-model="reason"
                            :options="\App\Models\DepositCharge::VOID_REASONS" selected="wrong_amount"
                            class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition" />
                        @error('void_reason')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="reason === 'other'" x-cloak class="mb-4">
                        <label for="void_deposit_note" class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                            Note <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="text" id="void_deposit_note" name="void_note" x-model="note" maxlength="255"
                            :required="reason === 'other'" placeholder="What happened with this charge?"
                            class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition">
                        @error('void_note')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" @click="close()"
                            class="h-11 px-5 rounded-full border border-[#E2E4EC] text-[#5B6A8E] text-sm font-semibold hover:bg-[#F7F8FC] hover:text-[#060D26] transition-all duration-200 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="h-11 px-6 rounded-full bg-[#DC2626] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                            Void charge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
