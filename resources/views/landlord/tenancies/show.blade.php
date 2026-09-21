@extends('layouts.landlord')

@section('page-title', 'Tenancy')

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
            'advance' => ['pill' => 'bg-[#ECEEF6] text-[#060D26] border-[#DA8E77]/25', 'label' => 'Paid · Advance'],
            // Part-covered by an overpayment. Deliberately NOT the amber
            // Partial pill: nobody is behind on a month that hasn't arrived,
            // and amber here would read as a collection problem.
            'advance_part' => ['pill' => 'bg-[#F7F8FC] text-[#060D26] border-[#DA8E77]/20', 'label' => 'Advance · part'],
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

        $inputClass = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#DA8E77]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';

        $thumb = $unit?->media->firstWhere('media_type', 'Image');
    @endphp

    {{-- x-data only so the "Record payment" button has an Alpine scope to
         $dispatch from; the modal listens for the event on the window. --}}
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16" x-data>

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#5B6A8E] mb-3">
            <a href="{{ route('landlord.tenants.index') }}"
                class="hover:text-[#060D26] transition-colors duration-200">My Tenants</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#060D26] font-medium">{{ $tenant->name ?? 'Tenancy' }}</span>
        </div>

        {{-- Tenant profile — who this is comes first; every fact carries a visible label. --}}
        <x-card flush class="mb-5">
            <div class="p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="flex items-center gap-4 min-w-0 flex-1">
                    <div class="w-16 h-16 rounded-full bg-[#ECEEF6] flex items-center justify-center text-[20px] font-bold text-[#060D26] shrink-0" aria-hidden="true">
                        {{ $initials ?: '?' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[12px] font-semibold text-[#5B6A8E]">Tenant</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-[22px] sm:text-[24px] font-semibold tracking-tight text-[#060D26] truncate">
                                {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown tenant' }}
                            </h1>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $statusPill }}">
                                {{ $reservation->rental_status }}
                            </span>
                            @if($isWalkIn)
                                {{-- Not platform-verified. The landlord asserted this person exists. --}}
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[12px] font-semibold"
                                    title="Added by you — identity not verified by AbangananHub">
                                    Walk-in
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if($reservation->conversation)
                        <a href="{{ route('conversations.show', $reservation->conversation) }}"
                            class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border border-[#060D26]/25 bg-white text-[#060D26] text-sm font-semibold hover:border-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Message
                        </a>
                    @endif

                    @if($isActive)
                        <button type="button" @click="$dispatch('open-record-payment')"
                            class="h-11 px-5 inline-flex items-center gap-2 rounded-xl bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-colors duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Record payment
                        </button>
                    @endif
                </div>
            </div>

            {{-- Labelled facts. Hairline grid: gap-px over a border-coloured backing. --}}
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-[#E2E4EC] border-t border-[#E2E4EC]">
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Contact number</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] truncate">
                        @if($tenant->contact_number)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="hover:text-[#B35A3D] hover:underline">{{ $tenant->contact_number }}</a>
                        @else
                            <span class="font-normal text-[#5B6A8E]">Not provided</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Email</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] truncate" title="{{ $tenant->email }}">
                        @if($tenant->email)
                            <a href="mailto:{{ $tenant->email }}" class="hover:text-[#B35A3D] hover:underline">{{ $tenant->email }}</a>
                        @else
                            <span class="font-normal text-[#5B6A8E]">Not provided</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Account</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $isWalkIn ? 'Walk-in tenant' : 'Platform tenant' }}</dd>
                    <p class="text-[12px] text-[#5B6A8E]">
                        {{ $isWalkIn ? 'Added by you, not verified' : ($tenant->email_verified_at ? 'Email verified' : 'Email not verified') }}
                    </p>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Occupants</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">
                        @if($reservation->occupants_count)
                            {{ $reservation->occupants_count }} {{ \Illuminate\Support\Str::plural('person', $reservation->occupants_count) }}
                        @else
                            <span class="font-normal text-[#5B6A8E]">Not provided</span>
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">{{ $hasStarted ? 'Moved in' : 'Planned move-in' }}</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">
                        {{ optional($reservation->target_move_in_date)->format('M d, Y') ?? 'Not recorded' }}
                    </dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Lease term</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">{{ $reservation->duration_of_stay ?? 'Not recorded' }}</dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">{{ $isActive ? 'Planned move-out' : 'Moved out' }}</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">
                        {{ optional($reservation->target_move_out_date)->format('M d, Y') ?? ($isActive ? 'Open-ended' : 'Not recorded') }}
                    </dd>
                </div>
                <div class="bg-white px-5 py-3.5 min-w-0">
                    <dt class="text-[12px] font-semibold text-[#5B6A8E]">Rent due</dt>
                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]">Day {{ $summary['dueDay'] }} of the month</dd>
                </div>
            </dl>

            @if($reservation->remarks)
                <div class="px-5 sm:px-6 py-4 border-t border-[#E2E4EC]">
                    <p class="text-[12px] font-semibold text-[#5B6A8E]">Notes</p>
                    <p class="mt-1 text-[13.5px] leading-relaxed text-[#060D26]">{{ $reservation->remarks }}</p>
                </div>
            @endif
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            {{-- ── Ledger column ──────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                @if($hasStarted)
                {{-- Money summary — labels stay plain; only overdue earns colour when it is > 0 --}}
                @php
                    // Rent and the security deposit are separate tiles: the deposit is
                    // held for the tenant and refundable, so folding it into
                    // "Collected" overstated what the landlord has actually earned.
                    $extraCharges = round($summary['otherCollected'] - $summary['depositCollected'], 2);
                    $tiles = [
                        ['label' => 'Rent collected',    'value' => $summary['monthlyCollected'], 'tone' => 'text-[#060D26]', 'sub' => $extraCharges > 0 ? '+ ₱' . number_format($extraCharges, 2) . ' other charges' : 'Rent payments recorded'],
                        ['label' => 'Security deposit',  'value' => $summary['depositCollected'], 'tone' => 'text-[#060D26]', 'sub' => $summary['depositCollected'] > 0 ? 'Held, refundable at move-out' : 'No deposit recorded'],
                        ['label' => 'Total unpaid', 'value' => $summary['outstanding'],   'tone' => 'text-[#060D26]', 'sub' => 'Unpaid rent to date'],
                        ['label' => 'Overdue',      'value' => $summary['overdueAmount'], 'tone' => $summary['overdueCount'] > 0 ? 'text-[#DC2626]' : 'text-[#060D26]', 'sub' => $summary['overdueCount'] . ' ' . \Illuminate\Support\Str::plural('month', $summary['overdueCount']) . ' behind'],
                    ];
                @endphp
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-px overflow-hidden rounded-2xl border border-[#E2E4EC] bg-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                    @foreach($tiles as $tile)
                        <div class="bg-white p-4 sm:p-5">
                            <p class="text-[12px] font-semibold text-[#5B6A8E]">{{ $tile['label'] }}</p>
                            <p class="mt-2 text-[22px] sm:text-[24px] font-extrabold leading-none tabular-nums {{ $tile['tone'] }}">&#8369;{{ number_format($tile['value'], 2) }}</p>
                            <p class="mt-2 text-[12.5px] text-[#5B6A8E]">{{ $tile['sub'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Rent ledger --}}
                <x-card flush>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-[#E2E4EC]">
                        <div>
                            <h2 class="text-[16px] font-semibold text-[#060D26]">Rent ledger</h2>
                            <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                ₱{{ number_format($summary['monthlyRent'], 2) }} per month, due on day {{ $summary['dueDay'] }}.
                            </p>
                            @if($summary['prepaidThrough'])
                                <p class="text-[12px] font-medium text-[#060D26] mt-1">
                                    Paid in advance through {{ $summary['prepaidThrough']->format('M Y') }}.
                                </p>
                            @endif
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
                        <div class="overflow-x-auto">
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
                                        @php $style = $periodStyleFor($period); @endphp
                                        <tr class="hover:bg-[#F7F8FC] transition-colors duration-150">
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $period['label'] }}</p>
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] whitespace-nowrap">
                                                {{ $period['due_on']->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['expected'], 2) }}
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
                                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">
                                                    {{ $style['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                @else
                    <x-card>
                        <p class="text-[14px] font-semibold text-[#060D26]">No rent to show yet</p>
                        <p class="mt-1 text-[13.5px] text-[#5B6A8E]">The rent ledger starts once {{ $tenant->first_name ?? 'the tenant' }} has moved in.</p>
                    </x-card>
                @endif

                {{-- All money received, one row per transaction. The ledger above
                     is what is owed; this is what was actually paid. A payment
                     split across months by RentPaymentAllocator shows as the
                     several transactions it was. Rent and non-rent (deposit,
                     initial, utilities) share one table — "Applied to" says
                     which is which. --}}
                @php
                    $allPayments = $monthlyTransactions->concat($otherCharges)
                        ->sortByDesc(fn ($p) => $p->paid_at ?? $p->created_at)
                        ->values();
                @endphp
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
                                <li class="px-5 py-3.5" @if($loop->index >= 6) x-show="moreRows" x-cloak @endif>
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
                        <div class="hidden lg:block overflow-x-auto">
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
                                        <tr class="hover:bg-[#F7F8FC] transition-colors duration-150"
                                            @if($loop->index >= 6) x-show="moreRows" x-cloak @endif>
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
                        @if($allPayments->count() > 6)
                            <div class="px-5 sm:px-6 py-3 border-t border-[#E2E4EC]">
                                <button type="button" x-show="!moreRows" x-on:click="moreRows = true"
                                    class="text-[12.5px] font-semibold text-[#060D26] hover:underline cursor-pointer">
                                    Show all {{ $allPayments->count() }} transactions
                                </button>
                            </div>
                        @endif
                    </x-card>
                @endif

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
                                        <tr @if($loop->index >= 6) x-show="moreRows" x-cloak @endif>
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
                                                    <span class="inline-flex items-center h-5 px-2 mt-1 rounded-full border border-[#DA8E77]/25 bg-[#ECEEF6] text-[#060D26] text-[10px] font-bold">
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

            {{-- ── Side column ────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- Unit + rent terms (tenant facts live in the profile card above) --}}
                <x-card>
                    <p class="text-[12px] font-semibold text-[#5B6A8E] mb-4">Unit</p>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-14 h-14 rounded-xl bg-[#F7F8FC] overflow-hidden shrink-0 ring-1 ring-[#5B6A8E]/10">
                            @if($thumb)
                                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[14.5px] font-bold text-[#060D26] truncate">{{ $unit->unit_label ?? 'No unit' }}</p>
                            <p class="text-[13px] text-[#5B6A8E] truncate">{{ $reservation->property->title ?? '' }}</p>
                        </div>
                    </div>

                    <dl class="space-y-2.5 text-[13.5px]">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#5B6A8E]">Monthly rent</dt>
                            <dd class="font-bold text-[#060D26] tabular-nums">&#8369;{{ number_format($summary['monthlyRent'], 2) }}</dd>
                        </div>
                        @if($isActive)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[#5B6A8E]">Payment status</dt>
                                <dd class="inline-flex items-center gap-1.5 font-semibold {{ $paymentStatusStyle['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $paymentStatusStyle['dot'] }}"></span>
                                    {{ $paymentStatusStyle['label'] }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[#5B6A8E]">Next due</dt>
                                <dd class="font-semibold text-[#060D26]">
                                    {{ $summary['nextDueDate']?->format('M d, Y') ?? '—' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-card>

                {{-- End tenancy --}}
                @if($isActive)
                    <x-card>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-2">End of tenancy</p>
                        <p class="text-[12.5px] text-[#5B6A8E] leading-relaxed mb-4">
                            Marks this tenancy complete and returns
                            <strong class="text-[#060D26]">{{ $unit->unit_label ?? 'the unit' }}</strong>
                            to your available units. The ledger stays readable but stops accepting new payments.
                        </p>

                        <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}"
                            data-confirm="End this tenancy?"
                            data-confirm-message="The unit becomes available again and no further payments can be recorded against this ledger. This cannot be undone."
                            data-confirm-button="End tenancy"
                            data-confirm-type="warning">
                            @csrf
                            <label for="move_out_date" class="{{ $labelClass }}">Move-out date</label>
                            <div class="mb-3">
                                <x-date-picker name="move_out_date" id="move_out_date" placeholder="Move-out date"
                                    value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" />
                            </div>

                            <button type="submit"
                                class="w-full h-11 rounded-xl border border-[#EF4444]/30 text-[#DC2626] text-sm font-semibold hover:bg-[#EF4444]/[0.06] transition-all duration-200 cursor-pointer">
                                End tenancy
                            </button>
                        </form>
                    </x-card>
                @elseif($reservation->rental_status === 'Completed')
                    <x-card class="bg-[#F7F8FC]">
                        <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Tenancy closed</p>
                        <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed">
                            @if($reservation->target_move_out_date)
                                This tenancy ended on <strong class="text-[#060D26]">{{ $reservation->target_move_out_date->format('M d, Y') }}</strong>.
                            @else
                                This tenancy has ended. No move-out date was recorded.
                            @endif
                            The ledger is kept as a record and no longer accepts payments.
                        </p>
                    </x-card>
                @else
                    <x-card class="bg-[#F7F8FC]">
                        <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Not moved in yet</p>
                        <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed">
                            This tenancy is <strong class="text-[#060D26]">{{ $reservation->rental_status }}</strong>. Payments can be recorded once the tenant has moved in.
                        </p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>

    {{-- Record payment modal — teleported so no ancestor's transform or
         overflow can clip it (RULES.md → Modals & Overlays). --}}
    @if($isActive)
        @include('landlord.tenancies.partials.record-payment-modal')
    @endif

    {{-- Void payment modal — unlike the record-payment modal above, this is
         NOT gated on $isActive: voiding a wrong entry on an ended tenancy is
         still fixing a wrong financial record (ReservationPolicy::voidPayment). --}}
    @include('landlord.tenancies.partials.void-payment-modal')

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
