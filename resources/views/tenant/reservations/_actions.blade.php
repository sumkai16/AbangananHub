{{--
    The action buttons on one row of the tenant's reservations list. Shared by the mobile card list and the desktop table,
    which used to carry two copies that drifted. Expects: $reservation, $canReview, $details (the details modal's data),
    $mobile (bool: full-width primary button on the card).

    One coloured button: the one thing to do next (or Review once there is nothing else). Chat and Details are small
    icon buttons, and Cancel is a quiet red text button last, so a row never wraps onto a second line of buttons.
    Cancel is not offered while money is held or a checkout is open (Reservation::cancellationBlockedReason()); the
    reason is shown instead, so there is never a button that only fails.
--}}
@php
    $mobile = $mobile ?? false;
    $status = $reservation->rental_status;
    $h = $mobile ? 'h-10' : 'h-9';
    $cta = $h.' inline-flex items-center justify-center whitespace-nowrap rounded-lg px-3.5 text-[12.5px] font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-colors duration-150'.($mobile ? ' flex-1' : '');
    $ghost = $h.' inline-flex items-center justify-center whitespace-nowrap rounded-lg px-3 text-[12.5px] font-semibold text-[#060D26] border border-[#E2E4EC] hover:bg-[#F7F8FC] transition-colors duration-150';
    $icon = $h.' w-9 shrink-0 inline-flex items-center justify-center rounded-lg border border-[#E2E4EC] text-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-150';

    $isInquiry = in_array($status, ['Inquiry', 'Under Negotiation'], true);
    $cancellable = $isInquiry || in_array($status, ['Pending Rental Agreement', 'Rental Agreement Signed'], true);
    $blockedReason = $cancellable ? $reservation->cancellationBlockedReason() : null;
    $next = $reservation->tenantNextStep();
    $reviewLabel = $status === 'Completed' ? 'Leave a review' : 'Review';

    // Once they live there, the tenancy thread is the conversation; the old inquiry thread is not.
    $chatUrl = $status === 'Occupied'
        ? route('tenancy.chat', $reservation)
        : ($reservation->conversation ? route('conversations.show', $reservation->conversation) : null);
@endphp

@if($next)
    <a href="{{ $next['url'] }}" class="{{ $cta }}">{{ $next['label'] }}</a>
@elseif($canReview)
    <a href="{{ route('properties.show', $reservation->property) }}#reviews" class="{{ $cta }}">{{ $reviewLabel }}</a>
@endif

@if($next && $canReview)
    <a href="{{ route('properties.show', $reservation->property) }}#reviews" class="{{ $ghost }}">{{ $reviewLabel }}</a>
@endif

@if($chatUrl)
    <a href="{{ $chatUrl }}" class="{{ $icon }}" title="Chat with your landlord" aria-label="Chat with your landlord">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
    </a>
@endif

<button type="button" @click="openModal({{ Js::from($details) }})" class="{{ $icon }} cursor-pointer" title="View details" aria-label="View details">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
</button>

@if($cancellable && $blockedReason === null)
    <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
        data-confirm="{{ $isInquiry ? 'Cancel this inquiry?' : 'Cancel this reservation?' }}"
        data-confirm-type="warning"
        data-confirm-message="{{ $reservation->directPaymentNotice('your landlord') ?? 'This action cannot be undone.' }}"
        data-confirm-button="{{ $isInquiry ? 'Cancel inquiry' : 'Cancel reservation' }}"
        data-confirm-cancel="Keep it">
        @csrf @method('PATCH')
        <button type="submit" class="{{ $h }} inline-flex items-center whitespace-nowrap rounded-lg px-2.5 text-[12.5px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.07] transition-colors duration-150 cursor-pointer">Cancel</button>
    </form>
@endif

@if($blockedReason)
    <p class="basis-full text-[11.5px] leading-snug text-[#5B6A8E] {{ $mobile ? '' : 'text-right' }}">{{ $blockedReason }}</p>
@endif

@include('payments.partials._refund-notice', ['reservation' => $reservation])
