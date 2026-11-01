@extends('layouts.admin')

@section('page-title', 'Landlord Payouts')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header title="Landlord Payouts" icon-path="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3"
        subtitle="Money the platform owes landlords. {{ $automatic ? 'It is sent to their GCash automatically; PayMongo charges about ₱10 per transfer (one free a week).' : 'Automatic payouts are off: pay landlords by hand.' }}" />

    @if (! $automatic)
        <div class="mb-6 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4 text-[13px] leading-6 text-[#B45309]">
            <p class="font-semibold">Manual mode: automatic payouts are switched off.</p>
            <p>Nothing is sent to PayMongo. Send each landlord's GCash by hand from your own GCash or the PayMongo dashboard, then press <strong>Mark Paid Out</strong> and type the reference. Turn automatic payouts back on in <a href="{{ route('admin.settings.edit') }}" class="underline font-semibold">System Settings</a> once PayMongo can send to GCash.</p>
        </div>
    @endif

    @if ($automatic && ! empty($configMissing))
        <div class="mb-6 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4 text-[13px] leading-6 text-[#B45309]">
            <p class="font-semibold">Automatic payouts are off.</p>
            <p>Set {{ implode(', ', $configMissing) }} in <code>.env</code> to turn them on. Until then money stays owed and you can send it by hand below, then press Mark Paid Out.</p>
        </div>
    @endif

    @if ($toApprove->isNotEmpty())
        <x-card flush class="mb-6">
            <div class="px-6 py-4 border-b border-[#E2E4EC]">
                <h2 class="text-[15px] font-semibold text-[#060D26]">GCash accounts to approve</h2>
                <p class="text-[12.5px] text-[#5B6A8E] mt-0.5">Nothing is sent to a landlord until you approve their GCash account. Check the name against their ID, and confirm the number with them if it looks wrong. Changing the number or name asks for approval again.</p>
            </div>
            <div class="divide-y divide-[#E2E4EC]">
                @foreach ($toApprove as $item)
                    @php $landlord = $item['landlord']; $account = $item['account']; @endphp
                    <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-semibold text-[#060D26]">{{ trim($landlord->first_name.' '.$landlord->last_name) }}</p>
                            <p class="text-[12px] text-[#5B6A8E]">GCash {{ $account->gcash_number }} &middot; account name &ldquo;{{ $account->gcash_account_name }}&rdquo;</p>
                            @if ($item['matches'] === true)
                                <p class="mt-0.5 text-[12px] font-semibold text-[#15803D]">Name matches their verified ID</p>
                            @elseif ($item['matches'] === false)
                                <p class="mt-0.5 text-[12px] font-semibold text-[#B45309]">Name does not match their ID ({{ implode(' / ', $item['idNames']) }}). Ask them whose account this is.</p>
                            @else
                                <p class="mt-0.5 text-[12px] text-[#5B6A8E]">No ID name on record to compare.</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.payouts.approveAccount', $landlord) }}"
                            data-confirm="Approve this GCash account?"
                            data-confirm-message="{{ $item['matches'] === false ? 'The name does not match their ID. Only approve if you have confirmed the account is theirs to use.' : 'Payouts to this landlord can then go out.' }}"
                            data-confirm-button="Approve">
                            @csrf
                            <button type="submit" class="h-10 px-4 rounded-xl bg-[#FF8A66] text-[12.5px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-colors cursor-pointer">Approve account</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    @if ($pending->isEmpty())
        <div class="bg-white border border-[#E2E4EC] rounded-2xl p-16 text-center shadow-[0_1px_3px_rgba(6,13,38,0.06)] mb-8">
            <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] border border-[#E2E4EC] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#060D26]">Nothing pending payout</p>
            <p class="text-[13px] text-[#5B6A8E] mt-1">Every released and paid rent payment has been sent to its landlord.</p>
        </div>
    @else
        <div class="space-y-4 mb-8">
            @foreach ($pending as $row)
                @php $landlord = $row['landlord']; @endphp
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-[#060D26] flex items-center justify-center shrink-0">
                                <span class="text-white text-[12px] font-bold">
                                    {{ strtoupper(substr($landlord->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($landlord->last_name ?? '', 0, 1)) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-[13.5px] font-semibold text-[#060D26]">
                                    {{ trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? '')) ?: '—' }}
                                </p>
                                @if ($landlord && $landlord->hasPayoutDestination())
                                    <p class="text-[12px] text-[#5B6A8E]">GCash: {{ $landlord->payoutAccount?->gcash_number }} ({{ $landlord->payoutAccount?->gcash_account_name }})</p>
                                @else
                                    <p class="text-[12px] font-semibold text-[#DC2626]">No GCash number on file — cannot pay out yet</p>
                                @endif
                                @if ($row['blocked'] && $landlord && $landlord->hasPayoutDestination())
                                    <p class="text-[12px] font-semibold text-[#B45309]">Waiting: {{ $row['blocked'] }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-[17px] font-bold text-[#060D26]">₱{{ number_format($row['total'], 2) }}</p>
                                <p class="text-[11px] text-[#5B6A8E]">{{ $row['payments']->count() }} {{ Str::plural('payment', $row['payments']->count()) }}</p>
                            </div>
                            @if ($landlord && $landlord->hasPayoutDestination())
                                @if ($landlord->payoutAccount?->isPaused())
                                    <form method="POST" action="{{ route('admin.payouts.resume', $landlord) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-[#E2E4EC] bg-white text-[12px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">Resume payouts</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.payouts.pause', $landlord) }}"
                                        data-confirm="Pause payouts to this landlord?" data-confirm-message="Nothing is sent to them until you resume. What they are owed stays owed." data-confirm-button="Pause">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-[#E2E4EC] bg-white text-[12px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">Pause</button>
                                    </form>
                                @endif
                                <button type="button"
                                    x-data
                                    x-on:click="$dispatch('open-payout-modal', { userId: {{ $landlord->user_id }}, name: '{{ trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? '')) }}', amount: '{{ number_format($row['total'], 2) }}' })"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#FF8A66] text-[12px] font-semibold text-[#060D26] hover:bg-[#E96F4F] shadow-sm transition-all duration-200 cursor-pointer">
                                    Mark Paid Out (sent by hand)
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto scrollbar-thin-light">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Tenant</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Type</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                                    <th class="px-6 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Settled</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E4EC]">
                                @foreach ($row['payments'] as $payment)
                                    @php $tenant = $payment->reservation?->tenant; @endphp
                                    <tr class="hover:bg-[#ECEEF6] transition-colors duration-200">
                                        <td class="px-6 py-3 text-[13px] text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                        <td class="px-6 py-3 text-[13px] text-[#5B6A8E]">{{ $payment->payment_type }}</td>
                                        <td class="px-6 py-3 text-[13px] font-semibold text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="px-6 py-3 text-[13px] text-[#5B6A8E]">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    {{-- Payouts: sent, still with PayMongo, or failed --}}
    <x-card flush>
        <div class="px-6 py-4 border-b border-[#E2E4EC]">
            <p class="text-[13px] font-semibold text-[#060D26]">Payouts</p>
        </div>
        @if ($payouts->isEmpty())
            <p class="px-6 py-8 text-[13px] text-[#5B6A8E] text-center">No payouts yet.</p>
        @else
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Amount</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Status</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Reference</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]">Sent</th>
                            <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-[#94A3B8]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($payouts as $payout)
                            @php
                                $landlord = $payout->landlord;
                                $badge = match ($payout->status) {
                                    'Sent' => 'bg-[#22C55E]/10 text-[#15803D]',
                                    'Failed' => 'bg-[#EF4444]/10 text-[#B91C1C]',
                                    default => 'bg-[#FBBF24]/15 text-[#B45309]',
                                };
                            @endphp
                            <tr class="hover:bg-[#ECEEF6] transition-colors duration-200 align-top">
                                <td class="px-6 py-3 text-[13px] text-[#060D26]">{{ $landlord ? trim($landlord->first_name.' '.$landlord->last_name) : '—' }}</td>
                                <td class="px-6 py-3 text-[13px] font-semibold text-[#060D26]">
                                    ₱{{ number_format($payout->amount, 2) }}
                                    <span class="block text-[11px] font-normal text-[#5B6A8E]">{{ $payout->payments_count }} {{ Str::plural('payment', $payout->payments_count) }}</span>
                                </td>
                                <td class="px-6 py-3 text-[13px]">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge }}">{{ $payout->status }}</span>
                                    @if ($payout->failure_reason)
                                        <span class="mt-1 block max-w-[260px] text-[11.5px] text-[#5B6A8E]">{{ $payout->failure_reason }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-[13px] text-[#5B6A8E]">
                                    {{ $payout->payout_reference ?? '—' }}
                                    @if ($payout->paidOutBy)
                                        <span class="block text-[11px]">by {{ $payout->paidOutBy->first_name }} (by hand)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-[13px] text-[#5B6A8E]">{{ $payout->paid_out_at?->format('M d, Y g:i A') ?? '—' }}</td>
                                <td class="px-6 py-3 text-right">
                                    @if ($payout->isFailed() && ! $payout->resolved_at)
                                        <form method="POST" action="{{ route('admin.payouts.retry', $payout) }}"
                                            data-confirm="Send this payout again?" data-confirm-message="Check the landlord's GCash number and name first. The money goes out again on the next run." data-confirm-button="Retry">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-[#FF8A66] text-[12px] font-semibold text-[#060D26] hover:bg-[#E96F4F] transition-all cursor-pointer">Retry</button>
                                        </form>
                                    @elseif ($payout->isProcessing() && $payout->paymongo_transfer_id)
                                        <form method="POST" action="{{ route('admin.payouts.check', $payout) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-[#E2E4EC] bg-white text-[12px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">Check status</button>
                                        </form>
                                    @elseif ($payout->isProcessing())
                                        <form method="POST" action="{{ route('admin.payouts.retry', $payout) }}"
                                            data-confirm="Did you check PayMongo?" data-confirm-message="Open the PayMongo dashboard and make sure this transfer was NOT sent. Retrying a transfer that did go out pays the landlord twice." data-confirm-button="Nothing was sent, retry">
                                            @csrf
                                            <input type="hidden" name="confirmed" value="1">
                                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-[#E2E4EC] bg-white text-[12px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">Retry (checked)</button>
                                        </form>
                                    @endif
                                </td>
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
                <h3 class="text-[15px] font-normal text-[#060D26] mb-1">Mark payout sent</h3>
                <p class="text-[13px] text-[#5B6A8E] mb-4">
                    Confirm you have sent <span x-text="'₱' + amount"></span> via GCash to
                    <span x-text="name" class="font-semibold"></span>, then record the reference below.
                </p>
                <form method="POST" x-bind:action="'/admin/payouts/' + userId + '/mark-paid-out'">
                    @csrf
                    <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5">GCash transaction reference</label>
                    <input type="text" name="payout_reference" required
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E4EC] rounded-lg text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all mb-4"
                        placeholder="e.g. 0123456789012">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" x-on:click="open = false"
                            class="px-4 py-2 border border-[#E2E4EC] rounded-lg text-[13px] font-semibold text-[#060D26] bg-white hover:brightness-95 transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-[#FF8A66] text-[#060D26] rounded-lg text-[13px] font-semibold hover:bg-[#E96F4F] transition-all shadow-sm">
                            Confirm payout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection
