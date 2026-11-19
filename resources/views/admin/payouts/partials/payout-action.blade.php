{{--
    The one button a payout row needs, if any: Retry for a failed payout nobody has looked at, Check status for one
    PayMongo is still processing, Retry (checked) for one that never got a PayMongo id (the admin must first make sure
    in the PayMongo dashboard that it was not sent, or the landlord is paid twice).
--}}
@if ($payout->isFailed() && ! $payout->resolved_at)
    <form method="POST" action="{{ route('admin.payouts.retry', $payout) }}"
        data-confirm="Send this payout again?" data-confirm-message="Check the landlord's GCash number and name first. The money goes out again on the next run." data-confirm-button="Retry">
        @csrf
        <button type="submit" class="h-8 px-3 rounded-lg bg-[#FF8A66] text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] cursor-pointer">Retry</button>
    </form>
@elseif ($payout->isProcessing() && $payout->paymongo_transfer_id)
    <form method="POST" action="{{ route('admin.payouts.check', $payout) }}">
        @csrf
        <button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Check status</button>
    </form>
@elseif ($payout->isProcessing())
    <form method="POST" action="{{ route('admin.payouts.retry', $payout) }}"
        data-confirm="Did you check PayMongo?" data-confirm-message="Open the PayMongo dashboard and make sure this transfer was NOT sent. Retrying a transfer that did go out pays the landlord twice." data-confirm-button="Nothing was sent, retry">
        @csrf
        <input type="hidden" name="confirmed" value="1">
        <button type="submit" class="h-8 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">Retry (checked)</button>
    </form>
@endif
