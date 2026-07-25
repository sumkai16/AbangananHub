@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select',
    'autosubmit' => false,
    'required' => false,
    'disabled' => false,
    'panelClass' => '',
    'xModel' => null,
    'onSelect' => null,
])

{{--
    Native <select> can be styled up to the closed trigger, but the open
    options list is rendered by the OS and ignores CSS entirely — that's the
    "plain browser dropdown" look. This swaps in an Alpine-driven list so the
    open state matches the rest of the design system everywhere a dropdown
    appears. `options` is a plain value => label map.

    Two ways to wire the selected value up to a form:
    - `name` — posts the value under that name via a hidden input, same as a
      native select. Use for plain filter/sort/GET-driven dropdowns.
    - `x-model="someVar"` — for dropdowns that already live inside an Alpine
      form (x-model="unitType" etc. driving conditional fields). This
      component declares `x-modelable="value"`, so passing x-model binds our
      internal `value` two-way with the named variable in the parent scope;
      Alpine resolves which ancestor actually owns that variable, so this
      works no matter how deep the nesting is. `name` can still be set
      alongside `x-model` if the value also needs to post directly.

    `required` can't be enforced with HTML's `required` attribute because
    browsers exempt type="hidden" inputs from constraint validation — so this
    validates on the closest form's submit instead and flags the trigger
    red + opens the panel instead of silently letting an empty value through.

    `onSelect` is a raw JS statement (has `val` in scope) for dropdowns that
    navigate instead of posting — e.g. onSelect="window.location.href = ...".
    Takes priority over `autosubmit` if both are set.
--}}

@php
    $selectedLabel = $selected !== null && $selected !== '' && array_key_exists($selected, $options)
        ? $options[$selected]
        : $placeholder;
@endphp

<div
    x-data="{
        open: false,
        invalid: false,
        value: @js((string) ($selected ?? '')),
        options: @js($options),
        placeholder: @js($placeholder),
        get label() { return this.options[this.value] ?? this.placeholder; },
        select(val) {
            this.value = val;
            this.invalid = false;
            this.open = false;
            @if($onSelect)
                {{ $onSelect }};
            @elseif($autosubmit)
                this.$nextTick(() => this.$refs.hiddenInput ? this.$refs.hiddenInput.form.submit() : this.$refs.trigger.closest('form').submit());
            @endif
        },
    }"
    x-modelable="value"
    @if($xModel) x-model="{{ $xModel }}" @endif
    x-init="
        const form = $el.closest('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (@js((bool) $required) && !value) {
                    e.preventDefault();
                    invalid = true;
                    open = true;
                    $refs.trigger.focus();
                }
            });
        }
    "
    @keydown.escape.window="open = false"
    @click.outside="open = false"
    class="relative">

    @if($name)
        <input type="hidden" name="{{ $name }}" x-ref="hiddenInput" :value="value">
    @endif

    <button type="button" x-ref="trigger" @click="open = !open" :aria-expanded="open" @disabled($disabled)
        aria-haspopup="listbox"
        :class="invalid ? 'ring-2 ring-red-400 border-red-400' : ''"
        {{ $attributes->class(['flex items-center justify-between gap-2 transition-shadow', $disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer']) }}>
        <span x-text="label" class="truncate"></span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            class="flex-shrink-0 text-[#64748B] transition-transform duration-200" :class="open ? 'rotate-180' : ''"
            aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="listbox"
        class="absolute z-30 mt-2 min-w-full w-max max-w-[280px] max-h-[280px] overflow-y-auto bg-white rounded-xl border border-[#E2E8F0] shadow-[0_12px_32px_rgba(15,23,42,0.14)] py-1.5 {{ $panelClass }}"
        style="display: none;">
        <template x-for="[val, lbl] in Object.entries(options)" :key="val">
            <button type="button" @click="select(val)" role="option" :aria-selected="value === val"
                class="w-full flex items-center justify-between gap-3 text-left px-4 py-2.5 text-[13.5px] font-medium transition-colors cursor-pointer hover:bg-[#EEF8F8]"
                :class="value === val ? 'text-[#156F8C] font-semibold bg-[#EEF8F8]/70' : 'text-[#1F2937]'">
                <span x-text="lbl" class="truncate"></span>
                <svg x-show="value === val" width="14" height="14" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="3" class="flex-shrink-0 text-[#2AA7A1]" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </button>
        </template>
    </div>
</div>
