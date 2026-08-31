@extends('layouts.admin')

@section('page-title', 'Payment Management')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Page header --}}
    <x-page-header title="Payment Management" subtitle="Manage escrow payments and releases." />

    {{-- Stat summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php
            $stats = [
                'Held' => ['label' => 'Held', 'value' => $counts['Held'], 'sub' => '₱'.number_format($sums['Held'], 2), 'valueColor' => '#B45309', 'iconBg' => 'rgba(251,191,36,0.10)', 'iconColor' => '#B45309'],
                'Released' => ['label' => 'Released', 'value' => $counts['Released'], 'sub' => '₱'.number_format($sums['Released'], 2), 'valueColor' => '#15803D', 'iconBg' => 'rgba(34,197,94,0.07)', 'iconColor' => '#059669'],
                'Paid' => ['label' => 'Recorded', 'value' => $counts['Paid'], 'sub' => '₱'.number_format($sums['Paid'], 2).' offline', 'valueColor' => '#156F8C', 'iconBg' => '#EEF8F8', 'iconColor' => '#156F8C'],
                'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'sub' => 'processing', 'valueColor' => '#64748B', 'iconBg' => 'rgba(148,163,184,0.12)', 'iconColor' => '#64748B'],
                'All' => ['label' => 'Total', 'value' => $counts['All'], 'sub' => 'all payments', 'valueColor' => '#156F8C', 'iconBg' => '#EEF8F8', 'iconColor' => '#156F8C'],
            ];
        @endphp
        @foreach ($stats as $key => $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :sub="$stat['sub']" :value-color="$stat['valueColor']" :icon-bg="$stat['iconBg']"
                :href="route('admin.payments.index', ['status' => $key])"
                :class="$status === $key ? 'ring-2 ring-[#2AA7A1]' : ''">
                <x-slot:icon>
                    @switch($key)
                        @case('Held')
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            @break
                        @case('Released')
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                            @break
                        @case('Paid')
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3.75h6M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            @break
                        @case('Pending')
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            @break
                        @default
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="{{ $stat['iconColor'] }}" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M9 17V9m4 8V5m4 12v-6" />
                            </svg>
                    @endswitch
                </x-slot:icon>
            </x-stat-card>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-0.5 border-b border-[#E2E8F0] mb-6 overflow-x-auto">
        @foreach (['All', 'Held', 'Released', 'Paid', 'Voided', 'Pending'] as $tab)
            <a href="{{ route('admin.payments.index', ['status' => $tab]) }}"
                class="px-4 py-2.5 text-[13px] font-semibold border-b-2 whitespace-nowrap transition-colors
                    {{ $status === $tab ? 'border-[#2AA7A1] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937]' }}">
                {{ $tab }}
                <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-[#156F8C]' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
            </a>
        @endforeach
    </div>

    @if ($payments->isEmpty())
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">No payments here</p>
            <p class="text-[13px] text-[#64748B] mt-1">No payments match this tab right now.</p>
        </div>
    @else
        <x-card flush>
            <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
                <p class="text-[13px] font-semibold text-[#1F2937]">
                    {{ $payments->total() }} {{ Str::plural('payment', $payments->total()) }}
                </p>
            </div>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Property / Unit</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Payment Date</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                            <th class="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($payments as $payment)
                            @php $tenant = $payment->reservation?->tenant; @endphp
                            <tr class="hover:bg-[#F7FCFC]/70 transition-all duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#156F8C] flex items-center justify-center shrink-0">
                                            <span class="text-white text-[12px] font-bold">
                                                {{ strtoupper(substr($tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($tenant->last_name ?? '', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                                    {{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}
                                                </p>
                                                @if ($tenant?->is_walk_in)
                                                    <span class="inline-flex items-center h-5 px-2 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[10px] font-bold"
                                                        title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                                @endif
                                            </div>
                                            <p class="text-[12px] text-[#64748B]">{{ $tenant?->email ?: '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] text-[#1F2937] font-medium">{{ $payment->reservation?->property?->title ?? '—' }}</p>
                                    <p class="text-[12px] text-[#64748B]">{{ $payment->reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-bold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-[12px] text-[#64748B]">{{ $payment->payment_type }}</p>
                                </td>
                                <td class="px-6 py-4 text-[13px] text-[#64748B]">
                                    {{ ($payment->paid_at ?? $payment->created_at)?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($payment->status === 'Held')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#FBBF24]/15 text-[11.5px] font-bold text-[#B45309]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#FBBF24]"></span>
                                            Held
                                        </span>
                                    @elseif ($payment->status === 'Released')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#22C55E]/15 text-[11.5px] font-bold text-[#15803D]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
                                            Released
                                        </span>
                                    @elseif ($payment->status === 'Paid')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#EEF8F8] text-[11.5px] font-bold text-[#156F8C]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1]"></span>
                                            Recorded
                                        </span>
                                    @elseif ($payment->status === 'Voided')
                                        {{-- Neutral, not red — this is a resolved correction, not an alert. --}}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#E2E8F0] text-[11.5px] font-bold text-[#64748B]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#94A3B8]"></span>
                                            Voided
                                        </span>
                                    @elseif ($payment->status === 'Pending')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#E2E8F0] text-[11.5px] font-bold text-[#64748B]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#94A3B8]"></span>
                                            Processing
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#E2E8F0] text-[11.5px] font-bold text-[#64748B]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#94A3B8]"></span>
                                            {{ $payment->status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($payment->status === 'Held')
                                        <form method="POST" action="{{ route('admin.payments.release', $payment) }}">
                                            @csrf
                                            <button type="submit"
                                                onclick="return confirm('Release this payment to the landlord? This cannot be undone.')"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#FF8A65] text-[12px] font-semibold text-white hover:brightness-95 shadow-sm transition-all duration-200 cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                </svg>
                                                Release
                                            </button>
                                        </form>
                                    @elseif ($payment->status === 'Released')
                                        <span class="text-[12px] text-[#64748B]">
                                            Released {{ $payment->released_at?->format('M d, Y') }}
                                        </span>
                                    @elseif ($payment->status === 'Voided')
                                        <span class="text-[12px] text-[#64748B]">
                                            {{ $payment->voidReasonLabel() }} · {{ $payment->voided_at?->format('M d, Y') }}
                                        </span>
                                    @elseif ($payment->isManuallyRecorded())
                                        {{-- Landlord-asserted, never escrowed — deliberately no
                                             Release button. This is the one row type an admin must
                                             be able to tell apart from platform-settled money. --}}
                                        <span class="text-[12px] text-[#64748B]">Recorded by landlord</span>
                                    @else
                                        <span class="text-[12px] text-[#94A3B8]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="px-6 py-4 border-t border-[#E2E8F0]">
                    {{ $payments->links() }}
                </div>
            @endif
        </x-card>
    @endif

</div>
@endsection
