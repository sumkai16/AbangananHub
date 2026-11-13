{{--
    The Rental strip: one bar per rent month, filled by how much of it is paid, grouped by year. Shared by the landlord's
    rent ledger and the tenant's rental page so both read the same.
    Needs: $reservation, $periods (RentLedger::periods()), $periodStyles, $periodKeyFor (closure), $periodDetails
    (month => detail payload for the period popup), $peso (closure), $tile (card classes).
--}}
@php
                // The lease strip: one cell per rent month of the lease. Counted from the real dates, so
                // Nov 5 → Dec 5 is one month, not two. An open-ended lease shows a year, or through two
                // months past today if it has run longer. Never shorter than the months actually billed.
                $moveIn = ($reservation->tenancyStartDate() ?? now())->copy();
                $leaseStart = $moveIn->copy()->startOfMonth();
                $leaseMonths = $reservation->target_move_out_date
                    ? (int) round($moveIn->diffInMonths($reservation->target_move_out_date))
                    : max(12, (int) round($leaseStart->diffInMonths(now()->copy()->startOfMonth())) + 3);
                $lastPeriod = $periods->last()['period'] ?? null;
                if ($lastPeriod) {
                    $leaseMonths = max($leaseMonths, (int) round($leaseStart->diffInMonths($lastPeriod->copy()->startOfMonth())) + 1);
                }
                $leaseMonths = max(1, min(36, $leaseMonths));
                $thisMonth = now()->format('Y-m');
                $monthsElapsed = max(0, min($leaseMonths, (int) round($leaseStart->diffInMonths(now()->copy()->startOfMonth())) + 1));

                // Bar = how much of the month is paid; colour = what that means. Track is the same hue, faint.
                $stripTones = [
                    'paid'         => ['track' => 'bg-[#22C55E]/20', 'fill' => 'bg-[#22C55E]', 'legend' => 'Paid'],
                    'partial'      => ['track' => 'bg-[#FBBF24]/25', 'fill' => 'bg-[#F59E0B]', 'legend' => 'Partial'],
                    'overdue'      => ['track' => 'bg-[#EF4444]/30', 'fill' => 'bg-[#EF4444]', 'legend' => 'Overdue'],
                    'due'          => ['track' => 'bg-[#ECEEF6] ring-1 ring-inset ring-[#5B6A8E]/45', 'fill' => 'bg-[#5B6A8E]', 'legend' => 'Due'],
                    'advance'      => ['track' => 'bg-[#060D26]/15', 'fill' => 'bg-[#060D26]', 'legend' => 'Paid ahead'],
                    'advance_part' => ['track' => 'bg-[#060D26]/15', 'fill' => 'bg-[#060D26]', 'legend' => 'Paid ahead'],
                    'upcoming'     => ['track' => 'bg-[#ECEEF6]', 'fill' => '', 'legend' => 'Not billed yet'],
                ];
                $stripCells = [];
                for ($i = 0; $i < $leaseMonths; $i++) {
                    $month = $leaseStart->copy()->addMonthsNoOverflow($i);
                    $period = $periods->first(fn ($p) => $p['period']->format('Y-m') === $month->format('Y-m'));
                    $key = $period ? $periodKeyFor($period) : 'upcoming';
                    $share = $period && $period['expected'] > 0 ? min(100, (int) round($period['paid'] / $period['expected'] * 100)) : 0;
                    $stripCells[] = [
                        'month' => $month,
                        'key' => $key,
                        'share' => $key === 'paid' || $key === 'advance' ? 100 : $share,
                        'detail' => $periodDetails->get($month->format('Y-m')),
                        'label' => $period
                            ? $month->format('F Y') . ': ' . $periodStyles[$key]['label'] . ', ' . $peso($period['paid']) . ' of ' . $peso($period['expected'])
                            : $month->format('F Y') . ': not billed yet',
                    ];
                }
                $legendKeys = collect($stripCells)->pluck('key')->map(fn ($k) => $k === 'advance_part' ? 'advance' : $k)->unique();
@endphp

                {{-- Lease: one bar per month, filled by how much of it is paid, grouped by year. The current year and any
                     year that holds an unpaid month are always shown; "See more" opens the rest in place (no page load). --}}
                @php
                    $yearGroups = collect($stripCells)->groupBy(fn ($c) => $c['month']->format('Y'));
                    $unpaidKeys = ['overdue', 'partial', 'due'];
                    $shownYears = $yearGroups->filter(fn ($cells, $year) => $year === now()->format('Y')
                        || collect($cells)->contains(fn ($c) => in_array($c['key'], $unpaidKeys, true)))->keys();
                    // An ended lease with nothing owed still shows its latest year rather than an empty tile.
                    if ($shownYears->isEmpty()) {
                        $shownYears = collect([$yearGroups->keys()->last()]);
                    }
                    $hiddenMonthCount = $yearGroups->reject(fn ($cells, $year) => $shownYears->contains($year))->sum(fn ($cells) => count($cells));
                    // A lease of 12 months or fewer fits on one row from md up, so its year groups sit side by side
                    // (each as wide as its months, one bar the same width as in a full year) and nothing is hidden.
                    // Longer leases keep the stacked years and "See more". On a phone every lease keeps 6 bars a row.
                    $fitsOneRow = count($stripCells) <= 12;
                @endphp
                <section aria-labelledby="lease-title" x-data="{ moreYears: false }" class="col-span-2 lg:col-span-4 {{ $tile }} p-4 sm:p-5 flex flex-col">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 id="lease-title" class="text-[14px] font-semibold text-[#060D26]">Rental</h2>
                        <p class="text-[12px] text-[#5B6A8E] tabular-nums">Month {{ $monthsElapsed }} of {{ $leaseMonths }}{{ $reservation->target_move_out_date ? '' : '+' }}</p>
                    </div>
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">
                        {{ $leaseStart->format('M Y') }} – {{ $leaseStart->copy()->addMonthsNoOverflow($leaseMonths - 1)->format('M Y') }}@unless($reservation->target_move_out_date) · open-ended @endunless
                    </p>

                    {{-- --n = months in the lease, --m = months in the year group: the one-row layout sizes from them. --}}
                    <div class="mt-3.5 space-y-4 {{ $fitsOneRow ? 'md:flex md:gap-x-6 md:space-y-0 md:[width:calc(var(--n)/12*100%)]' : '' }}" @if($fitsOneRow) style="--n: {{ count($stripCells) }}" @endif>
                        @foreach($yearGroups as $year => $cells)
                            @php $yearShown = $shownYears->contains($year); @endphp
                            <div @unless($yearShown) x-show="moreYears" x-cloak @endunless @if($fitsOneRow) class="min-w-0 md:!block md:[flex:var(--m)_1_0%]" style="--m: {{ count($cells) }}" @endif>
                                <h3 class="text-[12px] font-bold tabular-nums text-[#5B6A8E]">{{ $year }}</h3>
                                <ol class="mt-1 grid grid-cols-6 gap-x-1.5 gap-y-1 {{ $fitsOneRow ? 'md:[grid-template-columns:repeat(var(--m),minmax(0,1fr))]' : 'sm:grid-cols-12' }}" aria-label="Rent by month, {{ $year }}">
                                    @foreach($cells as $cell)
                                        @php
                                            $tone = $stripTones[$cell['key']];
                                            $isNow = $cell['month']->format('Y-m') === $thisMonth;
                                        @endphp
                                        <li>
                                            @if($cell['detail'])
                                                <button type="button" aria-haspopup="dialog" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}"
                                                    @click="$dispatch('open-period-detail', {{ Js::from($cell['detail']) }})"
                                                    class="group block w-full min-h-[44px] pt-2 text-left rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2 cursor-pointer">
                                            @else
                                                <div role="img" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}" class="block min-h-[44px] pt-2">
                                            @endif
                                                <span class="block h-2.5 rounded-full overflow-hidden {{ $tone['track'] }} transition-transform duration-200 group-hover:scale-y-[1.35] origin-bottom">
                                                    @if($cell['share'] > 0)
                                                        <span class="block h-full rounded-full {{ $tone['fill'] }}" style="width: {{ $cell['share'] }}%"></span>
                                                    @endif
                                                </span>
                                                <span class="mt-2 flex items-center gap-1 text-[11.5px] leading-none {{ $isNow ? 'font-bold text-[#060D26]' : 'font-medium text-[#5B6A8E] group-hover:text-[#060D26]' }}">
                                                    @if($isNow)<span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shrink-0" aria-hidden="true"></span>@endif
                                                    {{ $cell['month']->format('M') }}
                                                    @if($isNow)<span class="sr-only">(this month)</span>@endif
                                                </span>
                                            @if($cell['detail'])
                                                </button>
                                            @else
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>

                    @if($hiddenMonthCount > 0)
                        <button type="button" @click="moreYears = !moreYears" :aria-expanded="moreYears"
                            class="{{ $fitsOneRow ? 'md:hidden' : '' }} mt-3 self-start inline-flex h-11 items-center text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                            <span x-text="moreYears ? 'See less' : 'See more · {{ $hiddenMonthCount }} more {{ \Illuminate\Support\Str::plural('month', $hiddenMonthCount) }}'"></span>
                        </button>
                    @endif

                    <ul class="mt-auto pt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-[#5B6A8E]" aria-label="Legend">
                        @foreach($legendKeys as $key)
                            <li class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-1.5 rounded-full {{ $key === 'upcoming' || $key === 'due' ? $stripTones[$key]['track'] : $stripTones[$key]['fill'] }}" aria-hidden="true"></span>
                                {{ $stripTones[$key]['legend'] }}
                            </li>
                        @endforeach
                    </ul>
                </section>
