{{--
    "Refund tenant": one modal per page, opened by any button that dispatches `open-refund-payment` with
    { id, action, label, rent, fee, rows }. A rebuild of landlord/tenancies/partials/void-payment-modal's shell
    (DESIGN.md §6q): the reason is required, so this cannot be a plain data-confirm form. Solid red submit,
    because the modal's only job is this destructive action.
--}}
@php
    $refundReopenId = old('refund_payment_id');
@endphp
<div x-data="{
        show: {{ $errors->has('refund_reason') || ($refundReopenId && session('error')) ? 'true' : 'false' }},
        payment: {
            id: @js($refundReopenId),
            action: @js($refundReopenId ? route('admin.payments.refund', $refundReopenId) : ''),
            label: @js(old('refund_label', '')),
            rent: @js((float) old('refund_rent', 0)),
            fee: @js((float) old('refund_fee', 0)),
            rows: @js((int) old('refund_rows', 1)),
        },
        reason: @js(old('refund_reason', '')),
        includeFee: @js((bool) old('include_fee', false)),
        open(detail) {
            this.payment = detail;
            this.reason = '';
            this.includeFee = false;
            this.show = true;
        },
        close() { this.show = false; },
        total() { return this.payment.rent + (this.includeFee ? this.payment.fee : 0); },
        peso(value) {
            return '₱' + (value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
     }"
     x-on:open-refund-payment.window="open($event.detail)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            aria-modal="true" aria-labelledby="refund-payment-title">

            <div x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="close()"
                class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

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
                        <h2 id="refund-payment-title" class="text-[17px] font-normal text-[#060D26]">Refund the tenant</h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5" x-text="payment.label"></p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="payment.action" class="px-6 pb-6">
                    @csrf
                    {{-- Resubmitted only so a failed form can reopen this modal on the same payment. --}}
                    <input type="hidden" name="refund_payment_id" :value="payment.id">
                    <input type="hidden" name="refund_label" :value="payment.label">
                    <input type="hidden" name="refund_rent" :value="payment.rent">
                    <input type="hidden" name="refund_fee" :value="payment.fee">
                    <input type="hidden" name="refund_rows" :value="payment.rows">

                    <div class="flex items-start gap-2.5 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-3.5 py-3 mb-4">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="2" class="shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p class="text-[12px] text-[#5B6A8E] leading-relaxed">
                            This sends the held money back to the tenant through PayMongo and cancels the reservation, so
                            the unit opens up again. The landlord is not paid. It cannot be undone.
                            <span x-show="payment.rows > 1" x-cloak>Rent and deposit were paid together, so both are refunded.</span>
                        </p>
                    </div>

                    <div class="mb-4">
                        <label for="refund_reason" class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                            Why are you refunding this <span class="text-[#EF4444]">*</span>
                        </label>
                        <textarea id="refund_reason" name="refund_reason" x-model="reason" rows="3" required minlength="5" maxlength="255"
                            placeholder="For example: the landlord never handed over the keys."
                            class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-2.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition"></textarea>
                        <p class="text-[11px] text-[#5B6A8E] mt-1">Both people see that you refunded it. PayMongo keeps this note too.</p>
                        @error('refund_reason')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <label x-show="payment.fee > 0" x-cloak
                        class="flex items-start gap-2.5 rounded-xl border border-[#E2E4EC] px-3.5 py-3 mb-4 cursor-pointer hover:bg-[#F7F8FC] transition-colors duration-200">
                        <input type="checkbox" name="include_fee" value="1" x-model="includeFee"
                            class="mt-0.5 w-4 h-4 rounded border-[#5B6A8E]/40 text-[#B35A3D] focus:ring-[#FF8A66]/30 cursor-pointer">
                        <span class="text-[13px] text-[#060D26]">
                            <span class="font-semibold">Also refund the online payment fee (<span x-text="peso(payment.fee)"></span>)</span>
                            <span class="block text-[12px] text-[#5B6A8E] mt-0.5">Tick this when the landlord was at fault. PayMongo does not return its own charge, so AbangananHub pays it.</span>
                        </span>
                    </label>

                    <div class="flex items-center justify-between rounded-xl bg-[#FEF2F2] px-3.5 py-3 mb-5">
                        <span class="text-[12.5px] font-semibold text-[#060D26]">The tenant gets back</span>
                        <span class="text-[16px] font-extrabold tabular-nums text-[#DC2626]" x-text="peso(total())"></span>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" @click="close()"
                            class="h-11 px-5 rounded-full border border-[#E2E4EC] text-[#5B6A8E] text-sm font-semibold hover:bg-[#F7F8FC] hover:text-[#060D26] transition-all duration-200 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="h-11 px-6 rounded-full bg-[#DC2626] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                            Refund tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
