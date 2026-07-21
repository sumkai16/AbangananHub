@extends('layouts.admin')

@section('page-title', 'Payment Management')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Payment Management</h1>
        <p class="text-sm text-[#64748B] mt-1">Manage escrow payments and releases.</p>
    </div>

    {{-- Stat summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php
            $stats = [
                'Held' => ['label' => 'Held', 'value' => $counts['Held'], 'sub' => '₱'.number_format($sums['Held'], 2), 'accent' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
                'Released' => ['label' => 'Released', 'value' => $counts['Released'], 'sub' => '₱'.number_format($sums['Released'], 2), 'accent' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'sub' => 'processing', 'accent' => 'text-[#64748B]', 'dot' => 'bg-[#94A3B8]'],
                'All' => ['label' => 'Total', 'value' => $counts['All'], 'sub' => 'all payments', 'accent' => 'text-[#156F8C]', 'dot' => 'bg-[#156F8C]'],
            ];
        @endphp
        @foreach ($stats as $key => $stat)
            <a href="{{ route('admin.payments.index', ['status' => $key]) }}"
                class="bg-white border border-[#E2E8F0] rounded-2xl px-4 py-3.5 shadow-[0_1px_3px_rgba(15,23,42,0.06)] transition-all duration-200 hover:shadow-xl {{ $status === $key ? 'ring-2 ring-[#2AA7A1]' : '' }}">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $stat['dot'] }}"></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">{{ $stat['label'] }}</p>
                </div>
                <p class="text-2xl font-bold {{ $stat['accent'] }} mt-1">{{ $stat['value'] }}</p>
                <p class="text-[11px] text-[#94A3B8] mt-0.5 truncate">{{ $stat['sub'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-white border border-[#E2E8F0] rounded-2xl p-1 mb-5 w-fit max-w-full overflow-x-auto shadow-[0_1px_3px_rgba(15,23,42,0.06)]">
        @foreach (['All', 'Held', 'Released', 'Pending'] as $tab)
            <a href="{{ route('admin.payments.index', ['status' => $tab]) }}"
                class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-200 whitespace-nowrap
                    {{ $status === $tab
                        ? 'bg-[#2AA7A1] text-white shadow-sm'
                        : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                {{ $tab }}
                <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-white/80' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
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
        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
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
                                            <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                                {{ $tenant ? $tenant->first_name.' '.$tenant->last_name : '—' }}
                                            </p>
                                            <p class="text-[12px] text-[#64748B]">{{ $tenant->email ?? '' }}</p>
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
        </div>
    @endif

</div>
@endsection
