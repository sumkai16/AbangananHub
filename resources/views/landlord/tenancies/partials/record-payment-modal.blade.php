@php
    // Unsettled months first so the obvious choice is the top one; every month
    // stays selectable because a landlord may be recording a late top-up.
    $periodOptions = $periods->reverse()->values();
    // Fall back to the last month that has actually arrived, never $periods->last():
    // once a tenancy is prepaid the ledger runs into future months, and defaulting
    // to the furthest of those would pre-select a month already settled and invite
    // a duplicate payment against it.
    $defaultPeriod = $summary['oldestOverdue']
        ?? $summary['nextDue']
        ?? $periods->reject(fn ($p) => $p['is_future'])->last()
        ?? $periods->last();

    $modalInput = 'h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition';
    $modalLabel = 'block text-[12px] font-semibold text-[#1F2937] mb-1.5';

    $paymentTypeOptions = [
        'Monthly' => 'Monthly rent',
        'Deposit' => 'Security deposit',
        'Initial' => 'Initial payment',
        'Utility' => 'Utilities',
        'Other' => 'Other',
    ];

    $billingPeriodOptions = $periodOptions->mapWithKeys(function ($option) {
        // A future month is in this list only because rent was paid into it, so
        // its balance is not a debt and must not be worded like one.
        $suffix = $option['is_future']
            ? ($option['status'] === 'paid'
                ? ' — paid in advance'
                : ' — ₱' . number_format(max(0, $option['balance']), 2) . ' of this month still open')
            : match ($option['status']) {
                'overdue' => ' — overdue, ₱' . number_format(max(0, $option['balance']), 2) . ' left',
                'partial' => ' — ₱' . number_format(max(0, $option['balance']), 2) . ' left',
                'paid' => ' — already settled',
                default => ' — ₱' . number_format(max(0, $option['balance']), 2) . ' due',
            };
        return [$option['period']->toDateString() => $option['label'] . $suffix];
    })->all();

    $paymentMethodOptions = array_combine(
        ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'],
        ['Cash', 'GCash', 'Bank Transfer', 'Maya', 'Check', 'Other'],
    );
@endphp

{{--
    Two flags, not one: `show` drives visibility so the leave transition
    actually plays (x-if alone doesn't animate) — RULES.md → Modals & Overlays.
--}}
@php
    $recordPaymentFields = ['payment_type', 'amount', 'billing_period', 'payment_method', 'paid_at', 'reference_no', 'payment_notes'];
    // Flashed by Landlord\PaymentController::void() when the landlord ticked
    // "record a corrected payment straight after" — opens this modal
    // pre-filled with the voided entry's own figures instead of the
    // tenancy's usual defaults.
    $correction = session('correct_payment');
@endphp
{{-- A failed submit redirects back with errors, but `show` is client-only Alpine
     state defaulting to false — without this, the modal would re-render closed
     and the error would be completely invisible, not just non-inline. --}}
<div x-data="{
        show: {{ $errors->hasAny($recordPaymentFields) || $correction ? 'true' : 'false' }},
        type: @js($correction['payment_type'] ?? 'Monthly'),
        amount: @js($correction ? number_format($correction['amount'], 2, '.', '') : ($summary['monthlyRent'] > 0 ? number_format($summary['monthlyRent'], 2, '.', '') : '')),
        period: @js($correction['billing_period'] ?? ($defaultPeriod ? $defaultPeriod['period']->toDateString() : '')),
        // Unpaid months only, oldest first — mirrors RentPaymentAllocator's
        // own input so the preview below matches what the server will
        // actually write. Paid and future months are never fill targets.
        unsettledPeriods: @js($periods->reject(fn ($p) => $p['is_future'] || $p['status'] === 'paid')
            ->map(fn ($p) => ['period' => $p['period']->toDateString(), 'balance' => round($p['balance'], 2)])
            ->values()),
        monthlyRent: @js($summary['monthlyRent']),
        open() { this.show = true; },
        close() { this.show = false; },
        round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; },
        /*
         | Preview only — display, never the source of truth. The server
         | (App\Support\RentPaymentAllocator) allocates again from the posted
         | amount and its own figures once this is submitted. This mirrors
         | that class's logic exactly so the preview never disagrees with
         | what actually gets written.
         */
        get splitPreview() {
            if (this.type !== 'Monthly' || !this.period) return [];
            const amt = parseFloat(this.amount);
            if (isNaN(amt) || amt <= 0) return [];

            const found = this.unsettledPeriods.find(p => p.period === this.period);
            const startBalance = this.round2(Math.max(0, found ? found.balance : this.monthlyRent));

            let remaining = this.round2(amt);
            const rows = [];
            let [y, m] = this.period.split('-').map(Number);

            for (let i = 0; i < 60 && remaining > 0; i++) {
                const due = i === 0 ? startBalance : this.monthlyRent;
                const slice = this.round2(Math.min(remaining, due));
                if (slice > 0) {
                    rows.push({ label: this.monthLabelFor(y, m), amount: slice });
                    remaining = this.round2(remaining - slice);
                }
                m++;
                if (m > 12) { m = 1; y++; }
            }
            if (remaining > 0 && rows.length > 0) {
                rows[rows.length - 1].amount = this.round2(rows[rows.length - 1].amount + remaining);
            }
            return rows;
        },
        monthLabelFor(y, m) {
            return new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
        },
        get splitsMultipleMonths() { return this.splitPreview.length > 1; },
        peso(value) {
            return '₱' + (value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
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
                    {{-- Present only on the correction flow. old() keeps the link
                         alive across a failed re-submit, when the flashed session
                         value is already gone. --}}
                    <input type="hidden" name="replaces_payment_id"
                        value="{{ old('replaces_payment_id', $correction['payment_id'] ?? '') }}">

                    @if($correction)
                        <div class="flex items-start gap-2.5 rounded-xl bg-[#EEF8F8] border border-[#2AA7A1]/25 px-3.5 py-3 mb-4">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2"
                                class="shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                            </svg>
                            <p class="text-[12px] text-[#156F8C] leading-relaxed">
                                Recording a correction for the ₱{{ number_format($correction['amount'], 2) }} entry you just voided.
                            </p>
                        </div>
                    @endif

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="payment_type" class="{{ $modalLabel }}">
                                What for <span class="text-[#EF4444]">*</span>
                            </label>
                            <x-styled-select name="payment_type" x-model="type"
                                :options="$paymentTypeOptions" selected="Monthly"
                                class="{{ $modalInput }} bg-white" />
                            @error('payment_type')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="amount" class="{{ $modalLabel }}">
                                Amount (₱) <span class="text-[#EF4444]">*</span>
                            </label>
                            <input type="number" id="amount" name="amount" x-model="amount" min="1" max="1000000"
                                step="0.01" required placeholder="0.00" class="{{ $modalInput }}">
                            @error('amount')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Only rent settles a billing month; anything else would
                         inflate a period it doesn't belong to. --}}
                    <div x-show="type === 'Monthly'" x-cloak class="mb-4">
                        <label for="billing_period" class="{{ $modalLabel }}">
                            Which month <span class="text-[#EF4444]">*</span>
                        </label>
                        <x-styled-select name="billing_period" x-model="period"
                            :options="$billingPeriodOptions" :selected="$defaultPeriod ? $defaultPeriod['period']->toDateString() : ''"
                            class="{{ $modalInput }} bg-white" panel-class="max-w-[360px]" />
                        @error('billing_period')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Only appears once the amount reaches past one month —
                         an ordinary single-month payment never shows this. --}}
                    <div x-show="type === 'Monthly' && splitsMultipleMonths" x-cloak
                        class="mb-4 rounded-xl border border-[#2AA7A1]/25 bg-[#EEF8F8] px-3.5 py-3">
                        <p class="text-[12px] font-semibold text-[#156F8C] mb-2">
                            This covers <span x-text="splitPreview.length"></span> billing months:
                        </p>
                        <ul class="space-y-1">
                            <template x-for="(row, idx) in splitPreview" :key="idx">
                                <li class="flex items-center justify-between text-[12.5px] text-[#1F2937]">
                                    <span x-text="row.label"></span>
                                    <span class="font-semibold" x-text="peso(row.amount)"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="modal_payment_method" class="{{ $modalLabel }}">
                                Method <span class="text-[#EF4444]">*</span>
                            </label>
                            <x-styled-select name="payment_method"
                                :options="$paymentMethodOptions" selected="Cash"
                                class="{{ $modalInput }} bg-white" />
                            @error('payment_method')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="paid_at" class="{{ $modalLabel }}">
                                Date received <span class="text-[#EF4444]">*</span>
                            </label>
                            <input type="date" id="paid_at" name="paid_at" value="{{ now()->toDateString() }}"
                                max="{{ now()->toDateString() }}" required class="{{ $modalInput }} cursor-pointer">
                            @error('paid_at')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="modal_reference_no" class="{{ $modalLabel }}">
                            Reference no. <span class="text-[#64748B] font-normal">(optional)</span>
                        </label>
                        <input type="text" id="modal_reference_no" name="reference_no" maxlength="255"
                            placeholder="OR number, GCash reference…" class="{{ $modalInput }}">
                        @error('reference_no')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label for="payment_notes" class="{{ $modalLabel }}">
                            Notes <span class="text-[#64748B] font-normal">(optional)</span>
                        </label>
                        <textarea id="payment_notes" name="payment_notes" rows="2" maxlength="1000"
                            placeholder="Anything worth noting about this payment…"
                            class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-y">{{ old('payment_notes') }}</textarea>
                        @error('payment_notes')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-start gap-2.5 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] px-3.5 py-3 mb-5">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="2"
                            class="shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p class="text-[12px] text-[#64748B] leading-relaxed">
                            Recorded as received by you, not held in escrow by AbangananHub. If you enter something wrong,
                            you can void it afterwards from this ledger — the original entry stays on record.
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
