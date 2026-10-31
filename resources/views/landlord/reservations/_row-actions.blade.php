{{--
    One request's actions on the Reservations page, shared by the table and the phone cards: Details, the one next
    step for its stage (Accept · Send agreement · Confirm payment), Chat, and a quiet Reject / Cancel.
    Needs $reservation (tenant, conversation, paymentClaims loaded) and $modalData.
--}}
@php
    $status = $reservation->rental_status;
    $report = $status === 'Rental Agreement Signed'
        ? $reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'MoveIn')->first()
        : null;
    $primary = 'h-8 px-3 rounded-lg bg-[#FF8A66] text-[#060D26] text-[12px] font-semibold hover:bg-[#E96F4F] cursor-pointer transition-colors duration-200 whitespace-nowrap';
    $iconBtn = 'h-8 w-8 flex items-center justify-center rounded-lg border border-[#5B6A8E]/25 text-[#060D26] hover:bg-[#ECEEF6] transition-colors duration-200 shrink-0';
    $quiet = 'h-8 px-2.5 rounded-lg text-[12px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] hover:bg-[#EF4444]/[0.06] cursor-pointer transition-colors duration-200 whitespace-nowrap';
    $tenantName = $reservation->tenant->first_name ?? 'the tenant';
@endphp

<button type="button" @click="openModal({{ Js::from($modalData) }})"
    class="h-8 px-3 inline-flex items-center rounded-lg bg-[#ECEEF6] text-[#060D26] text-[12px] font-semibold hover:bg-[#E2E4EC] cursor-pointer transition-colors duration-200 whitespace-nowrap">
    Details
</button>

@if($status === 'Inquiry')
    <form action="{{ route('landlord.reservations.advanceNegotiation', $reservation) }}" method="POST">
        @csrf @method('PATCH')
        <button type="submit" class="{{ $primary }}">Accept</button>
    </form>
@elseif($status === 'Under Negotiation')
    <button type="button" class="{{ $primary }}"
        @click="openAgreement('{{ route('landlord.reservations.advanceAgreement', $reservation) }}', @js($reservation->agreementDefaults()))">
        Send agreement
    </button>
@elseif($report)
    <form method="POST" action="{{ route('landlord.paymentClaims.confirm', $report) }}"
        data-confirm="Confirm ₱{{ number_format((float) $report->amount, 2) }} from {{ $tenantName }}?"
        data-confirm-message="{{ $report->payment_method }}, reference {{ $report->reference_no }}, paid {{ $report->paid_on->format('M j') }}. Only confirm after you see it in your account. The unit is then held for {{ $tenantName }} and the lease moves to Contracts."
        data-confirm-button="Confirm payment">
        @csrf
        <button type="submit" class="{{ $primary }}">Confirm payment</button>
    </form>
@endif

@if($reservation->conversation)
    <a href="{{ route('conversations.show', $reservation->conversation) }}" aria-label="Open conversation" title="Chat" class="{{ $iconBtn }}">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
        </svg>
    </a>
@endif

{{-- Reject (before an agreement) or Cancel (after), as a quiet text button so the next step stays the obvious one. --}}
@if(in_array($status, ['Inquiry', 'Under Negotiation'], true))
    <form action="{{ route('landlord.reservations.reject', $reservation) }}" method="POST"
        data-confirm="Reject this request?" data-confirm-type="warning"
        data-confirm-message="{{ $tenantName }} is told the request was declined." data-confirm-button="Reject" data-confirm-cancel="Keep it">
        @csrf @method('PATCH')
        <button type="submit" class="{{ $quiet }}">Reject</button>
    </form>
@elseif(in_array($status, ['Pending Rental Agreement', 'Rental Agreement Signed'], true) && ($blocked = $reservation->cancellationBlockedReason()))
    {{-- Money held through the (switched-off) online checkout: say why there is no Cancel instead of a button that only fails. --}}
    <p class="basis-full max-w-[280px] text-[11.5px] leading-snug text-[#5B6A8E]">{{ $blocked }}</p>
@elseif(in_array($status, ['Pending Rental Agreement', 'Rental Agreement Signed'], true))
    <form action="{{ route('landlord.reservations.cancel', $reservation) }}" method="POST"
        data-confirm="Cancel this reservation?" data-confirm-type="warning"
        data-confirm-message="{{ $reservation->directPaymentNotice('you') ? $reservation->directPaymentNotice('you').' The unit will be marked Available again.' : 'The unit will be marked Available again.' }}"
        data-confirm-button="Cancel reservation" data-confirm-cancel="Keep it">
        @csrf @method('PATCH')
        <button type="submit" class="{{ $quiet }}">Cancel</button>
    </form>
@endif
