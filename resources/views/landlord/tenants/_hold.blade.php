{{--
    Unpaid rent on one contract: "Report unpaid rent" while rent is overdue, and the state of a report once made.
    An admin confirms a report before the tenant is held, so a landlord only ever asks. Shown inside the contract
    modal (landlord.contracts.partials._details). Needs $reservation. Mobile-first: 44px tap targets.
--}}
@php
    $holdMine = \App\Models\TenantHold::open()
        ->where('reservation_id', $reservation->reservation_id)
        ->where('landlord_id', auth()->id())
        ->latest('hold_id')
        ->first();

    $overdue = ! $holdMine && $reservation->tenant_id && in_array($reservation->rental_status, ['Occupied', 'Completed'], true)
        ? \App\Services\RentLedger::for($reservation)->summary()
        : null;
    $canReport = $overdue && (int) $overdue['overdueCount'] > 0;
    $minReason = (int) config('security.hold_match.min_reason_length', 10);
    $holdBtn = 'inline-flex min-h-[44px] items-center justify-center rounded-full px-5 text-[13px] font-semibold transition-colors duration-200 cursor-pointer';
@endphp

@if($holdMine)
    <div class="mx-6 mb-4 rounded-2xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.08] p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[11px] font-bold {{ $holdMine->status === \App\Models\TenantHold::ACTIVE ? 'border-[#EF4444]/25 bg-[#EF4444]/[0.08] text-[#B91C1C]' : 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.12] text-[#B45309]' }}">
                {{ $holdMine->statusLabel() }}
            </span>
            <span class="text-[12.5px] font-semibold tabular-nums text-[#060D26]">₱{{ number_format((float) $holdMine->amount_owed, 2) }} unpaid when reported</span>
        </div>
        <p class="mt-2 text-[12.5px] text-[#5B6A8E]">
            @if($holdMine->status === \App\Models\TenantHold::ACTIVE)
                {{ $reservation->tenant->first_name }} can still sign in and pay, and can message you, but cannot book anything new until the rent is paid.
            @else
                An admin is reviewing your report. Nothing changes for {{ $reservation->tenant->first_name }} until it is confirmed.
            @endif
        </p>
        <p class="mt-1 break-words text-[12.5px] text-[#060D26]">“{{ $holdMine->reason }}”</p>
        <form method="POST" action="{{ route('landlord.holds.release', $holdMine) }}" class="mt-3"
            data-confirm="Lift this hold?" data-confirm-message="{{ $reservation->tenant->first_name }} will be able to book again straight away."
            data-confirm-button="Lift hold" data-confirm-cancel="Keep it">
            @csrf
            <button type="submit" class="{{ $holdBtn }} border border-[#5B6A8E]/30 text-[#060D26] hover:bg-[#ECEEF6]">Lift hold</button>
        </form>
    </div>
@elseif($canReport)
    <div class="mx-6 mb-4 rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4" x-data="{ open: false }">
        <p class="text-[13px] font-semibold text-[#060D26]">
            {{ $overdue['overdueCount'] }} {{ \Illuminate\Support\Str::plural('month', $overdue['overdueCount']) }} unpaid · ₱{{ number_format((float) $overdue['overdueAmount'], 2) }} overdue
        </p>
        <button type="button" x-show="!open" @click="open = true"
            class="{{ $holdBtn }} mt-3 border border-[#B91C1C]/30 text-[#B91C1C] hover:bg-[#EF4444]/[0.06]">Report unpaid rent</button>

        <form x-show="open" x-cloak method="POST" action="{{ route('landlord.holds.store', $reservation) }}" class="mt-3">
            @csrf
            <label for="hold_reason_{{ $reservation->reservation_id }}" class="mb-1.5 block text-[12px] font-semibold text-[#060D26]">What happened?</label>
            <textarea id="hold_reason_{{ $reservation->reservation_id }}" name="reason" rows="3" required minlength="{{ $minReason }}" maxlength="1000"
                placeholder="For example: three months unpaid and not answering messages."
                class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-2.5 text-[16px] text-[#060D26] outline-none transition focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 sm:text-[14px]"></textarea>
            <p class="mt-1 text-[12px] text-[#5B6A8E]">At least {{ $minReason }} characters. An admin reviews this before {{ $reservation->tenant->first_name }}'s account is limited. They can still pay and message you.</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <button type="submit" class="{{ $holdBtn }} bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F]">Send report</button>
                <button type="button" @click="open = false" class="{{ $holdBtn }} text-[#5B6A8E] hover:text-[#060D26]">Cancel</button>
            </div>
        </form>
    </div>
@endif
