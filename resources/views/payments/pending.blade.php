@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-xl mx-auto px-4 py-16 text-center min-h-[calc(100vh-72px)] flex flex-col justify-center">

        <div class="w-14 h-14 rounded-full bg-[#ECEEF6] flex items-center justify-center mx-auto mb-6">
            <svg class="w-7 h-7 text-[#B35A3D]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <h1 class="text-xl font-normal text-[#060D26] tracking-tight mb-2">Payment Being Confirmed</h1>
        <p class="text-sm text-[#5B6A8E] leading-relaxed max-w-sm mx-auto mb-8">
            Your payment has been submitted. We're waiting for confirmation from the payment provider — this usually
            takes a few seconds.
        </p>

        @if($latestPayment && ($latestPayment->isHeld() || $latestPayment->isPaid()))
            <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3 text-left mb-6">
                <div class="w-8 h-8 rounded-full bg-[#22C55E]/[0.12] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#15803D]">Payment Confirmed</p>
                    <p class="text-xs text-[#15803D] mt-0.5">
                        @if($latestPayment->isHeld())
                            Funds are held by AbangananHub. Confirm your move-in on the agreement page to mark the unit as occupied.
                        @else
                            Your rent payment has been recorded.
                        @endif
                    </p>
                </div>
            </div>
        @endif

        {{-- What this one online payment covered. --}}
        @if($latestPayment && ($latestPayment->isHeld() || $latestPayment->isPaid()))
            @php $group = $latestPayment->checkoutGroup(); @endphp
            <dl class="mb-6 space-y-1.5 rounded-xl border border-[#E2E4EC] bg-white px-4 py-3 text-left text-[13px]">
                @foreach($group as $row)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">{{ $row->payment_type === 'Deposit' ? 'Security deposit' : ($row->payment_type === 'Monthly' ? 'Rent'.($row->billing_period ? ' for '.$row->billing_period->format('F Y') : '') : 'First month’s rent') }}</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($row->amount, 2) }}</dd></div>
                @endforeach
                @if($group->sum('service_fee') > 0)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Online payment fee</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($group->sum('service_fee'), 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-3 border-t border-[#E2E4EC] pt-1.5"><dt class="font-bold text-[#060D26]">Total paid</dt><dd class="font-bold tabular-nums text-[#060D26]">&#8369;{{ number_format($latestPayment->checkoutTotal(), 2) }}</dd></div>
            </dl>
        @endif

        <a href="{{ $returnRoute ?? route('agreements.show', $reservation) }}"
            class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-sm transition">
            {{ isset($returnRoute) ? 'Back to Rent Ledger' : 'Back to Agreement' }}
        </a>
        @if(! isset($returnRoute) && $reservation->conversation_id)
            <a href="{{ route('conversations.index', ['active' => $reservation->conversation_id]) }}"
                class="mt-3 inline-flex items-center justify-center px-6 py-2.5 rounded-xl border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC] text-[#060D26] font-bold text-sm transition">
                Back to chat
            </a>
        @endif
    </div>
@endsection