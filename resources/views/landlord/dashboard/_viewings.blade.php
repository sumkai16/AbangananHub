{{-- Dashboard: viewings (DashboardController::viewingSchedule). On the page it is one slim card: the next viewing,
     how many fall in the next two weeks and how many wait on the landlord's answer. Clicking it opens a calendar
     modal (bottom sheet on phones): a month grid with prev/next across this month and the next two, a marker on each
     day that has viewings (amber when one waits on the landlord), and the picked day's viewings beside it (under it
     on phones). Answering, rescheduling, blocking days and logging a phone viewing all happen on
     Reservations > Viewings, which every row and "Open calendar" lead to. --}}
@php
    // Inside the dashboard's tab card the button is a plain row; on its own it is a card.
    $cardChrome = ($embedded ?? false) ? '' : 'rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]';
    $months = $viewings['months'];
    $byDay = $viewings['byDay'];
    $needsAnswer = array_flip($viewings['needsAnswerDays']);
    $next = $viewings['next'];
    $landlordId = auth()->id();
    $today = now()->startOfDay();
    $todayIso = $today->toDateString();
    $busyDays = $byDay->keys()->values()->all();

    // Where each month opens: its first day with a viewing still ahead (this month) or at all (later months);
    // otherwise today for this month and the 1st for the others.
    $monthDefaults = $months->map(function ($month, $i) use ($busyDays, $todayIso) {
        $prefix = $month->format('Y-m-');
        $from = $i === 0 ? $todayIso : $month->toDateString();
        $busy = collect($busyDays)->sort()->first(fn ($d) => str_starts_with($d, $prefix) && $d >= $from);

        return $busy ?? ($i === 0 ? $todayIso : $month->toDateString());
    })->values()->all();
    // The modal opens on the month of the next viewing (if it is in range), else this month.
    $startMonth = $next ? max(0, $months->search(fn ($m) => $m->isSameMonth($next->scheduled_at))) : 0;
    $dayName = fn ($date) => $date->isToday() ? 'Today' : ($date->isTomorrow() ? 'Tomorrow' : $date->format('D, M j'));
@endphp

<div x-on:keydown.escape.window="open = false"
    x-data="{
        open: false,
        month: @js($startMonth),
        day: @js($monthDefaults[$startMonth]),
        busy: @js($busyDays),
        defaults: @js($monthDefaults),
        go(i) { this.month = i; this.day = this.defaults[i]; },
        get dayLabel() {
            return new Date(this.day + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        },
    }">
    {{-- The card --}}
    <button type="button" x-on:click="open = true; $nextTick(() => $refs.sheet.focus())" aria-haspopup="dialog"
        class="group flex w-full items-center gap-3.5 p-4 text-left transition-colors duration-200 hover:bg-[#F7F8FC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#FF8A66]/50 cursor-pointer sm:px-5 {{ $cardChrome }}">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#ECEEF6] text-[#060D26]" aria-hidden="true">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span id="dash-viewings-title" class="text-[15px] font-semibold text-[#060D26]">Viewings</span>
                @if($viewings['toAnswer'] > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FBBF24]/15 px-2 py-0.5 text-[11.5px] font-semibold text-[#B45309]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B]" aria-hidden="true"></span>{{ $viewings['toAnswer'] }} to answer
                    </span>
                @endif
            </span>
            <span class="mt-0.5 block truncate text-[13px] text-[#5B6A8E]">
                @if($next)
                    Next: {{ $dayName($next->scheduled_at) }} · {{ $next->scheduled_at->format('g:i A') }} · {{ $next->visitorName() }}
                @else
                    Nothing booked yet. Open the calendar.
                @endif
            </span>
        </span>
        <span class="hidden shrink-0 text-right sm:block">
            <span class="block text-[20px] font-bold leading-none tabular-nums text-[#060D26]">{{ $viewings['soon'] }}</span>
            <span class="mt-1 block text-[11.5px] text-[#5B6A8E]">next 2 weeks</span>
        </span>
        <svg class="shrink-0 text-[#B35A3D] transition-transform duration-200 group-hover:translate-x-0.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </button>

    {{-- The calendar --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[200] bg-[#060D26]/45"
            x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex h-full items-end justify-center sm:items-center sm:p-6">
                <div x-ref="sheet" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="viewings-sheet-title"
                    x-on:click.outside="open = false" x-show="open"
                    x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[0.98]" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                    class="flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-[0_24px_48px_-12px_rgba(6,13,38,0.35)] outline-none sm:max-h-[88vh] sm:max-w-3xl sm:rounded-2xl">

                    <div class="flex items-start gap-3 border-b border-[#E2E4EC] px-5 py-4 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <h2 id="viewings-sheet-title" class="text-[16px] font-semibold text-[#060D26]">Viewing calendar</h2>
                            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">
                                @if($viewings['toAnswer'] > 0)
                                    {{ $viewings['toAnswer'] }} waiting on your answer. Tap a day to see who is coming.
                                @else
                                    Tap a day to see who is coming.
                                @endif
                            </p>
                        </div>
                        <button type="button" x-on:click="open = false" aria-label="Close"
                            class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-[#5B6A8E] transition-colors hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    {{-- Phones: calendar, then the day's list, scrolling together. md+: side by side, the list scrolls alone. --}}
                    <div class="min-h-0 flex-1 overflow-y-auto md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:overflow-hidden">

                        {{-- Month grid --}}
                        <div class="px-4 pb-4 pt-3 sm:px-6 md:border-r md:border-[#E2E4EC]">
                            @foreach($months as $i => $month)
                                @php
                                    $gridStart = $month->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
                                    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Illuminate\Support\Carbon::SATURDAY);
                                    $cells = \Carbon\CarbonPeriod::create($gridStart, $gridEnd);
                                @endphp
                                <div x-show="month === {{ $i }}" @if($i !== $startMonth) x-cloak @endif>
                                    <div class="flex items-center justify-between">
                                        <button type="button" x-on:click="go({{ $i - 1 }})" @disabled($i === 0) aria-label="Previous month"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg text-[#060D26] transition-colors hover:bg-[#F1F3F8] disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent cursor-pointer">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                        </button>
                                        <p class="text-[15px] font-semibold text-[#060D26]" aria-live="polite">{{ $month->format('F Y') }}</p>
                                        <button type="button" x-on:click="go({{ $i + 1 }})" @disabled($i === $months->count() - 1) aria-label="Next month"
                                            class="flex h-10 w-10 items-center justify-center rounded-lg text-[#060D26] transition-colors hover:bg-[#F1F3F8] disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent cursor-pointer">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                        </button>
                                    </div>

                                    <div class="mt-2 grid grid-cols-7 text-center text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]" aria-hidden="true">
                                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                                            <span class="py-1.5">{{ $weekday }}</span>
                                        @endforeach
                                    </div>

                                    <div class="grid grid-cols-7 gap-1" role="group" aria-label="{{ $month->format('F Y') }}">
                                        @foreach($cells as $date)
                                            @if(! $date->isSameMonth($month))
                                                <span aria-hidden="true"></span>
                                                @continue
                                            @endif
                                            @php
                                                $iso = $date->toDateString();
                                                $count = $byDay->get($iso)?->count() ?? 0;
                                                $isToday = $iso === $todayIso;
                                                $isPast = $date->lt($today);
                                                $marker = isset($needsAnswer[$iso]) ? 'bg-[#F59E0B]' : 'bg-[#FF8A66]';
                                            @endphp
                                            <button type="button" x-on:click="day = @js($iso)" :aria-pressed="(day === @js($iso)).toString()"
                                                aria-label="{{ $date->format('l, F j') }}{{ $isToday ? ' (today)' : '' }}: {{ $count }} {{ \Illuminate\Support\Str::plural('viewing', $count) }}"
                                                :class="day === @js($iso) ? 'bg-[#060D26] text-white' : @js(($isToday ? 'ring-1 ring-inset ring-[#FF8A66] font-bold ' : '').($isPast ? 'text-[#5B6A8E]/60 hover:bg-[#F7F8FC]' : 'text-[#060D26] hover:bg-[#F1F3F8]'))"
                                                class="flex h-11 flex-col items-center justify-center rounded-xl text-[14px] tabular-nums transition-colors duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/60 sm:h-12">
                                                <span class="leading-none">{{ $date->format('j') }}</span>
                                                <span class="mt-1 flex h-3.5 items-center" aria-hidden="true">
                                                    @if($count > 1)
                                                        <span class="rounded-full {{ $marker }} px-1.5 text-[10px] font-bold leading-[14px] text-[#060D26]">{{ $count }}</span>
                                                    @elseif($count === 1)
                                                        <span class="h-1.5 w-1.5 rounded-full {{ $marker }}"></span>
                                                    @endif
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-[#5B6A8E]">
                                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-[#FF8A66]"></span>Booked</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-[#F59E0B]"></span>Needs your answer</span>
                            </p>
                        </div>

                        {{-- The picked day's viewings --}}
                        <div class="border-t border-[#E2E4EC] md:min-h-0 md:overflow-y-auto md:border-t-0">
                            <p class="px-5 pt-4 text-[13px] font-semibold text-[#060D26] sm:px-6" x-text="dayLabel" aria-live="polite"></p>

                            <p x-show="! busy.includes(day)" class="px-5 pb-5 pt-1.5 text-[13.5px] text-[#5B6A8E] sm:px-6">
                                {{ $byDay->isEmpty() ? 'No viewings booked yet. Tenants book them from your listings; add one you arranged by phone on the full calendar.' : 'Nothing booked this day.' }}
                            </p>

                            @foreach($byDay as $iso => $dayViewings)
                                <ul x-show="day === @js($iso)" @if($iso !== $monthDefaults[$startMonth]) x-cloak @endif class="mt-1 pb-2 divide-y divide-[#5B6A8E]/10">
                                    @foreach($dayViewings as $viewing)
                                        @php
                                            $status = $viewing->displayStatus();
                                            [$pillClass, $pillText] = match (true) {
                                                $viewing->isOpen() && $viewing->awaitsResponseFrom($landlordId) => ['bg-[#FBBF24]/15 text-[#B45309]', 'Needs your answer'],
                                                $status === 'Pending' => ['bg-[#F1F3F8] text-[#5B6A8E]', 'Waiting for tenant'],
                                                $status === 'Confirmed' => ['bg-[#22C55E]/10 text-[#15803D]', 'Confirmed'],
                                                $status === 'Completed' => ['bg-[#F1F3F8] text-[#5B6A8E]', 'Done'],
                                                default => ['bg-[#F1F3F8] text-[#5B6A8E]', $status],
                                            };
                                            $where = trim(($viewing->unit?->unit_label ? $viewing->unit->unit_label.' · ' : '').($viewing->property?->title ?? ''), ' ·');
                                        @endphp
                                        <li>
                                            <a href="{{ route('landlord.viewings.index', ['month' => $viewing->scheduled_at->format('Y-m')]) }}"
                                                class="group flex items-center gap-3 px-5 py-3 hover:bg-[#F7F8FC] active:bg-[#ECEEF6] transition-colors duration-200 sm:px-6">
                                                <span class="w-[72px] shrink-0 whitespace-nowrap text-[13.5px] font-bold tabular-nums text-[#060D26]">{{ $viewing->scheduled_at->format('g:i A') }}</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-[14px] font-semibold text-[#060D26]">{{ $viewing->visitorName() }}</span>
                                                    <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11.5px] font-semibold {{ $pillClass }}">{{ $pillText }}</span>
                                                    <span class="mt-1 block truncate text-[12.5px] text-[#5B6A8E]">{{ $where }}@if($viewing->isOffline()) · arranged offline @endif</span>
                                                </span>
                                                <svg class="shrink-0 text-[#B35A3D] group-hover:text-[#060D26] transition-colors duration-200" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                </svg>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-[#E2E4EC] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-end sm:px-6">
                        <button type="button" x-on:click="open = false"
                            class="inline-flex h-11 items-center justify-center rounded-full px-5 text-[14px] font-semibold text-[#5B6A8E] transition-colors hover:text-[#060D26] cursor-pointer">
                            Close
                        </button>
                        <a href="{{ route('landlord.viewings.index') }}"
                            class="inline-flex h-11 items-center justify-center rounded-full bg-[#FF8A66] px-6 text-[14px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26]/30">
                            Open full calendar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
