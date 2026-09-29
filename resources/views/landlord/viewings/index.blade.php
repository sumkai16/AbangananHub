@extends('layouts.landlord')

{{--
    Viewings tab of the Reservations page. See plans/unit-viewing-scheduling.md.

    Month calendar (tap a day to filter the list and block/unblock it) + one
    list of viewings. In-app requests are answered here or in the chat — both
    post to the same routes. "Add viewing" logs a visit arranged offline.
--}}

@section('content')
@php
    $me = auth()->id();
    $today = today()->toDateString();
    $dayCounts = $byDay->map->count();
    $blockedMap = $blockedDates->map(fn ($b) => ['id' => $b->blocked_date_id, 'reason' => $b->reason]);
@endphp

<div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10"
    x-data="{
        day: null,
        adding: {{ old('_form') === 'offline-new' ? 'true' : 'false' }},
        settingHours: false,
        counts: @js($dayCounts),
        blocked: @js($blockedMap),
        upcomingCount: {{ $upcomingCount }},
        today: @js($today),
        get visibleCount() { return this.day ? (this.counts[this.day] || 0) : this.upcomingCount; },
        get dayLabel() {
            if (!this.day) return '';
            const [y, m, d] = this.day.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
        },
    }">

    <x-page-header title="Reservations" subtitle="Manage rental requests and unit viewings.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <button type="button" @click="settingHours = true"
                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] text-sm font-semibold cursor-pointer transition-all duration-200">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                Viewing hours
            </button>
            <button type="button" @click="adding = true"
                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-sm font-bold cursor-pointer transition-all duration-200">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add viewing
            </button>
        </x-slot:actions>
    </x-page-header>

    @include('landlord.reservations._section-tabs', ['active' => 'viewings', 'pendingViewings' => $pendingCount])

    @if ($errors->any())
        <div class="mb-5 bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="space-y-4">

        {{-- ── Calendar — same shell as landlord/payments/_calendar, shorter cells ── --}}
        @php
            $monthViewingCount = $byDay->filter(fn ($vs, $iso) => \Illuminate\Support\Carbon::parse($iso)->isSameMonth($month))->flatten()->count();
            $viewingTone = function (\App\Models\ViewingRequest $v) use ($me) {
                $state = $v->displayStatus();
                return match (true) {
                    $v->isOpen() && $v->awaitsResponseFrom($me) => ['chip' => 'bg-[#FBBF24]/[0.14] text-[#B45309]', 'dot' => 'bg-[#F59E0B]'],
                    $state === 'Confirmed'                      => ['chip' => 'bg-[#22C55E]/[0.09] text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                    $state === 'Pending'                        => ['chip' => 'bg-[#ECEEF6] text-[#060D26]', 'dot' => 'bg-[#94A3B8]'],
                    default                                     => ['chip' => 'bg-[#F7F8FC] text-[#94A3B8]', 'dot' => 'bg-[#CBD5E1]'],
                };
            };
            $stripes = 'bg-[repeating-linear-gradient(135deg,#ECEEF6_0_4px,#F7F8FC_4px_8px)]';
        @endphp
        <x-card flush aria-label="Viewing calendar">
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 sm:px-6 py-4">
                <div class="min-w-0">
                    <h2 class="text-[18px] font-semibold text-[#060D26]">{{ $month->format('F Y') }}</h2>
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">
                        @if ($monthViewingCount)
                            <span class="font-semibold text-[#060D26]">{{ $monthViewingCount }}</span> {{ \Illuminate\Support\Str::plural('viewing', $monthViewingCount) }} this month
                            @if ($pendingCount)
                                · <span class="font-semibold text-[#B45309]">{{ $pendingCount }} {{ $pendingCount === 1 ? 'needs' : 'need' }} your answer</span>
                            @endif
                        @else
                            No viewings this month. Tap a day to block it.
                        @endif
                    </p>
                    <p class="mt-1 text-[12.5px] text-[#5B6A8E]">
                        <span class="font-semibold text-[#060D26]">Viewing hours:</span> {{ $hoursSummary }}
                        @unless ($hoursSet)
                            <span class="text-[#94A3B8]">(default)</span>
                        @endunless
                        · <button type="button" @click="settingHours = true" class="font-semibold text-[#060D26] underline decoration-[#E2E4EC] underline-offset-2 hover:decoration-[#060D26] cursor-pointer">Change</button>
                    </p>
                </div>
                <div class="inline-flex items-center rounded-xl border border-[#5B6A8E]/25 overflow-hidden divide-x divide-[#5B6A8E]/25">
                    <a href="{{ route('landlord.viewings.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" aria-label="Previous month"
                        class="h-9 w-9 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>
                    <a href="{{ route('landlord.viewings.index') }}" @if ($month->isSameMonth(now())) aria-current="date" @endif
                        class="h-9 px-3.5 inline-flex items-center text-[13px] font-semibold transition-colors duration-200 {{ $month->isSameMonth(now()) ? 'text-[#5B6A8E] bg-[#F7F8FC]' : 'text-[#060D26] hover:bg-[#ECEEF6]' }}">Today</a>
                    <a href="{{ route('landlord.viewings.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" aria-label="Next month"
                        class="h-9 w-9 inline-flex items-center justify-center text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-7 gap-px bg-[#E2E4EC] border-t border-[#E2E4EC]">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                    <div class="bg-[#F7F8FC] py-2 text-center lg:text-left lg:px-3 text-[11px] font-bold uppercase tracking-wide text-[#5B6A8E]">{{ $dow }}</div>
                @endforeach

                @for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
                    @php
                        $iso = $d->toDateString();
                        $inMonth = $d->isSameMonth($month);
                        $dayViewings = $byDay->get($iso, collect());
                        $isBlocked = $blockedDates->has($iso);
                        $isDayOff = ! $isBlocked && ! isset($week[$d->dayOfWeek]);
                        $isToday = $iso === $today;
                        $label = $d->format('l, F j')
                            . ($dayViewings->count() ? ', ' . $dayViewings->count() . ' ' . \Illuminate\Support\Str::plural('viewing', $dayViewings->count()) : '')
                            . ($isBlocked ? ', blocked' : '');
                    @endphp
                    @if (! $inMonth)
                        <div class="bg-[#F7F8FC] min-h-[56px] lg:min-h-[88px] p-1.5 lg:p-2">
                            <span class="inline-flex h-6 min-w-6 items-center justify-center text-[12px] tabular-nums text-[#94A3B8]/70">{{ $d->day }}</span>
                        </div>
                    @else
                        <button type="button" @click="day = day === '{{ $iso }}' ? null : '{{ $iso }}'"
                            :aria-pressed="day === '{{ $iso }}'" aria-label="{{ $label }}"
                            :class="day === '{{ $iso }}' ? 'ring-2 ring-inset ring-[#FF8A66]' : 'hover:bg-[#F7F8FC]'"
                            class="{{ $isBlocked ? $stripes : ($isToday ? 'bg-[#FFF7F4]' : ($isDayOff ? 'bg-[#F7F8FC]' : 'bg-white')) }} min-h-[56px] lg:min-h-[88px] p-1.5 lg:p-2 flex flex-col items-stretch gap-1 text-left cursor-pointer transition-colors duration-150">
                            <span class="flex items-center gap-1.5">
                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[12px] font-semibold tabular-nums
                                    {{ $isToday ? 'bg-[#FF8A66] text-[#060D26]' : ($isBlocked ? 'text-[#94A3B8] line-through' : 'text-[#060D26]') }}">{{ $d->day }}</span>
                                @if ($isBlocked)
                                    <span class="hidden lg:inline text-[10.5px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Blocked</span>
                                @elseif ($isDayOff)
                                    <span class="hidden lg:inline text-[10.5px] font-semibold uppercase tracking-wide text-[#94A3B8]">No viewings</span>
                                @endif
                            </span>

                            @if ($dayViewings->count())
                                {{-- Phone: one dot per viewing --}}
                                <span class="lg:hidden flex flex-wrap gap-1 px-0.5" aria-hidden="true">
                                    @foreach ($dayViewings->take(4) as $v)
                                        <span class="w-2 h-2 rounded-full {{ $viewingTone($v)['dot'] }}"></span>
                                    @endforeach
                                </span>

                                {{-- Desktop: time + first name --}}
                                <span class="hidden lg:flex flex-col gap-1" aria-hidden="true">
                                    @foreach ($dayViewings->take(2) as $v)
                                        <span class="flex items-center gap-1.5 rounded-md px-2 py-1 text-[11.5px] font-semibold leading-none {{ $viewingTone($v)['chip'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $viewingTone($v)['dot'] }}"></span>
                                            <span class="tabular-nums font-medium opacity-80">{{ $v->scheduled_at->format('g A') }}</span>
                                            <span class="truncate">{{ explode(' ', $v->visitorName())[0] }}</span>
                                        </span>
                                    @endforeach
                                    @if ($dayViewings->count() > 2)
                                        <span class="px-1 text-[11px] font-semibold text-[#5B6A8E]">+{{ $dayViewings->count() - 2 }} more</span>
                                    @endif
                                </span>
                            @endif
                        </button>
                    @endif
                @endfor
            </div>

            <ul class="flex flex-wrap gap-x-5 gap-y-1.5 px-5 sm:px-6 py-3 border-t border-[#E2E4EC] text-[12px] text-[#5B6A8E]">
                <li class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#F59E0B]"></span>Needs your answer</li>
                <li class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#94A3B8]"></span>Waiting for tenant</li>
                <li class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#22C55E]"></span>Confirmed</li>
                <li class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded {{ $stripes }} border border-[#E2E4EC]"></span>Blocked day</li>
                <li class="text-[#94A3B8] sm:ml-auto">Tap a day to see its viewings or block it.</li>
            </ul>
        </x-card>

        {{-- ── List ───────────────────────────────────────── --}}
        <section class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] overflow-hidden" aria-live="polite">
            <header class="px-4 sm:px-5 py-3.5 border-b border-[#E2E4EC] flex items-center gap-3 flex-wrap">
                <div class="flex-1 min-w-0">
                    <h2 class="text-[15px] font-bold text-[#060D26]" x-text="day ? dayLabel : 'Upcoming viewings'">Upcoming viewings</h2>
                    <p class="text-[12px] text-[#5B6A8E]" x-show="day && blocked[day]" x-cloak>
                        Blocked<span x-show="day && blocked[day] && blocked[day].reason" x-text="day && blocked[day] ? ': ' + blocked[day].reason : ''"></span>. Tenants can't pick this day.
                    </p>
                </div>

                <template x-if="day && day >= today && !blocked[day]">
                    <form action="{{ route('landlord.viewings.block') }}" method="POST">
                        @csrf
                        <input type="hidden" name="date" :value="day">
                        <button type="submit" class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-colors">Block this day</button>
                    </form>
                </template>
                <template x-if="day && blocked[day]">
                    <form :action="'{{ route('landlord.viewings.unblock', '__ID__') }}'.replace('__ID__', blocked[day].id)" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] cursor-pointer transition-colors">Unblock</button>
                    </form>
                </template>
                <button type="button" x-show="day" x-cloak @click="day = null"
                    class="h-9 px-3 rounded-lg text-[#5B6A8E] text-[12.5px] font-semibold hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer transition-colors">Show upcoming</button>
            </header>

            <ul class="divide-y divide-[#E2E4EC]">
                @foreach ($rows as $viewing)
                    @include('landlord.viewings._row', ['viewing' => $viewing, 'properties' => $properties, 'blockedIsos' => $blockedIsos])
                @endforeach
            </ul>

            <div x-show="visibleCount === 0" x-cloak class="px-5 py-12 text-center">
                <p class="text-[14px] font-semibold text-[#060D26]" x-text="day ? 'No viewings on this day' : 'No upcoming viewings'"></p>
                <p class="mt-1 text-[12.5px] text-[#5B6A8E]">
                    Tenants can ask to view a unit from the chat once you accept their inquiry.
                    Someone called or messaged you instead? Use <span class="font-semibold text-[#060D26]">Add viewing</span>.
                </p>
            </div>
        </section>
    </div>

    <x-picker-modal show="settingHours" title="Viewing hours"
        subtitle="The days and times tenants can ask to view your units. To take a single day off, block it on the calendar instead.">
        @include('landlord.viewings._hours-form', ['week' => $week, 'closeExpr' => 'settingHours = false'])
    </x-picker-modal>

    <x-picker-modal show="adding" title="Add a viewing" subtitle="For someone who arranged a visit by phone, message or in person. It's confirmed straight away.">
        @if ($properties->isEmpty())
            <p class="px-6 py-8 text-[13px] text-[#5B6A8E]">You need an approved property before you can add viewings.</p>
        @else
            @include('landlord.viewings._offline-form', [
                'viewing'     => null,
                'properties'  => $properties,
                'blockedIsos' => $blockedIsos,
                'closeExpr'   => 'adding = false',
            ])
        @endif
    </x-picker-modal>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/datetime-picker.js') }}"></script>
@endpush
