@extends('layouts.admin')

@section('page-title', 'Payments')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header title="Payments" subtitle="Online money held until move-in, and payments landlords recorded." />

    {{-- Pesos only: the tabs below carry the counts. --}}
    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'Held', 'value' => '₱'.number_format($sums['Held'], 2), 'note' => 'Waiting for move-in'],
        ['label' => 'Released', 'value' => '₱'.number_format($sums['Released'], 2), 'note' => 'Sent on to landlords'],
        ['label' => 'Recorded', 'value' => '₱'.number_format($sums['Paid'], 2), 'note' => 'Offline, reported by landlords'],
    ]" />

    <x-admin.tabs class="mb-4" :tabs="collect(['Held', 'Released', 'Paid' => 'Recorded', 'Pending' => 'Processing', 'Voided', 'Refunded', 'All'])->map(function ($label, $key) use ($counts, $status) {
        $tab = is_int($key) ? $label : $key;

        return ['label' => $label, 'count' => $counts[$tab], 'href' => route('admin.payments.index', ['status' => $tab]), 'active' => $status === $tab];
    })->values()->all()" />

    @if ($payments->isEmpty())
        <x-card flush><x-admin.empty title="No payments here" /></x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th>Property / Unit</x-admin.th>
                            <x-admin.th align="right">Amount</x-admin.th>
                            <x-admin.th>Payment Date</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th align="right">Action</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($payments as $payment)
                            @php $tenant = $payment->reservation?->tenant; @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-all duration-200">
                                <td class="px-4 py-3">
                                    
                                            <div class="flex items-center gap-1.5">
                                                <p class="text-[13.5px] font-semibold text-[#060D26]">
                                                    {{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}
                                                </p>
                                                @if ($tenant?->is_walk_in)
                                                    <span class="inline-flex items-center h-5 px-1.5 rounded bg-[#FBBF24]/[0.16] text-[#B45309] text-[11px] font-semibold"
                                                        title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                                @endif
                                            </div>
                                            <p class="text-[12px] text-[#5B6A8E]">{{ $tenant?->email ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-[13.5px] text-[#060D26] font-medium">{{ $payment->reservation?->property?->title ?? '—' }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $payment->reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="text-[13.5px] font-semibold tabular-nums text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $payment->payment_type }}</p>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-[#5B6A8E]">
                                    {{ ($payment->paid_at ?? $payment->created_at)?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.status :status="$payment->status" :label="['Paid' => 'Recorded', 'Pending' => 'Processing'][$payment->status] ?? $payment->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($payment->status === 'Held')
                                        @php
                                            $group = $heldGroups[$payment->payment_id] ?? collect([$payment]);
                                            $quote = \App\Services\PaymentRefunds::quote($group);
                                            $refundDetail = [
                                                'id' => $payment->payment_id,
                                                'action' => route('admin.payments.refund', $payment),
                                                'label' => trim(($tenant->first_name ?? '').' '.($tenant->last_name ?? '')).' · '.($payment->reservation?->property?->title ?? 'Reservation'),
                                                'rent' => $quote['rent'],
                                                'fee' => $quote['fee'],
                                                'rows' => $quote['rows'],
                                            ];
                                        @endphp
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($payment->recorded_by === null && $quote['paymongo_payment_id'])
                                            <button type="button" @click="$dispatch('open-refund-payment', {{ \Illuminate\Support\Js::from($refundDetail) }})"
                                                class="inline-flex h-9 items-center rounded-lg border border-[#EF4444]/30 px-3 text-[12.5px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06] transition-all duration-200 cursor-pointer">
                                                Refund tenant
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('admin.payments.release', $payment) }}"
                                            data-confirm="Release this payment to the landlord?"
                                            data-confirm-type="warning"
                                            data-confirm-message="This sends the funds to the landlord's payout and cannot be undone."
                                            data-confirm-button="Release payment">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex h-9 items-center rounded-lg bg-[#FF8A66] px-3 text-[12.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                                                Release
                                            </button>
                                        </form>
                                        </div>
                                    @elseif ($payment->status === 'Refunded')
                                        <div class="text-[12px] text-[#5B6A8E] leading-snug">
                                            <p>Refunded {{ $payment->refunded_at?->format('M d, Y') }}</p>
                                            @if ($payment->refund_status === 'failed')
                                                <p class="mt-0.5 font-bold text-[#DC2626]">Refund failed. The tenant has not been repaid.</p>
                                            @elseif ($payment->refund_status === 'processing' && $payment->refund_transfer_link)
                                                <p class="mt-0.5 text-[#B45309]">Waiting for the tenant to claim it
                                                    (<a href="{{ $payment->refund_transfer_link }}" target="_blank" rel="noopener noreferrer" class="underline font-semibold">link</a>)</p>
                                            @elseif (in_array($payment->refund_status, ['pending', 'processing'], true))
                                                <p class="mt-0.5 text-[#B45309]">PayMongo is still sending it</p>
                                            @elseif ($payment->refund_status === 'succeeded')
                                                <p class="mt-0.5 text-[#15803D]">Sent</p>
                                            @endif
                                            @if ($payment->refund_includes_fee)
                                                <p class="mt-0.5">Fee refunded too</p>
                                            @endif
                                        </div>
                                    @elseif ($payment->status === 'Released')
                                        <span class="text-[12px] text-[#5B6A8E]">
                                            Released {{ $payment->released_at?->format('M d, Y') }}
                                        </span>
                                    @elseif ($payment->status === 'Voided')
                                        <span class="text-[12px] text-[#5B6A8E]">
                                            {{ $payment->voidReasonLabel() }} · {{ $payment->voided_at?->format('M d, Y') }}
                                        </span>
                                    @elseif ($payment->isManuallyRecorded())
                                        {{-- Landlord-asserted, never escrowed — deliberately no
                                             Release button. This is the one row type an admin must
                                             be able to tell apart from platform-settled money. --}}
                                        <span class="text-[12px] text-[#5B6A8E]">Recorded by landlord</span>
                                    @else
                                        <span class="text-[12px] text-[#5B6A8E]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="px-4 py-3 border-t border-[#E2E4EC]">
                    {{ $payments->links() }}
                </div>
            @endif
        </x-card>
    @endif

    @include('admin.payments.partials.refund-modal')

</div>
@endsection
