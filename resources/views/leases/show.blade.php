@extends('layouts.landlord')

{{--
    The landlord's printable lease. For a walk-in: print, both sign on the
    paper lines, then upload the signed copy from the tenancy page. For an
    online tenant: what they e-signed, with the signature record.
    See plans/formal-lease-agreement.md.
--}}

@section('content')
@php
    $signedOnline = $reservation->agreed_at !== null;
    $backUrl = $reservation->rental_status === 'Occupied'
        ? route('landlord.tenancies.show', $reservation)
        : route('landlord.reservations.index');
@endphp

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">
    <div class="flex items-center justify-between gap-3 mb-5 print:hidden">
        <a href="{{ $backUrl }}" class="inline-flex items-center text-sm font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            Back
        </a>
        <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full border border-[#E2E4EC] bg-white text-[13px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] cursor-pointer transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
            Print / Save PDF
        </button>
    </div>

    <div class="mb-5">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1.5">
            <h1 class="text-2xl font-semibold text-[#060D26] tracking-tight">Lease Agreement</h1>
            <p class="text-[11px] font-bold text-[#5B6A8E] tracking-wider tabular-nums border border-[#E2E4EC] rounded-full px-2.5 py-1">{{ $terms['reference'] }}</p>
        </div>
        @unless ($signedOnline)
            <p class="text-sm text-[#5B6A8E] mt-1 print:hidden">
                Print this, have both parties sign and date it, then upload the signed copy on the tenancy page.
            </p>
        @endunless
    </div>

    <x-card flush class="p-5 sm:p-8 print:border-none print:shadow-none print:p-0">
        @include('leases._document', ['terms' => $terms])

        @if ($signedOnline)
            <div class="mt-6 rounded-xl border border-[#E2E4EC] p-5">
                <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wider mb-3">Signatures</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-[12px]">
                    <div>
                        <p class="text-[13px] font-bold text-[#060D26]">{{ $terms['landlord']['name'] }}</p>
                        <p class="text-[#5B6A8E]">Landlord</p>
                        @if ($reservation->landlord_tc_accepted_at)
                            <p class="text-[#15803D] font-semibold mt-1.5">Accepted {{ $reservation->landlord_tc_accepted_at->format('F j, Y \a\t g:i A') }}</p>
                        @endif
                    </div>
                    <div class="sm:border-l sm:border-[#E2E4EC] sm:pl-4">
                        <p class="text-[13px] font-bold text-[#060D26]">{{ $terms['tenant']['name'] }}</p>
                        <p class="text-[#5B6A8E]">Tenant</p>
                        <p class="text-[#15803D] font-semibold mt-1.5">Signed online {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}</p>
                        @if ($reservation->agreed_ip)
                            <p class="text-[10.5px] text-[#5B6A8E] mt-0.5">Recorded from {{ $reservation->agreed_ip }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Paper signing: blank lines, laid out to survive printing --}}
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-8 print:grid-cols-2">
                @foreach (['Landlord' => $terms['landlord']['name'], 'Tenant' => $terms['tenant']['name']] as $role => $name)
                    <div>
                        <div class="h-12 border-b border-[#060D26]"></div>
                        <p class="mt-1.5 text-[13px] font-bold text-[#060D26]">{{ $name ?: $role }}</p>
                        <p class="text-[11.5px] text-[#5B6A8E]">{{ $role }} — signature over printed name</p>
                        <div class="mt-5 h-8 border-b border-[#060D26] w-40"></div>
                        <p class="mt-1.5 text-[11.5px] text-[#5B6A8E]">Date</p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
@endsection
