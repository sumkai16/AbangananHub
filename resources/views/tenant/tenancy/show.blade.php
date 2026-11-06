@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        $unit = $reservation->unit;
        $isActive = $reservation->rental_status === 'Occupied';

        // Rows the tenant can open a receipt for get a link in the period popup.
        $withReceipts = function (array $detail) use ($reservation) {
            $detail['payments'] = array_map(fn ($p) => $p['has_receipt']
                ? $p + ['receipt_url' => route('tenancy.receipt', [$reservation, $p['id']])]
                : $p, $detail['payments']);

            return $detail;
        };

        $periodStyles = [
            'paid'    => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'partial' => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'due'     => ['pill' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]', 'label' => 'Due'],
            // A month settled before it arrived, from rent paid in advance.
            'advance' => ['pill' => 'bg-[#ECEEF6] text-[#060D26] border-[#FF8A66]/25', 'label' => 'Paid · Advance'],
            // Part-covered by an overpayment. Deliberately NOT the amber
            // Partial pill — a tenant is not behind on a month that has not
            // arrived, and amber would read as a demand.
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

        $thumb = $unit?->media->firstWhere('media_type', 'Image');

        // Review::canReview() already accepts 'Occupied' or 'Completed' — a
        // tenant can review while living there or after moving out; this page
        // is one of the few places that CTA is actually reachable from.
        $canReview = \App\Models\Review::canReview(auth()->id(), $reservation->property_id);

        // For the "View lease" modal below — by the time a tenant reaches this
        // page the agreement is already signed (route only links here once
        // rental_status is Occupied/Completed), so there is no sign/pay flow
        // to render, just the frozen terms and the signature record.
        $terms = \App\Support\LeaseTerms::for($reservation);
        $landlord = $reservation->property->landlord;
        $tenant = $reservation->tenant;
    @endphp

    @php
        $landlordUser = $reservation->property->landlord;
        $landlordName = trim(($landlordUser->first_name ?? '').' '.($landlordUser->last_name ?? '')) ?: 'your landlord';
        $hasStarted = in_array($reservation->rental_status, ['Occupied', 'Completed'], true);
        $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);

        // The month's pill and strip key, one rule shared with the Lease strip partial (and the landlord's ledger).
        $periodKeyFor = fn (array $period) => $period['is_future']
            ? ($period['status'] === 'paid' ? 'advance' : 'advance_part')
            : (isset($periodStyles[$period['status']]) ? $period['status'] : 'due');

        // One detail payload per month, shared by the Lease strip and the popup it opens.
        $periodDetails = $periods->mapWithKeys(function ($period) use ($periodStyleFor, $withReceipts) {
            $style = $periodStyleFor($period);
            $detail = \App\Services\RentLedger::periodPayload($period);
            $detail['pill'] = $style['pill'];
            $detail['status_label'] = $style['label'];

            return [$period['period']->format('Y-m') => $withReceipts($detail)];
        });

        $isOverdue = $summary['overdueCount'] > 0;
        // Late fees on months already billed and not waived: part of what is owed, so the tenant sees them up front.
        $lateFeeTotal = round((float) $periods->reject(fn ($p) => $p['is_future'])->sum('late_fee'), 2);
        $oldest = $summary['oldestOverdue'];
        $daysLate = $oldest ? (int) $oldest['due_on']->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
        $heroDot = [
            'overdue' => 'bg-[#F87171]', 'partial' => 'bg-[#FBBF24]', 'upcoming' => 'bg-white/60',
            'paid' => 'bg-[#4ADE80]', 'paid_ahead' => 'bg-[#FF8A66]',
        ][$summary['paymentStatus']] ?? 'bg-white/60';
        $heroLabel = ['overdue' => 'Overdue', 'partial' => 'Partial', 'upcoming' => 'Upcoming', 'paid' => 'Paid', 'paid_ahead' => 'Paid ahead'][$summary['paymentStatus']] ?? 'Upcoming';

        // Same look as the landlord's ledger: tiles share one shape so the grid reads as a set.
        $tile = 'rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]';
        $miniTile = $tile.' p-3.5 sm:p-4 flex flex-col justify-between gap-3 min-h-[100px]';
        $chip = 'w-7 h-7 rounded-lg inline-flex items-center justify-center shrink-0';
        $btnOutline = 'min-h-[44px] px-4 inline-flex items-center justify-center gap-2 rounded-xl border border-[#060D26]/20 bg-white text-[#060D26] text-[13.5px] font-semibold hover:border-[#FF8A66] hover:text-[#B35A3D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors duration-200 cursor-pointer';
        $th = 'py-3 text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide';

        $relativeDay = function ($date) {
            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

            return match (true) {
                $days === 0 => 'today',
                $days === 1 => 'tomorrow',
                $days === -1 => 'yesterday',
                $days > 1 => "in {$days} days",
                default => abs($days).' days ago',
            };
        };

        $canPayNow = $isActive && ($payablePeriod || $advancePeriod);
        $openRentCheckout = $reservation->payments->first(fn ($p) => $p->payment_type === 'Monthly' && $p->status === 'Pending'
            && $p->paymongo_checkout_session_id && ! $p->isStalePending());
        $pendingClaims = $reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'Monthly')->values();

        $activeDepositCharges = $reservation->depositCharges->reject->isVoided()->values();
        $depositCategoryLabels = \App\Models\DepositCharge::CATEGORIES;
        $allPayments = $monthlyTransactions->concat($otherCharges)->sortByDesc(fn ($p) => $p->paid_at ?? $p->created_at)->values();
        $ledgerCap = 6;
        $extraCharges = round($summary['otherCollected'] - $summary['depositCollected'], 2);
    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16 space-y-5" x-data>

        <header class="space-y-4 pb-1">
            <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-[13px] text-[#5B6A8E]">
                <a href="{{ route('tenancy.index') }}" class="hover:text-[#060D26] transition-colors duration-200">My Rentals</a>
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                <span class="text-[#060D26] font-medium" aria-current="page">{{ $unit->unit_label ?? 'Rent' }}</span>
            </nav>

            @if($errors->any())
                <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">{{ $errors->first() }}</div>
            @endif

            {{-- Tenants who signed before the emergency contact was asked for at signing have none on file. --}}
            @if($isActive && ! \App\Models\TenantDetail::completeFor(auth()->user()))
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] px-4 py-3">
                    <p class="text-[13px] text-[#B45309] font-medium">Your landlord has no emergency contact on file for you.</p>
                    <a href="{{ route('profile.edit') }}#emergency-contact" class="text-[13px] font-semibold text-[#060D26] underline underline-offset-2 cursor-pointer">Add it in Account Settings</a>
                </div>
            @endif

            {{-- Identity only; the payment action lives in the Balance tile, next to the number it acts on. The status pill
                 only shows when the rental is not running: "Occupied" is the normal state. --}}
            <div class="flex items-start gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-[#ECEEF6] overflow-hidden shrink-0 ring-1 ring-[#5B6A8E]/10">
                    @if($thumb)
                        <img loading="lazy" decoding="async" src="{{ \App\Support\Images::src($thumb->media_url, 'card') }}" alt="" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-[#5B6A8E]">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                        </div>
                    @endif
                </div>
                <div class="min-w-0 pt-0.5">
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <h1 class="text-[22px] sm:text-[26px] leading-tight font-semibold tracking-tight text-[#060D26] break-words">{{ $unit->unit_label ?? 'Your rental' }}</h1>
                        @unless($isActive)
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $statusPill }}">{{ $reservation->rental_status }}</span>
                        @endunless
                    </div>
                    <p class="mt-1 text-[13.5px] leading-snug text-[#5B6A8E] line-clamp-2">
                        {{ $reservation->property->title ?? '' }} · Landlord {{ $landlordName }}@if($reservation->target_move_in_date) · Moved in {{ $reservation->target_move_in_date->format('M j, Y') }}@endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="$dispatch('open-modal', 'lease-agreement')" class="{{ $btnOutline }}">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    View rental agreement
                </button>
                @if($hasStarted)
                    <a href="{{ route('tenancy.chat', $reservation) }}" class="{{ $btnOutline }}">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
                        Message landlord
                    </a>
                @endif
                @if($canReview)
                    <a href="{{ route('properties.show', $reservation->property) }}#reviews" class="{{ $btnOutline }}">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.48 3.499 1.681 3.408 3.762.545a.6.6 0 0 1 .333 1.023l-2.723 2.652.643 3.746a.6.6 0 0 1-.87.632L12 13.75l-3.306 1.755a.6.6 0 0 1-.87-.632l.643-3.746-2.723-2.652a.6.6 0 0 1 .333-1.023l3.762-.545 1.68-3.408a.6.6 0 0 1 1.08 0Z" /></svg>
                        Leave a review
                    </a>
                @endif
                @if($isActive)
                    <a href="{{ route('tenancy.chat', [$reservation, 'concern' => 'new']) }}" class="{{ $btnOutline }}">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                        Report a concern
                    </a>
                @endif
            </div>

            @unless($isActive)
                <p class="text-[13px] leading-relaxed text-[#5B6A8E]">
                    This rental ended on <strong class="text-[#060D26]">{{ optional($reservation->target_move_out_date)->format('M j, Y') ?? 'an unrecorded date' }}</strong>.
                    The ledger is kept as a record of what you paid.
                </p>
            @endunless
        </header>

        {{-- Bento: Balance is the big navy tile with the pay action; four small facts beside it; then the lease; then
             the records. Phone: two columns, Balance and the wide tiles span both. --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 lg:grid-flow-row-dense gap-3">

            <section aria-labelledby="balance-title" class="col-span-2 lg:row-span-2 rounded-2xl bg-[#060D26] text-white p-5 flex flex-col gap-4 shadow-[0_8px_24px_-12px_rgba(6,13,38,0.45)]">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="balance-title" class="text-[13px] font-semibold text-white/70">Balance due</h2>
                    <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-white/10 text-[12px] font-semibold text-white">
                        <span class="w-1.5 h-1.5 rounded-full {{ $heroDot }}" aria-hidden="true"></span>{{ $heroLabel }}
                    </span>
                </div>

                <div>
                    <p class="text-[32px] sm:text-[40px] leading-none font-bold tracking-tight tabular-nums {{ $isOverdue ? 'text-[#FCA5A5]' : 'text-white' }}">{{ $peso($summary['outstanding']) }}</p>
                    @if($isOverdue)
                        <p class="mt-2 text-[13.5px] leading-relaxed text-white/75 max-w-[48ch]">
                            <span class="font-semibold text-[#FCA5A5]">{{ $peso($summary['overdueAmount']) }} overdue</span>
                            across {{ $summary['overdueCount'] }} {{ \Illuminate\Support\Str::plural('month', $summary['overdueCount']) }}.
                        </p>
                    @elseif($summary['outstanding'] <= 0)
                        <p class="mt-2 text-[13.5px] leading-relaxed text-white/75 max-w-[48ch]">
                            @if($summary['prepaidThrough'])
                                You are paid through <span class="font-semibold text-white">{{ $summary['prepaidThrough']->format('F Y') }}</span>.
                            @elseif($isActive)
                                All paid up. Nothing is owed right now.
                            @else
                                This rental has ended. Nothing is owed.
                            @endif
                        </p>
                    @endif
                    @if($lateFeeTotal > 0)
                        <p class="mt-1 text-[12.5px] text-white/60">incl. {{ $peso($lateFeeTotal) }} late fees</p>
                    @endif
                </div>

                @if($canPayNow)
                    <div class="mt-auto">
                        <a href="#pay"
                            class="h-11 sm:h-10 px-4 inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl text-[13.5px] font-semibold bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#060D26] transition-colors duration-200 cursor-pointer">
                            {{ $pendingClaims->isNotEmpty() ? 'See your payment report' : ($payablePeriod ? 'Pay rent' : 'Pay ahead') }}
                        </a>
                    </div>
                @endif
            </section>

            <div class="{{ $miniTile }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Monthly rent</p>
                    <span class="{{ $chip }} bg-[#ECEEF6] text-[#060D26]" aria-hidden="true"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span>
                </div>
                <div>
                    <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['monthlyRent']) }}</p>
                    <p class="mt-1 text-[12px] text-[#5B6A8E]">Due on day {{ $summary['dueDay'] }}</p>
                </div>
            </div>

            <div class="{{ $miniTile }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12.5px] font-semibold text-[#5B6A8E]">{{ $isOverdue ? 'Oldest unpaid' : 'Next due' }}</p>
                    <span class="{{ $chip }} {{ $isOverdue ? 'bg-[#EF4444]/[0.10] text-[#DC2626]' : 'bg-[#ECEEF6] text-[#060D26]' }}" aria-hidden="true"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg></span>
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
                        <p class="mt-1 text-[12px] text-[#5B6A8E]">{{ $isActive ? 'Nothing scheduled' : 'Rental ended' }}</p>
                    @endif
                </div>
            </div>

            <div class="{{ $miniTile }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Paid to date</p>
                    <span class="{{ $chip }} bg-[#22C55E]/[0.10] text-[#15803D]" aria-hidden="true"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg></span>
                </div>
                <div>
                    <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['monthlyCollected']) }}</p>
                    <p class="mt-1 text-[12px] text-[#5B6A8E]">{{ $extraCharges > 0 ? '+ '.$peso($extraCharges).' other charges' : 'Rent, from '.$monthlyTransactions->count().' '.\Illuminate\Support\Str::plural('payment', $monthlyTransactions->count()) }}</p>
                </div>
            </div>

            <div class="{{ $miniTile }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[12.5px] font-semibold text-[#5B6A8E]">Deposit held</p>
                    <span class="{{ $chip }} bg-[#ECEEF6] text-[#060D26]" aria-hidden="true"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg></span>
                </div>
                <div>
                    <p class="text-[18px] sm:text-[20px] leading-none font-bold tracking-tight tabular-nums text-[#060D26]">{{ $peso($summary['depositRemaining']) }}</p>
                    <p class="mt-1 text-[12px] text-[#5B6A8E]">
                        @if($summary['depositCollected'] > 0)
                            <a href="#deposit" class="font-semibold text-[#060D26] underline underline-offset-2 decoration-[#060D26]/30 hover:text-[#B35A3D] hover:decoration-[#B35A3D] transition-colors duration-200">Deposit details</a>
                        @else
                            No deposit recorded
                        @endif
                    </p>
                </div>
            </div>

            @if($periods->isNotEmpty())
                @include('payments.partials._lease-strip', ['reservation' => $reservation, 'periods' => $periods, 'periodStyles' => $periodStyles, 'periodKeyFor' => $periodKeyFor, 'periodDetails' => $periodDetails, 'peso' => $peso, 'tile' => $tile])
            @endif
        </div>

        {{-- Pay rent: online (when this landlord can be paid online), by QR, or in cash. Online pays the earliest unsettled
             month; paying a month ahead is QR or cash. The Balance tile's button lands here. --}}
        @if($canPayNow)
            <section id="pay" class="scroll-mt-24">
                @if($openRentCheckout)
                    @include('payments.partials._checkout-processing', ['reservation' => $reservation, 'type' => 'Monthly'])
                @else
                    <x-payment-route-choice :reservation="$reservation" type="Monthly" :period="$payablePeriod ?? $advancePeriod" />
                @endif
            </section>
        @endif

        {{-- Payments: what you paid, newest first, plus any report still waiting for the landlord. --}}
        <x-card flush x-data="{ moreRows: false }">
            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 pt-4 pb-3 border-b border-[#E2E4EC]">
                <h2 class="flex items-center gap-2 text-[14px] font-semibold text-[#060D26]">
                    Payments
                    @if($allPayments->count() > 0)
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-[#ECEEF6] text-[11px] font-bold tabular-nums text-[#5B6A8E]">{{ $allPayments->count() }}</span>
                    @endif
                </h2>
            </div>

            @foreach($pendingClaims as $claim)
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 sm:px-6 py-3.5 bg-[#FBBF24]/[0.08] border-b border-[#E2E4EC]">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">
                        {{ $peso($claim->amount) }} <span class="font-medium text-[#5B6A8E]">· {{ $claim->payment_method }}@if($claim->reference_no) · Ref {{ $claim->reference_no }}@endif</span>
                    </p>
                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[11px] font-bold">Waiting for {{ $landlordUser->first_name ?? 'your landlord' }} to confirm</span>
                </div>
            @endforeach

            @if($allPayments->isNotEmpty())
                <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach($allPayments as $txn)
                        @php
                            $appliedTo = $txn->payment_type === 'Monthly' ? (optional($txn->billing_period)->format('M Y') ?? '—').' Rent' : $txn->payment_type;
                            $method = $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by landlord' : 'Online');
                        @endphp
                        <li class="px-5 py-3.5" @if($loop->index >= $ledgerCap) x-show="moreRows" x-cloak @endif>
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[14px] font-semibold text-[#060D26]">{{ $appliedTo }}</p>
                                <p class="text-[14px] font-semibold text-[#060D26] tabular-nums whitespace-nowrap">{{ $peso($txn->amount) }}</p>
                            </div>
                            <div class="mt-0.5 flex items-center justify-between gap-3">
                                <p class="text-[12px] text-[#5B6A8E] min-w-0 truncate">{{ optional($txn->paid_at)->format('M j, Y') ?? '—' }} · {{ $method }}@if($txn->reference_no) · Ref {{ $txn->reference_no }}@endif</p>
                                @if($txn->hasReceipt())
                                    <a href="{{ route('tenancy.receipt', [$reservation, $txn]) }}" class="shrink-0 inline-flex h-9 items-center text-[12.5px] font-semibold text-[#B35A3D] hover:underline">Receipt</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="hidden lg:block">
                    <table class="w-full">
                        <thead class="border-b border-[#E2E4EC]">
                            <tr>
                                <th scope="col" class="pl-6 pr-4 text-left {{ $th }}">Date</th>
                                <th scope="col" class="px-4 text-left {{ $th }}">For</th>
                                <th scope="col" class="px-4 text-left {{ $th }}">Method</th>
                                <th scope="col" class="px-4 text-left {{ $th }}">Reference</th>
                                <th scope="col" class="px-4 text-right {{ $th }}">Amount</th>
                                <th scope="col" class="pl-4 pr-6 text-right {{ $th }}"><span class="sr-only">Receipt</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($allPayments as $txn)
                                @php $appliedTo = $txn->payment_type === 'Monthly' ? (optional($txn->billing_period)->format('M Y') ?? '—').' Rent' : $txn->payment_type; @endphp
                                <tr class="hover:bg-[#F7F8FC] transition-colors duration-200" @if($loop->index >= $ledgerCap) x-show="moreRows" x-cloak @endif>
                                    <td class="pl-6 pr-4 py-3.5 text-[13px] text-[#060D26] whitespace-nowrap tabular-nums">{{ optional($txn->paid_at)->format('M d, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3.5 text-[13px] font-medium text-[#060D26] whitespace-nowrap">{{ $appliedTo }}</td>
                                    <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E]">{{ $txn->payment_method ?: ($txn->isManuallyRecorded() ? 'Recorded by landlord' : 'Online') }}</td>
                                    <td class="px-4 py-3.5 text-[13px] text-[#5B6A8E] tabular-nums">{{ $txn->reference_no ?: '—' }}</td>
                                    <td class="px-4 py-3.5 text-[13px] font-semibold text-[#060D26] text-right tabular-nums whitespace-nowrap">{{ $peso($txn->amount) }}</td>
                                    <td class="pl-4 pr-6 py-3.5 text-right whitespace-nowrap">
                                        @if($txn->hasReceipt())
                                            <a href="{{ route('tenancy.receipt', [$reservation, $txn]) }}" class="text-[12.5px] font-semibold text-[#B35A3D] hover:underline">Receipt</a>
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
                            class="inline-flex h-11 items-center text-[12.5px] font-semibold text-[#060D26] hover:text-[#B35A3D] focus:outline-none focus-visible:underline transition-colors duration-200 cursor-pointer">
                            Show all {{ $allPayments->count() }} payments
                        </button>
                    </div>
                @endif
            @elseif($pendingClaims->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-[14px] font-semibold text-[#060D26]">No payments yet</p>
                    <p class="text-[13px] text-[#5B6A8E] mt-1">Rent you pay online, and payments {{ $landlordUser->first_name ?? 'your landlord' }} confirms, show up here.</p>
                </div>
            @endif
        </x-card>

        {{-- Security deposit: read-only here; only the landlord can add a charge. --}}
        @if($summary['depositCollected'] > 0 || $activeDepositCharges->isNotEmpty())
            @php
                $heldShare = $summary['depositCollected'] > 0 ? max(0, min(100, (int) round($summary['depositRemaining'] / $summary['depositCollected'] * 100))) : 0;
            @endphp
            <x-card flush id="deposit" class="scroll-mt-24">
                <div class="px-5 sm:px-6 py-5">
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Security deposit</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E] tabular-nums">
                        <span class="font-semibold text-[#060D26]">{{ $peso($summary['depositRemaining']) }}</span> of {{ $peso($summary['depositCollected']) }} still held{{ $activeDepositCharges->isEmpty() ? ', no deductions.' : ', '.$peso($summary['depositCharged']).' deducted.' }}
                        Refundable at move-out.
                    </p>
                    @if($summary['depositCollected'] > 0)
                        <div class="mt-3 h-1.5 max-w-sm rounded-full bg-[#EF4444]/20 overflow-hidden" role="img" aria-label="{{ $heldShare }}% of the deposit still held">
                            <div class="h-full rounded-full bg-[#060D26]" style="width: {{ $heldShare }}%"></div>
                        </div>
                    @endif
                </div>
                @if($activeDepositCharges->isNotEmpty())
                    <ul class="border-t border-[#E2E4EC] divide-y divide-[#E2E4EC]">
                        @foreach($activeDepositCharges as $charge)
                            <li class="px-5 sm:px-6 py-3.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[14px] font-semibold text-[#060D26]">{{ $depositCategoryLabels[$charge->category] ?? $charge->category }}</p>
                                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">{{ $charge->description }}</p>
                                        <p class="mt-1 text-[11.5px] text-[#5B6A8E] tabular-nums">{{ optional($charge->charged_at)->format('M j, Y') }}</p>
                                    </div>
                                    <p class="text-[14px] font-semibold text-[#DC2626] tabular-nums whitespace-nowrap shrink-0">−{{ $peso($charge->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endif
    </div>

    @if($isActive)
        {{-- The landlord may record an offline payment while this page is open
             — mirrors landlord/payments/ledger.blade.php's listener so both
             sides of the ledger stay in sync without a manual refresh. --}}
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
    <x-rent-period-modal />

    {{-- "View lease" — read-only, since a tenant only reaches this page once
         the agreement is already signed. Opens over this page instead of
         navigating away; agreements.show (full page, printable) is still
         linked from the footer for printing/saving a copy. --}}
    <x-modal name="lease-agreement" maxWidth="2xl">
        <div class="flex flex-col max-h-[85vh]">
            <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-4 border-b border-[#E2E4EC] shrink-0">
                <div>
                    <h2 class="text-lg font-semibold text-[#060D26]">Rental Agreement</h2>
                    <p class="mt-1 text-[11px] font-bold text-[#5B6A8E] tracking-wider tabular-nums">{{ $terms['reference'] }}</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" aria-label="Close"
                    class="-mr-1.5 -mt-1 h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto px-6 py-5">
                @include('leases._document', ['terms' => $terms])

                @if($reservation->agreed_at || $reservation->landlord_tc_accepted_at)
                    <div class="mt-6 rounded-xl border border-[#E2E4EC] p-5">
                        <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wider mb-3">Signatures</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <p class="text-[13px] font-bold text-[#060D26]">{{ $landlord->first_name }} {{ $landlord->last_name }}</p>
                                <p class="text-[11px] text-[#5B6A8E]">Landlord</p>
                                @if($reservation->landlord_tc_accepted_at)
                                    <p class="text-[11px] text-[#15803D] font-semibold mt-1.5">
                                        Accepted {{ $reservation->landlord_tc_accepted_at->format('F j, Y \a\t g:i A') }}
                                    </p>
                                @else
                                    <p class="text-[11px] text-[#5B6A8E] mt-1.5">Awaiting acceptance</p>
                                @endif
                            </div>
                            <div class="sm:border-l sm:border-[#E2E4EC] sm:pl-4">
                                <p class="text-[13px] font-bold text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</p>
                                <p class="text-[11px] text-[#5B6A8E]">Tenant</p>
                                @if($reservation->agreed_at)
                                    <p class="text-[11px] text-[#15803D] font-semibold mt-1.5">
                                        Signed {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}
                                    </p>
                                @else
                                    <p class="text-[11px] text-[#5B6A8E] mt-1.5">Not yet signed</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 border-t border-[#E2E4EC] flex items-center justify-between gap-3 shrink-0">
                <a href="{{ route('tenancy.lease', $reservation) }}" target="_blank" rel="noopener"
                    class="text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#060D26] underline underline-offset-2">
                    Open full page / Print
                </a>
                <button type="button" x-on:click="$dispatch('close')"
                    class="h-10 px-5 rounded-xl border border-[#E2E4EC] text-[13px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </x-modal>
@endsection
