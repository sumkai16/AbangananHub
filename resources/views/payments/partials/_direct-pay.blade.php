{{--
    The tenant's "Pay your landlord" card: the landlord's shared QR, the plain-words notice, and the
    "I've paid" report. Money goes straight to the landlord; AbangananHub only keeps the record.
    Needs $reservation (property.landlord, unit, paymentClaims loaded) and $type ('MoveIn' | 'Monthly').
    For Monthly also $period (a RentLedger period: 'period' Carbon, 'balance', 'label').
--}}
@php
    $landlord = $reservation->property?->landlord;
    $landlordName = $landlord?->first_name ?? 'your landlord';
    $isMoveIn = $type === 'MoveIn';
    $share = \App\Models\PaymentQrShare::latestFor($reservation);

    $defaultAmount = $isMoveIn
        ? $reservation->moveInDue()
        : (float) ($period['balance'] ?? 0);
    $periodKey = $isMoveIn ? null : $period['period']->copy()->startOfMonth();

    $mine = $reservation->paymentClaims
        ->where('claim_type', $type)
        ->when($periodKey, fn ($c) => $c->filter(fn ($claim) => $claim->billing_period?->isSameDay($periodKey)))
        ->sortByDesc('payment_claim_id');
    $pending = $mine->firstWhere('status', 'Pending');
    $rejected = ! $pending && $mine->first()?->status === 'Rejected' ? $mine->first() : null;
    $conversationLink = $reservation->conversation_id
        ? route('conversations.index', ['active' => $reservation->conversation_id])
        : route('tenancy.chat', $reservation);
@endphp
<div id="{{ $isMoveIn ? 'pay' : 'pay-'.$periodKey->format('Y-m') }}" class="scroll-mt-24 rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5" x-data="{ form: {{ $errors->has('payment') || $errors->has('reference_no') || $errors->has('amount') || $errors->has('paid_on') || ($isMoveIn && request()->boolean('pay')) ? 'true' : 'false' }} }">
    <h3 class="text-[15px] font-normal text-[#060D26]">
        {{ $isMoveIn ? 'Pay your landlord to move in' : (($period['is_future'] ?? false) ? 'Pay ahead: '.$period['label'] : 'Pay your landlord: '.$period['label']) }}
    </h3>

    @if($isMoveIn)
        <dl class="mt-3 space-y-1 text-[13px]">
            @if($reservation->advanceMonths() > 0)
                <div class="flex justify-between"><dt class="text-[#5B6A8E]">{{ $reservation->advanceMonths() === 1 ? 'First month’s rent' : $reservation->advanceMonths().' months’ rent in advance' }}</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($reservation->advanceMonths() * $reservation->monthlyRent(), 2) }}</dd></div>
            @endif
            @if($reservation->agreedDeposit() > 0)
                <div class="flex justify-between"><dt class="text-[#5B6A8E]">Security deposit</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($reservation->agreedDeposit(), 2) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-[#E2E4EC] pt-1.5"><dt class="font-bold text-[#060D26]">Total</dt><dd class="font-bold tabular-nums text-[#060D26]">&#8369;{{ number_format($defaultAmount, 2) }}</dd></div>
        </dl>
    @else
        @if($period['is_future'] ?? false)
            <p class="mt-1 text-[13px] text-[#5B6A8E]">You&rsquo;re all paid up. Want to pay early? One month is <span class="font-bold tabular-nums text-[#060D26]">&#8369;{{ number_format($defaultAmount, 2) }}</span>. Paying more covers the months after it, in order.</p>
        @else
            <p class="mt-1 text-[13px] text-[#5B6A8E]">Balance for this month: <span class="font-bold tabular-nums text-[#060D26]">&#8369;{{ number_format($defaultAmount, 2) }}</span></p>
        @endif
    @endif

    <p class="mt-3 rounded-xl bg-[#FBBF24]/15 px-3 py-2 text-[12px] leading-relaxed text-[#060D26]">
        This goes straight to {{ $landlordName }}. AbangananHub doesn&rsquo;t hold, check or refund it, so only pay a QR your landlord shared in your chat.
    </p>

    <div class="mt-4">
        @if($share)
            @include('payments.partials._qr-block', ['share' => $share])
        @else
            <p class="rounded-xl border border-dashed border-[#5B6A8E]/40 px-3 py-3 text-[12.5px] text-[#5B6A8E]">
                {{ $landlordName }} hasn&rsquo;t shared a payment QR yet.
                <a href="{{ $conversationLink }}" class="font-bold text-[#060D26] underline">Ask in your chat</a>.
            </p>
        @endif
    </div>

    @if($rejected)
        <div class="mt-4 rounded-xl border border-[#EF4444]/25 bg-[#EF4444]/[0.05] px-3 py-2.5 text-[12.5px] text-[#060D26]">
            <p class="font-bold text-[#DC2626]">{{ $landlordName }} couldn&rsquo;t confirm your last report</p>
            <p class="mt-0.5">&ldquo;{{ $rejected->rejection_reason }}&rdquo; (&#8369;{{ number_format($rejected->amount, 2) }}, ref {{ $rejected->reference_no }})</p>
        </div>
    @endif

    @if($pending)
        <div class="mt-4 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3 py-3">
            <p class="text-[13px] font-bold text-[#060D26]">Waiting for {{ $landlordName }} to confirm</p>
            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">&#8369;{{ number_format($pending->amount, 2) }} by {{ $pending->payment_method }}, ref {{ $pending->reference_no }}, paid {{ $pending->paid_on->format('M j') }}.</p>
            <form method="POST" action="{{ route('paymentClaims.withdraw', $pending) }}" class="mt-2"
                data-confirm="Withdraw this report?" data-confirm-message="Use this if you entered it wrong. You can report again." data-confirm-button="Withdraw">
                @csrf @method('DELETE')
                <button type="submit" class="h-8 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Withdraw</button>
            </form>
        </div>
    @else
        <button type="button" x-show="!form" @click="form = true"
            class="mt-4 h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">
            I&rsquo;ve paid {{ $landlordName }}
        </button>

        <form x-show="form" x-cloak method="POST" action="{{ route('paymentClaims.store', $reservation) }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="claim_type" value="{{ $type }}">
            @unless($isMoveIn)
                <input type="hidden" name="billing_period" value="{{ $periodKey->toDateString() }}">
            @endunless

            @error('payment')<p class="rounded-lg bg-[#EF4444]/[0.06] px-3 py-2 text-[12.5px] text-[#DC2626]">{{ $message }}</p>@enderror

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="claim-amount-{{ $type }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Amount you paid</label>
                    <input id="claim-amount-{{ $type }}" name="amount" type="number" step="0.01" min="1" required inputmode="decimal"
                        value="{{ old('amount', number_format($defaultAmount, 2, '.', '')) }}"
                        class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] tabular-nums text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    @error('amount')<p class="mt-1 text-[12px] text-[#EF4444]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="claim-method-{{ $type }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Paid from</label>
                    <select id="claim-method-{{ $type }}" name="payment_method" required
                        class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                        @foreach(['GCash' => 'GCash', 'Maya' => 'Maya', 'GoTyme' => 'GoTyme', 'Bank Transfer' => 'Bank app', 'Other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="claim-ref-{{ $type }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Reference number</label>
                    <input id="claim-ref-{{ $type }}" name="reference_no" required maxlength="100" value="{{ old('reference_no') }}" placeholder="From your receipt"
                        class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    @error('reference_no')<p class="mt-1 text-[12px] text-[#EF4444]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="claim-date-{{ $type }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Date paid</label>
                    <input id="claim-date-{{ $type }}" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}"
                        class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    @error('paid_on')<p class="mt-1 text-[12px] text-[#EF4444]">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="claim-note-{{ $type }}" class="mb-1 block text-[12px] font-semibold text-[#060D26]">Note <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                <input id="claim-note-{{ $type }}" name="note" maxlength="500" value="{{ old('note') }}"
                    class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3 py-2.5 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
            </div>
            <p class="text-[11.5px] text-[#5B6A8E]">Paid in cash? Hand it over and ask {{ $landlordName }} to record it. There is no reference number to report.</p>
            <button type="submit" class="h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">Send to {{ $landlordName }}</button>
        </form>
    @endif
</div>
