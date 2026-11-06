@extends('layouts.admin')

@section('page-title', 'Payments')

@section('content')
<div class="admin-compact admin-fill">

    <x-admin.header title="Payments" subtitle="Online money held until move-in, and payments landlords recorded." />

    {{-- A tab already names the status, so the column only shows on All. --}}
    @php $showStatus = $status === 'All'; @endphp

    {{-- Pesos only: the tabs below carry the counts. --}}
    <x-stat-strip class="mb-3" :cells="[
        ['label' => 'Held', 'value' => '₱'.number_format($sums['Held'], 2), 'note' => 'Waiting for move-in'],
        ['label' => 'Released', 'value' => '₱'.number_format($sums['Released'], 2), 'note' => 'Sent on to landlords'],
        ['label' => 'Recorded', 'value' => '₱'.number_format($sums['Paid'], 2), 'note' => 'Offline, reported by landlords'],
    ]" />

    <x-admin.tabs class="mb-3" :tabs="collect(['All', 'Held', 'Released', 'Paid' => 'Recorded', 'Pending' => 'Processing', 'Voided', 'Refunded'])->map(function ($label, $key) use ($counts, $status) {
        $tab = is_int($key) ? $label : $key;

        return ['label' => $label, 'count' => $counts[$tab], 'href' => route('admin.payments.index', ['status' => $tab]), 'active' => $status === $tab];
    })->values()->all()" />

    @if ($payments->isEmpty())
        @php
            $empty = [
                'Held' => ['Nothing is being held', 'Online payments wait here until the tenant moves in.'],
                'Released' => ['No payments released yet', 'A held payment shows here once it is sent on to the landlord.'],
                'Paid' => ['No recorded payments', 'Payments a landlord records as paid offline show here.'],
                'Pending' => ['Nothing is processing', 'Payments that are still going through show here.'],
                'Voided' => ['No voided payments', 'Payments that were cancelled before settling show here.'],
                'Refunded' => ['No refunds', 'Payments you refund to a tenant show here.'],
                'All' => ['No payments yet', 'Online and recorded payments show here once tenants start paying.'],
            ][$status] ?? ['No payments here', null];
        @endphp
        <x-card flush fill>
            <x-empty-state flat fill symbol="wallet" :title="$empty[0]" :note="$empty[1]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.payments.index', ['status' => 'All']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all payments</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <x-card flush fill>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="w-full min-w-[1040px]">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>Tenant</x-admin.th>
                            <x-admin.th class="w-[28%]">Property / Unit</x-admin.th>
                            <x-admin.th>Landlord</x-admin.th>
                            <x-admin.th align="right">Amount</x-admin.th>
                            <x-admin.th>Paid by</x-admin.th>
                            <x-admin.th>Date</x-admin.th>
                            @if($showStatus)<x-admin.th>Status</x-admin.th>@endif
                            <x-admin.th align="right">Action</x-admin.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($payments as $payment)
                            @php
                                $tenant = $payment->reservation?->tenant;
                                $payLandlord = $payment->reservation?->property?->landlord;
                            @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if ($payment->reservation)
                                            <a href="{{ route('admin.reservations.show', $payment->reservation) }}" class="text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</a>
                                        @else
                                            <p class="text-[13px] font-semibold text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</p>
                                        @endif
                                        @if ($tenant?->is_walk_in)
                                            <span class="inline-flex items-center h-5 px-1.5 rounded bg-[#FBBF24]/[0.16] text-[#B45309] text-[11px] font-semibold"
                                                title="Walk-in tenant — identity not verified by AbangananHub">Walk-in</span>
                                        @endif
                                    </div>
                                    <p class="max-w-[240px] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $tenant?->email }}">{{ $tenant?->email ?: '—' }}</p>
                                </td>
                                <td class="max-w-0 px-4">
                                    <p class="truncate text-[13px] leading-5 text-[#060D26]" title="{{ $payment->reservation?->property?->title }}">{{ $payment->reservation?->property?->title ?? '—' }}</p>
                                    <p class="truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $payment->reservation?->unit?->unit_label ?? '' }}</p>
                                </td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#060D26]">{{ $payLandlord ? trim($payLandlord->first_name.' '.$payLandlord->last_name) : '—' }}</td>
                                <td class="px-4 text-right">
                                    <p class="text-[13px] font-semibold tabular-nums whitespace-nowrap text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</p>
                                    <p class="text-[12px] leading-4 text-[#5B6A8E]">{{ $payment->payment_type }}</p>
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    <p class="text-[13px] text-[#060D26]">{{ $payment->payment_method ?: '—' }}</p>
                                    @if ($payment->reference_no)<p class="max-w-[10rem] truncate text-[12px] leading-4 text-[#5B6A8E]" title="{{ $payment->reference_no }}">{{ $payment->reference_no }}</p>@endif
                                </td>
                                <td class="px-4 text-[13px] whitespace-nowrap text-[#5B6A8E]">
                                    {{ ($payment->paid_at ?? $payment->created_at)?->format('M j, Y') ?? '—' }}
                                </td>
                                @if($showStatus)
                                    <td class="px-4">
                                        <x-admin.status :status="$payment->status" :label="['Paid' => 'Recorded', 'Pending' => 'Processing'][$payment->status] ?? $payment->status" />
                                    </td>
                                @endif
                                <td class="px-4 text-right">
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
                                        <div class="flex items-center justify-end gap-1">
                                        @if ($payment->recorded_by === null && $quote['paymongo_payment_id'])
                                            <button type="button" @click="$dispatch('open-refund-payment', {{ \Illuminate\Support\Js::from($refundDetail) }})"
                                                class="inline-flex h-8 items-center rounded-lg px-2 text-[12.5px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.06] transition-colors duration-200 cursor-pointer">
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
                                                class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] hover:border-[#FF8A66] hover:bg-[#FF8A66]/[0.08] transition-colors duration-200 cursor-pointer">
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
            <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5">
                @if ($payments->hasPages())
                    <div class="w-full">{{ $payments->links() }}</div>
                @else
                    <p class="text-[13px] text-[#5B6A8E]">Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $payments->firstItem() }}–{{ $payments->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($payments->total()) }}</span></p>
                @endif
            </div>
        </x-card>
    @endif

    @include('admin.payments.partials.refund-modal')

</div>
@endsection
