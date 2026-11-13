{{--
    How tenants reserve this unit. Needs $unit (null on the create page). Fields: payment_arrangement,
    reservation_fee_amount, payment_deadline_days, reservation_conditions. See Reservation::paymentArrangement().
--}}
@php
    $arrangement = old('payment_arrangement', $unit->payment_arrangement ?? 'advance_deposit');
    $options = [
        'reservation_fee' => ['Reservation fee', 'A small fee holds the unit. The rest is due before move-in.'],
        'advance_deposit' => ['Advance + deposit', 'Agreed advance rent and the security deposit.'],
        'full_payment' => ['Full payment', 'Everything due up front. Same amount as advance + deposit.'],
    ];
    $field = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
@endphp
<div x-data="{ arrangement: @js($arrangement) }" class="space-y-3 rounded-xl border border-[#E2E4EC] p-4">
    <div>
        <p class="block text-[12px] font-semibold text-[#060D26]">Reservation payment</p>
        <p class="mt-0.5 text-[11.5px] text-[#5B6A8E]">What a tenant pays to reserve this unit. Full payment is never required just to hold it.</p>
    </div>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        @foreach($options as $value => [$label, $desc])
            <label class="cursor-pointer rounded-xl border p-3 transition"
                :class="arrangement === '{{ $value }}' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]'">
                <input type="radio" name="payment_arrangement" value="{{ $value }}" x-model="arrangement" class="sr-only">
                <span class="block text-[13px] font-semibold text-[#060D26]">{{ $label }}</span>
                <span class="mt-0.5 block text-[11.5px] text-[#5B6A8E]">{{ $desc }}</span>
            </label>
        @endforeach
    </div>
    @error('payment_arrangement')<p class="text-[11.5px] text-[#EF4444]">{{ $message }}</p>@enderror

    <div x-show="arrangement === 'reservation_fee'" x-cloak>
        <label for="reservation_fee_amount" class="mb-1.5 block text-[12px] font-semibold text-[#060D26]">Reservation fee (₱)</label>
        <input type="number" id="reservation_fee_amount" name="reservation_fee_amount" min="1" max="999999.99" step="0.01"
            :required="arrangement === 'reservation_fee'" value="{{ old('reservation_fee_amount', $unit->reservation_fee_amount ?? '') }}"
            placeholder="e.g. 1000" class="{{ $field }}">
        @error('reservation_fee_amount')<p class="mt-1 text-[11.5px] text-[#EF4444]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="payment_deadline_days" class="mb-1.5 block text-[12px] font-semibold text-[#060D26]">Payment deadline (days) <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
        <input type="number" id="payment_deadline_days" name="payment_deadline_days" min="1" max="7" step="1"
            value="{{ old('payment_deadline_days', $unit->payment_deadline_days ?? '') }}" placeholder="Platform default" class="{{ $field }}">
        <p class="mt-1 text-[11.5px] text-[#5B6A8E]">Days a tenant has to sign and pay before the reservation is released. 1 to 7. Blank uses the platform default.</p>
        @error('payment_deadline_days')<p class="mt-1 text-[11.5px] text-[#EF4444]">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="reservation_conditions" class="mb-1.5 block text-[12px] font-semibold text-[#060D26]">Reservation conditions <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
        <textarea id="reservation_conditions" name="reservation_conditions" rows="2" maxlength="500"
            placeholder="e.g. Fee is non-transferable. Balance due 3 days before move-in."
            class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-2.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition">{{ old('reservation_conditions', $unit->reservation_conditions ?? '') }}</textarea>
        @error('reservation_conditions')<p class="mt-1 text-[11.5px] text-[#EF4444]">{{ $message }}</p>@enderror
    </div>
</div>
