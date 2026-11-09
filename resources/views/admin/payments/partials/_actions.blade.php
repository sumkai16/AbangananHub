{{--
    What an admin can do with one payment, or what happened to it. Used by the list row and the grid card on
    admin/payments/index so the two cannot drift. Needs $payment and $heldGroups (held payments grouped for the refund
    quote). $align: 'end' (the table cell) or 'start' (the card).
--}}
@php
    $tenant = $payment->reservation?->tenant;
    $justify = ($align ?? 'end') === 'start' ? 'justify-start' : 'justify-end';
@endphp
@if ($payment->status === 'Held')
    @php
        $group = $heldGroups[$payment->payment_id] ?? collect([$payment]);
        $quote = \App\Services\PaymentRefunds::quote($group);
        $refundDetail = [
            'id' => $payment->payment_id,
            'action' => route('admin.payments.refund', $payment),
            'label' => trim(($tenant->first_name ?? '').' '.($tenant->last_name ?? '')).' · '.($payment->reservation?->property?->title ?? 'Reservation'),
            'rent' => $quote['rent'],
            'fee' => $quote['fee'],
            'rows' => $quote['rows'],
        ];
    @endphp
    <div class="flex items-center {{ $justify }} gap-1">
    @if ($payment->recorded_by === null && $quote['paymongo_payment_id'])
        <button type="button" @click="$dispatch('open-refund-payment', {{ \Illuminate\Support\Js::from($refundDetail) }})"
            class="inline-flex h-8 items-center rounded-lg px-2 text-[12.5px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06] transition-colors duration-200 cursor-pointer">
            Refund tenant
        </button>
    @endif
    <form method="POST" action="{{ route('admin.payments.release', $payment) }}"
        data-confirm="Release this payment to the landlord?"
        data-confirm-type="warning"
        data-confirm-message="This sends the funds to the landlord's payout and cannot be undone."
        data-confirm-button="Release payment">
        @csrf
        <button type="submit"
            class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] hover:border-[#FF8A66] hover:bg-[#FF8A66]/[0.08] transition-colors duration-200 cursor-pointer">
            Release
        </button>
    </form>
    </div>
@elseif ($payment->status === 'Refunded')
    <div class="text-[12px] text-[#5B6A8E] leading-snug">
        <p>Refunded {{ $payment->refunded_at?->format('M d, Y') }}</p>
        @if ($payment->refund_status === 'failed')
            <p class="mt-0.5 font-bold text-[#DC2626]">Refund failed. The tenant has not been repaid.</p>
        @elseif ($payment->refund_status === 'processing' && $payment->refund_transfer_link)
            <p class="mt-0.5 text-[#B45309]">Waiting for the tenant to claim it
                (<a href="{{ $payment->refund_transfer_link }}" target="_blank" rel="noopener noreferrer" class="underline font-semibold">link</a>)</p>
        @elseif (in_array($payment->refund_status, ['pending', 'processing'], true))
            <p class="mt-0.5 text-[#B45309]">PayMongo is still sending it</p>
        @elseif ($payment->refund_status === 'succeeded')
            <p class="mt-0.5 text-[#15803D]">Sent</p>
        @endif
        @if ($payment->refund_includes_fee)
            <p class="mt-0.5">Fee refunded too</p>
        @endif
    </div>
@elseif ($payment->status === 'Released')
    <span class="text-[12px] text-[#5B6A8E]">
        Released {{ $payment->released_at?->format('M d, Y') }}
    </span>
@elseif ($payment->status === 'Voided')
    <span class="text-[12px] text-[#5B6A8E]">
        {{ $payment->voidReasonLabel() }} · {{ $payment->voided_at?->format('M d, Y') }}
    </span>
@elseif ($payment->isManuallyRecorded())
    {{-- Landlord-asserted, never escrowed — deliberately no
         Release button. This is the one row type an admin must
         be able to tell apart from platform-settled money. --}}
    <span class="text-[12px] text-[#5B6A8E]">Recorded by landlord</span>
@else
    <span class="text-[12px] text-[#5B6A8E]">—</span>
@endif
