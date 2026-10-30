{{-- Reservations list, signed agreement paid directly: the report to answer, or the move-in to confirm. Needs $reservation (payments, paymentClaims loaded). --}}
@php
    $waitingClaim = $reservation->paymentClaims->where('status', 'Pending')->where('claim_type', 'MoveIn')->first();
    $chatLink = $reservation->conversation_id ? route('conversations.index', ['active' => $reservation->conversation_id]) : null;
@endphp
@if($reservation->move_in_paid_at && ! $reservation->payments->contains('status', 'Held'))
    <form action="{{ route('landlord.reservations.confirmDirectMoveIn', $reservation) }}" method="POST"
        data-confirm="Confirm move-in?" data-confirm-type="confirm"
        data-confirm-message="This starts {{ $reservation->tenant->first_name ?? 'the tenant' }}'s tenancy and rent schedule."
        data-confirm-button="Confirm move-in" data-confirm-cancel="Not yet">
        @csrf
        <button type="submit" class="h-8 px-3 rounded-lg bg-[#FF8A66] text-[#060D26] text-[12px] font-semibold hover:bg-[#E96F4F] cursor-pointer transition-all duration-200 whitespace-nowrap">Confirm move-in</button>
    </form>
@elseif($waitingClaim && $chatLink)
    <a href="{{ $chatLink }}" class="inline-flex h-8 items-center rounded-lg bg-[#FF8A66] px-3 text-[12px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-all duration-200 whitespace-nowrap">Payment to confirm</a>
@endif
