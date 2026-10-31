{{--
    Landlord: payment reports waiting for an answer. Confirming writes the money as paid; rejecting needs a
    reason the tenant will read. Needs $claims (pending PaymentClaims) and optionally $tenantName. Renders nothing when empty.
--}}
@foreach($claims as $claim)
    @php
        $label = $claim->claim_type === 'MoveIn' ? 'Move-in payment' : 'Rent for '.$claim->billing_period->format('F Y');
        // A move-in payment on or after the move-in date also starts the tenancy, so it is one button, not two.
        $andMoveIn = $claim->claim_type === 'MoveIn'
            && $claim->reservation->rental_status === 'Rental Agreement Signed'
            && $claim->reservation->moveInDateReached();
    @endphp
    <div class="rounded-2xl border border-[#FF8A66]/40 bg-[#FF8A66]/[0.06] p-4" x-data="{ reject: false }">
        <p class="text-[11px] font-bold uppercase tracking-wider text-[#B35A3D]">Payment to confirm &middot; {{ $label }}</p>
        <p class="mt-1 text-[13.5px] text-[#060D26]">
            <span class="font-bold">{{ $tenantName ?? 'Your tenant' }}</span>
            @if($claim->isCash())
                says they handed you <span class="font-bold tabular-nums">&#8369;{{ number_format($claim->amount, 2) }}</span> in cash
                on {{ $claim->paid_on->format('M j') }}.
            @else
                says they paid <span class="font-bold tabular-nums">&#8369;{{ number_format($claim->amount, 2) }}</span> by {{ $claim->payment_method }}
                on {{ $claim->paid_on->format('M j') }}.
            @endif
        </p>
        @if($claim->isCash())
            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">Confirm only if you actually received this cash. If you didn&rsquo;t, press Not received and say why.</p>
        @else
            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">Reference <span class="font-mono font-bold text-[#060D26]">{{ $claim->reference_no }}</span>. Look for it in your {{ $claim->payment_method }} account before you confirm.</p>
        @endif
        @if($claim->note)<p class="mt-1 text-[12px] italic text-[#5B6A8E]">&ldquo;{{ $claim->note }}&rdquo;</p>@endif

        <div class="mt-3 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('landlord.paymentClaims.confirm', $claim) }}"
                data-confirm="{{ $andMoveIn ? 'Confirm payment and move-in?' : 'Confirm this payment?' }}" data-confirm-message="{{ $andMoveIn ? 'It will count as paid, start the tenancy and the rent schedule, and your tenant is notified. Only confirm after you see the money in your account and hand over the keys.' : ($claim->isCash() ? 'It will count as paid and your tenant is notified. Only confirm if you received the cash.' : 'It will count as paid and your tenant is notified. Only confirm after you see the money in your account.') }}" data-confirm-button="{{ $andMoveIn ? 'Confirm & move in' : 'Confirm payment' }}">
                @csrf
                @if($andMoveIn)<input type="hidden" name="and_move_in" value="1">@endif
                <button type="submit" class="h-9 rounded-xl bg-[#FF8A66] px-4 text-[12.5px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">{{ $andMoveIn ? 'Confirm payment & move-in' : 'Confirm payment' }}</button>
            </form>
            <button type="button" @click="reject = !reject" class="h-9 rounded-xl border border-[#EF4444] px-4 text-[12.5px] font-semibold text-[#EF4444] hover:bg-[#EF4444]/5 transition-colors">Not received</button>
        </div>

        <form x-show="reject" x-cloak method="POST" action="{{ route('landlord.paymentClaims.reject', $claim) }}" class="mt-3 flex gap-2">
            @csrf
            <label for="reject-claim-{{ $claim->payment_claim_id }}" class="sr-only">Why not</label>
            <input id="reject-claim-{{ $claim->payment_claim_id }}" name="rejection_reason" required minlength="3" maxlength="255" placeholder="e.g. No payment with that reference in my account"
                class="min-w-0 flex-1 rounded-lg border border-[#E2E4EC] bg-white px-3 py-2 text-[12.5px] text-[#060D26] placeholder-[#5B6A8E] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
            <button type="submit" class="shrink-0 rounded-lg bg-[#EF4444] px-4 py-2 text-[12px] font-bold text-white hover:brightness-95 transition-all">Reject</button>
        </form>
    </div>
@endforeach
