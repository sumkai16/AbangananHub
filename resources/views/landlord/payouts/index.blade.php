@extends('layouts.landlord')

@section('page-title', 'Payments')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        {{-- The Payouts tab of Payments: same header and tabs as Rent (landlord/payments/index). --}}
        <x-page-header title="Payments" subtitle="What AbangananHub owes you from online payments, and what it has already sent to your GCash.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        @include('landlord.payments.partials._tabs', ['active' => 'payouts', 'awaitingPayout' => $pendingTotal])

        @if ($holdNotice)
            <div class="mb-6 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4 text-[13px] leading-6 text-[#92600A]" role="status">{{ $holdNotice }}</div>
        @endif

        {{-- No GCash yet: setting it up is the one thing to do here, so it comes first. --}}
        @unless ($hasPayoutDestination)
            @include('landlord.payouts.partials._account')
        @endunless

        <div class="stat-chips mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-stat-card label="Pending payout" :value="'₱' . number_format($pendingTotal, 2)" value-color="#B45309" icon-bg="rgba(251,191,36,0.10)"
                class="shadow-[0_10px_25px_rgba(6,13,38,0.07)] hover:-translate-y-0.5 transition-all duration-200"
                sub="{{ \App\Services\LandlordPayouts::automaticEnabled() ? 'Sent to your GCash automatically' : 'Paid to your GCash by AbangananHub' }}">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Paid out" :value="'₱' . number_format($paidOutTotal, 2)" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                class="shadow-[0_10px_25px_rgba(6,13,38,0.07)] hover:-translate-y-0.5 transition-all duration-200"
                sub="Payouts received to date">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        @if ($hasPayoutDestination)
            @include('landlord.payouts.partials._account')
        @endif

        {{-- Pending --}}
        <x-card flush class="mb-6">
            <div class="flex items-center justify-between border-b border-[#E2E4EC] bg-gradient-to-r from-[#F8FBFB] to-[#F3F7F8] px-5 py-4 sm:px-6">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#F59E0B] shadow-[0_0_0_4px_rgba(245,158,11,0.14)]"></span>
                    <p class="text-[13px] font-semibold text-[#060D26]">Pending payout</p>
                </div>
            </div>
            @if ($pending->isEmpty())
                <div class="flex min-h-[120px] items-center justify-center px-6 py-10">
                    <p class="text-[13px] text-[#5B6A8E]">Nothing pending — you're all caught up.</p>
                </div>
            @else
                {{-- Mobile card list — same data as the table below, stacked for a phone screen --}}
                <div class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach ($pending as $payment)
                        @php $tenant = $payment->reservation?->tenant; @endphp
                        <div class="px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                <p class="text-[13.5px] font-semibold text-[#060D26] shrink-0">₱{{ number_format($payment->amount, 2) }}</p>
                            </div>
                            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">{{ $payment->reservation?->unit?->unit_label ?? '—' }} · {{ $payment->payment_type }}@if ($payment->payout?->isProcessing()) · <span class="font-semibold text-[#B45309]">Sending to your GCash</span>@endif</p>
                            <p class="mt-0.5 text-[11.5px] text-[#94A3B8]">Settled {{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="hidden lg:block overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F8FAFC] text-left">
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Tenant</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Unit</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Type</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Amount</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Settled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC] bg-white">
                            @foreach ($pending as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr class="transition-colors duration-150 hover:bg-[#ECEEF6]">
                                    <td class="px-5 py-3 text-[13px] font-medium text-[#060D26] sm:px-6">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#5B6A8E] sm:px-6">{{ $payment->reservation?->unit?->unit_label ?? '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#5B6A8E] sm:px-6">{{ $payment->payment_type }}</td>
                                    <td class="px-5 py-3 text-[13px] font-semibold text-[#060D26] sm:px-6">₱{{ number_format($payment->amount, 2) }}@if ($payment->payout?->isProcessing())<span class="block text-[11px] font-semibold text-[#B45309]">Sending to your GCash</span>@endif</td>
                                    <td class="px-5 py-3 text-[13px] text-[#5B6A8E] sm:px-6">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- Paid out --}}
        <x-card flush>
            <div class="flex items-center justify-between border-b border-[#E2E4EC] bg-gradient-to-r from-[#F8FBFB] to-[#F3F7F8] px-5 py-4 sm:px-6">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#22C55E] shadow-[0_0_0_4px_rgba(34,197,94,0.14)]"></span>
                    <p class="text-[13px] font-semibold text-[#060D26]">Payout history</p>
                </div>
            </div>
            @if ($paidOut->isEmpty())
                <div class="flex min-h-[120px] items-center justify-center px-6 py-10">
                    <p class="text-[13px] text-[#5B6A8E]">No payouts recorded yet.</p>
                </div>
            @else
                {{-- Mobile card list — same data as the table below, stacked for a phone screen --}}
                <div class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach ($paidOut as $payment)
                        @php $tenant = $payment->reservation?->tenant; @endphp
                        <div class="px-5 py-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                <p class="text-[13.5px] font-semibold text-[#060D26] shrink-0">₱{{ number_format($payment->amount, 2) }}</p>
                            </div>
                            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">Ref: {{ $payment->payout?->payout_reference }}</p>
                            <p class="mt-0.5 text-[11.5px] text-[#94A3B8]">Paid out {{ $payment->payout?->paid_out_at?->format('M d, Y') }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="hidden lg:block overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F8FAFC] text-left">
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Tenant</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Amount</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">GCash reference</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Paid out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC] bg-white">
                            @foreach ($paidOut as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr class="transition-colors duration-150 hover:bg-[#ECEEF6]">
                                    <td class="px-5 py-3 text-[13px] font-medium text-[#060D26] sm:px-6">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] font-semibold text-[#060D26] sm:px-6">₱{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#5B6A8E] sm:px-6">{{ $payment->payout?->payout_reference }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#5B6A8E] sm:px-6">{{ $payment->payout?->paid_out_at?->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($paidOut->hasPages())
                    <div class="border-t border-[#E2E4EC] bg-[#F8FAFC] px-6 py-4">
                        {{ $paidOut->links() }}
                    </div>
                @endif
            @endif
        </x-card>

    </div>
@endsection
