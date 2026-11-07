@props([
    'since' => null,
    'approveAction',
    'approveLabel' => 'Approve',
    'approveConfirm' => 'Approve this?',
    'approveMessage' => null,
    'rejectAction',
    'rejectLabel' => 'Reject',
    'rejectConfirm' => 'Reject this?',
    'rejectMessage' => null,
    'field' => 'rejection_reason',
    'required' => true,
    'maxlength' => null,
    'placeholder' => 'The landlord will see this',
    // Buttons one above the other, for a narrow rail.
    'stacked' => false,
])
{{--
    Approve or reject, the same on every review page. The reason box stays folded until Reject is pressed, so a pending
    item costs one row of buttons. It opens by itself when the reason failed validation. Anything else that belongs with
    the decision, such as a checklist, goes in the slot under the buttons.
--}}
<x-card x-data="{ showReject: {{ $errors->has($field) ? 'true' : 'false' }} }">
    <h2 class="text-[14px] font-semibold text-[#060D26]">Your decision</h2>
    @if($since)
        <p class="text-[12.5px] text-[#5B6A8E]">Waiting since {{ $since }}.</p>
    @endif
    @isset($intro)
        <div class="mt-2.5">{{ $intro }}</div>
    @endisset

    <div class="mt-2.5 grid gap-2 {{ $stacked ? 'grid-cols-1' : 'grid-cols-2' }}">
        <form method="POST" action="{{ $approveAction }}"
            data-confirm="{{ $approveConfirm }}"
            data-confirm-type="confirm"
            @if($approveMessage) data-confirm-message="{{ $approveMessage }}" @endif
            data-confirm-button="{{ $approveLabel }}">
            @csrf
            <button type="submit" class="h-9 w-full rounded-lg bg-[#15803D] text-[13px] font-semibold text-white transition-all duration-200 hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803D]/50 focus-visible:ring-offset-2 cursor-pointer">{{ $approveLabel }}</button>
        </form>
        <button type="button" @click="showReject = !showReject" :aria-expanded="showReject"
            class="h-9 rounded-lg border border-[#EF4444]/30 text-[13px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#EF4444]/40 cursor-pointer">{{ $rejectLabel }}</button>
    </div>

    <form x-show="showReject" x-cloak method="POST" action="{{ $rejectAction }}" class="mt-3 border-t border-[#E2E4EC] pt-3"
        data-confirm="{{ $rejectConfirm }}"
        data-confirm-type="warning"
        @if($rejectMessage) data-confirm-message="{{ $rejectMessage }}" @endif
        data-confirm-button="{{ $rejectLabel }}">
        @csrf
        <label for="decision-{{ $field }}" class="mb-1 block text-[13px] font-medium text-[#060D26]">Reason for rejecting</label>
        <textarea name="{{ $field }}" id="decision-{{ $field }}" rows="3" @if($required) required @endif @if($maxlength) maxlength="{{ $maxlength }}" @endif placeholder="{{ $placeholder }}"
            class="w-full resize-none rounded-lg border border-[#E2E4EC] px-3 py-2 text-[13px] text-[#060D26] placeholder-[#5B6A8E] transition-colors duration-200 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">{{ old($field) }}</textarea>
        @error($field)
            <p class="mt-1 text-[12.5px] text-[#DC2626]">{{ $message }}</p>
        @enderror
        <button type="submit" class="mt-2 h-9 w-full rounded-lg bg-[#DC2626] text-[13px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Confirm rejection</button>
    </form>

    @if(! $slot->isEmpty())
        <div class="mt-3 border-t border-[#E2E4EC] pt-3">{{ $slot }}</div>
    @endif
</x-card>
