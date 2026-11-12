{{--
    The contract part of Add contract and of Add tenant: dates, rent and terms, signed agreement, move-in payment.
    It reads the Alpine state of whichever modal includes it: moveIn, moveOut, minMoveOut, rent, terms, leaseMode,
    recordPayment, method, paymentRequired and unit (the chosen unit: { rent, deposit, limit }). $idPrefix keeps the
    element ids unique when both modals are on one page. Posts to LeaseContractCreator's fields (StoreContractRequest).
--}}
@php
    $input = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[16px] sm:text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
    $label = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';
    $err = 'mt-1 text-[11.5px] font-semibold text-[#EF4444]';
    $section = 'text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]';
@endphp
<div class="grid gap-3">
    <p class="{{ $section }}">Dates</p>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="{{ $label }}" for="{{ $idPrefix }}_move_in">Move-in</label>
            <x-date-picker name="move_in_date" id="{{ $idPrefix }}_move_in" x-model="moveIn" :max="now()->addYear()->toDateString()" />
            @error('move_in_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" for="{{ $idPrefix }}_move_out">Move-out</label>
            <x-date-picker name="move_out_date" id="{{ $idPrefix }}_move_out" x-model="moveOut" min-expr="minMoveOut" />
            @error('move_out_date')<p class="{{ $err }}">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="grid gap-3">
    <p class="{{ $section }}">Terms</p>
    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="{{ $label }}" for="{{ $idPrefix }}_rent">Rent (₱)</label>
            <input id="{{ $idPrefix }}_rent" type="number" name="agreed_monthly_rent" x-model="rent" min="0" step="0.01" required class="{{ $input }}">
            @error('agreed_monthly_rent')<p class="{{ $err }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" for="{{ $idPrefix }}_due">Rent due day</label>
            <input id="{{ $idPrefix }}_due" type="number" name="rent_due_day" value="{{ old('rent_due_day') }}" min="1" max="28" placeholder="Auto" class="{{ $input }}">
            @error('rent_due_day')<p class="{{ $err }}">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" for="{{ $idPrefix }}_occupants">Occupants</label>
            <input id="{{ $idPrefix }}_occupants" type="number" name="occupants_count" value="{{ old('occupants_count', 1) }}" min="1" :max="unit?.limit ?? 20" :disabled="!unit && {{ $idPrefix === 'contract' ? 'true' : 'false' }}"
                x-effect="if (unit && unit.limit && Number($el.value) > Number(unit.limit)) $el.value = unit.limit"
                class="{{ $input }} disabled:bg-[#F7F8FC] disabled:text-[#94A3B8]">
            <p class="mt-1 text-[11.5px] text-[#5B6A8E]" x-show="unit && unit.limit" x-cloak>Max <span x-text="unit?.limit"></span> for this unit</p>
            <p class="mt-1 text-[11.5px] text-[#5B6A8E]" x-show="!unit">Set by the unit</p>
            @error('occupants_count')<p class="{{ $err }}">{{ $message }}</p>@enderror
        </div>
        <div class="col-span-3 -mt-1">
            <p class="text-[12px] text-[#5B6A8E]" x-show="unit">Deposit for this unit: <span class="font-semibold text-[#060D26]" x-text="unit && unit.deposit > 0 ? '₱' + Number(unit.deposit).toLocaleString() : 'None'"></span></p>
        </div>
    </div>
    <div>
        <label class="{{ $label }}" for="{{ $idPrefix }}_terms">Additional terms <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
        <textarea id="{{ $idPrefix }}_terms" name="agreement_terms_notes" x-model="terms" rows="2" maxlength="2000"
            class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-2.5 text-[13.5px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30"></textarea>
    </div>
</div>

<div class="grid gap-3">
    <p class="{{ $section }}">Signed agreement</p>
    <div class="flex gap-2">
        <label class="flex-1 flex items-center gap-2 rounded-xl border border-[#E2E4EC] px-3 py-2.5 text-[13px] cursor-pointer" :class="leaseMode === 'later' && 'border-[#FF8A66] bg-[#ECEEF6]'">
            <input type="radio" name="lease_mode" value="later" x-model="leaseMode" class="text-[#060D26] focus:ring-[#FF8A66]"> Upload later
        </label>
        <label class="flex-1 flex items-center gap-2 rounded-xl border border-[#E2E4EC] px-3 py-2.5 text-[13px] cursor-pointer" :class="leaseMode === 'now' && 'border-[#FF8A66] bg-[#ECEEF6]'">
            <input type="radio" name="lease_mode" value="now" x-model="leaseMode" class="text-[#060D26] focus:ring-[#FF8A66]"> Upload now
        </label>
    </div>
    <input x-show="leaseMode === 'now'" type="file" name="lease_file" accept=".pdf,.jpg,.jpeg,.png" class="text-[13px]">
    @error('lease_file')<p class="{{ $err }}">{{ $message }}</p>@enderror
</div>

<div class="grid gap-3">
    <div class="flex items-center justify-between gap-3">
        <p class="{{ $section }}">Move-in payment</p>
        <span x-show="paymentRequired" class="text-[11px] font-bold text-[#B45309]">Required: the move-in date has arrived</span>
        <label x-show="!paymentRequired" class="flex items-center gap-2 text-[12.5px] text-[#060D26] cursor-pointer">
            <input type="checkbox" x-model="recordPayment" class="rounded text-[#060D26] focus:ring-[#FF8A66]"> I already received money
        </label>
    </div>
    <template x-if="paymentRequired || recordPayment">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}" for="{{ $idPrefix }}_amount">Amount received (₱)</label>
                <input id="{{ $idPrefix }}_amount" type="number" name="initial_amount" value="{{ old('initial_amount') }}" min="1" step="0.01" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Method</label>
                <x-styled-select name="payment_method" :options="array_combine($methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Check', 'Other'], $methods)" x-model="method"
                    class="{{ $input }} text-left" />
            </div>
            <div x-show="method !== 'Cash'" class="col-span-2">
                <label class="{{ $label }}" for="{{ $idPrefix }}_ref">Reference no.</label>
                <input id="{{ $idPrefix }}_ref" type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="255" class="{{ $input }}">
            </div>
            <p class="col-span-2 text-[12px] text-[#5B6A8E]">Allocated to the deposit first, then rent from the move-in month.</p>
        </div>
    </template>
    @foreach(['initial_amount', 'payment_method', 'reference_no'] as $field)
        @error($field)<p class="{{ $err }}">{{ $message }}</p>@enderror
    @endforeach
</div>
