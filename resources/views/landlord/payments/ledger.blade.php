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
        $tenantName = trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown tenant';
        $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1)) ?: '?';
        $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);

        // Period pill styling, one map so the table, the phone list and the lease strip can't drift.
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
            // and amber here would read as a collection problem (DESIGN §6n).
            'advance_part' => ['pill' => 'bg-[#F7F8FC] text-[#060D26] border-[#FF8A66]/20', 'label' => 'Advance · part'],
        ];

        // A future period only exists in the ledger because it was paid into, so it never carries an arrears colour.
        $periodKeyFor = fn (array $period) => $period['is_future']
            ? ($period['status'] === 'paid' ? 'advance' : 'advance_part')
            : (isset($periodStyles[$period['status']]) ? $period['status'] : 'due');
        $periodStyleFor = fn (array $period) => $periodStyles[$periodKeyFor($period)];

        // Same five values and colours as landlord/payments/index.blade.php's $paymentStyles,
        // so a tenancy reads identically from the portfolio table and from here.
        $paymentStatusStyles = [
            'overdue'    => ['dot' => 'bg-[#DC2626]', 'text' => 'text-[#DC2626]', 'label' => 'Overdue'],
            'partial'    => ['dot' => 'bg-[#B45309]', 'text' => 'text-[#B45309]', 'label' => 'Partial'],
            'upcoming'   => ['dot' => 'bg-[#5B6A8E]', 'text' => 'text-[#5B6A8E]', 'label' => 'Upcoming'],
            'paid'       => ['dot' => 'bg-[#15803D]', 'text' => 'text-[#15803D]', 'label' => 'Paid'],
            'paid_ahead' => ['dot' => 'bg-[#060D26]', 'text' => 'text-[#060D26]', 'label' => 'Paid Ahead'],
        ];
        $paymentStatusStyle = $paymentStatusStyles[$summary['paymentStatus']] ?? $paymentStatusStyles['upcoming'];

        $statusPill = match ($reservation->rental_status) {
            'Occupied'  => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
            'Completed' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]',
            default     => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        };

        // Read by the record-payment / deposit modals included at the bottom.
        $inputClass = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';

        // Needed by the statement, the Deposit tab and the add-deposit-charge modal, started or not.
        $activeDepositCharges = $reservation->depositCharges->reject->isVoided()->values();
        $voidedDepositCharges = $reservation->depositCharges->filter->isVoided()->values();
        $canChargeDeposit = $summary['depositCollected'] > 0;
        $depositCategoryLabels = \App\Models\DepositCharge::CATEGORIES;

        // "in 3 days" / "today" / "4 days ago" — a due date is easier to act on as a distance than as a date alone.
        $relativeDay = function ($date) {
            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

            return match (true) {
                $days === 0 => 'today',
                $days === 1 => 'tomorrow',
                $days === -1 => 'yesterday',
                $days > 1 => "in {$days} days",
                default => abs($days) . ' days ago',
            };
        };

        // Shared button looks, so every secondary action on the page is the same object.
        $btnOutline = 'h-11 px-4 inline-flex items-center justify-center gap-2 rounded-xl border border-[#060D26]/20 bg-white text-[#060D26] text-sm font-semibold hover:border-[#FF8A66] hover:text-[#B35A3D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer';
        $btnQuiet = 'inline-flex h-9 items-center text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer';
        $th = 'py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide';
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16 space-y-5" x-data>

        {{-- Header, bare on the page (DESIGN §6b). The tenant is the title; rent terms live in the statement below. --}}
        <header class="space-y-4 pb-2 sm:pb-3">
            <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-[13px] text-[#5B6A8E]">
                <a href="{{ route('landlord.payments.index') }}" class="hover:text-[#060D26] transition-colors duration-200">Payments</a>
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span class="text-[#060D26] font-medium" aria-current="page">Rent ledger</span>
            </nav>

            {{-- Identity only: the actions live in the Balance tile, next to the number they act on. Kept to two quiet
                 lines: the name, then where they live. "Occupied" is the normal state here, so the status pill only
                 shows when it is not (moved out, not moved in yet); the Balance tile already carries the paid state. --}}
            <div class="flex items-start gap-3.5 min-w-0">
                @if($tenant?->profile_picture)
                    <img src="{{ $tenant->profile_picture }}" alt="" class="w-12 h-12 rounded-full object-cover shrink-0 ring-1 ring-[#E2E4EC]">
                @else
                    <span class="w-12 h-12 rounded-full shrink-0 inline-flex items-center justify-center bg-[#060D26] text-white text-[15px] font-semibold tracking-wide" aria-hidden="true">{{ $initials }}</span>
                @endif
                <div class="min-w-0 pt-0.5">
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <h1 class="text-[22px] sm:text-[26px] leading-tight font-semibold tracking-tight text-[#060D26] break-words">{{ $tenantName }}</h1>
                        @unless($isActive)
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $statusPill }}">
                                {{ $reservation->rental_status }}
                            </span>
                        @endunless
                    </div>
                    {{-- Wraps instead of truncating: on a phone the property name is the part that got cut off. --}}
                    <p class="mt-1 text-[13.5px] leading-snug text-[#5B6A8E] line-clamp-2">
                        {{ $unit->unit_label ?? 'No unit' }} · {{ $reservation->property->title ?? '' }}@if($tenantCode)<span class="tabular-nums"> · {{ $tenantCode }}</span>@endif
                    </p>
                </div>
            </div>
        </header>

        @if($hasStarted)
            @php
                // One detail payload per period, keyed by month, shared by the lease strip and both month lists.
                $periodDetails = $periods->mapWithKeys(function ($period) use ($periodStyleFor, $reservation) {
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

                    return [$period['period']->format('Y-m') => $detail];
                });

                $unpaidPeriods = $periods->reject(fn ($p) => $p['is_future'] || $p['status'] === 'paid')->values();
                $pendingClaims = $reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'Monthly');
                $allPayments = $monthlyTransactions->concat($otherCharges)
                    ->sortByDesc(fn ($p) => $p->paid_at ?? $p->created_at)
                    ->values();
                $extraCharges = round($summary['otherCollected'] - $summary['depositCollected'], 2);
                $isOverdue = $summary['overdueCount'] > 0;

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

            @php
                // Bento tiles: one job each. Small tiles share one shape so the grid reads as a set.
                $tile = 'rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]';
                $miniTile = $tile . ' p-3.5 sm:p-4 flex flex-col justify-between gap-3 min-h-[100px]';
                $chip = 'w-7 h-7 rounded-lg inline-flex items-center justify-center shrink-0';
                $heroDot = [
                    'overdue' => 'bg-[#F87171]', 'partial' => 'bg-[#FBBF24]', 'upcoming' => 'bg-white/60',
                    'paid' => 'bg-[#4ADE80]', 'paid_ahead' => 'bg-[#FF8A66]',
                ][$summary['paymentStatus']] ?? 'bg-white/60';
                $oldest = $summary['oldestOverdue'];
                $daysLate = $oldest ? (int) $oldest['due_on']->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
                $leaseWide = $unpaidPeriods->isEmpty();
                $thisMonthShare = $summary['dueThisMonth'] > 0
                    ? min(100, (int) round($summary['collectedThisMonth'] / $summary['dueThisMonth'] * 100))
                    : null;
            @endphp

            {{-- Bento: Balance is the big navy tile with the actions; four small facts beside it; then the lease and
                 what needs doing; then the records. Phone: two columns, Balance and the wide tiles span both. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 lg:grid-flow-row-dense gap-3">

                {{-- Balance (2×2) --}}
                <section aria-labelledby="balance-title" class="col-span-2 lg:row-span-2 rounded-2xl bg-[#060D26] text-white p-5 flex flex-col gap-4 shadow-[0_8px_24px_-12px_rgba(6,13,38,0.45)]">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="balance-title" class="text-[13px] font-semibold text-white/70">Balance due</h2>
                        <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-white/10 text-[12px] font-semibold text-white">
                            <span class="w-1.5 h-1.5 rounded-full {{ $heroDot }}" aria-hidden="true"></span>{{ $paymentStatusStyle['label'] }}
                        </span>
                    </div>

                    <div>
                        <p class="text-[32px] sm:text-[40px] leading-none font-bold tracking-tight tabular-nums {{ $isOverdue ? 'text-[#FCA5A5]' : 'text-white' }}">
                            {{ $peso($summary['outstanding']) }}
                        </p>
                        <p class="mt-2 text-[13.5px] leading-relaxed text-white/75 max-w-[48ch]">
                            @if($isOverdue)
                                <span class="font-semibold text-[#FCA5A5]">{{ $peso($summary['overdueAmount']) }} overdue</span>
                                across {{ $summary['overdueCount'] }} {{ \Illuminate\Support\Str::plural('month', $summary['overdueCount']) }}.
                            @elseif($summary['outstanding'] > 0 && $summary['nextDueDate'])
                                Due <span class="font-semibold text-white">{{ $summary['nextDueDate']->format('M j, Y') }}</span>, {{ $relativeDay($summary['nextDueDate']) }}.
                            @elseif($summary['prepaidThrough'])
                                Paid in advance through <span class="font-semibold text-white">{{ $summary['prepaidThrough']->format('F Y') }}</span>.
                            @elseif($isActive)
                                All paid up. Nothing is owed right now.
                            @else
                                This lease has ended. Nothing is owed.
                            @endif
                        </p>
                    </div>

                    @if($thisMonthShare !== null)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[12.5px]">
                                <span class="text-white/70">{{ now()->format('F') }} collected</span>
                                <span class="tabular-nums text-white"><span class="font-semibold">{{ $peso($summary['collectedThisMonth']) }}</span><span class="text-white/60"> of {{ $peso($summary['dueThisMonth']) }}</span></span>
                            </div>
                            <div class="mt-2 h-1.5 rounded-full bg-white/15 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $thisMonthShare }}" aria-label="{{ now()->format('F') }} rent collected">
                                <div class="h-full rounded-full bg-[#FF8A66]" style="width: {{ $thisMonthShare }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($isActive)
                        <div class="mt-auto grid grid-cols-1 sm:flex gap-2">
                            <button type="button" @click="$dispatch('open-record-payment')"
                                class="h-11 sm:h-10 px-4 inline-flex items-center justify-center gap-2 rounded-xl bg-[#FF8A66] text-[#060D26] text-[13.5px] font-semibold hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#060D26] transition-colors duration-200 cursor-pointer">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Record payment
                            </button>
                            @if(! $isWalkIn && $summary['outstanding'] > 0)
                                <form method="POST" action="{{ route('landlord.tenancies.remind', $reservation) }}"
                                    data-confirm="Send a rent reminder?" data-confirm-message="{{ $tenant->first_name }} gets a notification about the unpaid rent." data-confirm-button="Send reminder">
                                    @csrf
                                    <button type="submit"
                                        class="w-full h-11 sm:h-10 px-4 inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 text-white text-[13.5px] font-semibold hover:bg-white/10 hover:border-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white transition-colors duration-200 cursor-pointer">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                        </svg>
                                        Remind tenant
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- Monthly rent --}}
                <div class="{{ $miniTile }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Monthly rent</p>
                        <span class="{{ $chip }} bg-[#ECEEF6] text-[#060D26]" aria-hidden="true">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                        </span>
                    </div>
                    <div>
                        <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['monthlyRent']) }}</p>
                        <p class="mt-1 text-[12px] text-[#5B6A8E]">Due on day {{ $summary['dueDay'] }}</p>
                    </div>
                </div>

                {{-- Next due, or the oldest unpaid month when someone is behind --}}
                <div class="{{ $miniTile }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12.5px] font-semibold text-[#5B6A8E]">{{ $isOverdue ? 'Oldest unpaid' : 'Next due' }}</p>
                        <span class="{{ $chip }} {{ $isOverdue ? 'bg-[#EF4444]/[0.10] text-[#DC2626]' : 'bg-[#ECEEF6] text-[#060D26]' }}" aria-hidden="true">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                        </span>
                    </div>
                    <div>
                        @if($isOverdue && $oldest)
                            <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight text-[#DC2626]">{{ $oldest['due_on']->format('M j') }}</p>
                            <p class="mt-1 text-[12px] font-semibold text-[#DC2626]">{{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} late</p>
                        @elseif($isActive && $summary['nextDueDate'])
                            <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight text-[#060D26]">{{ $summary['nextDueDate']->format('M j') }}</p>
                            <p class="mt-1 text-[12px] text-[#5B6A8E]">{{ ucfirst($relativeDay($summary['nextDueDate'])) }} · {{ $summary['nextDueDate']->format('Y') }}</p>
                        @else
                            <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight text-[#5B6A8E]">—</p>
                            <p class="mt-1 text-[12px] text-[#5B6A8E]">{{ $isActive ? 'Nothing scheduled' : 'Lease ended' }}</p>
                        @endif
                    </div>
                </div>

                {{-- Rent collected --}}
                <div class="{{ $miniTile }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Rent collected</p>
                        <span class="{{ $chip }} bg-[#22C55E]/[0.10] text-[#15803D]" aria-hidden="true">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                        </span>
                    </div>
                    <div>
                        <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['monthlyCollected']) }}</p>
                        <p class="mt-1 text-[12px] text-[#5B6A8E]">
                            {{ $extraCharges > 0 ? '+ '.$peso($extraCharges).' other charges' : 'From '.$monthlyTransactions->count().' '.\Illuminate\Support\Str::plural('payment', $monthlyTransactions->count()) }}
                        </p>
                    </div>
                </div>

                {{-- Deposit held --}}
                <div class="{{ $miniTile }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Deposit held</p>
                        <span class="{{ $chip }} bg-[#ECEEF6] text-[#060D26]" aria-hidden="true">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                        </span>
                    </div>
                    <div>
                        <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['depositRemaining']) }}</p>
                        <p class="mt-1 text-[12px] text-[#5B6A8E]">
                            @if($summary['depositCollected'] > 0)
                                of {{ $peso($summary['depositCollected']) }}
                                @if($canChargeDeposit && $summary['depositRemaining'] > 0)
                                    · <button type="button" @click="$dispatch('open-add-deposit-charge')" class="font-semibold text-[#060D26] underline underline-offset-2 decoration-[#060D26]/30 hover:text-[#B35A3D] hover:decoration-[#B35A3D] transition-colors duration-200 cursor-pointer">Deduct</button>
                                @endif
                            @else
                                No deposit recorded
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Payment reports to answer: each is its own tile, they are the most urgent thing on the page. --}}
                @foreach($pendingClaims as $claim)
                    <div class="col-span-2 [&>div]:h-full [&>div]:shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                        @include('payments.partials._claim-review', ['claims' => collect([$claim]), 'tenantName' => $reservation->tenant?->first_name])
                    </div>
                @endforeach

                {{-- Needs action: months still owed, each with a one-tap Record. Right of the lease on desktop, before it on a phone. --}}
                @if($unpaidPeriods->isNotEmpty())
                    <section aria-labelledby="ledger-needs-action" class="col-span-2 lg:col-start-3 {{ $tile }} overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-b border-[#E2E4EC]">
                            <h2 id="ledger-needs-action" class="flex items-center gap-2 text-[14px] font-semibold text-[#060D26]">
                                Needs action
                                <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-[#FF8A66] text-[11px] font-bold text-[#060D26] tabular-nums">{{ $unpaidPeriods->count() }}</span>
                            </h2>
                            <p class="text-[12.5px] text-[#5B6A8E]">Still owed <span class="font-semibold tabular-nums {{ $isOverdue ? 'text-[#DC2626]' : 'text-[#060D26]' }}">{{ $peso($summary['outstanding']) }}</span></p>
                        </div>
                        <ul class="divide-y divide-[#E2E4EC]">
                            @foreach($unpaidPeriods as $unpaid)
                                @php $owedOverdue = $unpaid['status'] === 'overdue'; @endphp
                                <li class="flex items-center gap-3 px-4 sm:px-5 py-2.5">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $owedOverdue ? 'bg-[#DC2626]' : ($unpaid['status'] === 'partial' ? 'bg-[#F59E0B]' : 'bg-[#5B6A8E]') }}" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $unpaid['label'] }}</p>
                                        <p class="text-[12px] {{ $owedOverdue ? 'text-[#DC2626]' : 'text-[#5B6A8E]' }}">{{ $owedOverdue ? 'Overdue ·' : 'Due' }} {{ $unpaid['due_on']->format('M j') }}</p>
                                    </div>
                                    <p class="text-[13.5px] font-semibold tabular-nums {{ $owedOverdue ? 'text-[#DC2626]' : 'text-[#060D26]' }}">{{ $peso(max(0, $unpaid['balance'])) }}</p>
                                    @if($isActive)
                                        <button type="button"
                                            @click="$dispatch('open-record-payment', { period: '{{ $unpaid['period']->toDateString() }}', balance: '{{ number_format(max(0, $unpaid['balance']), 2, '.', '') }}' })"
                                            aria-label="Record payment for {{ $unpaid['label'] }}"
                                            class="h-9 px-3.5 rounded-lg border border-[#060D26]/20 text-[12.5px] font-semibold text-[#060D26] hover:border-[#FF8A66] hover:text-[#B35A3D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer">
                                            Record
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Lease: one bar per month, filled by how much of it is paid. Tap a billed month for its detail. --}}
                <section aria-labelledby="lease-title" class="col-span-2 {{ $leaseWide ? 'lg:col-span-4' : 'lg:col-start-1' }} {{ $tile }} p-4 sm:p-5 flex flex-col">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 id="lease-title" class="text-[14px] font-semibold text-[#060D26]">Lease</h2>
                        <p class="text-[12px] text-[#5B6A8E] tabular-nums">Month {{ $monthsElapsed }} of {{ $leaseMonths }}{{ $reservation->target_move_out_date ? '' : '+' }}</p>
                    </div>
                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">
                        {{ $leaseStart->format('M Y') }} – {{ $leaseStart->copy()->addMonthsNoOverflow($leaseMonths - 1)->format('M Y') }}@unless($reservation->target_move_out_date) · open-ended @endunless
                    </p>

                    {{-- Columns follow the lease length, so a two-month lease fills the tile instead of using 2 of 12 slots. --}}
                    <ol class="mt-3.5 grid grid-cols-[repeat(var(--cols),minmax(0,1fr))] sm:grid-cols-[repeat(var(--cols-sm),minmax(0,1fr))] gap-x-1.5 gap-y-4"
                        style="--cols: {{ min(6, $leaseMonths) }}; --cols-sm: {{ $leaseWide ? min(12, $leaseMonths) : min(6, $leaseMonths) }}" aria-label="Rent by month">
                        @foreach($stripCells as $cell)
                            @php
                                $tone = $stripTones[$cell['key']];
                                $isNow = $cell['month']->format('Y-m') === $thisMonth;
                                $showYear = $loop->first || $cell['month']->month === 1;
                            @endphp
                            <li>
                                @if($cell['detail'])
                                    <button type="button" aria-haspopup="dialog" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}"
                                        @click="$dispatch('open-period-detail', {{ Js::from($cell['detail']) }})"
                                        class="group block w-full text-left rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2 cursor-pointer">
                                @else
                                    <div role="img" aria-label="{{ $cell['label'] }}" title="{{ $cell['label'] }}" class="block">
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
                                    <span class="mt-1 block text-[10.5px] leading-none text-[#5B6A8E]/80 tabular-nums {{ $showYear ? '' : 'invisible' }}" @unless($showYear) aria-hidden="true" @endunless>{{ $cell['month']->format('Y') }}</span>
                                @if($cell['detail'])
                                    </button>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    <ul class="mt-auto pt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-[12px] text-[#5B6A8E]" aria-label="Legend">
                        @foreach($legendKeys as $key)
                            <li class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-1.5 rounded-full {{ $key === 'upcoming' || $key === 'due' ? $stripTones[$key]['track'] : $stripTones[$key]['fill'] }}" aria-hidden="true"></span>
                                {{ $stripTones[$key]['legend'] }}
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Records: one card, the tabs are its header. Panels hide with x-show, so all of them stay in the page. --}}
                @php
                    $hasDeposit = $canChargeDeposit || $activeDepositCharges->isNotEmpty() || $voidedDepositCharges->isNotEmpty();
                    $tabList = array_filter([
                        'months'   => ['label' => 'Months', 'count' => $periods->count()],
                        'payments' => ['label' => 'Payments', 'count' => $allPayments->count()],
                        'deposit'  => $hasDeposit ? ['label' => 'Deposit', 'count' => null] : null,
                        'voided'   => $voidedTransactions->isNotEmpty() ? ['label' => 'Voided', 'count' => $voidedTransactions->count()] : null,
                    ]);
                    $ledgerCap = 6;
                    $ledgerTotal = $periods->count();
                    // Latest 6 months by default; an older Overdue/Partial one is never hidden — an unpaid month is what this list is for.
                    $isCollapsed = fn (array $period, int $i) => $i < $ledgerTotal - $ledgerCap
                        && ($period['is_future'] || ! in_array($period['status'], ['overdue', 'partial'], true));
                    $hiddenPeriods = $periods->filter(fn ($period, $i) => $isCollapsed($period, $i))->count();
                    // Newest month first: the one a landlord is asking about is almost always the latest.
                    $periodsNewestFirst = $periods->map(fn ($p, $i) => $p + ['_index' => $i])->reverse()->values();
                @endphp
                <x-card flush class="col-span-2 lg:col-span-4" x-data="{ tab: 'months' }">
                    <div class="flex gap-1 px-2 sm:px-3 overflow-x-auto overflow-y-hidden border-b border-[#E2E4EC]" role="tablist" aria-label="Records">
                        @foreach($tabList as $key => $t)
                            <button type="button" role="tab" id="ledger-tab-{{ $key }}" aria-controls="ledger-panel-{{ $key }}"
                                @click="tab = @js($key)" :aria-selected="tab === @js($key)" :tabindex="tab === @js($key) ? 0 : -1"
                                @keydown.arrow-right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                                @keydown.arrow-left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()"
                                class="shrink-0 inline-flex items-center gap-2 px-3 sm:px-4 h-11 text-[13.5px] font-semibold border-b-2 -mb-px whitespace-nowrap focus:outline-none focus-visible:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer"
                                :class="tab === @js($key) ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]'">
                                {{ $t['label'] }}
                                @if($t['count'])
                                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-bold tabular-nums transition-colors duration-200"
                                        :class="tab === @js($key) ? 'bg-[#060D26] text-white' : 'bg-[#ECEEF6] text-[#5B6A8E]'">{{ $t['count'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    {{-- Months --}}
                    <div x-show="tab === 'months'" role="tabpanel" id="ledger-panel-months" aria-labelledby="ledger-tab-months" x-data="{ moreRows: false }">
                        <h2 class="sr-only">Rent ledger</h2>
                        @if($periods->isEmpty())
                            <div class="px-6 py-12 text-center">
                                <p class="text-[14px] font-semibold text-[#060D26]">No billing periods yet</p>
                                <p class="text-[13px] text-[#5B6A8E] mt-1">Rent starts accruing from the move-in date.</p>
                            </div>
                        @else
                            {{-- Phone: one tappable row per month (DESIGN §0b) --}}
                            <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                                @foreach($periodsNewestFirst as $period)
                                    @php $style = $periodStyleFor($period); @endphp
                                    <li @if($isCollapsed($period, $period['_index'])) x-show="moreRows" x-cloak @endif>
                                        <button type="button" aria-haspopup="dialog"
                                            @click="$dispatch('open-period-detail', {{ Js::from($periodDetails->get($period['period']->format('Y-m'))) }})"
                                            class="w-full flex items-center gap-3 px-5 py-3.5 text-left hover:bg-[#F7F8FC] focus:outline-none focus-visible:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <p class="text-[14px] font-semibold text-[#060D26]">{{ $period['label'] }}</p>
                                                    <span class="inline-flex items-center h-5 px-2 rounded-full border text-[10.5px] font-bold {{ $style['pill'] }}">{{ $style['label'] }}</span>
                                                </div>
                                                <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
                                                    Due {{ $period['due_on']->format('M j, Y') }}
                                                    @if($period['late_fee'] > 0)
                                                        · <span class="text-[#B45309]">incl. {{ $peso($period['late_fee']) }} late fee</span>
                                                    @elseif($period['late_fee_waived'])
                                                        · late fee waived
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="text-[13.5px] font-semibold text-[#060D26] tabular-nums">{{ $peso($period['paid']) }}</p>
                                                <p class="text-[11.5px] tabular-nums {{ $period['balance'] > 0 && ! $period['is_future'] ? 'font-semibold text-[#DC2626]' : 'text-[#5B6A8E]' }}">
                                                    {{ $period['balance'] > 0 ? $peso($period['balance']).' left' : 'of '.$peso($period['expected']) }}
                                                </p>
                                            </div>
                                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="shrink-0 text-[#94A3B8]" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Desktop: full table --}}
                            <div class="hidden lg:block">
                                <table class="w-full">
                                    <thead class="border-b border-[#E2E4EC]">
                                        <tr>
                                            <th scope="col" class="pl-6 pr-4 text-left {{ $th }}">Month</th>
                                            <th scope="col" class="px-4 text-left {{ $th }}">Due on</th>
                                            <th scope="col" class="px-4 text-right {{ $th }}">Expected</th>
                                            <th scope="col" class="px-4 text-right {{ $th }}">Paid</th>
                                            <th scope="col" class="px-4 text-right {{ $th }}">Balance</th>
                                            <th scope="col" class="pl-4 pr-6 text-left {{ $th }}">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#E2E4EC]">
                                        @foreach($periodsNewestFirst as $period)
                                            @php $style = $periodStyleFor($period); @endphp
                                            <tr class="group hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer"
                                                @if($isCollapsed($period, $period['_index'])) x-show="moreRows" x-cloak @endif
                                                @click="$dispatch('open-period-detail', {{ Js::from($periodDetails->get($period['period']->format('Y-m'))) }})">
                                                <td class="pl-6 pr-4 py-3.5">
                                                    <button type="button" aria-haspopup="dialog" class="text-left text-[13.5px] font-semibold text-[#060D26] cursor-pointer focus:outline-none focus-visible:underline">{{ $period['label'] }}</button>
                                                </td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap tabular-nums">{{ $period['due_on']->format('M d, Y') }}</td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] text-right whitespace-nowrap tabular-nums">
                                                    {{ $peso($period['expected']) }}
                                                    @if($period['late_fee'] > 0)
                                                        <span class="block text-[11px] text-[#B45309]">incl. {{ $peso($period['late_fee']) }} late fee</span>
                                                    @elseif($period['late_fee_waived'])
                                                        <span class="block text-[11px] text-[#5B6A8E]">late fee waived</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right whitespace-nowrap tabular-nums">{{ $peso($period['paid']) }}</td>
                                                {{-- Red is for money owed. A future month's balance is not owed yet, so it stays neutral (DESIGN §6n). --}}
                                                <td class="px-4 py-3.5 text-[13px] text-right whitespace-nowrap tabular-nums {{ $period['balance'] > 0 && ! $period['is_future'] ? 'font-semibold text-[#DC2626]' : 'text-[#5B6A8E]' }}">
                                                    {{ $peso(max(0, $period['balance'])) }}
                                                </td>
                                                <td class="pl-4 pr-6 py-3.5">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">{{ $style['label'] }}</span>
                                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="shrink-0 text-[#94A3B8] group-hover:text-[#060D26] group-hover:translate-x-0.5 transition duration-200" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                                        </svg>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($hiddenPeriods > 0)
                                <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                    <button type="button" @click="moreRows = !moreRows" :aria-expanded="moreRows"
                                        class="text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                                        <span x-text="moreRows ? 'Show latest only' : 'Show all {{ $ledgerTotal }} months'"></span>
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Payments --}}
                    <div x-show="tab === 'payments'" x-cloak role="tabpanel" id="ledger-panel-payments" aria-labelledby="ledger-tab-payments" x-data="{ moreRows: false }">
                        @if($allPayments->isNotEmpty())
                            {{-- Phone: one stacked row per payment (DESIGN §0b) --}}
                            <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                                @foreach($allPayments as $txn)
                                    @php
                                        $appliedTo = $txn->payment_type === 'Monthly'
                                            ? (optional($txn->billing_period)->format('M Y') ?? '—') . ' Rent'
                                            : $txn->payment_type;
                                        $method = $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by you' : 'Online');
                                    @endphp
                                    <li class="px-5 py-3.5" @if($loop->index >= $ledgerCap) x-show="moreRows" x-cloak @endif>
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-[14px] font-semibold text-[#060D26]">{{ $appliedTo }}</p>
                                            <p class="text-[14px] font-semibold text-[#060D26] tabular-nums whitespace-nowrap">{{ $peso($txn->amount) }}</p>
                                        </div>
                                        <div class="mt-0.5 flex items-center justify-between gap-3">
                                            <p class="text-[12px] text-[#5B6A8E] min-w-0 truncate">
                                                {{ optional($txn->paid_at)->format('M j, Y') ?? '—' }} · {{ $method }}@if($txn->reference_no) · Ref {{ $txn->reference_no }}@endif
                                            </p>
                                            @if($txn->canBeVoided())
                                                <button type="button"
                                                    @click="$dispatch('open-void-payment', {
                                                        id: {{ $txn->payment_id }},
                                                        action: @js(route('landlord.payments.void', $txn)),
                                                        amount: {{ (float) $txn->amount }},
                                                        label: @js($appliedTo . ', recorded ' . (optional($txn->paid_at)->format('M d, Y') ?? ''))
                                                    })"
                                                    class="{{ $btnQuiet }} -my-2 shrink-0">
                                                    Void
                                                </button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Desktop: full table --}}
                            <div class="hidden lg:block">
                                <table class="w-full">
                                    <thead class="border-b border-[#E2E4EC]">
                                        <tr>
                                            <th scope="col" class="pl-6 pr-4 text-left {{ $th }}">Date</th>
                                            <th scope="col" class="px-4 text-left {{ $th }}">For</th>
                                            <th scope="col" class="px-4 text-left {{ $th }}">Method</th>
                                            <th scope="col" class="px-4 text-left {{ $th }}">Reference</th>
                                            <th scope="col" class="px-4 text-right {{ $th }}">Amount</th>
                                            <th scope="col" class="pl-4 pr-6 text-right {{ $th }}"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#E2E4EC]">
                                        @foreach($allPayments as $txn)
                                            @php
                                                $appliedTo = $txn->payment_type === 'Monthly'
                                                    ? (optional($txn->billing_period)->format('M Y') ?? '—') . ' Rent'
                                                    : $txn->payment_type;
                                            @endphp
                                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200" @if($loop->index >= $ledgerCap) x-show="moreRows" x-cloak @endif>
                                                <td class="pl-6 pr-4 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap tabular-nums">{{ optional($txn->paid_at)->format('M d, Y') ?? '—' }}</td>
                                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#060D26] whitespace-nowrap">{{ $appliedTo }}</td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E]">{{ $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by you' : 'Online') }}</td>
                                                <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] tabular-nums">{{ $txn->reference_no ?: '—' }}</td>
                                                <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right tabular-nums whitespace-nowrap">{{ $peso($txn->amount) }}</td>
                                                <td class="pl-4 pr-6 py-3.5 text-right whitespace-nowrap">
                                                    @if($txn->canBeVoided())
                                                        <button type="button"
                                                            @click="$dispatch('open-void-payment', {
                                                                id: {{ $txn->payment_id }},
                                                                action: @js(route('landlord.payments.void', $txn)),
                                                                amount: {{ (float) $txn->amount }},
                                                                label: @js($appliedTo . ', recorded ' . (optional($txn->paid_at)->format('M d, Y') ?? ''))
                                                            })"
                                                            class="{{ $btnQuiet }}">
                                                            Void
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($allPayments->count() > $ledgerCap)
                                <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                    <button type="button" x-show="!moreRows" @click="moreRows = true"
                                        class="text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                                        Show all {{ $allPayments->count() }} payments
                                    </button>
                                </div>
                            @endif
                        @else
                            <div class="px-6 py-12 text-center">
                                <p class="text-[14px] font-semibold text-[#060D26]">No payments recorded yet</p>
                                <p class="text-[13px] text-[#5B6A8E] mt-1">Payments {{ $tenant->first_name ?? 'the tenant' }} makes online, and ones you record, show up here.</p>
                                @if($isActive)
                                    <button type="button" @click="$dispatch('open-record-payment')" class="{{ $btnOutline }} mt-4">Record a payment</button>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Deposit: the claim-by-claim record behind the "Deposit held" figure. --}}
                    @if($hasDeposit)
                        <div x-show="tab === 'deposit'" x-cloak role="tabpanel" id="ledger-panel-deposit" aria-labelledby="ledger-tab-deposit" x-data="{ moreCharges: false }">
                            @php
                                $heldShare = $summary['depositCollected'] > 0
                                    ? max(0, min(100, (int) round($summary['depositRemaining'] / $summary['depositCollected'] * 100)))
                                    : 0;
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4 px-5 sm:px-6 py-5">
                                <div class="flex-1 min-w-0">
                                    <h2 class="text-[15px] font-semibold text-[#060D26]">Security deposit</h2>
                                    <p class="mt-0.5 text-[13px] text-[#5B6A8E] tabular-nums">
                                        <span class="font-semibold text-[#060D26]">{{ $peso($summary['depositRemaining']) }}</span> of {{ $peso($summary['depositCollected']) }} still held{{ $activeDepositCharges->isEmpty() ? ', no deductions.' : ', '.$peso($summary['depositCharged']).' deducted.' }}
                                    </p>
                                    @if($summary['depositCollected'] > 0)
                                        <div class="mt-3 h-1.5 max-w-sm rounded-full bg-[#EF4444]/20 overflow-hidden" role="img" aria-label="{{ $heldShare }}% of the deposit still held">
                                            <div class="h-full rounded-full bg-[#060D26]" style="width: {{ $heldShare }}%"></div>
                                        </div>
                                    @endif
                                </div>
                                @if($canChargeDeposit)
                                    <button type="button" @click="$dispatch('open-add-deposit-charge')"
                                        title="For damages or unpaid bills at move-out. Rent payments go under Record payment."
                                        @disabled($summary['depositRemaining'] <= 0)
                                        class="{{ $btnOutline }} shrink-0 disabled:border-transparent disabled:bg-[#ECEEF6] disabled:text-[#5B6A8E] disabled:cursor-not-allowed">
                                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                        </svg>
                                        Deduct from deposit
                                    </button>
                                @endif
                            </div>

                            @if($activeDepositCharges->isNotEmpty())
                                <ul class="border-t border-[#E2E4EC] divide-y divide-[#E2E4EC]">
                                    @foreach($activeDepositCharges as $charge)
                                        @php
                                            $category = $depositCategoryLabels[$charge->category] ?? $charge->category;
                                            $charger = trim(($charge->charger->first_name ?? '') . ' ' . ($charge->charger->last_name ?? '')) ?: 'You';
                                        @endphp
                                        <li class="px-5 sm:px-6 py-3.5" @if($loop->index >= $ledgerCap) x-show="moreCharges" x-cloak @endif>
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-[14px] font-semibold text-[#060D26]">{{ $category }}</p>
                                                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">{{ $charge->description }}</p>
                                                    <p class="mt-1 text-[11.5px] text-[#5B6A8E] tabular-nums">{{ optional($charge->charged_at)->format('M j, Y') }} · {{ $charger }}</p>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <p class="text-[14px] font-semibold text-[#DC2626] tabular-nums whitespace-nowrap">−{{ $peso($charge->amount) }}</p>
                                                    <button type="button"
                                                        @click="$dispatch('open-void-deposit-charge', {
                                                            id: {{ $charge->deposit_charge_id }},
                                                            action: @js(route('landlord.depositCharges.void', $charge)),
                                                            amount: {{ (float) $charge->amount }},
                                                            label: @js($category . ', recorded ' . (optional($charge->charged_at)->format('M d, Y') ?? ''))
                                                        })"
                                                        class="{{ $btnQuiet }}">
                                                        Void
                                                    </button>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($activeDepositCharges->count() > $ledgerCap)
                                    <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                        <button type="button" x-show="!moreCharges" @click="moreCharges = true"
                                            class="text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                                            Show all {{ $activeDepositCharges->count() }} deductions
                                        </button>
                                    </div>
                                @endif
                            @endif

                            {{-- Voided deductions stay on the record: a void removes an entry from the arithmetic, never from the history. --}}
                            @if($voidedDepositCharges->isNotEmpty())
                                <div class="border-t border-[#E2E4EC] bg-[#FBFBFD]">
                                    <h3 class="px-5 sm:px-6 pt-4 pb-1 text-[12px] font-semibold text-[#5B6A8E]">Voided deductions · not counted</h3>
                                    <ul class="divide-y divide-[#E2E4EC]">
                                        @foreach($voidedDepositCharges as $charge)
                                            <li class="px-5 sm:px-6 py-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <p class="text-[13.5px] font-semibold text-[#5B6A8E] line-through">{{ $depositCategoryLabels[$charge->category] ?? $charge->category }}</p>
                                                    <p class="text-[13.5px] font-semibold text-[#5B6A8E] line-through tabular-nums whitespace-nowrap">{{ $peso($charge->amount) }}</p>
                                                </div>
                                                <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
                                                    Voided {{ optional($charge->voided_at)->format('M j, Y') }} · {{ $charge->voidReasonLabel() }}@if($charge->void_note) · {{ $charge->void_note }}@endif
                                                </p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Voided: kept deliberately — a voided entry is removed from the arithmetic, never from the record. --}}
                    @if($voidedTransactions->isNotEmpty())
                        <div x-show="tab === 'voided'" x-cloak role="tabpanel" id="ledger-panel-voided" aria-labelledby="ledger-tab-voided" x-data="{ moreRows: false }">
                            <p class="px-5 sm:px-6 py-3 text-[12.5px] text-[#5B6A8E] border-b border-[#E2E4EC] bg-[#FBFBFD]">Corrections made to this ledger. None of these count toward any total.</p>
                            <ul class="divide-y divide-[#E2E4EC]">
                                @foreach($voidedTransactions as $voided)
                                    @php
                                        $voidedFor = $voided->payment_type === 'Monthly'
                                            ? (optional($voided->billing_period)->format('M Y') . ' Rent')
                                            : $voided->payment_type;
                                    @endphp
                                    <li class="px-5 sm:px-6 py-3.5" @if($loop->index >= $ledgerCap) x-show="moreRows" x-cloak @endif>
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-[14px] font-semibold text-[#060D26]">
                                                    {{ $voidedFor }}
                                                    @if($voided->replacements->isNotEmpty())
                                                        <span class="ml-1.5 align-middle inline-flex items-center h-5 px-2 rounded-full border border-[#FF8A66]/25 bg-[#ECEEF6] text-[#060D26] text-[10.5px] font-bold">Corrected</span>
                                                    @endif
                                                </p>
                                                <p class="mt-0.5 text-[12.5px] text-[#060D26]">{{ $voided->voidReasonLabel() }}@if($voided->void_note) <span class="text-[#5B6A8E]">· {{ $voided->void_note }}</span>@endif</p>
                                                <p class="mt-1 text-[11.5px] text-[#5B6A8E] tabular-nums">
                                                    Paid {{ optional($voided->paid_at)->format('M j, Y') ?? '—' }}@if($voided->reference_no) · Ref {{ $voided->reference_no }}@endif
                                                    · Voided {{ optional($voided->voided_at)->format('M j, Y') }}@if($voided->voider) by {{ $voided->voider->first_name }}@endif
                                                </p>
                                            </div>
                                            <p class="text-[14px] font-semibold text-[#5B6A8E] line-through tabular-nums whitespace-nowrap">{{ $peso($voided->amount) }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @if($voidedTransactions->count() > $ledgerCap)
                                <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                    <button type="button" x-show="!moreRows" @click="moreRows = true"
                                        class="text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                                        Show all {{ $voidedTransactions->count() }} voided entries
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-card>
            </div>
        @else
            <x-card>
                <p class="text-[14px] font-semibold text-[#060D26]">No rent to show yet</p>
                <p class="mt-1 text-[13.5px] text-[#5B6A8E] max-w-[65ch]">{{ $tenant->first_name ?? 'The tenant' }} has not moved in yet, so no rent has fallen due. The rent ledger starts, and payments can be recorded, once they move in.</p>
                {{-- Deposit held is still a fact before move-in; the figure is kept so the page never hides money already taken. --}}
                <p class="mt-4 pt-4 border-t border-[#E2E4EC] flex items-baseline justify-between gap-4 text-[13.5px]">
                    <span class="text-[#5B6A8E]">Deposit held</span>
                    <span class="font-semibold text-[#060D26] tabular-nums">{{ $peso($summary['depositRemaining']) }}</span>
                </p>
            </x-card>
        @endif
    </div>

    {{-- Record payment modal — teleported so no ancestor's transform or overflow can clip it (RULES.md → Modals & Overlays). --}}
    @if($isActive)
        @include('landlord.payments.partials.record-payment-modal')
    @endif

    {{-- Void payment modal — NOT gated on $isActive: voiding a wrong entry on an ended tenancy is still
         fixing a wrong financial record (ReservationPolicy::voidPayment). --}}
    <x-rent-period-modal />
    @include('landlord.payments.partials.void-payment-modal')

    {{-- Deposit charge modals — not gated on $isActive: recordDepositCharge/voidDepositCharge
         are deliberately not restricted to an active tenancy. --}}
    @include('landlord.payments.partials.add-deposit-charge-modal')
    @include('landlord.payments.partials.void-deposit-charge-modal')

    <script src="{{ asset('js/date-picker.js') }}"></script>

    @if($isActive)
        {{-- The tenant may be paying rent in another tab right now — the webhook/checkout-return settle lands
             out-of-band. A full reload is deliberate: every figure here derives from one server-rendered
             RentLedger read, and re-deriving that in JS would duplicate its status logic. --}}
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
