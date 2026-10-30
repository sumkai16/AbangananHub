{{--
    The action buttons on one row of the tenant's reservations list. Shared by the mobile card list and the desktop table,
    which used to carry two copies that drifted. Expects: $reservation, $canReview, $pad (vertical padding class).

    Order is deliberate: the one thing to do next, then chat, then the destructive action last. Cancel is not
    offered while money is held or a checkout is open (Reservation::cancellationBlockedReason()); the reason is
    shown instead, so there is never a button that only fails.
--}}
@php
    $status = $reservation->rental_status;
    $btn = 'text-[12px] font-semibold rounded-lg px-3 '.$pad.' transition-all duration-150';
    $ghost = $btn.' text-[#060D26] border border-[#E2E4EC] hover:bg-[#F7F8FC]';
    $cta = $btn.' text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F]';

    $isInquiry = in_array($status, ['Inquiry', 'Under Negotiation'], true);
    $cancellable = $isInquiry || in_array($status, ['Pending Rental Agreement', 'Rental Agreement Signed'], true);
    $blockedReason = $cancellable ? $reservation->cancellationBlockedReason() : null;
    $next = $reservation->tenantNextStep();

    // Once they live there, the tenancy thread is the conversation; the old inquiry thread is not.
    $chatUrl = $status === 'Occupied'
        ? route('tenancy.chat', $reservation)
        : ($reservation->conversation ? route('conversations.show', $reservation->conversation) : null);
@endphp

@if($next)
    <a href="{{ $next['url'] }}" class="{{ $cta }}">{{ $next['label'] }}</a>
@endif

@if($canReview)
    <a href="{{ route('properties.show', $reservation->property) }}#reviews"
        class="{{ $status === 'Completed' && ! $next ? $cta : $ghost }}">
        {{ $status === 'Completed' ? 'Leave a review' : 'Review' }}
    </a>
@endif

@if($chatUrl)
    <a href="{{ $chatUrl }}" class="{{ $ghost }}">Chat</a>
@endif

@if($cancellable && $blockedReason === null)
    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
        data-confirm="{{ $isInquiry ? 'Cancel this inquiry?' : 'Cancel this reservation?' }}"
        data-confirm-type="warning"
        data-confirm-message="{{ $reservation->directPaymentNotice('your landlord') ?? 'This action cannot be undone.' }}"
        data-confirm-button="{{ $isInquiry ? 'Cancel inquiry' : 'Cancel reservation' }}"
        data-confirm-cancel="Keep it">
        @csrf @method('PATCH')
        <button type="submit" class="{{ $btn }} text-white bg-[#EF4444] hover:brightness-95">Cancel</button>
    </form>
@endif

@if($blockedReason)
    <p class="basis-full text-[11.5px] leading-snug text-[#5B6A8E] sm:text-right">{{ $blockedReason }}</p>
@endif
