@extends('layouts.landlord')

@section('page-title', 'Payment Receipt')

@section('content')
    @php
        $tenant = $reservation->tenant;
        $method = $payment->payment_method;
        $businessName = $business->business_name ?? (auth()->user()->name ?? 'AbangananHub Landlord');
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16">

        {{-- Toolbar — hidden when printing --}}
        <div class="flex items-center justify-between gap-3 mb-5 print:hidden">
            <a href="{{ route('landlord.tenancies.show', $reservation) }}"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-full border border-[#E2E8F0] bg-white text-[#1F2937] text-sm font-semibold hover:bg-[#F7FCFC] transition-all duration-200 cursor-pointer">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to tenancy
            </a>
            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 h-10 px-5 rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                </svg>
                Print
            </button>
        </div>

        <x-card>
            {{-- Letterhead --}}
            <div class="flex items-start justify-between gap-4 pb-5 border-b border-[#E2E8F0]">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1">Payment received</p>
                    <h1 class="text-2xl font-extrabold text-[#156F8C]">{{ $businessName }}</h1>
                </div>
                <div class="text-right">
                    <p class="text-[12px] text-[#64748B]">Receipt no.</p>
                    <p class="text-[15px] font-bold text-[#1F2937]">#{{ str_pad($payment->payment_id, 6, '0', STR_PAD_LEFT) }}</p>
                </div>
            </div>

            {{-- Parties --}}
            <div class="grid sm:grid-cols-2 gap-6 py-5 border-b border-[#E2E8F0]">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Received from</p>
                    <p class="text-[15px] font-bold text-[#1F2937]">
                        {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Tenant' }}
                    </p>
                    @if($tenant->contact_number)
                        <p class="text-[13px] text-[#64748B] mt-0.5">{{ $tenant->contact_number }}</p>
                    @endif
                    @if($tenant->email)
                        <p class="text-[13px] text-[#64748B]">{{ $tenant->email }}</p>
                    @endif
                </div>
                <div class="sm:text-right">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">For</p>
                    <p class="text-[15px] font-bold text-[#1F2937]">{{ $reservation->unit->unit_label ?? 'Unit' }}</p>
                    <p class="text-[13px] text-[#64748B] mt-0.5">{{ $reservation->property->title ?? '' }}</p>
                </div>
            </div>

            {{-- Line --}}
            <div class="py-5 border-b border-[#E2E8F0]">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <div>
                        <p class="text-[14px] font-semibold text-[#1F2937]">
                            {{ $payment->payment_type }}{{ $payment->payment_type === 'Monthly' ? ' rent' : '' }}
                        </p>
                        @if($payment->billing_period)
                            <p class="text-[12.5px] text-[#64748B]">For {{ $payment->billing_period->format('F Y') }}</p>
                        @endif
                    </div>
                    <p class="text-[16px] font-bold text-[#1F2937]">₱{{ number_format((float) $payment->amount, 2) }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-y-2 gap-x-4 text-[13px] mt-4">
                    <dt class="text-[#64748B]">Date received</dt>
                    <dd class="text-right font-medium text-[#1F2937]">{{ optional($payment->paid_at)->format('M d, Y') ?? '—' }}</dd>

                    <dt class="text-[#64748B]">Method</dt>
                    <dd class="text-right font-medium text-[#1F2937]">{{ $method }}</dd>

                    @if($payment->reference_no)
                        <dt class="text-[#64748B]">Reference no.</dt>
                        <dd class="text-right font-medium text-[#1F2937]">{{ $payment->reference_no }}</dd>
                    @endif
                </dl>
            </div>

            {{-- Total --}}
            <div class="flex items-center justify-between gap-4 py-5">
                <p class="text-[13px] font-bold uppercase tracking-wider text-[#94A3B8]">Total received</p>
                <p class="text-2xl font-extrabold text-[#2AA7A1]">₱{{ number_format((float) $payment->amount, 2) }}</p>
            </div>

            @if($payment->payment_notes)
                <div class="pt-4 border-t border-[#E2E8F0]">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-1.5">Notes</p>
                    <p class="text-[12.5px] text-[#64748B] leading-relaxed">{{ $payment->payment_notes }}</p>
                </div>
            @endif

            <p class="mt-6 text-[11px] text-[#94A3B8] leading-relaxed">
                Recorded by {{ $payment->recorder?->name ?? 'the landlord' }} on {{ $payment->created_at->format('M d, Y') }}.
                This is a landlord-issued acknowledgement of a payment collected directly and is not processed or held by
                AbangananHub.
            </p>
        </x-card>
    </div>
@endsection
