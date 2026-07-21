@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-xl mx-auto px-4 py-16 text-center min-h-[calc(100vh-72px)] flex flex-col justify-center">

        <div class="w-14 h-14 rounded-full bg-[#EEF8F8] flex items-center justify-center mx-auto mb-6">
            <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <h1 class="text-xl font-extrabold text-[#1F2937] tracking-tight mb-2">Payment Being Confirmed</h1>
        <p class="text-sm text-[#64748B] leading-relaxed max-w-sm mx-auto mb-8">
            Your GCash payment has been submitted. We're waiting for confirmation from the payment provider — this usually
            takes a few seconds.
        </p>

        @if($latestPayment && $latestPayment->isHeld())
            <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3 text-left mb-6">
                <div class="w-8 h-8 rounded-full bg-[#22C55E]/[0.12] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#15803D]">Payment Confirmed</p>
                    <p class="text-xs text-[#15803D] mt-0.5">Funds are held by AbangananHub. Confirm your move-in on the agreement page to mark the unit as occupied.</p>
                </div>
            </div>
        @endif

        <a href="{{ route('agreements.show', $reservation) }}"
            class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-[#1F2937] hover:brightness-95 text-white font-bold text-sm transition">
            Back to Agreement
        </a>
    </div>
@endsection