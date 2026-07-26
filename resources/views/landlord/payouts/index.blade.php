@extends('layouts.landlord')

@section('page-title', 'Payouts')

@section('content')
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-10">

        <x-page-header title="Payouts" subtitle="What AbangananHub owes you, and what it has already sent to your GCash.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        @unless ($hasPayoutDestination)
            <div class="p-4 bg-[#FBBF24]/[0.10] border border-[#FBBF24]/35 rounded-xl text-[13px] text-[#B45309] mb-6">
                No GCash number on file — AbangananHub can't send you a payout until you add one in
                <a href="{{ route('landlord.profile.edit') }}" class="font-semibold underline">Edit profile</a>.
            </div>
        @endunless

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            <x-stat-card label="Pending payout" :value="'₱' . number_format($pendingTotal, 2)" value-color="#B45309" icon-bg="rgba(251,191,36,0.10)"
                sub="Waiting on AbangananHub to send">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Paid out" :value="'₱' . number_format($paidOutTotal, 2)" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)"
                sub="Payouts received to date">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        {{-- Pending --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-[#E2E8F0]">
                <p class="text-[13px] font-semibold text-[#1F2937]">Pending payout</p>
            </div>
            @if ($pending->isEmpty())
                <p class="px-6 py-8 text-[13px] text-[#64748B] text-center">Nothing pending — you're all caught up.</p>
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Unit</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Type</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Settled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach ($pending as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr>
                                    <td class="px-6 py-3 text-[13px] text-[#1F2937]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->reservation?->unit?->unit_label ?? '—' }}</td>
                                    <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->payment_type }}</td>
                                    <td class="px-6 py-3 text-[13px] font-semibold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Paid out --}}
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[#E2E8F0]">
                <p class="text-[13px] font-semibold text-[#1F2937]">Payout history</p>
            </div>
            @if ($paidOut->isEmpty())
                <p class="px-6 py-8 text-[13px] text-[#64748B] text-center">No payouts recorded yet.</p>
            @else
                <div class="overflow-x-auto scrollbar-thin-light">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">GCash reference</th>
                                <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Paid out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach ($paidOut as $payment)
                                @php $tenant = $payment->reservation?->tenant; @endphp
                                <tr>
                                    <td class="px-6 py-3 text-[13px] text-[#1F2937]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                    <td class="px-6 py-3 text-[13px] font-semibold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->payout_reference }}</td>
                                    <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->paid_out_at?->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($paidOut->hasPages())
                    <div class="px-6 py-4 border-t border-[#E2E8F0]">
                        {{ $paidOut->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
@endsection
