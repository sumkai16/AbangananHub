@extends('layouts.landlord')

@section('page-title', 'Payouts')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-5 pb-10">

        <div class="mb-6">
            <x-page-header title="Payouts" subtitle="What AbangananHub owes you, and what it has already sent to your GCash.">
                <x-slot:icon>
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                    </svg>
                </x-slot:icon>
            </x-page-header>
        </div>

        @unless ($hasPayoutDestination)
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-[#F5D47A] bg-gradient-to-r from-[#FFF8E7] via-[#FFF6D7] to-[#FDF7E8] p-4 shadow-[0_10px_25px_rgba(217,119,6,0.08)]">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#FDE68A] text-[#B45309] shadow-inner shadow-[#FBBF24]/40">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M11.48 3.5l8.808 15.3A1.5 1.5 0 0 1 18.908 21H5.092a1.5 1.5 0 0 1-1.388-2.2L12.52 3.5a1.5 1.5 0 0 1 2.96 0Z" />
                    </svg>
                </div>
                <div class="text-[13px] leading-6 text-[#7A4F08]">
                    No GCash number on file — AbangananHub can't send you a payout until you add one in
                    <a href="{{ route('landlord.profile.edit') }}" class="font-semibold underline decoration-2 underline-offset-2">Edit profile</a>.
                </div>
            </div>
        @endunless

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-stat-card label="Pending payout" :value="'₱' . number_format($pendingTotal, 2)" value-color="#B45309" icon-bg="rgba(251,191,36,0.10)"
                class="shadow-[0_10px_25px_rgba(15,23,42,0.07)] hover:-translate-y-0.5 transition-all duration-200"
                sub="Waiting on AbangananHub to send">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Paid out" :value="'₱' . number_format($paidOutTotal, 2)" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                class="shadow-[0_10px_25px_rgba(15,23,42,0.07)] hover:-translate-y-0.5 transition-all duration-200"
                sub="Payouts received to date">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        {{-- Pending --}}
        <div class="mb-6 overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex items-center justify-between border-b border-[#E2E8F0] bg-gradient-to-r from-[#F8FBFB] to-[#F3F7F8] px-5 py-4 sm:px-6">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#F59E0B] shadow-[0_0_0_4px_rgba(245,158,11,0.14)]"></span>
                    <p class="text-[13px] font-semibold text-[#1F2937]">Pending payout</p>
                </div>
            </div>
            @if ($pending->isEmpty())
                <div class="flex min-h-[120px] items-center justify-center px-6 py-10">
                    <p class="text-[13px] text-[#64748B]">Nothing pending — you're all caught up.</p>
                </div>
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
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
                        <tbody class="divide-y divide-[#E2E8F0] bg-white">
                            @foreach ($pending as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr class="transition-colors duration-150 hover:bg-[#F8FAFC]">
                                    <td class="px-5 py-3 text-[13px] font-medium text-[#1F2937] sm:px-6">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#64748B] sm:px-6">{{ $payment->reservation?->unit?->unit_label ?? '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#64748B] sm:px-6">{{ $payment->payment_type }}</td>
                                    <td class="px-5 py-3 text-[13px] font-semibold text-[#1F2937] sm:px-6">₱{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#64748B] sm:px-6">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Paid out --}}
        <div class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex items-center justify-between border-b border-[#E2E8F0] bg-gradient-to-r from-[#F8FBFB] to-[#F3F7F8] px-5 py-4 sm:px-6">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#10B981] shadow-[0_0_0_4px_rgba(16,185,129,0.14)]"></span>
                    <p class="text-[13px] font-semibold text-[#1F2937]">Payout history</p>
                </div>
            </div>
            @if ($paidOut->isEmpty())
                <div class="flex min-h-[120px] items-center justify-center px-6 py-10">
                    <p class="text-[13px] text-[#64748B]">No payouts recorded yet.</p>
                </div>
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F8FAFC] text-left">
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Tenant</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Amount</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">GCash reference</th>
                                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-[#94A3B8] sm:px-6">Paid out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0] bg-white">
                            @foreach ($paidOut as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr class="transition-colors duration-150 hover:bg-[#F8FAFC]">
                                    <td class="px-5 py-3 text-[13px] font-medium text-[#1F2937] sm:px-6">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-5 py-3 text-[13px] font-semibold text-[#1F2937] sm:px-6">₱{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#64748B] sm:px-6">{{ $payment->payout_reference }}</td>
                                    <td class="px-5 py-3 text-[13px] text-[#64748B] sm:px-6">{{ $payment->paid_out_at?->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($paidOut->hasPages())
                    <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] px-6 py-4">
                        {{ $paidOut->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
@endsection
