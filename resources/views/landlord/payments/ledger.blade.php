@extends('layouts.landlord')

@section('page-title', 'Payments')

@section('content')
    @php
        $tenant = $reservation->tenant;
        $unit = $reservation->unit;
        $isWalkIn = (bool) $tenant?->is_walk_in;
        $isActive = $reservation->rental_status === 'Occupied';
        // Before move-in there is nothing to bill: the ledger derives periods from the move-in date, so showing it would report arrears for a tenant who has not arrived.
        $hasStarted = in_array($reservation->rental_status, ['Occupied', 'Completed'], true);
        $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1));

        // Period pill styling, one map so the table and the tiles can't drift.
        $periodStyles = [
            'paid'    => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'partial' => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'due'     => ['pill' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]', 'label' => 'Due'],
            // A month settled before it arrived. Distinct from plain Paid so a
            // landlord can tell rent already banked from rent still to come.
            'advance' => ['pill' => 'bg-[#ECEEF6] text-[#060D26] border-[#FF8A66]/25', 'label' => 'Paid · Advance'],
            // Part-covered by an overpayment. Deliberately NOT the amber
            // Partial pill: nobody is behind on a month that hasn't arrived,
            // and amber here would read as a collection problem.
            'advance_part' => ['pill' => 'bg-[#F7F8FC] text-[#060D26] border-[#FF8A66]/20', 'label' => 'Advance · part'],
        ];

        // A future period only exists in the ledger because it was paid into,
        // so it never carries an arrears colour.
        $periodStyleFor = function (array $period) use ($periodStyles) {
            if ($period['is_future']) {
                return $period['status'] === 'paid' ? $periodStyles['advance'] : $periodStyles['advance_part'];
            }

            return $periodStyles[$period['status']] ?? $periodStyles['due'];
        };

        $statusPill = match ($reservation->rental_status) {
            'Occupied'  => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
            'Completed' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]',
            default     => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        };

        // Payment status — same five values and same colours as
        // landlord/payments/index.blade.php's $paymentStyles, so a tenancy
        // reads identically whether the landlord arrived from the portfolio
        // table or opened this ledger directly.
        $paymentStatusStyles = [
            'overdue'    => ['dot' => 'bg-[#DC2626]', 'text' => 'text-[#DC2626]', 'label' => 'Overdue'],
            'partial'    => ['dot' => 'bg-[#B45309]', 'text' => 'text-[#B45309]', 'label' => 'Partial'],
            'upcoming'   => ['dot' => 'bg-[#5B6A8E]', 'text' => 'text-[#5B6A8E]', 'label' => 'Upcoming'],
            'paid'       => ['dot' => 'bg-[#15803D]', 'text' => 'text-[#15803D]', 'label' => 'Paid'],
            'paid_ahead' => ['dot' => 'bg-[#060D26]', 'text' => 'text-[#060D26]', 'label' => 'Paid Ahead'],
        ];
        $paymentStatusStyle = $paymentStatusStyles[$summary['paymentStatus']] ?? $paymentStatusStyles['upcoming'];

        $inputClass = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';

        $thumb = $unit?->media->firstWhere('media_type', 'Image');
    @endphp

    {{-- x-data only so the "Record payment" button has an Alpine scope to
         $dispatch from; the modal listens for the event on the window. --}}
    {{-- Totals first, then the rent calendar beside what needs doing (stacked on a phone), then the records in tabs. x-data gives the Record buttons a scope to $dispatch from and holds the tab. --}}
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16 space-y-5" x-data="{ tab: 'months' }">

        {{-- Header, bare on the page (DESIGN §6b) --}}
        <div>
            <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#5B6A8E] mb-3">
                <a href="{{ route('landlord.payments.index') }}" class="hover:text-[#060D26] transition-colors duration-200">Payments</a>
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span class="text-[#060D26] font-medium">{{ $tenant->name ?? 'Tenancy' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[12px] font-semibold text-[#5B6A8E]">Rent ledger{{ $tenantCode ? ' · '.$tenantCode : '' }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-[22px] sm:text-[26px] font-semibold tracking-tight text-[#060D26] truncate">
                            {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown tenant' }}
                        </h1>
                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $statusPill }}">
                            {{ $reservation->rental_status }}
                        </span>
                    </div>
                    <p class="text-[13px] text-[#5B6A8E]">
                        {{ $reservation->property->title ?? '' }} · {{ $unit->unit_label ?? 'No unit' }}
                        · <span class="tabular-nums">₱{{ number_format($summary['monthlyRent'], 2) }}</span>/mo, due day {{ $summary['dueDay'] }}
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                    @if($isActive && ! $isWalkIn && $summary['outstanding'] > 0)
                        <form method="POST" action="{{ route('landlord.tenancies.remind', $reservation) }}"
                            data-confirm="Send a rent reminder?" data-confirm-message="{{ $tenant->first_name }} gets a notification about the unpaid rent." data-confirm-button="Send reminder">
                            @csrf
                            <button type="submit"
                                class="w-full sm:w-auto h-11 px-4 inline-flex items-center justify-center rounded-xl border border-[#060D26]/25 bg-white text-[#060D26] text-sm font-semibold hover:border-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                                Remind tenant
                            </button>
                        </form>
                    @endif
                    @if($isActive)
                        <button type="button" @click="$dispatch('open-record-payment')"
                            class="h-11 px-5 inline-flex items-center justify-center gap-2 rounded-xl bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Record payment
                        </button>
                    @endif
                </div>
            </div>
        </div>
        {{-- Needed by the deposit tile, the Deposit tab and the add-deposit-charge modal, started or not. --}}
        @php
            $activeDepositCharges = $reservation->depositCharges->reject->isVoided()->values();
            $voidedDepositCharges = $reservation->depositCharges->filter->isVoided()->values();
            $canChargeDeposit = $summary['depositCollected'] > 0;
            $depositCategoryLabels = \App\Models\DepositCharge::CATEGORIES;
        @endphp

        @if($hasStarted)
                {{-- Rent calendar — the whole lease at a glance, one tile per month, then
                     only the months still owed with a one-tap Record. The ledger below
                     stays the detailed record; this is the quick read. --}}
                @php
                    $calStart = ($reservation->tenancyStartDate() ?? now())->copy()->startOfMonth();
                    $calMonths = $reservation->target_move_out_date
                        ? (int) round($calStart->diffInMonths($reservation->target_move_out_date->copy()->startOfMonth()))
                        : 12;
                    $calMonths = max(1, min($calMonths, 24));
                    $periodsByMonth = $periods->keyBy(fn ($p) => $p['period']->format('Y-m'));
                    $calTiles = [
                        'paid'         => 'bg-[#22C55E]/[0.07] border-[#22C55E]/25 text-[#15803D]',
                        'partial'      => 'bg-[#FBBF24]/[0.10] border-[#FBBF24]/35 text-[#B45309]',
                        'overdue'      => 'bg-[#EF4444]/[0.07] border-[#EF4444]/25 text-[#DC2626]',
                        'due'          => 'bg-[#F7F8FC] border-[#E2E4EC] text-[#5B6A8E]',
                        'advance'      => 'bg-[#ECEEF6] border-[#FF8A66]/25 text-[#060D26]',
                        'advance_part' => 'bg-[#F7F8FC] border-[#FF8A66]/20 text-[#060D26]',
                        'upcoming'     => 'bg-white border-dashed border-[#E2E4EC] text-[#5B6A8E]',
                    ];
                    $unpaidPeriods = $periods->reject(fn ($p) => $p['is_future'] || $p['status'] === 'paid')->values();
                @endphp
                @php
                    $allPayments = $monthlyTransactions->concat($otherCharges)
                        ->sortByDesc(fn ($p) => $p->paid_at ?? $p->created_at)
                        ->values();
                @endphp

        {{-- Where things stand: rent in, rent still owed, deposit still held. Only an overdue amount earns colour. --}}
        @php
            $extraCharges = round($summary['otherCollected'] - $summary['depositCollected'], 2);
            $isOverdue = $summary['overdueCount'] > 0;
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-px overflow-hidden rounded-2xl border border-[#E2E4EC] bg-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
            <div class="bg-white p-4 sm:p-5">
                <p class="text-[12px] font-semibold text-[#5B6A8E]">Rent collected</p>
                <p class="mt-2 text-[22px] sm:text-[24px] font-extrabold leading-none tabular-nums text-[#060D26]">&#8369;{{ number_format($summary['monthlyCollected'], 2) }}</p>
                <p class="mt-2 text-[12.5px] text-[#5B6A8E]">{{ $extraCharges > 0 ? '+ ₱'.number_format($extraCharges, 2).' other charges' : 'Rent payments recorded' }}</p>
            </div>
            <div class="bg-white p-4 sm:p-5">
                <p class="text-[12px] font-semibold text-[#5B6A8E]">Unpaid</p>
                <p class="mt-2 text-[22px] sm:text-[24px] font-extrabold leading-none tabular-nums {{ $isOverdue ? 'text-[#DC2626]' : 'text-[#060D26]' }}">&#8369;{{ number_format($summary['outstanding'], 2) }}</p>
                <p class="mt-2 text-[12.5px] {{ $isOverdue ? 'text-[#DC2626]' : 'text-[#5B6A8E]' }}">
                    @if($isOverdue)
                        ₱{{ number_format($summary['overdueAmount'], 2) }} overdue · {{ $summary['overdueCount'] }} {{ \Illuminate\Support\Str::plural('month', $summary['overdueCount']) }}
                    @elseif($isActive && $summary['nextDueDate'])
                        Next due {{ $summary['nextDueDate']->format('M j, Y') }}
                    @else
                        Nothing owed
                    @endif
                </p>
            </div>
            <div class="bg-white p-4 sm:p-5">
                <p class="text-[12px] font-semibold text-[#5B6A8E]">Deposit held</p>
                <p class="mt-2 text-[22px] sm:text-[24px] font-extrabold leading-none tabular-nums text-[#060D26]">&#8369;{{ number_format($summary['depositRemaining'], 2) }}</p>
                <p class="mt-2 text-[12.5px] text-[#5B6A8E]">
                    @if($summary['depositCollected'] > 0)
                        of ₱{{ number_format($summary['depositCollected'], 2) }} collected
                        @if($canChargeDeposit && $summary['depositRemaining'] > 0)
                            · <button type="button" @click="$dispatch('open-add-deposit-charge')" class="font-semibold text-[#060D26] underline underline-offset-2 hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer">Deduct</button>
                        @endif
                    @else
                        No deposit recorded
                    @endif
                </p>
            </div>
        </div>

        @php
            $pendingClaims = $reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'Monthly');
            $needsAction = $pendingClaims->isNotEmpty() || $unpaidPeriods->isNotEmpty();
        @endphp
        {{-- The calendar and what needs doing sit side by side on a wide screen; with nothing to do, the calendar takes the row. --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">
            <div class="{{ $needsAction ? 'lg:col-span-3' : 'lg:col-span-5' }}">
                <x-card flush>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                        <div>
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Rent calendar</h2>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                ₱{{ number_format($summary['monthlyRent'], 2) }} per month, due on day {{ $summary['dueDay'] }}.
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 2xl:grid-cols-6 gap-2 p-4 sm:p-5">
                        @for($i = 0; $i < $calMonths; $i++)
                            @php
                                $month = $calStart->copy()->addMonthsNoOverflow($i);
                                $p = $periodsByMonth->get($month->format('Y-m'));
                                if ($p) {
                                    $tileStyle = $periodStyleFor($p);
                                    $tileKey = $p['is_future'] ? ($p['status'] === 'paid' ? 'advance' : 'advance_part') : $p['status'];
                                    $tileLabel = $tileStyle['label'];
                                } else {
                                    $tileKey = 'upcoming';
                                    $tileLabel = 'Upcoming';
                                }
                            @endphp
                            <div class="rounded-xl border px-3 py-2.5 {{ $calTiles[$tileKey] ?? $calTiles['upcoming'] }}">
                                <p class="text-[11px] font-bold uppercase tracking-wide opacity-80">{{ $month->format('M Y') }}</p>
                                <p class="mt-0.5 text-[12.5px] font-semibold leading-tight">{{ $tileLabel }}</p>
                            </div>
                        @endfor
                    </div>
                </x-card>
            </div>
        {{-- Needs action: payment reports to answer, then the months still owed. Only when there is something. --}}
        @if($needsAction)
            <section aria-labelledby="ledger-needs-action" class="space-y-3 lg:col-span-2">
                <h2 id="ledger-needs-action" class="text-[12px] font-bold uppercase tracking-wide text-[#5B6A8E]">Needs action</h2>
                @if($pendingClaims->isNotEmpty())
                    <div class="space-y-3">
                        @include('payments.partials._claim-review', ['claims' => $pendingClaims, 'tenantName' => $reservation->tenant?->first_name])
                    </div>
                @endif
                @if($unpaidPeriods->isNotEmpty())
                    <x-card flush>
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                            <h3 class="text-[16px] font-semibold text-[#060D26]">Months still owed</h3>
                        </div>
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($unpaidPeriods as $unpaid)
                                <li class="flex items-center gap-3 px-5 sm:px-6 py-3.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $unpaid['label'] }}</p>
                                        <p class="text-[12px] text-[#5B6A8E]">Due {{ $unpaid['due_on']->format('M d, Y') }}</p>
                                    </div>
                                    <p class="text-[13.5px] font-semibold tabular-nums {{ $unpaid['status'] === 'overdue' ? 'text-[#DC2626]' : 'text-[#060D26]' }}">₱{{ number_format(max(0, $unpaid['balance']), 2) }}</p>
                                    @if($isActive)
                                        <button type="button"
                                            @click="$dispatch('open-record-payment', { period: '{{ $unpaid['period']->toDateString() }}', balance: '{{ number_format(max(0, $unpaid['balance']), 2, '.', '') }}' })"
                                            class="h-9 px-3.5 rounded-lg border border-[#060D26]/25 text-[12.5px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] hover:border-[#060D26]/40 transition-colors duration-200 cursor-pointer">
                                            Record
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </section>
        @endif
        </div>

        {{-- Records, one tab at a time. Panels are hidden with x-show, so all of them are still in the page. --}}
        @php
            $hasDeposit = $canChargeDeposit || $activeDepositCharges->isNotEmpty() || $voidedDepositCharges->isNotEmpty();
            $tabList = array_filter([
                'months' => 'Months',
                'payments' => 'Payments'.($allPayments->isNotEmpty() ? ' ('.$allPayments->count().')' : ''),
                'deposit' => $hasDeposit ? 'Deposit' : null,
                'voided' => $voidedTransactions->isNotEmpty() ? 'Voided ('.$voidedTransactions->count().')' : null,
            ]);
        @endphp
        <section aria-label="Records" class="space-y-3">
            <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-[#E2E4EC]" role="tablist">
                @foreach($tabList as $key => $label)
                    <button type="button" role="tab" @click="tab = @js($key)" :aria-selected="tab === @js($key)"
                        class="shrink-0 px-4 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-px whitespace-nowrap transition-colors duration-200 cursor-pointer"
                        :class="tab === @js($key) ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div x-show="tab === 'months'" role="tabpanel">
                {{-- Rent ledger --}}
                @php
                    // Latest 6 periods by default; an older Overdue/Partial one is never hidden —
                    // an unpaid month is exactly what this table is for.
                    $ledgerCap = 6;
                    $ledgerTotal = $periods->count();
                    $isCollapsed = fn (array $period, int $i) => $i < $ledgerTotal - $ledgerCap
                        && ($period['is_future'] || ! in_array($period['status'], ['overdue', 'partial'], true));
                    $hiddenPeriods = $periods->filter(fn ($period, $i) => $isCollapsed($period, $i))->count();
                    // Nothing owed → the calendar above already tells the story, so the rows start
                    // folded away. Any overdue/partial month keeps the table open: that is when the numbers matter.
                    $ledgerNeedsAttention = $periods->contains(fn ($p) => ! $p['is_future'] && in_array($p['status'], ['overdue', 'partial'], true));
                    $ledgerStartsOpen = $periods->isEmpty() || $ledgerNeedsAttention;
                @endphp
                <x-card flush x-data="{ moreRows: false, open: {{ $ledgerStartsOpen ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-[#E2E4EC]"
                        :class="open ? 'border-b' : ''">
                        <div>
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Rent ledger</h2>
                            @if($summary['prepaidThrough'])
                                <p class="text-[12px] font-medium text-[#060D26] mt-0.5">
                                    Paid in advance through {{ $summary['prepaidThrough']->format('M Y') }}.
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($hiddenPeriods > 0)
                                <button type="button" x-show="open" @click="moreRows = !moreRows" :aria-expanded="moreRows"
                                    class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] text-[12.5px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer">
                                    <span x-text="moreRows ? 'Show latest only' : 'Show all {{ $ledgerTotal }} periods'"></span>
                                </button>
                            @endif
                            @unless($ledgerStartsOpen)
                                <button type="button" @click="open = !open" :aria-expanded="open"
                                    class="h-9 px-3.5 rounded-lg border border-[#E2E4EC] text-[12.5px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer">
                                    <span x-text="open ? 'Hide payment details' : 'Show payment details'"></span>
                                </button>
                            @endunless
                        </div>
                    </div>

                    @if($periods->isEmpty())
                        <div class="flex flex-col items-center justify-center py-10 px-6 text-center">
                            <p class="text-[14px] font-semibold text-[#060D26]">No billing periods yet</p>
                            <p class="text-[13px] text-[#5B6A8E] mt-1">
                                Rent starts accruing from the move-in date.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto" x-show="open" @unless($ledgerStartsOpen) x-cloak @endunless>
                            <table class="w-full min-w-[640px]">
                                <thead class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Period</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Due on</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Expected</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Paid</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Balance</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($periods as $period)
                                        @php
                                            $style = $periodStyleFor($period);
                                            $detail = \App\Services\RentLedger::periodPayload($period);
                                            $detail['pill'] = $style['pill'];
                                            $detail['status_label'] = $style['label'];
                                            $detail['payments'] = array_map(
                                                fn ($pay) => $pay['can_void'] ? $pay + ['void_action' => route('landlord.payments.void', $pay['id'])] : $pay,
                                                $detail['payments']
                                            );
                                            if ($period['late_fee'] > 0) {
                                                $detail['waive_action'] = route('landlord.tenancies.waiveLateFee', $reservation);
                                            }
                                        @endphp
                                        <tr class="hover:bg-[#ECEEF6] transition-colors duration-200 cursor-pointer"
                                            @if($isCollapsed($period, $loop->index)) x-show="moreRows" x-cloak @endif
                                            @click="$dispatch('open-period-detail', {{ Js::from($detail) }})">
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <button type="button" aria-haspopup="dialog" class="text-left text-[13.5px] font-semibold text-[#060D26] cursor-pointer focus-visible:underline">{{ $period['label'] }}</button>
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap">
                                                {{ $period['due_on']->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['expected'], 2) }}
                                                @if($period['late_fee'] > 0)
                                                    <span class="block text-[11px] text-[#B45309]">incl. ₱{{ number_format($period['late_fee'], 2) }} late fee</span>
                                                @elseif($period['late_fee_waived'])
                                                    <span class="block text-[11px] text-[#5B6A8E]">late fee waived</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['paid'], 2) }}
                                            </td>
                                            {{-- Red is for money owed. A future month's balance is
                                                 not owed yet, so it stays neutral. --}}
                                            <td class="px-4 py-3.5 text-[13px] text-right whitespace-nowrap {{ $period['balance'] > 0 && ! $period['is_future'] ? 'font-semibold text-[#DC2626]' : 'text-[#5B6A8E]' }}">
                                                ₱{{ number_format(max(0, $period['balance']), 2) }}
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">
                                                        {{ $style['label'] }}
                                                    </span>
                                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="shrink-0 text-[#94A3B8]" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            <div x-show="tab === 'payments'" x-cloak role="tabpanel">
                @if($allPayments->isNotEmpty())
                    {{-- Capped to the 6 most recent so a long-running tenancy doesn't
                         push everything below it off-screen — same "View all (N)"
                         expand pattern as the unit picker on properties/show. --}}
                    <x-card flush x-data="{ moreRows: false }">
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Payments</h2>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5">Every payment received, most recent first.</p>
                        </div>
                        {{-- Phone: one stacked card per payment (mobile-first, DESIGN §0b) --}}
                        <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                            @foreach($allPayments as $txn)
                                @php
                                    $appliedTo = $txn->payment_type === 'Monthly'
                                        ? (optional($txn->billing_period)->format('M Y') ?? '—') . ' Rent'
                                        : $txn->payment_type;
                                @endphp
                                <li class="px-5 py-3.5" @if($loop->index >= 3) x-show="moreRows" x-cloak @endif>
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $appliedTo }}</p>
                                        <p class="text-[13.5px] font-semibold text-[#060D26] tabular-nums whitespace-nowrap">₱{{ number_format((float) $txn->amount, 2) }}</p>
                                    </div>
                                    <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
                                        {{ optional($txn->paid_at)->format('M d, Y') ?? '—' }}
                                        · {{ $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by you' : 'Online') }}
                                        @if($txn->reference_no) · Ref {{ $txn->reference_no }} @endif
                                    </p>
                                    @if($txn->canBeVoided())
                                        <button type="button"
                                            @click="$dispatch('open-void-payment', {
                                                id: {{ $txn->payment_id }},
                                                action: @js(route('landlord.payments.void', $txn)),
                                                amount: {{ (float) $txn->amount }},
                                                label: @js($appliedTo . ', recorded ' . (optional($txn->paid_at)->format('M d, Y') ?? ''))
                                            })"
                                            class="mt-1 inline-flex h-9 items-center text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
                                            Void
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        {{-- Desktop: full table --}}
                        <div class="hidden lg:block relative overflow-x-auto">
                            <table class="w-full min-w-[760px]">
                                <thead class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Reference</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Amount</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Method</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Applied to</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($allPayments as $txn)
                                        @php
                                            $isRent = $txn->payment_type === 'Monthly';
                                            $appliedTo = $isRent
                                                ? (optional($txn->billing_period)->format('M Y') ?? '—') . ' Rent'
                                                : $txn->payment_type;
                                        @endphp
                                        <tr class="hover:bg-[#ECEEF6] transition-colors duration-200"
                                            @if($loop->index >= 3) x-show="moreRows" x-cloak @endif>
                                            <td class="px-5 sm:px-6 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap">
                                                {{ optional($txn->paid_at)->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E]">{{ $txn->reference_no ?: '—' }}</td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right tabular-nums whitespace-nowrap">
                                                ₱{{ number_format((float) $txn->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E]">
                                                {{ $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by you' : 'Online') }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap">{{ $appliedTo }}</td>
                                            <td class="px-5 sm:px-6 py-3.5 text-right whitespace-nowrap">
                                                @if($txn->canBeVoided())
                                                    <button type="button"
                                                        @click="$dispatch('open-void-payment', {
                                                            id: {{ $txn->payment_id }},
                                                            action: @js(route('landlord.payments.void', $txn)),
                                                            amount: {{ (float) $txn->amount }},
                                                            label: @js($appliedTo . ', recorded ' . (optional($txn->paid_at)->format('M d, Y') ?? ''))
                                                        })"
                                                        class="text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
                                                        Void
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($allPayments->count() > 3)
                            <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                <button type="button" x-show="!moreRows" x-on:click="moreRows = true"
                                    class="text-[12.5px] font-semibold text-[#060D26] hover:underline cursor-pointer">
                                    Show all {{ $allPayments->count() }} transactions
                                </button>
                            </div>
                        @endif
                    </x-card>
                @else
                    <x-card><p class="text-[13.5px] text-[#5B6A8E]">No payments recorded yet.</p></x-card>
                @endif
            </div>

            <div x-show="tab === 'deposit'" x-cloak role="tabpanel" class="space-y-5">
                {{-- Security deposit charges — separate from the tiles above:
                     those show Held/Charged/Left as numbers, this is the actual
                     claim-by-claim record a tenant needs to see why. --}}
                @if($canChargeDeposit || $activeDepositCharges->isNotEmpty())
                    <x-card flush x-data="{ moreCharges: false }">
                        {{-- With no charges the card is just this one row; the divider only appears once there is a list under it. --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-[#E2E4EC] {{ $activeDepositCharges->isNotEmpty() ? 'border-b' : '' }}">
                            <div>
                                <h2 class="text-[16px] font-semibold text-[#060D26]">Security deposit</h2>
                                <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                    ₱{{ number_format($summary['depositRemaining'], 2) }} of ₱{{ number_format($summary['depositCollected'], 2) }} still held{{ $activeDepositCharges->isEmpty() ? ', no deductions.' : '.' }}
                                </p>
                            </div>
                            @if($canChargeDeposit)
                                <button type="button" @click="$dispatch('open-add-deposit-charge')"
                                    title="For damages or unpaid bills at move-out. Rent payments go under Record payment."
                                    {{ $summary['depositRemaining'] <= 0 ? 'disabled' : '' }}
                                    class="h-10 px-4 inline-flex items-center gap-2 rounded-xl text-sm font-semibold transition-colors duration-200
                                        {{ $summary['depositRemaining'] <= 0
                                            ? 'bg-[#ECEEF6] text-[#5B6A8E] cursor-not-allowed'
                                            : 'bg-white border border-[#060D26]/25 hover:bg-[#F7F8FC] hover:border-[#060D26]/40 text-[#060D26] cursor-pointer' }}">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                    </svg>
                                    Deduct from deposit
                                </button>
                            @endif
                        </div>

                        @if($activeDepositCharges->isEmpty())
                            {{-- Nothing to list — the header row above already says so. --}}
                        @else
                            {{-- Phone: one stacked card per charge (mobile-first, DESIGN §0b) --}}
                            <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                                @foreach($activeDepositCharges as $charge)
                                    <li class="px-5 py-3.5" @if($loop->index >= 6) x-show="moreCharges" x-cloak @endif>
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $depositCategoryLabels[$charge->category] ?? $charge->category }}</p>
                                            <p class="text-[13.5px] font-semibold text-[#DC2626] tabular-nums whitespace-nowrap">-₱{{ number_format((float) $charge->amount, 2) }}</p>
                                        </div>
                                        <p class="mt-0.5 text-[12px] text-[#5B6A8E]">{{ $charge->description }}</p>
                                        <p class="mt-1 text-[11.5px] text-[#5B6A8E]">
                                            {{ optional($charge->charged_at)->format('M d, Y') }}
                                            · {{ trim(($charge->charger->first_name ?? '') . ' ' . ($charge->charger->last_name ?? '')) ?: 'You' }}
                                        </p>
                                        <button type="button"
                                            @click="$dispatch('open-void-deposit-charge', {
                                                id: {{ $charge->deposit_charge_id }},
                                                action: @js(route('landlord.depositCharges.void', $charge)),
                                                amount: {{ (float) $charge->amount }},
                                                label: @js(($depositCategoryLabels[$charge->category] ?? $charge->category) . ', recorded ' . (optional($charge->charged_at)->format('M d, Y') ?? ''))
                                            })"
                                            class="mt-1 inline-flex h-9 items-center text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
                                            Void
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Desktop: full table --}}
                            <div class="hidden lg:block relative overflow-x-auto">
                                <table class="w-full min-w-[760px]">
                                    <thead class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                        <tr>
                                            <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Date</th>
                                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Category</th>
                                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Description</th>
                                            <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Amount</th>
                                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Recorded by</th>
                                            <th scope="col" class="px-5 sm:px-6 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">
                                                <span class="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#E2E4EC]">
                                        @foreach($activeDepositCharges as $charge)
                                            <tr class="hover:bg-[#ECEEF6] transition-colors duration-200"
                                                @if($loop->index >= 6) x-show="moreCharges" x-cloak @endif>
                                                <td class="px-5 sm:px-6 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap">
                                                    {{ optional($charge->charged_at)->format('M d, Y') }}
                                                </td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap">{{ $depositCategoryLabels[$charge->category] ?? $charge->category }}</td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] max-w-xs truncate" title="{{ $charge->description }}">{{ $charge->description }}</td>
                                                <td class="px-4 py-3.5 text-[13px] font-semibold text-[#DC2626] text-right tabular-nums whitespace-nowrap">
                                                    -₱{{ number_format((float) $charge->amount, 2) }}
                                                </td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap">
                                                    {{ trim(($charge->charger->first_name ?? '') . ' ' . ($charge->charger->last_name ?? '')) ?: 'You' }}
                                                </td>
                                                <td class="px-5 sm:px-6 py-3.5 text-right whitespace-nowrap">
                                                    <button type="button"
                                                        @click="$dispatch('open-void-deposit-charge', {
                                                            id: {{ $charge->deposit_charge_id }},
                                                            action: @js(route('landlord.depositCharges.void', $charge)),
                                                            amount: {{ (float) $charge->amount }},
                                                            label: @js(($depositCategoryLabels[$charge->category] ?? $charge->category) . ', recorded ' . (optional($charge->charged_at)->format('M d, Y') ?? ''))
                                                        })"
                                                        class="text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
                                                        Void
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($activeDepositCharges->count() > 6)
                                <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                    <button type="button" x-show="!moreCharges" x-on:click="moreCharges = true"
                                        class="text-[12.5px] font-semibold text-[#060D26] hover:underline cursor-pointer">
                                        Show all {{ $activeDepositCharges->count() }} charges
                                    </button>
                                </div>
                            @endif
                        @endif
                    </x-card>
                @endif
                {{-- Voided deposit charges — kept on the page deliberately, same
                     reasoning as the payments "Voided entries" card below. --}}
                @if($voidedDepositCharges->isNotEmpty())
                    <x-card flush>
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Voided deposit charges</h2>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5">Corrections made to this deposit's ledger. These do not count toward anything.</p>
                        </div>
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($voidedDepositCharges as $charge)
                                <li class="px-5 sm:px-6 py-3.5">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-[13.5px] font-semibold text-[#5B6A8E] line-through">{{ $depositCategoryLabels[$charge->category] ?? $charge->category }}</p>
                                        <p class="text-[13.5px] font-semibold text-[#5B6A8E] line-through tabular-nums whitespace-nowrap">₱{{ number_format((float) $charge->amount, 2) }}</p>
                                    </div>
                                    <p class="mt-0.5 text-[12px] text-[#5B6A8E]">{{ $charge->description }}</p>
                                    <p class="mt-1 text-[11.5px] text-[#5B6A8E]">
                                        Voided {{ optional($charge->voided_at)->format('M d, Y') }} — {{ $charge->voidReasonLabel() }}
                                        @if($charge->void_note) — {{ $charge->void_note }} @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </div>

            <div x-show="tab === 'voided'" x-cloak role="tabpanel">
                {{-- Voided entries — kept on the page deliberately: a voided
                     entry is removed from the arithmetic, never from the record. --}}
                @if($voidedTransactions->isNotEmpty())
                    <x-card flush x-data="{ moreRows: false }">
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Voided entries</h2>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5">Corrections made to this ledger. These do not count toward anything.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px]">
                                <thead class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Reference</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Amount</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Applied to</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Reason</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">Voided</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E4EC]">
                                    @foreach($voidedTransactions as $voided)
                                        <tr class="hover:bg-[#ECEEF6] transition-colors duration-200" @if($loop->index >= 6) x-show="moreRows" x-cloak @endif>
                                            <td class="px-5 sm:px-6 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap">
                                                {{ optional($voided->paid_at)->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E]">{{ $voided->reference_no ?: '—' }}</td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#5B6A8E] text-right whitespace-nowrap line-through">
                                                ₱{{ number_format((float) $voided->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap">
                                                {{ $voided->payment_type === 'Monthly' ? (optional($voided->billing_period)->format('M Y') . ' Rent') : $voided->payment_type }}
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <p class="text-[13px] text-[#060D26]">{{ $voided->voidReasonLabel() }}</p>
                                                @if($voided->void_note)
                                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">{{ $voided->void_note }}</p>
                                                @endif
                                                @if($voided->replacements->isNotEmpty())
                                                    <span class="inline-flex items-center h-5 px-2 mt-1 rounded-full border border-[#FF8A66]/25 bg-[#ECEEF6] text-[#060D26] text-[10px] font-bold">
                                                        Corrected
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#E2E4EC] bg-[#F7F8FC] text-[#5B6A8E] text-[11px] font-bold">
                                                    Voided
                                                </span>
                                                <p class="text-[11px] text-[#5B6A8E] mt-1 whitespace-nowrap">
                                                    {{ optional($voided->voided_at)->format('M d, Y') }}
                                                    @if($voided->voider)
                                                        by {{ $voided->voider->first_name }}
                                                    @endif
                                                </p>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($voidedTransactions->count() > 6)
                            <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                <button type="button" x-show="!moreRows" x-on:click="moreRows = true"
                                    class="text-[12.5px] font-semibold text-[#060D26] hover:underline cursor-pointer">
                                    Show all {{ $voidedTransactions->count() }} voided entries
                                </button>
                            </div>
                        @endif
                    </x-card>
                @endif
            </div>
        </section>

        @else
            <x-card>
                <p class="text-[14px] font-semibold text-[#060D26]">No rent to show yet</p>
                <p class="mt-1 text-[13.5px] text-[#5B6A8E]">{{ $tenant->first_name ?? 'The tenant' }} has not moved in yet, so no rent has fallen due. The ledger starts and payments can be recorded once they move in.</p>
            </x-card>
        @endif
    </div>

    {{-- Record payment modal — teleported so no ancestor's transform or
         overflow can clip it (RULES.md → Modals & Overlays). --}}
    @if($isActive)
        @include('landlord.payments.partials.record-payment-modal')
    @endif

    {{-- Void payment modal — unlike the record-payment modal above, this is
         NOT gated on $isActive: voiding a wrong entry on an ended tenancy is
         still fixing a wrong financial record (ReservationPolicy::voidPayment). --}}
    <x-rent-period-modal />
    @include('landlord.payments.partials.void-payment-modal')

    {{-- Deposit charge modals — same teleported shape as the payment ones
         above, not gated on $isActive: recordDepositCharge/voidDepositCharge
         are deliberately not restricted to an active tenancy. --}}
    @include('landlord.payments.partials.add-deposit-charge-modal')
    @include('landlord.payments.partials.void-deposit-charge-modal')

    <script src="{{ asset('js/date-picker.js') }}"></script>

    @if($isActive)
        {{-- The tenant may be paying rent in another tab right now — the
             webhook/checkout-return settle lands out-of-band, so without this
             the landlord has to manually refresh to see it. A full reload is
             deliberate, same as agreements/show.blade.php: this page's tiles,
             ledger rows and pill all derive from one server-rendered RentLedger
             read, and re-deriving that in JS would duplicate its status logic
             and risk drifting from it. --}}
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.Echo) return;
                    window.Echo.private('user.{{ auth()->id() }}')
                        .listen('.PaymentStatusUpdated', (e) => {
                            if (e.reservation_id === {{ $reservation->reservation_id }}) {
                                window.location.reload();
                            }
                        });
                });
            </script>
        @endpush
    @endif
@endsection
