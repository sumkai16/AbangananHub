{{--
    The list behind the "Needs you" button: failed payouts to retry and GCash accounts to approve. Nothing is sent to
    these landlords until an admin acts. The same actions exist on the landlord's row in the Owed table.
--}}
@php
    $initials = fn (string $name) => Str::of($name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('');
@endphp
<div id="needs-panel" x-show="needs" x-cloak x-transition.opacity.duration.150ms role="region" aria-label="Needs you"
    class="absolute right-0 top-11 z-30 w-[min(540px,92vw)] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_16px_40px_rgba(6,13,38,0.16)]">
    <div class="flex items-start justify-between gap-3 px-5 py-3.5">
        <div>
            <h2 class="text-[15px] font-semibold text-[#060D26]">Needs you <span class="ml-1.5 rounded-md bg-[#ECEEF6] px-1.5 py-0.5 text-[12px] font-semibold tabular-nums text-[#4C5B80]">{{ $needsCount }}</span></h2>
            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">Nothing is sent to these landlords until you act.@if ($nextRun) Fix one before {{ $nextRun->format('g:i A') }} and it joins the run.@endif</p>
        </div>
        <button type="button" x-on:click="needs = false" aria-label="Close the list" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#E2E4EC] text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div class="max-h-[min(60vh,560px)] overflow-y-auto border-t border-[#E2E4EC]">
        @if ($failed->isNotEmpty())
            <h3 class="bg-[#F7F8FC] px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Failed payouts</h3>
        @endif
    <ul class="divide-y divide-[#E2E4EC]">
        @foreach ($failed as $payout)
            @php $name = trim(($payout->landlord->first_name ?? '').' '.($payout->landlord->last_name ?? '')) ?: '—'; @endphp
            <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3.5">
                <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full bg-[#EF4444]/[0.09] text-[#B91C1C]" aria-hidden="true"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg></span>
                <div class="min-w-0 flex-1 basis-56">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">Retry {{ $name }}'s payout</p>
                    <p class="text-[12.5px] text-[#5B6A8E]">Failed {{ $payout->updated_at?->format('M j, g:i A') }}@if ($payout->failure_reason): “{{ $payout->failure_reason }}”@endif. Confirm the GCash number with them first.</p>
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-[14px] font-bold tabular-nums text-[#060D26]">₱{{ number_format($payout->amount, 2) }}</p>
                    @include('admin.payouts.partials.payout-action', ['payout' => $payout])
                </div>
            </li>
        @endforeach
    </ul>
        @if ($toApprove->isNotEmpty())
            <h3 class="border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">GCash accounts to approve</h3>
        @endif
    <ul class="divide-y divide-[#E2E4EC]">
        @foreach ($toApprove as $item)
            @php
                $landlord = $item['landlord']; $account = $item['account'];
                $name = trim($landlord->first_name.' '.$landlord->last_name);
                $owedNow = $owedBy[$landlord->user_id] ?? null;
            @endphp
            <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3.5">
                <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full bg-[#FBBF24]/[0.18] text-[#92400E]" aria-hidden="true"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg></span>
                <div class="min-w-0 flex-1 basis-56">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">Approve {{ $name }}'s GCash account</p>
                    <p class="text-[12.5px] text-[#5B6A8E]">GCash {{ $account->gcash_number }} &middot; account name &ldquo;{{ $account->gcash_account_name }}&rdquo;</p>
                    @if ($item['matches'] === true)
                        <p class="text-[12.5px] font-semibold text-[#15803D]">Name matches their verified ID.</p>
                    @elseif ($item['matches'] === false)
                        <p class="text-[12.5px] font-semibold text-[#92400E]">Name does not match their ID ({{ implode(' / ', $item['idNames']) }}). Ask whose account this is.</p>
                    @else
                        <p class="text-[12.5px] text-[#5B6A8E]">No ID name on record to compare.</p>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-[13px] tabular-nums {{ $owedNow ? 'font-bold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $owedNow ? '₱'.number_format($owedNow, 2) : 'Nothing owed yet' }}</p>
                    @include('admin.payouts.partials.approve-form', ['landlord' => $landlord, 'matches' => $item['matches']])
                </div>
            </li>
        @endforeach
    </ul>
    </div>
</div>
