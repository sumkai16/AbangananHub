@php
    // Unsettled months first so the obvious choice is the top one; every month
    // stays selectable because a landlord may be recording a late top-up.
    $periodOptions = $periods->reverse()->values();
    $defaultPeriod = ($summary['oldestOverdue'] ?? $summary['nextDue'] ?? $periods->last());

    $modalInput = 'h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition';
    $modalLabel = 'block text-[12px] font-semibold text-[#1F2937] mb-1.5';
@endphp

{{--
    Two flags, not one: `show` drives visibility so the leave transition
    actually plays (x-if alone doesn't animate) — RULES.md → Modals & Overlays.
--}}
<div x-data="{
        show: false,
        type: 'Monthly',
        amount: @js($summary['monthlyRent'] > 0 ? number_format($summary['monthlyRent'], 2, '.', '') : ''),
        period: @js($defaultPeriod ? $defaultPeriod['period']->toDateString() : ''),
        open() { this.show = true; },
        close() { this.show = false; },
     }"
     x-on:open-record-payment.window="open()"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            aria-modal="true" aria-labelledby="record-payment-title">

            {{-- Backdrop --}}
            <div x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="close()"
                class="absolute inset-0 bg-[#0F172A]/40 backdrop-blur-sm"></div>

            {{-- Panel --}}
            <div x-show="show"
                x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_20px_60px_rgba(15,23,42,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="record-payment-title" class="text-[17px] font-bold text-[#1F2937]">Record a payment</h2>
                        <p class="text-[12.5px] text-[#64748B] mt-0.5">
                            Money you have already received from
                            {{ trim(($reservation->tenant->first_name ?? '') . ' ' . ($reservation->tenant->last_name ?? '')) ?: 'this tenant' }}.
                        </p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('landlord.payments.store', $reservation) }}" class="px-6 pb-6">
                    @csrf

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="payment_type" class="{{ $modalLabel }}">
                                What for <span class="text-[#EF4444]">*</span>
                            </label>
                            <select id="payment_type" name="payment_type" x-model="type"
                                class="{{ $modalInput }} bg-white cursor-pointer">
                                <option value="Monthly">Monthly rent</option>
                                <option value="Deposit">Security deposit</option>
                                <option value="Initial">Initial payment</option>
                                <option value="Utility">Utilities</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="amount" class="{{ $modalLabel }}">
                                Amount (₱) <span class="text-[#EF4444]">*</span>
                            </label>
                            <input type="number" id="amount" name="amount" x-model="amount" min="1" max="1000000"
                                step="0.01" required placeholder="0.00" class="{{ $modalInput }}">
                        </div>
                    </div>

                    {{-- Only rent settles a billing month; anything else would
                         inflate a period it doesn't belong to. --}}
                    <div x-show="type === 'Monthly'" x-cloak class="mb-4">
                        <label for="billing_period" class="{{ $modalLabel }}">
                            Which month <span class="text-[#EF4444]">*</span>
                        </label>
                        <select id="billing_period" name="billing_period" x-model="period"
                            class="{{ $modalInput }} bg-white cursor-pointer">
                            @foreach($periodOptions as $option)
                                <option value="{{ $option['period']->toDateString() }}">
                                    {{ $option['label'] }}
                                    @if($option['status'] === 'overdue')
                                        — overdue, ₱{{ number_format(max(0, $option['balance']), 2) }} left
                                    @elseif($option['status'] === 'partial')
                                        — ₱{{ number_format(max(0, $option['balance']), 2) }} left
                                    @elseif($option['status'] === 'paid')
                                        — already settled
                                    @else
                                        — ₱{{ number_format(max(0, $option['balance']), 2) }} due
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="modal_payment_method" class="{{ $modalLabel }}">
                                Method <span class="text-[#EF4444]">*</span>
                            </label>
                            <select id="modal_payment_method" name="payment_method"
                                class="{{ $modalInput }} bg-white cursor-pointer">
                                @foreach(['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'] as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="paid_at" class="{{ $modalLabel }}">
                                Date received <span class="text-[#EF4444]">*</span>
                            </label>
                            <input type="date" id="paid_at" name="paid_at" value="{{ now()->toDateString() }}"
                                max="{{ now()->toDateString() }}" required class="{{ $modalInput }} cursor-pointer">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="modal_reference_no" class="{{ $modalLabel }}">
                            Reference no. <span class="text-[#64748B] font-normal">(optional)</span>
                        </label>
                        <input type="text" id="modal_reference_no" name="reference_no" maxlength="255"
                            placeholder="OR number, GCash reference…" class="{{ $modalInput }}">
                    </div>

                    <div class="mb-5">
                        <label for="payment_notes" class="{{ $modalLabel }}">
                            Notes <span class="text-[#64748B] font-normal">(optional)</span>
                        </label>
                        <textarea id="payment_notes" name="payment_notes" rows="2" maxlength="1000"
                            placeholder="Anything worth noting about this payment…"
                            class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-y"></textarea>
                    </div>

                    <div class="flex items-start gap-2.5 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] px-3.5 py-3 mb-5">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                            class="shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p class="text-[12px] text-[#64748B] leading-relaxed">
                            Recorded as received by you, not held in escrow by AbangananHub. Nothing in the app can reverse a
                            recorded payment, so check the amount before saving.
                        </p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" @click="close()"
                            class="h-11 px-5 rounded-full border border-[#E2E8F0] text-[#64748B] text-sm font-semibold hover:bg-[#F7FCFC] hover:text-[#1F2937] transition-all duration-200 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="h-11 px-6 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                            Record payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
