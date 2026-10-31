{{--
    Where a tenant's refunded online payment stands, shown on their reservation row. A QR Ph refund stays
    "processing" until the tenant opens the transfer link and claims the money, so the link is the whole point
    of this notice for them. Needs $reservation with `payments` loaded. Prints nothing when nothing was refunded.
--}}
@php
    $refunded = $reservation->payments->where('status', 'Refunded');
    $refundLead = $refunded->first();
    $refundTotal = $refunded->sum('amount') + ($refundLead?->refund_includes_fee ? $refunded->sum('service_fee') : 0);
    $claimLink = $refundLead?->refund_transfer_link;
    $claimable = $refundLead?->refund_status === 'processing' && is_string($claimLink) && str_starts_with($claimLink, 'https://');
@endphp
@if($refundLead)
    <p class="basis-full text-[11.5px] leading-snug text-[#5B6A8E] sm:text-right">
        @if($refundLead->refund_status === 'failed')
            <span class="font-semibold text-[#DC2626]">Your &#8369;{{ number_format($refundTotal, 2) }} refund could not be completed.</span>
            An administrator is looking into it.
        @elseif($claimable)
            Your &#8369;{{ number_format($refundTotal, 2) }} was refunded.
            <a href="{{ $claimLink }}" target="_blank" rel="noopener noreferrer" class="font-bold text-[#060D26] underline">Claim your refund</a>
            within 3 days.
        @elseif($refundLead->refund_status === 'succeeded')
            &#8369;{{ number_format($refundTotal, 2) }} was refunded to you on {{ $refundLead->refunded_at?->format('M d, Y') }}.
        @else
            Your &#8369;{{ number_format($refundTotal, 2) }} refund is on its way. GCash usually receives it within the day.
        @endif
    </p>
@endif
