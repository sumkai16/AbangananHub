{{--
    The card for an online checkout the tenant started and has not finished: they paid and it is still clearing,
    or they closed the PayMongo page. Needs $reservation and $type ('Initial' | 'Monthly'). Offers the three ways
    out so nobody waits for the checkout to expire on its own: go back to it, check whether it was paid, or
    cancel it and choose again.
--}}
@php $initial = $type === 'Initial'; @endphp
<div class="rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 w-5 h-5 text-[#5B6A8E] shrink-0 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <div>
            <p class="text-sm font-bold text-[#060D26]">Payment in progress</p>
            <p class="mt-0.5 text-xs leading-relaxed text-[#5B6A8E]">
                If you finished paying, it can take a moment to clear. If you closed the PayMongo page, go back to it, or cancel and choose how to pay again. Nothing is charged until the payment goes through.
            </p>
        </div>
    </div>

    @if($errors->has('payment'))
        <div role="alert" class="mt-3 rounded-xl border border-[#EF4444]/30 bg-[#EF4444]/10 px-3 py-2.5 text-[12.5px] font-semibold text-[#B91C1C]">
            {{ $errors->first('payment') }}
        </div>
    @endif

    <div class="mt-4 grid gap-2 sm:grid-cols-3">
        {{-- New tab: PayMongo opens beside this page, which stays here to check or cancel from. --}}
        <form method="POST" action="{{ route('payments.continue', $reservation) }}" target="_blank" rel="noopener">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <button type="submit" class="h-11 w-full rounded-xl bg-[#FF8A66] px-3 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">Continue to payment</button>
        </form>
        <a href="{{ route($initial ? 'payments.success' : 'payments.rent.success', $reservation) }}"
            class="flex h-11 w-full items-center justify-center rounded-xl border border-[#E2E4EC] bg-white px-3 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">I&rsquo;ve paid, check now</a>
        <form method="POST" action="{{ route('payments.cancel', $reservation) }}"
            data-confirm="Cancel this payment?" data-confirm-type="warning"
            data-confirm-message="The PayMongo checkout is closed and nothing is charged. You can then pay online again, by QR or in cash."
            data-confirm-button="Cancel payment">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <button type="submit" class="h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3 text-[13px] font-bold text-[#B91C1C] transition-colors hover:bg-[#FEF2F2]">Cancel payment</button>
        </form>
    </div>
</div>
