{{--
    Date + time picker.

    Replaces <input type="datetime-local">, which browsers render as an
    unstyleable segmented control ("07 / 23 / 2026, --:-- --") that ignores the
    design system and, more importantly, can't show the one thing that decides
    whether a choice is a good one here: where the escalation deadline falls.
    The grid marks it, so the pair can see they're agreeing to a handover the
    escrow clock won't wait for.

    Alpine + Tailwind only — no picker library. DESIGN.md §3 rules out
    third-party CSS systems, and a runtime CDN dependency has already bitten
    this app once (face-api on the verification wizard).

    Behaviour lives in public/js/datetime-picker.js, NOT an @push('scripts')
    block: ConversationController returns the bare chat-panel partial on AJAX
    with no layout, so a pushed script would never run.

    The default slot renders inside this component's Alpine scope, so action
    buttons can bind to `value`, `date`, `time` and `label` directly.

    @props
      name      form field name; posts as "Y-m-d\TH:i"
      value     Carbon|null   currently chosen slot
      min       Carbon|null   earliest selectable day
      deadline  Carbon|null   marked on the grid; days after it are flagged
--}}

@props([
    'name',
    'value' => null,
    'min' => null,
    'deadline' => null,
])

<div x-data="datetimePicker({
        date: @js($value?->format('Y-m-d')),
        time: @js($value?->format('H:i')),
        min: @js(($min ?? now())->format('Y-m-d')),
        deadline: @js($deadline?->format('Y-m-d')),
    })">

    <input type="hidden" name="{{ $name }}" :value="value">

    <div class="px-5 sm:px-6 py-5">
        <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#64748B] mb-5">When will you meet?</p>

        <div class="grid lg:grid-cols-[300px_minmax(0,1fr)] gap-7">

            {{-- ── Month grid ─────────────────────────────── --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <button type="button" @click="shiftMonth(-1)" :disabled="!canGoBack"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#EEF8F8] hover:text-[#1F2937] disabled:opacity-25 disabled:cursor-not-allowed transition-colors">
                        <span class="sr-only">Previous month</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <p class="text-[15px] font-bold text-[#1F2937]" x-text="monthLabel" aria-live="polite"></p>
                    <button type="button" @click="shiftMonth(1)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[#64748B] hover:bg-[#EEF8F8] hover:text-[#1F2937] transition-colors">
                        <span class="sr-only">Next month</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-7" aria-hidden="true">
                    @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $i => $dow)
                        <span
                            class="h-8 flex items-center justify-center text-[12px] font-semibold text-[#94A3B8]">{{ $dow }}</span>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-y-1">
                    <template x-for="cell in grid" :key="cell.key">
                        <div class="flex flex-col items-center">
                            <template x-if="cell.iso">
                                <button type="button" @click="date = cell.iso" :disabled="cell.disabled"
                                    :aria-pressed="date === cell.iso"
                                    :aria-label="cell.label + (cell.beyondDeadline ? ' — after the review deadline' : '')"
                                    class="w-10 h-10 rounded-lg text-[14px] font-semibold transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    :class="date === cell.iso
                                        ? 'bg-[#2AA7A1] text-white'
                                        : (cell.beyondDeadline
                                            ? 'text-[#B45309] hover:bg-[#FBBF24]/[0.14]'
                                            : 'text-[#1F2937] hover:bg-[#EEF8F8]')">
                                    <span x-text="cell.day"></span>
                                </button>
                            </template>

                            {{-- Adjacent month: shown so the first week reads as
                                 dates rather than a gap, never selectable. --}}
                            <template x-if="cell.adjacent">
                                <span class="w-10 h-10 flex items-center justify-center text-[14px] font-medium text-[#CBD5E1]"
                                    x-text="cell.day" aria-hidden="true"></span>
                            </template>

                            <template x-if="cell.blank">
                                <span class="w-10 h-10 block"></span>
                            </template>

                            {{-- Markers sit under the cell so they never fight
                                 the selected fill for contrast. --}}
                            <span class="h-[2px] w-6 rounded-full mt-0.5"
                                :class="cell.isDeadline
                                    ? 'bg-[#EF4444]'
                                    : (cell.isToday ? 'bg-[#2AA7A1]' : 'bg-transparent')"
                                aria-hidden="true"></span>
                        </div>
                    </template>
                </div>

                <template x-if="deadlineIso">
                    <p class="mt-3 flex items-center gap-2 text-[11.5px] text-[#64748B]">
                        <span class="w-3.5 h-[2px] rounded-full bg-[#EF4444] shrink-0" aria-hidden="true"></span>
                        Review deadline
                    </p>
                </template>
            </div>

            {{-- ── Time ───────────────────────────────────── --}}
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#64748B] mb-3">Select a time slot</p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <template x-for="t in presets" :key="t.value">
                        <button type="button" @click="time = t.value" :aria-pressed="time === t.value"
                            class="h-12 rounded-xl text-[14px] font-semibold transition-colors"
                            :class="time === t.value
                                ? 'bg-[#2AA7A1] text-white border border-[#2AA7A1]'
                                : 'bg-white text-[#1F2937] border border-[#E2E8F0] hover:border-[#2AA7A1]'"
                            x-text="t.label"></button>
                    </template>
                </div>

                <p class="mt-6 text-[11px] font-bold uppercase tracking-[0.11em] text-[#64748B] mb-2">Other time</p>

                <div class="relative sm:max-w-[320px]">
                    <svg class="w-4 h-4 text-[#64748B] absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <label :for="$id('time')" class="sr-only">Choose another time</label>
                    <input type="time" :id="$id('time')" x-model="time" step="900"
                        class="w-full h-12 rounded-xl border border-[#E2E8F0] bg-white pl-10 pr-3 text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] outline-none">
                </div>

                <div class="mt-5 flex items-start gap-2" aria-live="polite">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" :class="value ? 'text-[#2AA7A1]' : 'text-[#94A3B8]'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <p class="text-[13px] italic">
                        <span x-show="value" class="text-[#1F2937]">Selected: <span x-text="longLabel"></span></span>
                        <span x-show="!value" class="text-[#64748B]">Pick a day and a time to continue</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if (isset($slot) && trim($slot) !== '')
        <div class="px-5 sm:px-6 py-4 bg-[#F7FCFC] border-t border-[#E2E8F0] flex flex-wrap items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
