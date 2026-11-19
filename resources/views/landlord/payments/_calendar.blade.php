{{-- Payment calendar that zooms like a map: all years -> one year -> one month (the day grid).
     Controller builds one payload for every year (Landlord\PaymentController::buildCalendar) and
     public/js/payments-calendar.js switches between the levels in the browser, so a click never waits on the server.
     Default is the current month. Class strings that the script returns (status dots, chips) are passed in below,
     because Tailwind only scans these views, not public/js. --}}
@php
    $chipTone = [
        'overdue'    => 'bg-[#EF4444]/[0.08] text-[#DC2626]',
        'partial'    => 'bg-[#FBBF24]/[0.14] text-[#B45309]',
        'upcoming'   => 'bg-[#ECEEF6] text-[#060D26]',
        'paid'       => 'bg-[#22C55E]/[0.09] text-[#15803D]',
        'paid_ahead' => 'bg-[#ECEEF6] text-[#060D26]',
    ];
    // Previous / next, shown in the title row of the year and month levels (a month at a time, or a year at a time).
    $arrows = <<<'HTML'
<div class="inline-flex shrink-0 items-center rounded-xl border border-[#5B6A8E]/25 overflow-hidden divide-x divide-[#5B6A8E]/25">
    <button type="button" @click="step(-1)" :aria-label="level === 'years' ? 'Previous ' + size + ' years' : level === 'year' ? 'Previous year' : 'Previous month'" class="h-11 w-11 sm:h-10 sm:w-10 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] focus:outline-none focus-visible:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
    </button>
    <button type="button" @click="step(1)" :aria-label="level === 'years' ? 'Next ' + size + ' years' : level === 'year' ? 'Next year' : 'Next month'" class="h-11 w-11 sm:h-10 sm:w-10 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] focus:outline-none focus-visible:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
    </button>
</div>
HTML;
    $chevron = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>';
@endphp

<script src="{{ asset('js/payments-calendar.js') }}?v={{ @filemtime(public_path('js/payments-calendar.js')) }}"></script>
<script type="application/json" id="payments-calendar-data">@json($calendar)</script>

<div x-data="paymentsCalendar({ source: 'payments-calendar-data', styles: @js($paymentStyles), chips: @js($chipTone), noDot: 'bg-[#E2E4EC]' })" x-cloak
    @keydown.escape.window="closeDay()" @popstate.window="onPopState()" class="space-y-3">
    <x-card flush class="lg:flex lg:flex-col lg:min-h-[calc(100vh-var(--cal-top,0px))]">

        {{-- Where you are, and the way back up. Each crumb is a zoom level. --}}
        <div class="flex items-center justify-between gap-x-3 px-4 sm:px-6 py-2.5 border-b border-[#E2E4EC]">
            <nav aria-label="Calendar zoom" class="flex min-w-0 flex-wrap items-center text-[14px]">
                <button type="button" @click="zoomTo('years')" :aria-current="level === 'years' ? 'location' : null"
                    :class="level === 'years' ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                    class="inline-flex h-11 sm:h-10 items-center rounded-lg px-1.5 -ml-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer">All years</button>
                <template x-if="level !== 'years'">
                    <span class="inline-flex items-center text-[#94A3B8]">{!! $chevron !!}</span>
                </template>
                <template x-if="level !== 'years'">
                    <button type="button" @click="zoomTo('year')" :aria-current="level === 'year' ? 'location' : null"
                        :class="level === 'year' ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="inline-flex h-11 sm:h-10 items-center rounded-lg px-1.5 tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer" x-text="year"></button>
                </template>
                <template x-if="level === 'month'">
                    <span class="inline-flex items-center text-[#94A3B8]">{!! $chevron !!}</span>
                </template>
                <template x-if="level === 'month'">
                    <span aria-current="location" class="inline-flex h-11 sm:h-10 items-center px-1.5 font-semibold text-[#060D26]" x-text="monthName(mon)"></span>
                </template>
            </nav>

            <button type="button" @click="goToday()" :aria-current="isCurrentView ? 'date' : null"
                :class="isCurrentView ? 'bg-[#F7F8FC] text-[#5B6A8E]' : 'text-[#060D26] hover:bg-[#ECEEF6]'"
                class="h-11 sm:h-10 shrink-0 px-3 sm:px-4 rounded-xl border border-[#5B6A8E]/25 text-[13px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer">Today</button>
        </div>
        <p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>

        {{-- The three levels sit in one grid cell, so the zoom can cross-fade them. Only one is visible at rest. --}}
        <div x-ref="stage" class="relative grid grid-cols-[minmax(0,1fr)] overflow-hidden lg:flex-1">

            {{-- ───────── Level 1: every year ───────── --}}
            <section x-ref="pYears" x-show="vis.years" aria-labelledby="cal-h-years" class="col-start-1 row-start-1 p-4 sm:p-6 lg:flex lg:flex-col lg:py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 lg:flex lg:flex-wrap lg:items-center lg:gap-x-5">
                <h2 id="cal-h-years" x-ref="hYears" tabindex="-1" class="text-[18px] sm:text-[20px] font-semibold tabular-nums text-[#060D26] focus:outline-none" x-text="windowRange"></h2>
                <template x-if="sumFor('years').expected > 0">
                    <div class="lg:flex lg:items-center lg:gap-3">
                        <p class="mt-0.5 lg:mt-0 text-[12.5px] text-[#5B6A8E] tabular-nums">
                            <span class="font-semibold text-[#060D26]" x-text="peso(sumFor('years').collected, 2)"></span> collected of <span x-text="peso(sumFor('years').expected, 2)"></span> due
                        </p>
                        <div class="mt-2 lg:mt-0 h-1.5 w-56 lg:w-40 max-w-full rounded-full bg-[#E2E4EC] overflow-hidden" role="progressbar" aria-label="Rent collected in these years"
                            aria-valuemin="0" aria-valuemax="100" :aria-valuenow="sumFor('years').pct">
                            <div class="h-full rounded-full bg-[#22C55E]" :style="'width:' + sumFor('years').pct + '%'"></div>
                        </div>
                    </div>
                </template>
                <template x-if="sumFor('years').expected <= 0">
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">No rent falls due in these years.</p>
                </template>
                    </div>
                    {!! $arrows !!}
                </div>

                <div class="mt-4 lg:mt-3 grid grid-cols-1 gap-3 sm:gap-4 lg:flex-1 lg:grid-cols-3 lg:auto-rows-fr xl:grid-cols-4">
                    <template x-for="y in windowYears" :key="y">
                        <article :data-tile-year="y"
                            :class="y === todayYear ? 'border-[#FF8A66] ring-1 ring-[#FF8A66]/40' : 'border-[#E2E4EC]'"
                            class="overflow-hidden rounded-2xl border bg-white lg:flex lg:flex-col">
                            <button type="button" @click="zoomIn('year', { year: y }, $el)" :aria-label="yearLabel(y)"
                                class="block w-full px-4 pt-3.5 pb-3 lg:pt-2.5 lg:pb-2 text-left hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC] focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#FF8A66] transition-colors duration-150 cursor-pointer">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="text-[22px] font-semibold leading-none tabular-nums text-[#060D26]" x-text="y"></span>
                                    <span class="text-[12px] tabular-nums text-[#5B6A8E]" x-text="brief(yearAgg(y))"></span>
                                </span>
                                <span class="mt-2.5 block h-1.5 rounded-full bg-[#E2E4EC] overflow-hidden">
                                    <span class="block h-full rounded-full bg-[#22C55E]" :style="'width:' + pct(yearAgg(y)) + '%'"></span>
                                </span>
                            </button>
                            <div class="grid grid-cols-4 gap-1.5 px-3 pb-3 lg:px-4 lg:pb-3 lg:flex-1 lg:auto-rows-fr lg:min-h-[168px] lg:max-h-[276px]">
                                <template x-for="m in 12" :key="m">
                                    <button type="button" :data-mini="y + '-' + m" @click="zoomIn('month', { year: y, mon: m }, $el)" :aria-label="tileLabel(y, m)"
                                        :class="isNow(y, m) ? 'bg-[#FFF7F4] ring-1 ring-inset ring-[#FF8A66]' : 'bg-[#F7F8FC] hover:bg-[#ECEEF6]'"
                                        class="flex min-h-[52px] lg:min-h-0 flex-col items-center justify-center gap-1 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-150 cursor-pointer">
                                        <span class="text-[10.5px] lg:text-[12px] font-bold uppercase leading-none tracking-wide text-[#5B6A8E]" x-text="abbr(m)"></span>
                                        <span class="h-2 w-2 rounded-full" :class="dotClass(y, m)" aria-hidden="true"></span>
                                        <span x-show="mAgg(y, m).due > 0" class="text-[11px] font-semibold leading-none tabular-nums text-[#060D26]" x-text="compact(mAgg(y, m).expected)"></span>
                                    </button>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>
            </section>

            {{-- ───────── Level 2: one year, twelve months ───────── --}}
            <section x-ref="pYear" x-show="vis.year" aria-labelledby="cal-h-year" class="col-start-1 row-start-1 p-4 sm:p-6 lg:flex lg:flex-col lg:py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                <h2 id="cal-h-year" x-ref="hYear" tabindex="-1" class="text-[18px] sm:text-[20px] font-semibold tabular-nums text-[#060D26] focus:outline-none" x-text="year"></h2>
                <template x-if="sumFor('year').expected > 0">
                    <div>
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] tabular-nums">
                            <span class="font-semibold text-[#060D26]" x-text="peso(sumFor('year').collected, 2)"></span> collected of <span x-text="peso(sumFor('year').expected, 2)"></span> due
                        </p>
                        <div class="mt-2 h-1.5 w-56 max-w-full rounded-full bg-[#E2E4EC] overflow-hidden" role="progressbar" aria-label="Rent collected this year"
                            aria-valuemin="0" aria-valuemax="100" :aria-valuenow="sumFor('year').pct">
                            <div class="h-full rounded-full bg-[#22C55E]" :style="'width:' + sumFor('year').pct + '%'"></div>
                        </div>
                    </div>
                </template>
                <template x-if="sumFor('year').expected <= 0">
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">No rent falls due this year.</p>
                </template>
                    </div>
                    {!! $arrows !!}
                </div>

                <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-2.5 sm:gap-3 lg:flex-1 lg:auto-rows-fr lg:gap-3">
                    <template x-for="m in 12" :key="m">
                        <button type="button" :data-tile-month="m" @click="zoomIn('month', { mon: m }, $el)" :aria-label="tileLabel(year, m)"
                            :class="isNow(year, m) ? 'border-[#FF8A66] bg-[#FFF7F4] ring-1 ring-[#FF8A66]/40' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                            class="flex min-h-[104px] flex-col justify-between gap-2 rounded-xl border p-2.5 sm:p-3 lg:min-h-0 lg:p-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-150 cursor-pointer">
                            <span class="flex items-center justify-between gap-1">
                                <span class="text-[13px] sm:text-[14px] lg:text-[15px] font-semibold text-[#060D26]">
                                    <span class="sm:hidden" x-text="abbr(m)"></span><span class="hidden sm:inline" x-text="monthName(m)"></span>
                                </span>
                                <span x-show="isNow(year, m)" class="text-[10px] font-bold uppercase tracking-wide text-[#B35A3D]">Now</span>
                            </span>
                            <template x-if="mAgg(year, m).due > 0">
                                <span class="block">
                                    <span class="flex flex-wrap items-baseline gap-x-1.5 leading-tight tabular-nums">
                                        <span class="text-[12px] sm:text-[13px] lg:text-[16px] font-semibold text-[#060D26]" x-text="peso(mAgg(year, m).collected)"></span>
                                        <span class="text-[11px] sm:text-[12px] lg:text-[12.5px] text-[#5B6A8E]" x-text="'of ' + peso(mAgg(year, m).expected)"></span>
                                    </span>
                                    <span class="mt-1.5 block h-1 rounded-full bg-[#E2E4EC] overflow-hidden">
                                        <span class="block h-full rounded-full bg-[#22C55E]" :style="'width:' + pct(mAgg(year, m)) + '%'"></span>
                                    </span>
                                    <span class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] lg:text-[12px] font-semibold leading-tight">
                                        <span x-show="mAgg(year, m).overdue > 0" class="text-[#DC2626]" x-text="mAgg(year, m).overdue + ' overdue'"></span>
                                        <span x-show="mAgg(year, m).partial > 0" class="text-[#B45309]" x-text="mAgg(year, m).partial + ' partial'"></span>
                                        <span x-show="mAgg(year, m).upcoming > 0" class="text-[#5B6A8E]" x-text="mAgg(year, m).upcoming + ' upcoming'"></span>
                                    </span>
                                </span>
                            </template>
                            <template x-if="mAgg(year, m).due <= 0">
                                <span class="block text-[12px] text-[#94A3B8]">No rent due</span>
                            </template>
                        </button>
                    </template>
                </div>
            </section>

            {{-- ───────── Level 3: one month, by day ───────── --}}
            <section x-ref="pMonth" x-show="vis.month" aria-labelledby="cal-h-month" class="col-start-1 row-start-1 lg:flex lg:flex-col">
                <div class="flex items-start justify-between gap-3 px-4 sm:px-6 pt-4 sm:pt-6">
                    <div class="min-w-0">
                    <h2 id="cal-h-month" x-ref="hMonth" tabindex="-1" class="text-[18px] sm:text-[20px] font-semibold text-[#060D26] focus:outline-none" x-text="monthName(mon) + ' ' + year"></h2>
                    <template x-if="sumFor('month').expected > 0">
                        <div>
                            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] tabular-nums">
                                <span class="font-semibold text-[#060D26]" x-text="peso(sumFor('month').collected, 2)"></span> collected of <span x-text="peso(sumFor('month').expected, 2)"></span> due
                            </p>
                            <div class="mt-2 h-1.5 w-56 max-w-full rounded-full bg-[#E2E4EC] overflow-hidden" role="progressbar" aria-label="Rent collected this month"
                                aria-valuemin="0" aria-valuemax="100" :aria-valuenow="sumFor('month').pct">
                                <div class="h-full rounded-full bg-[#22C55E]" :style="'width:' + sumFor('month').pct + '%'"></div>
                            </div>
                        </div>
                    </template>
                    <template x-if="sumFor('month').expected <= 0">
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">No rent falls due this month.</p>
                    </template>
                    </div>
                    {!! $arrows !!}
                </div>

                <div class="mt-4 grid grid-cols-7 gap-px bg-[#E2E4EC] border-t border-[#E2E4EC] lg:flex-1 lg:[grid-template-rows:auto_repeat(var(--weeks),minmax(var(--row-min,88px),1fr))]" :style="'--weeks:' + weeks">
                    <template x-for="dow in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="dow">
                        <div class="bg-[#F7F8FC] py-2 text-center lg:text-left lg:px-3 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]" x-text="dow"></div>
                    </template>

                    <template x-for="c in cells" :key="c.key">
                        <div class="contents">
                            <template x-if="c.out">
                                <div class="bg-[#F7F8FC] min-h-[56px] lg:min-h-0 p-1.5 lg:p-2">
                                    <span class="inline-flex h-6 min-w-6 items-center justify-center text-[12px] tabular-nums text-[#94A3B8]/70" x-text="c.day"></span>
                                </div>
                            </template>
                            <template x-if="!c.out">
                                <button type="button" data-day-cell @click="openDay(c.key, $el)" :aria-label="dayLabel(c)"
                                    :class="[c.today ? 'bg-[#FFF7F4]' : 'bg-white', selected === c.key ? 'ring-2 ring-inset ring-[#FF8A66]' : 'hover:bg-[#F7F8FC]']"
                                    class="relative block min-h-[56px] lg:min-h-0 p-1.5 lg:p-0 text-left cursor-pointer transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#FF8A66]">
                                    {{-- From lg up the content sits in its own layer, so it never sizes the (equal-height) week rows: the rows come only from the space available and their minimum. --}}
                                    <span class="flex flex-col items-stretch gap-1 lg:absolute lg:inset-0 lg:overflow-hidden lg:p-1.5">
                                    <span class="flex items-center justify-between gap-1">
                                        <span :class="c.today ? 'bg-[#FF8A66] text-[#060D26]' : 'text-[#060D26]'"
                                            class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[12px] font-semibold tabular-nums" x-text="c.day"></span>
                                        <span x-show="c.entries.length > chipLimit" class="hidden lg:inline text-[11px] font-semibold text-[#5B6A8E] underline underline-offset-2"
                                            x-text="'See more · +' + (c.entries.length - chipLimit)"></span>
                                    </span>

                                    {{-- Phone: one dot per payment --}}
                                    <span x-show="c.entries.length" class="lg:hidden flex flex-wrap items-center gap-1 px-0.5">
                                        <template x-for="(e, i) in c.entries.slice(0, 4)" :key="i">
                                            <span class="w-2 h-2 rounded-full" :class="styles[e.status].dot"></span>
                                        </template>
                                        <span x-show="c.entries.length > 4" class="text-[10px] font-semibold text-[#5B6A8E]" x-text="'+' + (c.entries.length - 4)"></span>
                                    </span>

                                    {{-- Desktop: named chips --}}
                                    <span x-show="c.entries.length" class="hidden lg:flex flex-col gap-1">
                                        <template x-for="(e, i) in c.entries.slice(0, chipLimit)" :key="i">
                                            <span class="flex items-center gap-1.5 rounded-md px-2 py-1 text-[11.5px] font-semibold leading-none" :class="chips[e.status]">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="styles[e.status].dot"></span>
                                                <span class="truncate" x-text="firstName(e)"></span>
                                                <span class="ml-auto tabular-nums font-medium opacity-80" x-text="peso(e.expected)"></span>
                                            </span>
                                        </template>
                                    </span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </section>
        </div>

        <ul class="flex shrink-0 flex-wrap gap-x-5 gap-y-1.5 px-4 sm:px-6 py-3 border-t border-[#E2E4EC] text-[12px] text-[#5B6A8E]">
            @foreach($paymentStyles as $s)
                <li class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>{{ $s['label'] }}
                </li>
            @endforeach
        </ul>
    </x-card>

    {{-- A picked day opens over the page, so choosing one never needs a scroll. --}}
    <div x-show="selected" x-cloak @click="closeDay()"
        class="fixed inset-0 z-[199] bg-black/40 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"></div>

    <x-card flush x-show="selected" x-cloak role="dialog" aria-modal="true" x-bind:aria-label="dayTitle"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 scale-95"
        class="fixed z-[200] inset-3 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-2xl sm:max-h-[80vh] flex flex-col">
        <div class="px-5 sm:px-6 py-3.5 border-b border-[#E2E4EC] flex items-center gap-3 shrink-0">
            <h3 class="flex-1 min-w-0 text-[14px] font-semibold text-[#060D26]">
                <span x-text="dayTitle"></span>
                <span x-show="dayEntries.length" class="font-normal text-[#5B6A8E]" x-text="'· ' + dayEntries.length + (dayEntries.length === 1 ? ' payment' : ' payments') + ' due'"></span>
            </h3>
            <button type="button" x-ref="closeBtn" @click="closeDay()" aria-label="Close"
                class="h-11 w-11 sm:h-9 sm:w-9 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p x-show="! dayEntries.length" class="px-5 sm:px-6 py-4 text-[13px] text-[#5B6A8E]">No rent falls due on this day.</p>
        <ul x-show="dayEntries.length" class="divide-y divide-[#E2E4EC] overflow-y-auto flex-1">
            <template x-for="(e, i) in dayEntries" :key="i">
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 sm:px-6 py-3.5">
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="text-[13.5px] font-semibold text-[#060D26] truncate" x-text="person(e).name"></p>
                        <p class="text-[12px] text-[#5B6A8E] truncate" x-text="person(e).unit + ' · ' + person(e).property"></p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold" :class="styles[e.status].text">
                        <span class="w-2 h-2 rounded-full" :class="styles[e.status].dot"></span><span x-text="styles[e.status].label"></span>
                    </span>
                    <p class="text-[13px] tabular-nums text-right">
                        <span class="font-semibold text-[#060D26]" x-text="peso(e.paid, 2)"></span>
                        <span class="text-[#5B6A8E]" x-text="'of ' + peso(e.expected, 2)"></span>
                    </p>
                    <a :href="person(e).url" data-page-panel data-page-panel-back="Payments"
                        class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] transition-colors duration-200 whitespace-nowrap">
                        Open ledger <span aria-hidden="true">→</span>
                    </a>
                </li>
            </template>
        </ul>
    </x-card>
</div>
