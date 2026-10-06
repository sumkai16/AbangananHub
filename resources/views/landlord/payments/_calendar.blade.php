{{-- Calendar: rent due dates by day. Cells are buttons that pick a
     day; the panel underneath lists that day in full and links to
     each ledger. Same layout at every width — the cell just shows
     dots on a phone and named chips from lg up. --}}
@php
    $month = $calendar['month'];
    $days = $calendar['days'];
    ksort($days);
    $eventDays = array_keys($days);
    $gridStart = $month->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
    $prevMonth = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $nextMonth = $month->copy()->addMonthNoOverflow()->format('Y-m');
    $todayKey = now()->format('Y-m-d');
    $chipTone = [
        'overdue'    => 'bg-[#EF4444]/[0.08] text-[#DC2626]',
        'partial'    => 'bg-[#FBBF24]/[0.14] text-[#B45309]',
        'upcoming'   => 'bg-[#ECEEF6] text-[#060D26]',
        'paid'       => 'bg-[#22C55E]/[0.09] text-[#15803D]',
        'paid_ahead' => 'bg-[#ECEEF6] text-[#060D26]',
    ];            @endphp

<div x-data="{ selected: null, eventDays: @js($eventDays) }" class="space-y-3">
    <x-card flush>
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 sm:px-6 py-4">
            <div class="min-w-0">
                <h2 class="text-[18px] font-semibold text-[#060D26]">{{ $month->format('F Y') }}</h2>
                @if($calendar['expected'] > 0)
                    @php $calPct = min(100, (int) round($calendar['collected'] / $calendar['expected'] * 100)); @endphp
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E] tabular-nums">
                        <span class="font-semibold text-[#060D26]">₱{{ number_format($calendar['collected'], 2) }}</span> collected of ₱{{ number_format($calendar['expected'], 2) }} due
                    </p>
                    <div class="mt-2 h-1.5 w-56 max-w-full rounded-full bg-[#E2E4EC] overflow-hidden" role="progressbar"
                        aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $calPct }}" aria-label="Rent collected this month">
                        <div class="h-full rounded-full bg-[#22C55E]" style="width: {{ $calPct }}%"></div>
                    </div>
                @else
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">No rent falls due this month.</p>
                @endif
            </div>
            <div class="inline-flex items-center rounded-xl border border-[#5B6A8E]/25 overflow-hidden divide-x divide-[#5B6A8E]/25">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar', 'month' => $prevMonth]) }}" data-cal-nav="prev" aria-label="Previous month"
                    class="h-9 w-9 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar', 'month' => null]) }}"
    data-cal-nav="today"
                    @if($month->isSameMonth(now())) aria-current="date" @endif
                    class="h-9 px-3.5 inline-flex items-center text-[13px] font-semibold transition-colors duration-200 {{ $month->isSameMonth(now()) ? 'text-[#5B6A8E] bg-[#F7F8FC]' : 'text-[#060D26] hover:bg-[#ECEEF6]' }}">Today</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar', 'month' => $nextMonth]) }}" data-cal-nav="next" aria-label="Next month"
                    class="h-9 w-9 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-px bg-[#E2E4EC] border-t border-[#E2E4EC]">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                <div class="bg-[#F7F8FC] py-2 text-center lg:text-left lg:px-3 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $dow }}</div>
            @endforeach

            @for($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
                @php
                    $key = $d->format('Y-m-d');
                    $inMonth = $d->isSameMonth($month);
                    $entries = $days[$key] ?? [];
                    $isToday = $key === $todayKey;
                @endphp
                @if(! $inMonth)
                    <div class="bg-[#F7F8FC] min-h-[56px] lg:min-h-[88px] p-1.5 lg:p-2">
                        <span class="inline-flex h-6 min-w-6 items-center justify-center text-[12px] tabular-nums text-[#94A3B8]/70">{{ $d->day }}</span>
                    </div>
                @else
                    <button type="button" @click="selected = '{{ $key }}'"
                        :class="selected === '{{ $key }}' ? 'ring-2 ring-inset ring-[#FF8A66]' : 'hover:bg-[#F7F8FC]'"
                        aria-label="{{ $d->format('F j') }}{{ count($entries) ? ', ' . count($entries) . ' due' : '' }}"
                        class="{{ $isToday ? 'bg-[#FFF7F4]' : 'bg-white' }} min-h-[56px] lg:min-h-[88px] p-1.5 lg:p-2 flex flex-col items-stretch gap-1 text-left cursor-pointer transition-colors duration-150">
                        <span class="inline-flex h-6 min-w-6 items-center justify-center self-start rounded-full px-1 text-[12px] font-semibold tabular-nums {{ $isToday ? 'bg-[#FF8A66] text-[#060D26]' : 'text-[#060D26]' }}">{{ $d->day }}</span>

                        {{-- Phone: one dot per payment --}}
                        @if($entries)
                            <span class="lg:hidden flex flex-wrap gap-1 px-0.5">
                                @foreach(array_slice($entries, 0, 4) as $e)
                                    <span class="w-2 h-2 rounded-full {{ $paymentStyles[$e['status']]['dot'] }}"></span>
                                @endforeach
                            </span>

                            {{-- Desktop: named chips --}}
                            <span class="hidden lg:flex flex-col gap-1">
                                @foreach(array_slice($entries, 0, 2) as $e)
                                    <span class="flex items-center gap-1.5 rounded-md px-2 py-1 text-[11.5px] font-semibold leading-none {{ $chipTone[$e['status']] }}">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $paymentStyles[$e['status']]['dot'] }}"></span>
                                        <span class="truncate">{{ explode(' ', $e['name'])[0] }}</span>
                                        <span class="ml-auto tabular-nums font-medium opacity-80">₱{{ number_format($e['expected'], 0) }}</span>
                                    </span>
                                @endforeach
                                @if(count($entries) > 2)
                                    <span class="px-1 text-[11px] font-semibold text-[#5B6A8E]">+{{ count($entries) - 2 }} more</span>
                                @endif
                            </span>
                        @endif
                    </button>
                @endif
            @endfor
        </div>

        <ul class="flex flex-wrap gap-x-5 gap-y-1.5 px-5 sm:px-6 py-3 border-t border-[#E2E4EC] text-[12px] text-[#5B6A8E]">
            @foreach($paymentStyles as $s)
                <li class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>{{ $s['label'] }}
                </li>
            @endforeach
        </ul>
    </x-card>

    {{-- Selected day — pops up as a modal instead of sitting inline, so
         picking a day never needs a scroll down the page. Each day already
         renders once for its own unique key, so showing whichever is
         selected as a fixed overlay duplicates nothing. --}}
    <div x-show="selected" x-cloak @click="selected = null" @keydown.escape.window="selected = null"
        class="fixed inset-0 z-[199] bg-black/40 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"></div>

    @foreach($days as $key => $entries)
        {{-- Scale/opacity only, never translate-y: the element is already
             centered with -translate-x/y-1/2, and Tailwind's translate-y-*
             utilities share one CSS variable, so an entrance transition using
             translate-y would fight the centering transform mid-animation. --}}
        <x-card flush x-show="selected === '{{ $key }}'" x-cloak role="dialog" aria-modal="true"
            aria-label="{{ \Carbon\Carbon::parse($key)->format('l, F j') }}"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 scale-95"
            class="fixed z-[200] inset-3 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-2xl sm:max-h-[80vh] flex flex-col">
            <div class="px-5 sm:px-6 py-3.5 border-b border-[#E2E4EC] flex items-center gap-3 shrink-0">
                <h3 class="flex-1 min-w-0 text-[14px] font-semibold text-[#060D26]">
                    {{ \Carbon\Carbon::parse($key)->format('l, F j') }}
                    <span class="font-normal text-[#5B6A8E]">· {{ count($entries) }} {{ \Illuminate\Support\Str::plural('payment', count($entries)) }} due</span>
                </h3>
                <button type="button" @click="selected = null" aria-label="Close"
                    class="h-9 w-9 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer transition-colors">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <ul class="divide-y divide-[#E2E4EC] overflow-y-auto flex-1">
                @foreach($entries as $e)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 sm:px-6 py-3.5">
                        <div class="min-w-0 flex-1 basis-48">
                            <p class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $e['name'] }}</p>
                            <p class="text-[12px] text-[#5B6A8E] truncate">{{ $e['unit'] }} · {{ $e['property'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold {{ $paymentStyles[$e['status']]['text'] }}">
                            <span class="w-2 h-2 rounded-full {{ $paymentStyles[$e['status']]['dot'] }}"></span>{{ $paymentStyles[$e['status']]['label'] }}
                        </span>
                        <p class="text-[13px] tabular-nums text-right">
                            <span class="font-semibold text-[#060D26]">₱{{ number_format($e['paid'], 2) }}</span>
                            <span class="text-[#5B6A8E]">of ₱{{ number_format($e['expected'], 2) }}</span>
                        </p>
                        <a href="{{ $e['url'] }}"
                            class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] transition-colors duration-200 whitespace-nowrap">
                            Open ledger <span aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endforeach

    <x-card flush x-show="selected && ! eventDays.includes(selected)" x-cloak role="dialog" aria-modal="true" aria-label="No rent due"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 scale-95"
        class="fixed z-[200] inset-3 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md py-3.5 px-5 sm:px-6 flex items-center justify-between gap-3">
        <p class="text-[13px] text-[#5B6A8E]">No rent falls due on this day.</p>
        <button type="button" @click="selected = null" aria-label="Close"
            class="h-9 w-9 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer transition-colors">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </x-card>
</div>
