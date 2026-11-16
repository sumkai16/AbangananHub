@props(['reservation', 'type', 'period' => null])
{{--
    "How do you want to pay?" The options, picked each time: pay online through PayMongo, pay the landlord's own QR
    and report it, or pay cash in person and let the landlord record it. Only the ways the landlord turned on in
    Settings > Payments show (App\Support\PaymentMethods, which never leaves the tenant with none).
    Needs $reservation (property.landlord, unit, paymentClaims loaded) and $type ('MoveIn' | 'Monthly').
    For Monthly also $period (a RentLedger period). The direct side is the existing payments.partials._direct-pay.
--}}
@php
    $isMoveIn = $type === 'MoveIn';
    $landlordName = $reservation->property?->landlord?->first_name ?? 'your landlord';
    // Move-in: what is still owed (after any part already paid), split into the rent side and the deposit.
    $owedParts = $isMoveIn ? $reservation->moveInParts($reservation->moveInShortfall()) : null;
    $onlineAmount = $isMoveIn ? round($owedParts['fee'] + $owedParts['rent'], 2) : max(0, (float) ($period['balance'] ?? 0));
    $deposit = $isMoveIn ? $owedParts['deposit'] : 0.0;
    $fee = \App\Support\ServiceFee::for($onlineAmount);
    $onlineTotal = $onlineAmount + $deposit + $fee;
    $methods = \App\Support\PaymentMethods::forReservation($reservation);
    // Online pays the earliest unsettled month only, so paying a month ahead is QR or cash.
    $online = $methods['online'] && ! ($period['is_future'] ?? false);
    // Online takes any amount from the minimum (the reservation fee, then whatever is still owed) up to everything
    // owed, so a tenant can pay in parts before move-in. Nothing left to pay = nothing to offer.
    if ($isMoveIn && $reservation->moveInShortfall() <= 0.009) {
        $online = false;
    }
    $feeUnit = $isMoveIn && $reservation->reservationFee() > 0;
    // Online amount: free choice between $minPay and $maxPay (only reservation-fee units have a gap between them).
    $maxPay = $isMoveIn ? $reservation->moveInShortfall() : 0.0;
    $minPay = $isMoveIn ? min($reservation->minimumMoveInPayment() ?: $maxPay, $maxPay) : 0.0;
    $flex = $online && $isMoveIn && $maxPay - $minPay > 0.009;
    $feeFirst = $feeUnit && ! $reservation->reservationFeePaid() && $reservation->moveInPaid() <= 0;
    $qr = $methods['qr'];
    $cash = $methods['cash'];
    $direct = $qr || $cash;
    // A landlord who takes online only, and a month paid ahead (online pays the due month): nothing to offer yet.
    $noWayYet = ! $online && ! $direct;
    $cashAmount = $isMoveIn ? ($reservation->minimumMoveInPayment() ?: $onlineAmount + $deposit) : $onlineAmount;
    $chatLink = $reservation->conversation_id
        ? route('conversations.index', ['active' => $reservation->conversation_id])
        : route('tenancy.chat', $reservation);
    $checkoutUrl = $isMoveIn ? route('payments.checkout', $reservation) : route('payments.rentCheckout', $reservation);

    // Open the direct side when the tenant is already part-way through it: a report waiting or turned down, a
    // report form that came back with errors, or the chat's "I've paid" link.
    $periodKey = $isMoveIn ? null : $period['period']->copy()->startOfMonth();
    $claims = $reservation->paymentClaims
        ->where('claim_type', $type)
        ->when($periodKey, fn ($c) => $c->filter(fn ($claim) => $claim->billing_period?->isSameDay($periodKey)));
    $latestClaim = $claims->sortByDesc('payment_claim_id')->first();
    $pendingClaim = $claims->sortByDesc('payment_claim_id')->firstWhere('status', 'Pending');
    $rejectedClaim = ! $pendingClaim && $latestClaim?->status === 'Rejected' ? $latestClaim : null;
    // Reopen the side the tenant was on: a cash report back on cash, any other report (or a report form that
    // came back with errors, or the chat's "I've paid" link) on the QR side.
    $startCash = $cash && (($pendingClaim ?? $rejectedClaim)?->isCash() || old('payment_method') === 'Cash');
    $startDirect = $qr && ! $startCash && ($claims->contains(fn ($claim) => in_array($claim->status, ['Pending', 'Rejected'], true))
        || old('claim_type') === $type
        || ($isMoveIn && request()->boolean('pay')));
    // The chat's "I've paid" link (pay=1) lands on cash when the landlord takes cash but not QR.
    $startCash = $startCash || (! $qr && $cash && $isMoveIn && request()->boolean('pay'));
    // A checkout that came back with an error ('payment', and no report form input) reopens the online side so the message is seen.
    $startOnline = ! $startDirect && ($errors->has('payment') || $errors->has('accept_terms'));
    $anchor = $isMoveIn ? 'choose-pay' : 'choose-pay-'.$periodKey->format('Y-m');

    // Behind by two months or more: "Pay all" reports the whole balance in one go. The report stays on the oldest
    // unpaid month (that is where the landlord's confirmation starts) and the same allocator that spreads any lump of
    // rent (RentPaymentAllocator) lays it over the months, so the tenant is not made to file one report per month.
    // Only for QR and cash: an online checkout still pays one month at a time.
    $unsettled = $isMoveIn ? collect() : \App\Services\RentLedger::for($reservation)->unsettledPeriods();
    $payAllMonths = $unsettled->count();
    $payAllTotal = round((float) $unsettled->sum(fn ($p) => max(0, $p['balance'])), 2);
    $canPayAll = ! $isMoveIn && $direct && $payAllMonths >= 2 && ! ($period['is_future'] ?? false);
    $oneAmountStr = number_format($cashAmount, 2, '.', '');
    $allAmountStr = number_format($payAllTotal, 2, '.', '');
    // After a failed submit the form comes back with what was typed; keep whichever choice that matches.
    $startPayAll = $canPayAll && (string) old('amount') === $allAmountStr;
    $payAllRange = $canPayAll ? $unsettled->first()['label'].' – '.$unsettled->last()['label'] : '';
    // Which card is picked when the page opens: the side the tenant was on, else the only way there is, else
    // (no online) the first direct way, else nothing so the tenant chooses.
    $startRoute = match (true) {
        $startCash => 'cash',
        $startDirect => 'direct',
        $startOnline && $online => 'online',
        $online && ! $direct => 'online',
        ! $online && $qr => 'direct',
        ! $online && $cash => 'cash',
        default => null,
    };
    // How many cards sit side by side: online + "directly", or each direct way on its own.
    $cardCount = $online ? ($direct ? 2 : 1) : (int) $qr + (int) $cash;
    $directWays = $qr && $cash ? 'own QR or cash' : 'own QR';
@endphp
<div id="{{ $anchor }}" class="scroll-mt-24 space-y-3" x-data="{
        route: @js($startRoute), cashForm: @js($startCash && $errors->any()),
        payAll: @js($startPayAll),
        // This month, or the whole balance: both amount fields (QR and cash) follow the choice.
        setPayAll(flag) {
            this.payAll = flag;
            const value = flag ? @js($allAmountStr) : @js($oneAmountStr);
            this.$root.querySelectorAll('input[name=amount]').forEach(i => { i.value = value; });
        },
    }">
    <div>
        <h3 class="text-[15px] font-normal text-[#060D26]">
            @if($pendingClaim)
                Your payment is waiting for {{ $landlordName }}
            @else
                {{ $isMoveIn ? 'How do you want to pay to move in?' : 'How do you want to pay '.$period['label'].'?' }}
            @endif
        </h3>
        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">{{ $pendingClaim ? 'To pay a different way, withdraw this report first.' : 'You can choose differently next time.' }}</p>
    </div>

    {{-- A report is waiting: the other ways to pay are hidden, so there is one clear thing on screen (and no way
         to pay twice). Withdrawing it brings the choices back. --}}
    @if($pendingClaim)
        @include('payments.partials._claim-status', ['pending' => $pendingClaim, 'rejected' => null, 'landlordName' => $landlordName])
    @elseif($noWayYet)
        <p class="rounded-2xl border border-[#E2E4EC] bg-white p-4 text-[13px] leading-relaxed text-[#060D26]">
            {{ $landlordName }} takes online payments only, and paying online opens when this month is due.
        </p>
    @else
    {{-- Online on: two cards, "online" (protected, held) or "directly" (no fee, no protection), and the QR / cash
         choice sits inside "directly" so the tenant never sees two QR options. Online off: QR and cash side by side. --}}
    <div class="grid gap-3 {{ $cardCount > 1 ? 'sm:grid-cols-2' : '' }}" role="group" aria-label="Payment method">
        @if($online)
            <button type="button" @click="route = 'online'" :aria-pressed="route === 'online'"
                :class="route === 'online' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07] ring-2 ring-[#FF8A66]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay online</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    GCash or QR Ph on PayMongo. {{ $isMoveIn ? 'AbangananHub holds it until you confirm move-in, so you are protected.' : 'Your ledger updates right away.' }}
                </span>
            </button>
            @if($direct)
            <button type="button" @click="route = {{ $qr && $cash ? "(route === 'cash' ? 'cash' : 'direct')" : ($qr ? "'direct'" : "'cash'") }}" :aria-pressed="route === 'direct' || route === 'cash'"
                :class="route === 'direct' || route === 'cash' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07] ring-2 ring-[#FF8A66]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay {{ $landlordName }} directly</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    @if($qr){{ $landlordName }}&rsquo;s {{ $directWays }}@else Cash, in person @endif. No fee, but it goes straight to them; AbangananHub doesn&rsquo;t hold or refund it.
                </span>
            </button>
            @endif
        @else
            @if($qr)
            <button type="button" @click="route = 'direct'" :aria-pressed="route === 'direct'"
                :class="route === 'direct' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07] ring-2 ring-[#FF8A66]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay {{ $landlordName }} by QR</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    Their own QR, then tell us you paid. It goes straight to them; AbangananHub doesn&rsquo;t hold or refund it.
                </span>
            </button>
            @endif
            @if($cash)
            <button type="button" @click="route = 'cash'" :aria-pressed="route === 'cash'"
                :class="route === 'cash' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07] ring-2 ring-[#FF8A66]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay cash</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    Hand it to {{ $landlordName }} in person. They record it here, and you are told when they do.
                </span>
            </button>
            @endif
        @endif
    </div>

    @if($online && $qr && $cash)
        <div x-show="route === 'direct' || route === 'cash'" x-cloak role="group" aria-label="How to pay {{ $landlordName }} directly"
            class="flex rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] p-1 text-[13px] font-bold">
            <button type="button" @click="route = 'direct'" :aria-pressed="route === 'direct'"
                :class="route === 'direct' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E]'"
                class="min-h-[40px] flex-1 rounded-lg px-3 transition-colors">{{ $landlordName }}&rsquo;s QR</button>
            <button type="button" @click="route = 'cash'" :aria-pressed="route === 'cash'"
                :class="route === 'cash' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E]'"
                class="min-h-[40px] flex-1 rounded-lg px-3 transition-colors">Cash</button>
        </div>
    @endif

    @if($canPayAll)
        <div x-show="route === 'direct' || route === 'cash'" x-cloak class="rounded-2xl border border-[#E2E4EC] bg-white p-3 sm:p-4">
            <p class="text-[13px] font-semibold text-[#060D26]">You owe {{ $payAllMonths }} months ({{ $payAllRange }})</p>
            <div class="mt-2 grid grid-cols-2 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] p-1 text-[13px] font-bold" role="group" aria-label="How much to pay">
                <button type="button" @click="setPayAll(false)" :aria-pressed="!payAll"
                    :class="!payAll ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E]'"
                    class="min-h-[44px] rounded-lg px-2 transition-colors">This month<span class="block text-[12px] font-semibold tabular-nums">&#8369;{{ number_format($cashAmount, 2) }}</span></button>
                <button type="button" @click="setPayAll(true)" :aria-pressed="payAll"
                    :class="payAll ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E]'"
                    class="min-h-[44px] rounded-lg px-2 transition-colors">Pay all {{ $payAllMonths }} months<span class="block text-[12px] font-semibold tabular-nums">&#8369;{{ number_format($payAllTotal, 2) }}</span></button>
            </div>
            <p x-show="payAll" x-cloak class="mt-2 text-[12px] leading-relaxed text-[#5B6A8E]">
                One report for {{ $payAllRange }}. {{ $landlordName }} confirms it once and it is spread over those months, oldest first.
            </p>
        </div>
    @endif

    <div x-show="route === 'direct' || route === 'cash'" x-cloak>
        <x-payment-safety-tips :landlord-name="$landlordName" />
    </div>

    {{-- Online: the PayMongo checkout. One payment covers the rent, the deposit (move-in only) and the online fee. --}}
    @if($online)
    <div x-show="route === 'online'" x-cloak>
        @if($canPayAll)
            <p x-show="payAll" x-cloak class="mb-2 rounded-xl bg-[#FBBF24]/15 px-3 py-2 text-[12px] leading-relaxed text-[#060D26]">
                Online pays one month at a time, so this pays {{ $period['label'] }} only. To pay all {{ $payAllMonths }} months at once, pay {{ $landlordName }} directly.
            </p>
        @endif
        <form action="{{ $checkoutUrl }}" method="POST" class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5" x-data="{ agreed: {{ old('accept_terms') ? 'true' : 'false' }}@if($flex),
                        amount: {{ json_encode(old('amount', round($minPay, 2))) }},
                        min: {{ json_encode(round($minPay, 2)) }}, max: {{ json_encode(round($maxPay, 2)) }},
                        feeFirst: {{ json_encode($feeFirst) }}, resFee: {{ json_encode((float) $reservation->reservationFee()) }},
                        depLeft: {{ json_encode(max(0.0, (float) $reservation->agreedDeposit() - $reservation->moveInPaid())) }},
                        pct: {{ json_encode((float) config('rentals.online_payment_fee_percent', 0)) }}, fixed: {{ json_encode((float) config('rentals.online_payment_fee_fixed', 0)) }},
                        get amt() { const n = parseFloat(this.amount); return isNaN(n) ? 0 : n; },
                        get ok() { return this.amt + 0.01 >= this.min && this.amt <= this.max + 0.01; },
                        get fee() { return this.feeFirst ? Math.min(this.amt, this.resFee) : 0; },
                        get dep() { return Math.min(this.amt - this.fee, Math.max(0, this.depLeft - this.fee)); },
                        get rent() { return Math.max(0, this.amt - this.fee - this.dep); },
                        get onlineFee() { const b = this.fee + this.rent; return b > 0 && (this.pct > 0 || this.fixed > 0) ? Math.round((b * this.pct / 100 + this.fixed) * 100) / 100 : 0; },
                        money(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
                    @endif }">
            @csrf
            @if($errors->has('payment'))
                <div role="alert" class="mb-3 rounded-xl border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-[13px] font-semibold text-[#B91C1C]">
                    {{ $errors->first('payment') }}
                </div>
            @endif
            @if($flex)
                <div>
                    <label for="online-amount-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">How much do you want to pay now?</label>
                    <div class="flex items-center gap-2">
                        <span class="text-[16px] font-bold text-[#060D26]">&#8369;</span>
                        <input id="online-amount-{{ $anchor }}" name="amount" type="number" step="0.01" inputmode="decimal" x-model="amount" :min="min" :max="max" required
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[16px] sm:text-[14px] tabular-nums text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" @click="amount = min" class="rounded-full border border-[#E2E4EC] px-3 py-1 text-[12px] font-bold text-[#060D26] hover:bg-[#F7F8FC]">{{ $feeFirst ? 'Reservation fee' : 'Minimum' }} &#8369;{{ number_format($minPay, 2) }}</button>
                        <button type="button" @click="amount = max" class="rounded-full border border-[#E2E4EC] px-3 py-1 text-[12px] font-bold text-[#060D26] hover:bg-[#F7F8FC]">{{ $feeFirst ? 'Pay in full' : 'Pay the balance' }} &#8369;{{ number_format($maxPay, 2) }}</button>
                    </div>
                    <p x-show="!ok" x-cloak class="mt-1.5 text-[12px] text-[#DC2626]">Enter an amount from &#8369;{{ number_format($minPay, 2) }} to &#8369;{{ number_format($maxPay, 2) }}.</p>
                    @error('amount')<p class="mt-1.5 text-[12px] text-[#DC2626]">{{ $message }}</p>@enderror
                    <p class="mt-2 rounded-lg bg-[#F7F8FC] px-3 py-2 text-[12px] leading-relaxed text-[#060D26]">
                        @if($feeFirst)
                            Pay the &#8369;{{ number_format($reservation->reservationFee(), 2) }} reservation fee now to hold the unit, or more. The rest of the &#8369;{{ number_format($maxPay, 2) }} is due before you move in, and you can pay it online later in as many parts as you like.
                        @else
                            &#8369;{{ number_format($maxPay, 2) }} is still due before you move in. Pay all of it now, or part of it and the rest later.
                        @endif
                    </p>
                    <dl class="mt-3 space-y-1.5 text-[13px]">
                        <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Payment</dt><dd class="font-semibold tabular-nums text-[#060D26]" x-text="money(ok ? amt : 0)"></dd></div>
                        <div class="flex justify-between gap-3" x-show="onlineFee > 0" x-cloak><dt class="text-[#5B6A8E]">Online payment fee</dt><dd class="font-semibold tabular-nums text-[#060D26]" x-text="money(onlineFee)"></dd></div>
                        <div class="flex justify-between gap-3 border-t border-[#E2E4EC] pt-1.5"><dt class="font-bold text-[#060D26]">Total</dt><dd class="text-[18px] font-extrabold tabular-nums text-[#060D26]" x-text="money(ok ? amt + onlineFee : 0)"></dd></div>
                    </dl>
                </div>
            @endif
            <dl class="space-y-1.5 text-[13px] {{ $flex ? 'hidden' : '' }}">
                <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">{{ $isMoveIn ? $reservation->moveInRentLabel() : $period['label'] }}</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($onlineAmount, 2) }}</dd></div>
                @if($deposit > 0)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Security deposit</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($deposit, 2) }}</dd></div>
                @endif
                @if($fee > 0)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Online payment fee</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($fee, 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-3 border-t border-[#E2E4EC] pt-1.5"><dt class="font-bold text-[#060D26]">Total</dt><dd class="text-[18px] font-extrabold tabular-nums text-[#060D26]">&#8369;{{ number_format($onlineTotal, 2) }}</dd></div>
            </dl>
            @if($fee > 0)
                <p class="mt-2 text-[12px] leading-relaxed text-[#5B6A8E]">The fee covers the cost of paying online. Paying {{ $landlordName }} directly has no fee.</p>
            @endif
            <div class="mt-3"><x-payment-agree route="online" :landlord-name="$landlordName" :move-in="$isMoveIn" :fee="$fee" :id="'agree-online-'.$anchor" /></div>
            <button type="submit" :disabled="!agreed{{ $flex ? ' || !ok' : '' }}"
                class="mt-4 h-11 w-full rounded-xl bg-[#FF8A66] px-5 text-sm font-bold text-[#060D26] shadow-sm transition-all duration-200 hover:bg-[#E96F4F] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#FF8A66]">
                @if($flex)
                    <span x-text="ok ? 'Pay ' + money(amt + onlineFee) + ' online' : 'Enter a valid amount'"></span>
                @else
                    Pay &#8369;{{ number_format($onlineTotal, 2) }} online
                @endif
            </button>
            <p x-show="!agreed" x-cloak class="mt-1.5 text-center text-[12.5px] text-[#5B6A8E]">Tick the box above to continue.</p>
            <x-payment-test-notice class="mt-3" />
        </form>
    </div>
    @endif

    {{-- Direct: the QR and the "I've paid" report. --}}
    @if($qr)
    <div x-show="route === 'direct'" x-cloak>
        @include('payments.partials._direct-pay', ['reservation' => $reservation, 'type' => $type, 'period' => $period])
    </div>
    @endif

    {{-- Cash: no report to file. The landlord records it when they are handed the money (the same record-a-payment
         the landlord already has), and the tenant is notified. --}}
    @if($cash)
    <div x-show="route === 'cash'" x-cloak>
        <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5">
            <h3 class="text-[15px] font-normal text-[#060D26]">Pay {{ $landlordName }} in cash</h3>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-[#060D26]">&#8369;{{ number_format($cashAmount, 2) }}</p>
            @if($feeUnit)
                <p class="mt-1 inline-block rounded-full bg-[#FF8A66]/15 px-2.5 py-0.5 text-[11.5px] font-bold text-[#060D26]">{{ $reservation->reservationFeePaid() ? 'Balance before move-in' : 'Reservation fee' }}</p>
                <p class="mt-2 rounded-lg bg-[#F7F8FC] px-3 py-2 text-[12.5px] leading-relaxed text-[#060D26]">
                    @if($reservation->reservationFeePaid())
                        Your reservation fee is paid and the unit is held for you. Pay the balance of &#8369;{{ number_format($reservation->moveInShortfall(), 2) }} before you move in.
                    @else
                        This holds the unit with a reservation fee of &#8369;{{ number_format($reservation->reservationFee(), 2) }}. The remaining &#8369;{{ number_format($reservation->moveInBalanceAfterFee(), 2) }} ({{ $reservation->moveInBreakdown() }} in total) is due before you move in.
                    @endif
                </p>
            @else
                <p class="text-[12px] text-[#5B6A8E]">{{ $isMoveIn ? $reservation->moveInBreakdown() : $period['label'] }}</p>
            @endif
            <ol class="mt-3 space-y-1.5 text-[13px] leading-relaxed text-[#060D26] list-decimal pl-5">
                <li>Agree a time and place with {{ $landlordName }} in <a href="{{ $chatLink }}" class="font-bold underline">your chat</a>.</li>
                <li>Hand over the exact amount and ask for a receipt.</li>
                <li>{{ $landlordName }} records it here. You get a notification, and it shows as paid. If they haven&rsquo;t, report it below so there is a record.</li>
            </ol>
            <p class="mt-3 rounded-xl bg-[#FBBF24]/15 px-3 py-2 text-[12px] leading-relaxed text-[#060D26]">
                Cash goes straight to {{ $landlordName }}. AbangananHub doesn&rsquo;t hold, check or refund it, and nothing counts as paid until {{ $landlordName }} records it.
            </p>

            @include('payments.partials._claim-status', ['pending' => $pendingClaim, 'rejected' => $rejectedClaim, 'landlordName' => $landlordName])

            @unless($pendingClaim)
                <a href="{{ $chatLink }}" class="mt-4 flex h-11 w-full items-center justify-center rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">Message {{ $landlordName }}</a>

                {{-- Already handed it over and {{ $landlordName }} has not recorded it? Report it, so there is a record. --}}
                <button type="button" x-show="!cashForm" @click="cashForm = true"
                    class="mt-2 h-11 w-full rounded-xl border border-[#E2E4EC] bg-white text-[14px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">
                    I&rsquo;ve handed over the cash
                </button>
                <form x-show="cashForm" x-cloak method="POST" action="{{ route('paymentClaims.store', $reservation) }}" class="mt-3 space-y-3 border-t border-[#E2E4EC] pt-3" x-data="{ agreed: {{ old('accept_terms') ? 'true' : 'false' }} }">
                    @csrf
                    <input type="hidden" name="claim_type" value="{{ $type }}">
                    <input type="hidden" name="payment_method" value="Cash">
                    @unless($isMoveIn)
                        <input type="hidden" name="billing_period" value="{{ $periodKey->toDateString() }}">
                    @endunless
                    @error('payment')<p class="rounded-lg bg-[#EF4444]/[0.06] px-3 py-2 text-[12.5px] text-[#DC2626]">{{ $message }}</p>@enderror
                    <p class="text-[12px] leading-relaxed text-[#5B6A8E]">Only report this after you actually handed the money over. {{ $landlordName }} is asked to confirm it.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="cash-amount-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Amount you handed over</label>
                            <input id="cash-amount-{{ $anchor }}" name="amount" type="number" step="0.01" min="1" required inputmode="decimal"
                                value="{{ old('payment_method') === 'Cash' ? old('amount') : number_format($cashAmount, 2, '.', '') }}"
                                class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[16px] sm:text-[14px] tabular-nums text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                        </div>
                        <div>
                            <label for="cash-date-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Date handed over</label>
                            <input id="cash-date-{{ $anchor }}" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}"
                                class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[16px] sm:text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                        </div>
                    </div>
                    <div>
                        <label for="cash-note-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Note <span class="font-normal text-[#5B6A8E]">(optional, e.g. receipt number)</span></label>
                        <input id="cash-note-{{ $anchor }}" name="note" maxlength="500" value="{{ old('note') }}"
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[16px] sm:text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    </div>
                    <x-payment-agree route="cash" :landlord-name="$landlordName" :id="'agree-cash-'.$anchor" />
                    <button type="submit" :disabled="!agreed" class="h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#FF8A66]">Report cash to {{ $landlordName }}</button>
                    <p x-show="!agreed" x-cloak class="mt-1.5 text-center text-[12.5px] text-[#5B6A8E]">Tick the box above to continue.</p>
                </form>
            @endunless
        </div>
    </div>
    @endif
    @endif
</div>
