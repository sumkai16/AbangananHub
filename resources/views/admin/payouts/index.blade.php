@extends('layouts.admin')

@section('page-title', 'Landlord Payouts')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header title="Landlord Payouts"
        subtitle="Money the platform owes landlords, waiting on a manual GCash transfer." />

    @if ($pending->isEmpty())
        <div class="bg-white border border-[#E2E8F0] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06)] mb-8">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">Nothing pending payout</p>
            <p class="text-[13px] text-[#64748B] mt-1">Every released and paid rent payment has been sent to its landlord.</p>
        </div>
    @else
        <div class="space-y-4 mb-8">
            @foreach ($pending as $row)
                @php $landlord = $row['landlord']; @endphp
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#156F8C] flex items-center justify-center shrink-0">
                                <span class="text-white text-[12px] font-bold">
                                    {{ strtoupper(substr($landlord->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($landlord->last_name ?? '', 0, 1)) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-[13.5px] font-semibold text-[#1F2937]">
                                    {{ trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? '')) ?: '—' }}
                                </p>
                                @if ($landlord && $landlord->hasPayoutDestination())
                                    <p class="text-[12px] text-[#64748B]">GCash: {{ $landlord->gcash_number }} ({{ $landlord->gcash_account_name }})</p>
                                @else
                                    <p class="text-[12px] font-semibold text-[#DC2626]">No GCash number on file — cannot pay out yet</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-[17px] font-bold text-[#1F2937]">₱{{ number_format($row['total'], 2) }}</p>
                                <p class="text-[11px] text-[#64748B]">{{ $row['payments']->count() }} {{ Str::plural('payment', $row['payments']->count()) }}</p>
                            </div>
                            @if ($landlord && $landlord->hasPayoutDestination())
                                <button type="button"
                                    x-data
                                    x-on:click="$dispatch('open-payout-modal', { userId: {{ $landlord->user_id }}, name: '{{ trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? '')) }}', amount: '{{ number_format($row['total'], 2) }}' })"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#FF8A65] text-[12px] font-semibold text-white hover:brightness-95 shadow-sm transition-all duration-200 cursor-pointer">
                                    Mark Paid Out
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto scrollbar-thin-light">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Type</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Settled</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                @foreach ($row['payments'] as $payment)
                                    @php $tenant = $payment->reservation?->tenant; @endphp
                                    <tr>
                                        <td class="px-6 py-3 text-[13px] text-[#1F2937]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                        <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->payment_type }}</td>
                                        <td class="px-6 py-3 text-[13px] font-semibold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    {{-- Recently paid out --}}
    <x-card flush>
        <div class="px-6 py-4 border-b border-[#E2E8F0]">
            <p class="text-[13px] font-semibold text-[#1F2937]">Recently paid out</p>
        </div>
        @if ($recentlyPaidOut->isEmpty())
            <p class="px-6 py-8 text-[13px] text-[#64748B] text-center">No payouts recorded yet.</p>
        @else
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Reference</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Paid out</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        @foreach ($recentlyPaidOut as $payment)
                            @php $landlord = $payment->reservation?->property?->landlord; @endphp
                            <tr>
                                <td class="px-6 py-3 text-[13px] text-[#1F2937]">{{ $landlord ? trim($landlord->first_name.' '.$landlord->last_name) : '—' }}</td>
                                <td class="px-6 py-3 text-[13px] font-semibold text-[#1F2937]">₱{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->payout_reference }}</td>
                                <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->paid_out_at?->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-[13px] text-[#64748B]">{{ $payment->payoutRecorder?->first_name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Mark-paid-out modal --}}
    <template x-teleport="body">
        <div x-data="{ open: false, userId: null, name: '', amount: '' }"
            x-on:open-payout-modal.window="open = true; userId = $event.detail.userId; name = $event.detail.name; amount = $event.detail.amount"
            x-show="open" x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 px-4">
            <div x-show="open" x-on:click.outside="open = false" class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
                <h3 class="text-[15px] font-bold text-[#1F2937] mb-1">Mark payout sent</h3>
                <p class="text-[13px] text-[#64748B] mb-4">
                    Confirm you have sent <span x-text="'₱' + amount"></span> via GCash to
                    <span x-text="name" class="font-semibold"></span>, then record the reference below.
                </p>
                <form method="POST" x-bind:action="'/admin/payouts/' + userId + '/mark-paid-out'">
                    @csrf
                    <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">GCash transaction reference</label>
                    <input type="text" name="payout_reference" required
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all mb-4"
                        placeholder="e.g. 0123456789012">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" x-on:click="open = false"
                            class="px-4 py-2 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] bg-white hover:brightness-95 transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-[#FF8A65] text-white rounded-lg text-[13px] font-semibold hover:brightness-95 transition-all shadow-sm">
                            Confirm payout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection
