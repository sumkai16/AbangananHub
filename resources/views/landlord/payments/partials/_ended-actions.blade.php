{{-- Payments row actions for a lease that has ended. Two choices for an ex-tenant who still owes rent: collect it
     (Open ledger, then Record payment) or write it off. It is written off by itself `rentals.write_off_after_days` after move-out;
     "Write off now" does it sooner. A write-off removes it from "Still owed" and can be undone ("Collect again"). Needs $item (the row) and $reservation. --}}
@if($item['isEnded'])
    @if($item['summary']['outstanding'] > 0)
        <form method="POST" action="{{ route('landlord.tenancies.stopCollecting', $reservation) }}"
            data-confirm="Write off this balance now?"
            data-confirm-message="₱{{ number_format($item['summary']['outstanding'], 2) }} will no longer count as owed. The months stay on the ledger and you can collect it again any time."
            data-confirm-button="Write off now" data-confirm-type="warning">
            @csrf
            <button type="submit" class="h-9 px-3 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[12.5px] font-semibold text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer whitespace-nowrap">Write off now</button>
        </form>
    @elseif($item['writtenOff'])
        <form method="POST" action="{{ route('landlord.tenancies.resumeCollecting', $reservation) }}">
            @csrf
            <button type="submit" class="h-9 px-3 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[12.5px] font-semibold text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer whitespace-nowrap">Collect again</button>
        </form>
    @endif
@endif
