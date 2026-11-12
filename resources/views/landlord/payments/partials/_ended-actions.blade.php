{{-- Payments row actions for a lease that has ended. Two choices for an ex-tenant who still owes rent: collect it
     (Open ledger, then Record payment) or give up on it. Giving up removes it from "Still owed" and stops reminders;
     it can be undone. Needs $item (the row) and $reservation. --}}
@if($item['isEnded'])
    @if($item['summary']['outstanding'] > 0)
        <form method="POST" action="{{ route('landlord.tenancies.stopCollecting', $reservation) }}"
            data-confirm="Stop collecting this balance?"
            data-confirm-message="₱{{ number_format($item['summary']['outstanding'], 2) }} will no longer count as owed, and {{ $reservation->tenant->first_name }} gets no more reminders. The months stay on the ledger and you can resume any time."
            data-confirm-button="Stop collecting" data-confirm-type="warning">
            @csrf
            <button type="submit" class="h-9 px-3 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[12.5px] font-semibold text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer whitespace-nowrap">Stop collecting</button>
        </form>
    @elseif($item['writtenOff'])
        <form method="POST" action="{{ route('landlord.tenancies.resumeCollecting', $reservation) }}">
            @csrf
            <button type="submit" class="h-9 px-3 inline-flex items-center justify-center rounded-full border border-[#5B6A8E]/30 text-[12.5px] font-semibold text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer whitespace-nowrap">Resume collecting</button>
        </form>
    @endif
@endif
