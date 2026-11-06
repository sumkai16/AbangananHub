{{--
    Everything owed, one landlord per row, biggest first. The row says what is in the way and offers the one action that
    fits; the chevron opens the GCash account (with the name check) and the payments that make up the amount.
--}}
@if ($pending->isEmpty())
    <x-card flush><x-admin.empty title="Nothing pending payout" note="Every released and paid rent payment has been sent to its landlord." /></x-card>
@else
    @php
        $initials = fn (string $name) => Str::of($name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('');
    @endphp
    <x-card flush>
        <div class="overflow-x-auto scrollbar-thin-light">
            <table class="w-full min-w-[920px] table-fixed text-left">
                <colgroup><col class="w-[30%]"><col class="w-[14%]"><col class="w-[31%]"><col class="w-[19%]"><col class="w-[6%]"></colgroup>
                <thead>
                    <tr class="border-b border-[#E2E4EC] bg-[#F7F8FC]">
                        <x-admin.th class="!pl-5">Landlord</x-admin.th>
                        <x-admin.th>Owed</x-admin.th>
                        <x-admin.th>State</x-admin.th>
                        <x-admin.th>Next step</x-admin.th>
                        <x-admin.th><span class="sr-only">Show payments</span></x-admin.th>
                    </tr>
                </thead>
                @foreach ($pending as $row)
                    @php
                        $landlord = $row['landlord'];
                        $id = $landlord->user_id;
                        $name = trim(($landlord->first_name ?? '').' '.($landlord->last_name ?? '')) ?: '—';
                        $account = $landlord->payoutAccount;
                        $hasDestination = $landlord->hasPayoutDestination();
                        $kind = $row['kind'];
                        $failedPayout = $failedByLandlord[$id] ?? null;
                        $count = $row['payments']->count();
                        [$label, $tone, $text, $textClass] = match (true) {
                            $kind === 'failed' => ['Payout failed', 'red', 'A failed payout is waiting for you.', 'font-semibold text-[#B91C1C]'],
                            $kind === 'not_approved' => ['GCash not approved', 'amber',
                                match ($row['matches']) { true => 'Name matches their verified ID.', false => 'Name does not match their ID.', default => 'No ID name on record to compare.' },
                                match ($row['matches']) { true => 'font-semibold text-[#15803D]', false => 'font-semibold text-[#92400E]', default => 'text-[#5B6A8E]' }],
                            $kind === 'hold' => ['On hold', 'neutral', 'GCash details changed. Resumes '.$account?->payouts_hold_until?->format('M j, g:i A').'.', 'text-[#5B6A8E]'],
                            $kind === 'paused' => ['Paused', 'neutral', 'Paused by an administrator.', 'text-[#5B6A8E]'],
                            $kind === 'no_details' => ['No GCash number', 'red', 'Has to add GCash details before anything can go out.', 'text-[#5B6A8E]'],
                            $byHand => ['Pay by hand', 'amber', 'Send it from GCash, then record it.', 'text-[#5B6A8E]'],
                            default => ['Ready', 'green', $nextRun ? 'Goes out at '.$nextRun->format('g:i A').'.' : 'Goes out on the next run.', 'text-[#5B6A8E]'],
                        };
                        // A JS object literal for the modal. Only the name is user text: addslashes keeps an apostrophe from ending the string, e() keeps it HTML-safe.
                        $modalPayload = "{ userId: {$id}, name: '".e(addslashes($name))."', amount: '".number_format($row['total'], 2)."', rawAmount: '".number_format($row['total'], 2, '.', '')."' }";
                    @endphp
                    <tbody class="border-b border-[#E2E4EC] last:border-b-0" data-name="{{ Str::lower($name) }}"
                        x-show="! q || $el.dataset.name.includes(q.toLowerCase())">
                        <tr class="transition-colors duration-200 hover:bg-[#F7F8FC]">
                            <td class="px-4 py-3.5 !pl-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-[#ECEEF6] text-[11px] font-bold text-[#060D26]" aria-hidden="true">{{ $initials($name) }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-[13.5px] font-semibold text-[#060D26]">{{ $name }}</p>
                                        @if ($hasDestination)
                                            <p class="truncate text-[12.5px] text-[#5B6A8E]">GCash {{ $account?->gcash_number }} ({{ $account?->gcash_account_name }})</p>
                                        @else
                                            <p class="text-[12.5px] font-semibold text-[#DC2626]">No GCash number on file — cannot pay out yet</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-[13.5px] font-bold tabular-nums text-[#060D26]">₱{{ number_format($row['total'], 2) }}</p>
                                <p class="text-[12.5px] text-[#5B6A8E]">{{ $count }} {{ Str::plural('payment', $count) }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <x-admin.status :status="$label" :tone="$tone" />
                                <p class="mt-1 text-[12.5px] {{ $textClass }}">{{ $text }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($kind === 'failed' && $failedPayout)
                                    @include('admin.payouts.partials.payout-action', ['payout' => $failedPayout])
                                @elseif ($kind === 'not_approved')
                                    @include('admin.payouts.partials.approve-form', ['landlord' => $landlord, 'matches' => $row['matches']])
                                @elseif ($kind === 'paused')
                                    <form method="POST" action="{{ route('admin.payouts.resume', $landlord) }}">
                                        @csrf
                                        <button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Resume payouts</button>
                                    </form>
                                @elseif ($kind === null && $byHand && $hasDestination)
                                    <button type="button" x-data x-on:click="$dispatch('open-payout-modal', {!! $modalPayload !!})"
                                        class="h-8 px-3 rounded-lg bg-[#FF8A66] text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Mark Paid Out</button>
                                @elseif ($kind === null && $hasDestination)
                                    <form method="POST" action="{{ route('admin.payouts.pause', $landlord) }}"
                                        data-confirm="Pause payouts to this landlord?" data-confirm-message="Nothing is sent to them until you resume. What they are owed stays owed." data-confirm-button="Pause">
                                        @csrf
                                        <button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Pause</button>
                                    </form>
                                @else
                                    <span class="text-[12.5px] text-[#5B6A8E]">{{ $kind === 'no_details' ? 'Waiting on the landlord' : 'Nothing to do' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <button type="button" x-on:click="open = open === {{ $id }} ? null : {{ $id }}" x-bind:aria-expanded="open === {{ $id }}"
                                    aria-label="Show payments for {{ $name }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="transition-transform duration-200" x-bind:class="open === {{ $id }} && 'rotate-180'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open === {{ $id }}" x-cloak>
                            <td colspan="5" class="bg-[#F7F8FC] px-5 pb-4 pt-1">
                                <div class="flex flex-wrap gap-3.5">
                                    <div class="min-w-[280px] flex-1 basis-72 rounded-xl border border-[#E2E4EC] bg-white p-4">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">GCash account</p>
                                        @if ($hasDestination)
                                            <dl class="mt-2.5 space-y-1.5 text-[13px]">
                                                <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Number</dt><dd class="font-semibold tabular-nums text-[#060D26]">{{ $account?->gcash_number }}</dd></div>
                                                <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">On the account</dt><dd class="font-semibold text-[#060D26]">{{ $account?->gcash_account_name }}</dd></div>
                                                @if ($row['idNames'])
                                                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">On their ID</dt><dd class="text-right font-semibold text-[#060D26]">{{ implode(' / ', $row['idNames']) }}</dd></div>
                                                @endif
                                            </dl>
                                            @if ($kind === 'not_approved' && $row['matches'] === false)
                                                <p class="mt-2.5"><x-admin.status status="Names do not match" tone="red" /></p>
                                                <p class="mt-2 text-[12.5px] text-[#5B6A8E]">Ask them whose account this is before you approve it.</p>
                                            @endif
                                            <div class="mt-3.5 flex flex-wrap gap-2">
                                                @if ($account?->isPaused())
                                                    <form method="POST" action="{{ route('admin.payouts.resume', $landlord) }}">@csrf<button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] cursor-pointer">Resume payouts</button></form>
                                                @elseif ($kind !== null || $byHand)
                                                    <form method="POST" action="{{ route('admin.payouts.pause', $landlord) }}" data-confirm="Pause payouts to this landlord?" data-confirm-message="Nothing is sent to them until you resume. What they are owed stays owed." data-confirm-button="Pause">@csrf<button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] cursor-pointer">Pause</button></form>
                                                @endif
                                                @unless ($byHand)
                                                    <button type="button" x-data x-on:click="$dispatch('open-payout-modal', {!! $modalPayload !!})" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#B35A3D] hover:bg-[#F7F8FC] cursor-pointer">Record a transfer sent by hand</button>
                                                @endunless
                                            </div>
                                        @else
                                            <p class="mt-2.5 text-[13px] font-semibold text-[#DC2626]">No GCash number on file. They have to add one before anything can be sent.</p>
                                        @endif
                                    </div>
                                    <div class="min-w-[320px] flex-[2] basis-96 overflow-hidden rounded-xl border border-[#E2E4EC] bg-white">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="border-b border-[#E2E4EC] bg-[#F7F8FC]">
                                                    <x-admin.th>Tenant</x-admin.th>
                                                    <x-admin.th>Type</x-admin.th>
                                                    <x-admin.th align="right">Amount</x-admin.th>
                                                    <x-admin.th align="right">Settled</x-admin.th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-[#E2E4EC]">
                                                @foreach ($row['payments'] as $payment)
                                                    @php $tenant = $payment->reservation?->tenant; @endphp
                                                    <tr>
                                                        <td class="px-4 py-2.5 text-[13px] text-[#060D26]">{{ $tenant ? trim($tenant->first_name.' '.$tenant->last_name) : '—' }}</td>
                                                        <td class="px-4 py-2.5 text-[13px] text-[#5B6A8E]">{{ $payment->payment_type }}</td>
                                                        <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-[#060D26]">₱{{ number_format($payment->amount, 2) }}</td>
                                                        <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-[#5B6A8E]">{{ ($payment->released_at ?? $payment->paid_at)?->format('M d, Y') ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3 text-[12.5px] text-[#5B6A8E]">
            <span>{{ $pending->count() }} {{ Str::plural('landlord', $pending->count()) }}, biggest amount first</span>
            @unless ($byHand)
                <span>Sent one yourself? Open a landlord's row and choose <strong class="text-[#060D26]">Record a transfer sent by hand</strong>.</span>
            @endunless
        </div>
    </x-card>
@endif
