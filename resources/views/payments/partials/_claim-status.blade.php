{{--
    The tenant's view of a payment report: waiting for the landlord, or turned down (with a way to send it to
    an admin). Shared by the QR panel and the cash panel.
    Needs $pending and $rejected (a PaymentClaim or null each) and $landlordName.
--}}
@if($rejected)
    <div class="mt-4 rounded-xl border border-[#EF4444]/25 bg-[#EF4444]/[0.05] px-3 py-2.5 text-[12.5px] text-[#060D26]">
        <p class="font-bold text-[#DC2626]">{{ $landlordName }} couldn&rsquo;t confirm your last report</p>
        <p class="mt-0.5">&ldquo;{{ $rejected->rejection_reason }}&rdquo; (&#8369;{{ number_format($rejected->amount, 2) }}, {{ $rejected->isCash() ? 'cash' : 'ref '.$rejected->reference_no }})</p>
        @if($rejected->canBeDisputed())
            <p class="mt-2 text-[12px] text-[#5B6A8E]">Sure you paid? AbangananHub can look at it. Your report, the reference and {{ $landlordName }}&rsquo;s reason go to an admin.</p>
            <form method="POST" action="{{ route('paymentClaims.dispute', $rejected) }}" class="mt-2"
                data-confirm="Send this to AbangananHub?" data-confirm-message="An admin will review your report against {{ $landlordName }}&rsquo;s answer. You can still report the payment again." data-confirm-button="Send for review">
                @csrf
                <button type="submit" class="h-9 rounded-lg border border-[#DC2626] bg-white px-3 text-[12px] font-bold text-[#DC2626] hover:bg-[#EF4444]/5 transition-colors">Send to AbangananHub for review</button>
            </form>
        @elseif($rejected->disputed_at)
            <p class="mt-2 text-[12px] font-semibold text-[#5B6A8E]">Sent to AbangananHub for review on {{ $rejected->disputed_at->format('M j') }}.</p>
        @endif
    </div>
@endif

@if($pending)
    <div class="mt-4 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3 py-3">
        <p class="text-[13px] font-bold text-[#060D26]">Waiting for {{ $landlordName }} to confirm</p>
        <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
            &#8369;{{ number_format($pending->amount, 2) }}
            {{ $pending->isCash() ? 'in cash' : 'by '.$pending->payment_method.', ref '.$pending->reference_no }},
            {{ $pending->isCash() ? 'handed over' : 'paid' }} {{ $pending->paid_on->format('M j') }}.
        </p>
        <form method="POST" action="{{ route('paymentClaims.withdraw', $pending) }}" class="mt-2"
            data-confirm="Withdraw this report?" data-confirm-message="Use this if you entered it wrong. You can report again." data-confirm-button="Withdraw">
            @csrf @method('DELETE')
            <button type="submit" class="h-8 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Withdraw</button>
        </form>
    </div>
@endif
