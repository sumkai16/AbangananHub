@props(['reservation', 'type', 'period' => null])
{{--
    "How do you want to pay?" The options, picked each time: pay online through PayMongo (only when the landlord
    can be paid online, Payments::onlineFor), pay the landlord's own QR and report it, or pay cash in person and
    let the landlord record it. QR and cash always show, so a landlord without GCash still gets both.
    Needs $reservation (property.landlord, unit, paymentClaims loaded) and $type ('MoveIn' | 'Monthly').
    For Monthly also $period (a RentLedger period). The direct side is the existing payments.partials._direct-pay.
--}}
@php
    $isMoveIn = $type === 'MoveIn';
    $landlordName = $reservation->property?->landlord?->first_name ?? 'your landlord';
    $onlineAmount = $isMoveIn ? (float) $reservation->monthlyRent() : max(0, (float) ($period['balance'] ?? 0));
    $deposit = $isMoveIn ? (float) $reservation->agreedDeposit() : 0.0;
    $fee = \App\Support\ServiceFee::for($onlineAmount);
    $onlineTotal = $onlineAmount + $deposit + $fee;
    // Online pays the earliest unsettled month only, so paying a month ahead is QR or cash.
    $online = \App\Support\Payments::onlineFor($reservation) && ! ($period['is_future'] ?? false);
    $cashAmount = $isMoveIn ? $onlineAmount + $deposit : $onlineAmount;
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
    $startCash = ($pendingClaim ?? $rejectedClaim)?->isCash() || old('payment_method') === 'Cash';
    $startDirect = ! $startCash && ($claims->contains(fn ($claim) => in_array($claim->status, ['Pending', 'Rejected'], true))
        || old('claim_type') === $type
        || ($isMoveIn && request()->boolean('pay')));
    // A checkout that came back with an error ('payment', and no report form input) reopens the online side so the message is seen.
    $startOnline = ! $startDirect && ($errors->has('payment') || $errors->has('accept_terms'));
    $anchor = $isMoveIn ? 'choose-pay' : 'choose-pay-'.$periodKey->format('Y-m');
@endphp
<div id="{{ $anchor }}" class="scroll-mt-24 space-y-3" x-data="{ route: @js($startCash ? 'cash' : ($startDirect ? 'direct' : ($startOnline && $online ? 'online' : ($online ? null : 'direct')))), cashForm: @js($startCash && $errors->any()) }">
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
    @else
    {{-- Online on: two cards, "online" (protected, held) or "directly" (no fee, no protection), and the QR / cash
         choice sits inside "directly" so the tenant never sees two QR options. Online off: QR and cash side by side. --}}
    <div class="grid gap-3 sm:grid-cols-2" role="group" aria-label="Payment method">
        @if($online)
            <button type="button" @click="route = 'online'" :aria-pressed="route === 'online'"
                :class="route === 'online' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay online</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    GCash or QR Ph on PayMongo. {{ $isMoveIn ? 'AbangananHub holds it until you confirm move-in, so you are protected.' : 'Your ledger updates right away.' }}
                </span>
            </button>
            <button type="button" @click="route = (route === 'cash' ? 'cash' : 'direct')" :aria-pressed="route === 'direct' || route === 'cash'"
                :class="route === 'direct' || route === 'cash' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay {{ $landlordName }} directly</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    {{ $landlordName }}&rsquo;s own QR or cash. No fee, but it goes straight to them; AbangananHub doesn&rsquo;t hold or refund it.
                </span>
            </button>
        @else
            <button type="button" @click="route = 'direct'" :aria-pressed="route === 'direct'"
                :class="route === 'direct' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay {{ $landlordName }} by QR</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    Their own QR, then tell us you paid. It goes straight to them; AbangananHub doesn&rsquo;t hold or refund it.
                </span>
            </button>
            <button type="button" @click="route = 'cash'" :aria-pressed="route === 'cash'"
                :class="route === 'cash' ? 'border-[#FF8A66] bg-[#FF8A66]/[0.07]' : 'border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]'"
                class="min-h-[44px] rounded-2xl border p-4 text-left transition-colors">
                <span class="block text-[14px] font-bold text-[#060D26]">Pay cash</span>
                <span class="mt-0.5 block text-[12px] leading-relaxed text-[#5B6A8E]">
                    Hand it to {{ $landlordName }} in person. They record it here, and you are told when they do.
                </span>
            </button>
        @endif
    </div>

    @if($online)
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

    <div x-show="route === 'direct' || route === 'cash'" x-cloak>
        <x-payment-safety-tips :landlord-name="$landlordName" />
    </div>

    {{-- Online: the PayMongo checkout. One payment covers the rent, the deposit (move-in only) and the online fee. --}}
    @if($online)
    <div x-show="route === 'online'" x-cloak>
        <form action="{{ $checkoutUrl }}" method="POST" class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5" x-data="{ agreed: {{ old('accept_terms') ? 'true' : 'false' }} }">
            @csrf
            @if($errors->has('payment'))
                <div role="alert" class="mb-3 rounded-xl border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-[13px] font-semibold text-[#B91C1C]">
                    {{ $errors->first('payment') }}
                </div>
            @endif
            <dl class="space-y-1.5 text-[13px]">
                <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">{{ $isMoveIn ? 'First month’s rent' : $period['label'] }}</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($onlineAmount, 2) }}</dd></div>
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
            <button type="submit" :disabled="!agreed"
                class="mt-4 h-11 w-full rounded-xl bg-[#FF8A66] px-5 text-sm font-bold text-[#060D26] shadow-sm transition-all duration-200 hover:bg-[#E96F4F] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#FF8A66]">
                Pay &#8369;{{ number_format($onlineTotal, 2) }} online
            </button>
            <x-payment-test-notice class="mt-3" />
        </form>
    </div>
    @endif

    {{-- Direct: the QR and the "I've paid" report. --}}
    <div x-show="route === 'direct'" x-cloak>
        @include('payments.partials._direct-pay', ['reservation' => $reservation, 'type' => $type, 'period' => $period])
    </div>

    {{-- Cash: no report to file. The landlord records it when they are handed the money (the same record-a-payment
         the landlord already has), and the tenant is notified. --}}
    <div x-show="route === 'cash'" x-cloak>
        <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5">
            <h3 class="text-[15px] font-normal text-[#060D26]">Pay {{ $landlordName }} in cash</h3>
            <p class="mt-1 text-2xl font-extrabold tabular-nums text-[#060D26]">&#8369;{{ number_format($cashAmount, 2) }}</p>
            <p class="text-[12px] text-[#5B6A8E]">{{ $isMoveIn ? 'First month’s rent'.($deposit > 0 ? ' and security deposit' : '') : $period['label'] }}</p>
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
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cash-amount-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Amount you handed over</label>
                            <input id="cash-amount-{{ $anchor }}" name="amount" type="number" step="0.01" min="1" required inputmode="decimal"
                                value="{{ old('payment_method') === 'Cash' ? old('amount') : number_format($cashAmount, 2, '.', '') }}"
                                class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] tabular-nums text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                        </div>
                        <div>
                            <label for="cash-date-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Date handed over</label>
                            <input id="cash-date-{{ $anchor }}" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}"
                                class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                        </div>
                    </div>
                    <div>
                        <label for="cash-note-{{ $anchor }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Note <span class="font-normal text-[#5B6A8E]">(optional, e.g. receipt number)</span></label>
                        <input id="cash-note-{{ $anchor }}" name="note" maxlength="500" value="{{ old('note') }}"
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    </div>
                    <x-payment-agree route="cash" :landlord-name="$landlordName" :id="'agree-cash-'.$anchor" />
                    <button type="submit" :disabled="!agreed" class="h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#FF8A66]">Report cash to {{ $landlordName }}</button>
                </form>
            @endunless
        </div>
    </div>
    @endif
</div>
