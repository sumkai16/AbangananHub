{{--
    Date-only picker for move-in / move-out fields.

    Replaces <input type="date">, which browsers render as an unstyleable
    native control. Sibling of <x-datetime-picker> (date+time, square cells,
    light panel) but date-only with circular cells on a dark navy popover —
    see DESIGN.md §6h for why a custom picker exists at all, and §3 for the
    dark-panel exception this component introduces.

    x-modelable="date" lets a caller bind straight to an existing Alpine
    variable with a plain x-model, so cross-field logic that already lives in
    the surrounding form (e.g. properties/show's onMoveInChange()/canSubmit)
    needs zero new wiring. minExpr/maxExpr/disabledExpr are raw Alpine
    expression strings evaluated via x-effect on this root element — since it
    shares the DOM scope with any ancestor x-data, they can read a sibling
    field's live value (e.g. min-expr="moveIn || minMoveIn") without the
    parent's x-data knowing this component exists.

    Behaviour lives in public/js/date-picker.js, loaded via a plain
    <script src> tag on each consuming page (see that file's header).

    @props
      name          form field name; posts as "Y-m-d"
      id            input id, defaults to name
      value         string|null   initial ISO date ('Y-m-d'), used when no x-model is supplied
      min / max     string|null   static ISO bounds
      minExpr / maxExpr / disabledExpr   string|null   raw Alpine expressions, reactive
      placeholder   trigger text when empty
--}}

@props([
    'name',
    'id' => null,
    'value' => null,
    'min' => null,
    'max' => null,
    'minExpr' => null,
    'maxExpr' => null,
    'disabledExpr' => null,
    'placeholder' => 'mm / dd / yyyy',
])

@php
    $inputId = $id ?? $name;
@endphp

<div
    x-data="datePicker({ date: @js($value), min: @js($min), max: @js($max) })"
    x-modelable="date"
    @if ($minExpr) x-effect="min = ({{ $minExpr }}) || null" @endif
    @if ($maxExpr) x-effect="max = ({{ $maxExpr }}) || null" @endif
    @if ($disabledExpr) x-effect="fieldDisabled = !!({{ $disabledExpr }})" @endif
    @click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    {{ $attributes->class(['relative']) }}
>
    <input type="hidden" name="{{ $name }}" :value="date">

    <button type="button" id="{{ $inputId }}" @click="toggle()" :disabled="fieldDisabled"
        :aria-expanded="open" aria-haspopup="dialog"
        class="w-full text-left rounded-xl bg-white border px-3.5 h-10 flex items-center transition-all focus:outline-none focus:border-[#2AA7A1]/60 focus:ring-4 focus:ring-[#2AA7A1]/10 disabled:cursor-not-allowed disabled:bg-[#F7FCFC] border-[#E2E8F0]">
        <span class="text-sm font-semibold" :class="date ? 'text-[#1F2937]' : 'text-[#94A3B8] font-normal'"
            x-text="date ? formatted : '{{ $placeholder }}'"></span>
    </button>

    <div x-show="open" x-cloak x-transition
        class="absolute z-30 mt-2 w-[300px] rounded-2xl bg-[#0F172A] ring-1 ring-white/10 shadow-2xl overflow-hidden">
        <div class="px-4 pt-4 pb-3">
            <div class="flex items-center justify-between mb-3">
                <button type="button" @click="shiftMonth(-1)" :disabled="!canGoBack"
                    class="w-7 h-7 rounded-lg flex items-center justify-center text-white/50 hover:bg-white/10 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                    <span class="sr-only">Previous month</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <p class="text-[13.5px] font-bold text-white" x-text="monthLabel" aria-live="polite"></p>
                <button type="button" @click="shiftMonth(1)" :disabled="!canGoForward"
                    class="w-7 h-7 rounded-lg flex items-center justify-center text-white/50 hover:bg-white/10 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                    <span class="sr-only">Next month</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-7" aria-hidden="true">
                @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dow)
                    <span class="h-7 flex items-center justify-center text-[11px] font-semibold text-white/40">{{ $dow }}</span>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-y-1">
                <template x-for="cell in grid" :key="cell.key">
                    <div class="flex items-center justify-center">
                        <template x-if="cell.iso">
                            <button type="button" @click="select(cell.iso)" :disabled="cell.disabled"
                                :aria-pressed="date === cell.iso" :aria-label="cell.label"
                                class="w-9 h-9 rounded-full text-[13px] font-semibold transition-colors disabled:opacity-25 disabled:cursor-not-allowed"
                                :class="date === cell.iso
                                    ? 'bg-[#2AA7A1] text-white'
                                    : (cell.isToday
                                        ? 'text-white ring-1 ring-[#2AA7A1] hover:bg-white/10'
                                        : 'text-white/85 hover:bg-white/10')">
                                <span x-text="cell.day"></span>
                            </button>
                        </template>

                        <template x-if="cell.adjacent">
                            <span class="w-9 h-9 flex items-center justify-center text-[13px] font-medium text-white/25"
                                x-text="cell.day" aria-hidden="true"></span>
                        </template>

                        <template x-if="cell.blank">
                            <span class="w-9 h-9 block"></span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div class="px-4 py-3 border-t border-white/10 flex items-center justify-between">
            <button type="button" @click="clear()" class="text-[12.5px] font-semibold text-white/50 hover:text-white transition-colors">
                Clear
            </button>
            <button type="button" @click="today()" class="text-[12.5px] font-bold text-[#2AA7A1] hover:brightness-110 transition-all">
                Today
            </button>
        </div>
    </div>
</div>
