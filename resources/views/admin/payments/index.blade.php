@extends('layouts.admin')

@section('page-title', 'Payment Management')

@section('content')
<div class="max-w-6xl">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Payment Management</h1>
        <p class="text-[13.5px] text-gray-500 mt-1">Manage escrow payments and releases.</p>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-1 mb-6 w-fit max-w-full overflow-x-auto shadow-lg">
        @foreach (['All', 'Held', 'Released', 'Pending'] as $tab)
            <a href="{{ route('admin.payments.index', ['status' => $tab]) }}"
                class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-150
                    {{ $status === $tab
                        ? 'bg-[#2AA7A1] text-white shadow-sm'
                        : 'text-gray-500 hover:text-[#1A1A2E] hover:bg-gray-50' }}">
                {{ $tab }}
            </a>
        @endforeach
    </div>

    @if ($payments->isEmpty())
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1A1A2E]">No payments here</p>
            <p class="text-[13px] text-gray-400 mt-1">No payments match this tab right now.</p>
        </div>
    @else
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                <p class="text-[13px] font-semibold text-[#1A1A2E]">
                    {{ $payments->total() }} {{ Str::plural('payment', $payments->total()) }}
                </p>
            </div>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50/60 border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Tenant</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Property / Unit</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Amount</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Payment Date</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-400">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($payments as $payment)
                            @php $tenant = $payment->reservation?->tenant; @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                                            <span class="text-[#156F8C] text-[12px] font-bold">
                                                {{ strtoupper(substr($tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($tenant->last_name ?? '', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-[13.5px] font-semibold text-[#1A1A2E]">
                                                {{ $tenant ? $tenant->first_name.' '.$tenant->last_name : '—' }}
                                            </p>
                                            <p class="text-[12px] text-gray-400">{{ $tenant->email ?? '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] text-[#1A1A2E] font-medium">{{ $payment->reservation?->property?->title ?? '—' }}</p>
                                    <p class="text-[12px] text-gray-400">{{ $payment->reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[13.5px] font-bold text-[#1A1A2E]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-[12px] text-gray-400">{{ $payment->payment_type }}</p>
                                </td>
                                <td class="px-6 py-4 text-[13px] text-gray-400">
                                    {{ ($payment->paid_at ?? $payment->created_at)?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($payment->status === 'Held')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-[11.5px] font-bold text-amber-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Held
                                        </span>
                                    @elseif ($payment->status === 'Released')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-[11.5px] font-bold text-emerald-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Released
                                        </span>
                                    @elseif ($payment->status === 'Pending')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 border border-slate-200 text-[11.5px] font-bold text-slate-500">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Processing
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-50 border border-gray-100 text-[11.5px] font-bold text-gray-500">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
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
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#FF8A65] text-[12px] font-semibold text-white hover:bg-[#F4744E] shadow-sm transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                                </svg>
                                                Release
                                            </button>
                                        </form>
                                    @elseif ($payment->status === 'Released')
                                        <span class="text-[12px] text-gray-400">
                                            Released {{ $payment->released_at?->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span class="text-[12px] text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="px-6 py-4 border-t border-gray-50">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
