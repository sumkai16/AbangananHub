{{-- The latest payouts with one status: still with PayMongo, failed, or sent. --}}
<x-card flush fill>
    @if ($rows->isEmpty())
        <x-empty-state flat fill symbol="wallet" :title="$empty[0]" :note="$empty[1]" />
    @else
        <div class="overflow-x-auto scrollbar-thin-light">
            <table class="w-full min-w-[820px]">
                <thead>
                    <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                        <x-admin.th class="w-[26%]">Landlord</x-admin.th>
                        <x-admin.th align="right">Amount</x-admin.th>
                        <x-admin.th>Status</x-admin.th>
                        <x-admin.th>Reference</x-admin.th>
                        <x-admin.th>Sent</x-admin.th>
                        <x-admin.th></x-admin.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E4EC]">
                    @foreach ($rows as $payout)
                        @php $landlord = $payout->landlord; @endphp
                        <tr class="hover:bg-[#F7F8FC] transition-colors duration-200 align-top">
                            <td class="px-4 py-2.5 text-[13px] text-[#060D26]">{{ $landlord ? trim($landlord->first_name.' '.$landlord->last_name) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-[#060D26]">
                                ₱{{ number_format($payout->amount, 2) }}
                                <span class="block text-[11px] font-normal text-[#5B6A8E]">{{ $payout->payments_count }} {{ Str::plural('payment', $payout->payments_count) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-[13px]">
                                <x-admin.status :status="$payout->status" />
                                @if ($payout->failure_reason)
                                    <span class="mt-1 block max-w-[260px] text-[11.5px] text-[#5B6A8E]">{{ $payout->failure_reason }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[13px] text-[#5B6A8E]">
                                {{ $payout->payout_reference ?? '—' }}
                                @if ($payout->paidOutBy)
                                    <span class="block text-[11px]">by {{ $payout->paidOutBy->first_name }} (by hand)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[13px] text-[#5B6A8E]">{{ $payout->paid_out_at?->format('M d, Y g:i A') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">@include('admin.payouts.partials.payout-action', ['payout' => $payout])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3 text-[12.5px] text-[#5B6A8E]">The latest 30 payouts across all statuses.</p>
    @endif
</x-card>
