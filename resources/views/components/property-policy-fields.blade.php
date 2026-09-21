@props([
    // Pre-fill from PropertyPolicies::formValues(); old() input wins after a failed submit.
    'values' => [],
])

{{--
    "Who is this for, and what are the rules" — the three property-level answers
    (living arrangement, occupancy preference, house rules). They live on the
    property, not on each unit, so every unit inherits them and the tenant reads
    one answer. Used by the add-property wizard and the property edit page.
--}}
@php
    $living = old('living_arrangement', $values['living_arrangement'] ?? '');
    $occupancy = old('occupancy_preference', $values['occupancy_preference'] ?? 'No Preference');
    // old() can't tell "ticked nothing" from "no old input", so only trust it after a real failed submit.
    $ticked = session()->hasOldInput() ? (array) old('house_rules', []) : ($values['house_rules'] ?? []);
    $customText = session()->hasOldInput() ? (string) old('custom_house_rules', '') : ($values['custom_house_rules'] ?? '');

    $cardBase = 'block h-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-3 transition-colors duration-200 hover:border-[#060D26]/40 '
        . 'peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66]/[0.06] '
        . 'peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66] peer-focus-visible:ring-offset-2';
    $chipBase = 'flex items-center gap-2.5 rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 transition-colors duration-200 hover:border-[#060D26]/40 '
        . 'peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66]/[0.06] peer-checked:[&_.box]:bg-[#FF8A66] peer-checked:[&_.box]:border-[#FF8A66] peer-checked:[&_.tick]:opacity-100 '
        . 'peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66] peer-focus-visible:ring-offset-2';
@endphp

<x-card class="space-y-6 !p-4 sm:!p-5" x-data="{ customOpen: {{ filled($customText) ? 'true' : 'false' }} }">

    {{-- Living arrangement --}}
    <fieldset>
        <legend class="text-[13px] font-semibold text-[#060D26]">Living arrangement</legend>
        <p class="text-[12px] text-[#5B6A8E] mt-0.5 mb-2.5">How tenants live in this property.</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            @foreach(\App\Support\PropertyPolicies::LIVING_ARRANGEMENTS as $value => $hint)
                <label class="cursor-pointer">
                    <input type="radio" name="living_arrangement" value="{{ $value }}" class="peer sr-only" required @checked($living === $value)>
                    <span class="{{ $cardBase }}">
                        <span class="block text-[14px] font-semibold text-[#060D26]">{{ $value }}</span>
                        <span class="block mt-0.5 text-[12px] leading-snug text-[#5B6A8E]">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('living_arrangement')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
    </fieldset>

    {{-- Occupancy preference --}}
    <fieldset>
        <legend class="text-[13px] font-semibold text-[#060D26]">Occupancy preference</legend>
        <p class="text-[12px] text-[#5B6A8E] mt-0.5 mb-2.5">Who you'd like to rent to.</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            @foreach(\App\Support\PropertyPolicies::OCCUPANCY_PREFERENCES as $value => $hint)
                <label class="cursor-pointer">
                    <input type="radio" name="occupancy_preference" value="{{ $value }}" class="peer sr-only" required @checked($occupancy === $value)>
                    <span class="{{ $cardBase }}">
                        <span class="block text-[14px] font-semibold text-[#060D26]">{{ $value }}</span>
                        <span class="block mt-0.5 text-[12px] leading-snug text-[#5B6A8E]">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('occupancy_preference')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
    </fieldset>

    {{-- House rules --}}
    <fieldset>
        <legend class="text-[13px] font-semibold text-[#060D26]">House rules</legend>
        <p class="text-[12px] text-[#5B6A8E] mt-0.5 mb-2.5">Tap every rule that applies. They apply to all units in this property.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach(\App\Support\PropertyPolicies::HOUSE_RULES as $rule)
                <label class="cursor-pointer">
                    <input type="checkbox" name="house_rules[]" value="{{ $rule }}" class="peer sr-only" @checked(in_array($rule, $ticked, true))>
                    <span class="{{ $chipBase }}">
                        <span class="box flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-[#5B6A8E]/40 bg-white transition-colors duration-200">
                            <svg class="tick opacity-0 transition-opacity duration-200 text-[#060D26]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <span class="text-[13px] font-semibold text-[#060D26] leading-tight">{{ $rule }}</span>
                    </span>
                </label>
            @endforeach

            {{-- Custom rules: not a stored value, just reveals the text box --}}
            <label class="cursor-pointer">
                <input type="checkbox" x-model="customOpen" class="peer sr-only" aria-controls="custom_house_rules">
                <span class="{{ $chipBase }}">
                    <span class="box flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-[#5B6A8E]/40 bg-white transition-colors duration-200">
                        <svg class="tick opacity-0 transition-opacity duration-200 text-[#060D26]" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    <span class="text-[13px] font-semibold text-[#060D26] leading-tight">Custom Rules</span>
                </span>
            </label>
        </div>
        @error('house_rules')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
        @error('house_rules.*')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror

        <div x-show="customOpen" x-cloak class="mt-3">
            <label for="custom_house_rules" class="block text-[12px] font-semibold text-[#060D26] mb-1.5">Your own rules <span class="font-normal text-[#5B6A8E]">(one per line)</span></label>
            {{-- Disabled while closed so an unticked box doesn't submit stale text --}}
            <textarea id="custom_house_rules" name="custom_house_rules" rows="3" :disabled="!customOpen"
                maxlength="{{ \App\Support\PropertyPolicies::MAX_CUSTOM_RULES * (\App\Support\PropertyPolicies::MAX_CUSTOM_RULE_LENGTH + 1) }}"
                placeholder="e.g. No cooking after 10 PM"
                class="w-full rounded-xl border border-[#E2E4EC] p-3.5 text-[14px] leading-relaxed text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-colors duration-200">{{ $customText }}</textarea>
            <p class="text-[11.5px] text-[#5B6A8E] mt-1.5">Up to {{ \App\Support\PropertyPolicies::MAX_CUSTOM_RULES }} rules, {{ \App\Support\PropertyPolicies::MAX_CUSTOM_RULE_LENGTH }} characters each.</p>
            @error('custom_house_rules')<p class="text-xs text-[#DC2626] mt-1.5">{{ $message }}</p>@enderror
        </div>
    </fieldset>
</x-card>
