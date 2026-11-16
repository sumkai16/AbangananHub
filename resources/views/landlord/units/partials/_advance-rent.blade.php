{{--
    "Require advance rent" with a 1 or 2 month choice. Lives inside the unit form's Alpine scope, which must hold
    `rentalFee` and `advanceMonths` (0 = none, 1, 2). Posts `advance_months`. Reservation::agreementDefaults() starts
    every agreement from this value; the landlord can still change it per tenant. See plans/unit-advance-rent-option.md.
--}}
<div class="rounded-xl border p-4 space-y-3 transition"
    :class="advanceMonths > 0 ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white'">
    <input type="hidden" name="advance_months" :value="advanceMonths">

    <label for="advance_required" class="flex items-start gap-2.5 cursor-pointer">
        <input type="checkbox" id="advance_required" class="mt-0.5 h-[18px] w-[18px] shrink-0 rounded border-[#5B6A8E]/40 text-[#FF8A66] focus:ring-[#FF8A66]"
            :checked="advanceMonths > 0" x-on:change="advanceMonths = $event.target.checked ? 1 : 0">
        <span>
            <span class="block text-[13.5px] font-bold text-[#060D26]">Require advance rent</span>
            <span class="block text-[12px] text-[#5B6A8E]">Tenants pay rent for these months up front, at move-in.</span>
        </span>
    </label>

    <div x-show="advanceMonths > 0" x-cloak class="grid grid-cols-2 gap-2 max-w-[360px]" role="radiogroup" aria-label="Advance rent months">
        @foreach ([1 => ['1 month', 'Most common'], 2 => ['2 months', 'Higher upfront cost']] as $n => [$label, $hint])
            <label class="cursor-pointer rounded-xl border p-3 transition"
                :class="advanceMonths === {{ $n }} ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6]'">
                <input type="radio" class="sr-only" name="advance_choice" value="{{ $n }}" :checked="advanceMonths === {{ $n }}" x-on:change="advanceMonths = {{ $n }}">
                <span class="block text-[13px] font-semibold text-[#060D26]">{{ $label }}</span>
                <span class="block text-[11.5px] text-[#5B6A8E]">{{ $hint }}</span>
            </label>
        @endforeach
    </div>
    @error('advance_months')<p class="text-[11.5px] text-[#EF4444]">{{ $message }}</p>@enderror

    @include('landlord.partials._rent-control-note', ['rent' => 'rentalFee', 'deposit' => '0', 'advance' => 'advanceMonths'])
</div>
