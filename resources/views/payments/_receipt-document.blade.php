{{--
    The printable payment receipt, shared by the tenant and landlord receipt pages.
    Needs: $payment, $reservation, $business (RentalBusiness|null). Colours are fixed (paper, not theme) and
    forced to print: without print-color-adjust the browser drops the navy band and total card.
--}}
@php
    $tenant = $reservation->tenant;
    $landlord = $reservation->property->landlord;
    $businessName = $business->business_name ?? $landlord->name ?? 'Your landlord';
    $tenantName = trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Tenant';
    $receivedBy = $payment->recorder?->name ?? $landlord->name ?? 'the landlord';
    $label = 'text-[10.5px] font-bold uppercase tracking-[0.12em] text-[#B35A3D]';
@endphp

<article class="paper relative overflow-hidden print:flex print:flex-1 print:flex-col rounded-xl border border-[#E2E4EC] bg-white text-[#060D26] shadow-sm print:rounded-none print:border-0 print:shadow-none [print-color-adjust:exact] [-webkit-print-color-adjust:exact]">

    {{-- Band --}}
    <header class="bg-[#060D26] text-white px-5 sm:px-12 pt-8 sm:pt-11 pb-7 sm:pb-10 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5">
        <div>
            <x-brand-logo tone="on-dark" mark="w-8 h-8" text="text-[19px]" />
            <h1 class="mt-5 sm:mt-6 text-[28px] sm:text-[34px] font-semibold tracking-tight">Payment receipt</h1>
        </div>
        <div class="sm:text-right leading-relaxed">
            <p class="text-[10.5px] font-semibold uppercase tracking-[0.14em] text-[#FF8A66]">Receipt no.</p>
            <p class="text-[20px] font-semibold">#{{ str_pad($payment->payment_id, 6, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[12px] text-[#aab3cf]">Issued {{ $payment->created_at->format('M d, Y') }}</p>
        </div>
    </header>
    <div class="h-1.5 bg-[#FF8A66]"></div>

    <div class="px-5 sm:px-12 py-7 sm:py-10 print:flex print:flex-1 print:flex-col">

        {{-- Parties --}}
        <div class="grid sm:grid-cols-3 gap-6 sm:gap-8">
            <div>
                <p class="{{ $label }}">Received from</p>
                <p class="mt-2 text-[15px] font-semibold">{{ $tenantName }}</p>
                <div class="text-[12.5px] leading-relaxed text-[#3b4766]">
                    @if($tenant->contact_number)<p>{{ $tenant->contact_number }}</p>@endif
                    @if($tenant->email)<p class="break-all">{{ $tenant->email }}</p>@endif
                </div>
            </div>
            <div>
                <p class="{{ $label }}">Paid to</p>
                <p class="mt-2 text-[15px] font-semibold">{{ $businessName }}</p>
                <div class="text-[12.5px] leading-relaxed text-[#3b4766]">
                    <p>Received by {{ $receivedBy }}</p>
                    @if($landlord->email)<p class="break-all">{{ $landlord->email }}</p>@endif
                </div>
            </div>
            <div>
                <p class="{{ $label }}">Unit</p>
                <p class="mt-2 text-[15px] font-semibold">{{ $reservation->unit->unit_label ?? 'Unit' }}</p>
                <p class="text-[12.5px] leading-relaxed text-[#3b4766]">{{ $reservation->property->title ?? '' }}</p>
            </div>
        </div>

        {{-- Line item --}}
        <div class="mt-8 sm:mt-10">
            <div class="hidden sm:flex rounded-t-md bg-[#ECEEF6] px-3.5 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.1em] text-[#3b4766]">
                <div class="flex-1">Description</div>
                <div class="w-28">Period</div>
                <div class="w-28">Date paid</div>
                <div class="w-24">Method</div>
                <div class="w-28 text-right">Amount</div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-0 border-b border-[#E2E4EC] px-3.5 py-4 text-[12.5px]">
                <div class="sm:flex-1 text-[14px] sm:text-[13px] font-semibold">
                    {{ $payment->payment_type }}{{ $payment->payment_type === 'Monthly' ? ' rent' : '' }}
                </div>
                <div class="sm:w-28 text-[#3b4766]"><span class="sm:hidden font-semibold">For </span>{{ $payment->billing_period?->format('F Y') ?? '-' }}</div>
                <div class="sm:w-28 text-[#3b4766]"><span class="sm:hidden font-semibold">Paid </span>{{ optional($payment->paid_at)->format('M d, Y') ?? '-' }}</div>
                <div class="sm:w-24 text-[#3b4766]"><span class="sm:hidden font-semibold">By </span>{{ $payment->payment_method ?: '-' }}</div>
                <div class="sm:w-28 sm:text-right text-[14px] sm:text-[13px] font-semibold">₱{{ number_format((float) $payment->amount, 2) }}</div>
            </div>
        </div>

        {{-- Blank ruled rows, paper only: room to add a line by hand, and it fills the sheet like an invoice pad. --}}
        <div class="hidden print:block" aria-hidden="true">
            <div class="h-9 border-b border-[#E2E4EC]"></div>
            <div class="h-9 border-b border-[#E2E4EC]"></div>
            <div class="h-9 border-b border-[#E2E4EC]"></div>
        </div>

        {{-- Notes and total --}}
        <div class="mt-6 sm:mt-7 flex flex-col-reverse sm:flex-row sm:items-start sm:justify-between gap-6 sm:gap-10">
            <div class="flex-1 text-[12.5px] leading-relaxed">
                @if($payment->amountInWords())
                    <p class="{{ $label }}">Amount in words</p>
                    <p class="mt-1.5">{{ $payment->amountInWords() }}</p>
                @endif
                <p class="{{ $label }} mt-4">Reference no.</p>
                <p class="mt-1.5">{{ $payment->reference_no ?: 'None' }}</p>
                @if($payment->payment_notes)
                    <p class="{{ $label }} mt-4">Notes</p>
                    <p class="mt-1.5">{{ $payment->payment_notes }}</p>
                @endif
            </div>
            <div class="sm:w-72 rounded-xl bg-[#060D26] text-white px-5 py-[18px]">
                <p class="text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#FF8A66]">Total paid</p>
                <p class="mt-0.5 text-[30px] font-bold leading-tight">₱{{ number_format((float) $payment->amount, 2) }}</p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="mt-10 sm:mt-14 print:mt-auto print:pt-12 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
            <p class="max-w-md text-[10.5px] leading-relaxed text-[#5B6A8E]">
                @if($payment->claim)
                    {{ $tenantName }} reported this payment on {{ $payment->claim->created_at->format('M d, Y') }} and {{ $businessName }} confirmed it on {{ ($payment->claim->decided_at ?? $payment->created_at)->format('M d, Y') }}.
                @else
                    Recorded by {{ $receivedBy }} on {{ $payment->created_at->format('M d, Y') }}.
                @endif
                Paid directly to the landlord. AbangananHub keeps the record and does not process or hold the money.
            </p>
            <div class="w-52 border-t border-[#060D26] pt-1.5 text-center text-[11px]">
                {{ $receivedBy }}<br><span class="text-[#5B6A8E]">Authorized signature</span>
            </div>
        </div>
    </div>
</article>
