{{--
    Landlord: record the move-in money yourself (cash at the door, or a transfer you already saw), without
    waiting for the tenant to report it. Needs $reservation (unit loaded). Posts to
    landlord.reservations.recordMoveInPayment, which also withdraws any pending report from the tenant.
--}}
@php $due = $reservation->moveInDue(); @endphp
<div x-data="{ open: false }" class="mt-2">
    <button type="button" x-show="!open" @click="open = true"
        class="h-8 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[11.5px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Record move-in payment</button>

    <form x-show="open" x-cloak method="POST" action="{{ route('landlord.reservations.recordMoveInPayment', $reservation) }}"
        class="space-y-2 rounded-xl border border-[#E2E4EC] bg-white p-3">
        @csrf
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label for="rmi-amount-{{ $reservation->reservation_id }}" class="mb-1 block text-[11px] font-semibold text-[#060D26]">Amount received</label>
                <input id="rmi-amount-{{ $reservation->reservation_id }}" name="initial_amount" type="number" step="0.01" min="1" required value="{{ number_format($due, 2, '.', '') }}"
                    class="w-full rounded-lg border border-[#E2E4EC] px-2.5 py-2 text-[13px] tabular-nums text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
            </div>
            <div>
                <label for="rmi-method-{{ $reservation->reservation_id }}" class="mb-1 block text-[11px] font-semibold text-[#060D26]">Received by</label>
                <select id="rmi-method-{{ $reservation->reservation_id }}" name="payment_method" required
                    class="w-full rounded-lg border border-[#E2E4EC] px-2.5 py-2 text-[13px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
                    @foreach(['Cash', 'GCash', 'Maya', 'GoTyme', 'Bank Transfer', 'Other'] as $m)
                        <option value="{{ $m }}">{{ $m === 'Bank Transfer' ? 'Bank app' : $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rmi-date-{{ $reservation->reservation_id }}" class="mb-1 block text-[11px] font-semibold text-[#060D26]">Date received</label>
                <input id="rmi-date-{{ $reservation->reservation_id }}" name="payment_date" type="date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}"
                    class="w-full rounded-lg border border-[#E2E4EC] px-2.5 py-2 text-[13px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
            </div>
            <div>
                <label for="rmi-ref-{{ $reservation->reservation_id }}" class="mb-1 block text-[11px] font-semibold text-[#060D26]">Reference <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                <input id="rmi-ref-{{ $reservation->reservation_id }}" name="reference_no" maxlength="100"
                    class="w-full rounded-lg border border-[#E2E4EC] px-2.5 py-2 text-[13px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66]">
            </div>
        </div>
        <p class="text-[11px] text-[#5B6A8E]">&#8369;{{ number_format($due, 2) }} is the first month plus the deposit. It is split into those, and anything extra counts as rent paid ahead.</p>
        <button type="submit" class="h-9 w-full rounded-lg bg-[#FF8A66] text-[12px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">Record payment</button>
    </form>
</div>
