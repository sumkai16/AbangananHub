{{--
    A payment QR the landlord shared in this chat. Needs $message (with ->paymentQrShare), $isLandlord.
    The newest one is the only one to pay to; older ones are shown greyed out as "Replaced".
--}}
@php
    $share = $message->paymentQrShare;
    $mine = $message->sender_id === auth()->id();
@endphp
@if($share)
    <div class="w-full max-w-[360px] {{ $mine ? 'self-end' : 'self-start' }} mb-1.5" data-payment-qr-card="{{ $share->payment_qr_share_id }}">
        <div class="overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-sm {{ $share->isCurrent() ? '' : 'opacity-60' }}">
            <div class="flex items-center justify-between gap-3 px-4 pt-3.5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E]">Payment QR &middot; {{ $share->sharedBy?->first_name ?? 'Landlord' }} (landlord)</p>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[10.5px] font-bold {{ $share->isCurrent() ? 'bg-[#22C55E]/10 text-[#15803D]' : 'bg-[#E2E4EC] text-[#5B6A8E]' }}">
                    {{ $share->isCurrent() ? 'Current' : 'Replaced '.$share->replaced_at->format('M j') }}
                </span>
            </div>
            <div class="px-4 py-3">
                @if($share->isCurrent())
                    @include('payments.partials._qr-block', ['share' => $share, 'compact' => true])
                @else
                    <p class="text-[13px] font-bold text-[#060D26]">{{ $share->account_name }}</p>
                    <p class="mt-1 text-[11.5px] text-[#5B6A8E]">This QR was replaced. Do not pay to it.</p>
                @endif
            </div>
            @if($share->isCurrent() && ! $mine)
                <p class="border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2 text-[11px] text-[#5B6A8E]">Only pay to a QR your landlord shared here. After paying, tap &ldquo;I've paid&rdquo; so they can confirm it.</p>
            @endif
        </div>
    </div>
@endif
