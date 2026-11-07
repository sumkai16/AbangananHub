@props([
    'approveAction',
    'approveLabel' => 'Approve',
    'approveConfirm' => 'Approve this?',
    'approveMessage' => null,
    'rejectAction',
    'rejectLabel' => 'Reject',
    'rejectConfirm' => 'Reject this?',
    'rejectMessage' => null,
    'field' => 'rejection_reason',
    'placeholder' => 'The landlord will see this',
    'note' => 'A rejection needs a reason; the landlord will see it.',
])
{{--
    The foot of a review desk: Approve, and Reject which unfolds the reason box above the bar. Same forms, confirm dialogs
    and field names as x-admin.decision (which is the card version, for a rail). Opens the reason box by itself when the
    reason failed validation.
--}}
<div x-data="{ showReject: {{ $errors->has($field) ? 'true' : 'false' }} }" {{ $attributes->class('mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC]') }}>
    <form x-show="showReject" x-cloak method="POST" action="{{ $rejectAction }}" class="border-b border-[#E2E4EC] px-5 py-3"
        data-confirm="{{ $rejectConfirm }}" data-confirm-type="warning"
        @if($rejectMessage) data-confirm-message="{{ $rejectMessage }}" @endif
        data-confirm-button="{{ $rejectLabel }}">
        @csrf
        <label for="bar-{{ $field }}" class="mb-1 block text-[13px] font-medium text-[#060D26]">Reason for rejecting</label>
        <div class="flex items-start gap-2">
            <textarea name="{{ $field }}" id="bar-{{ $field }}" rows="2" required placeholder="{{ $placeholder }}"
                class="min-w-0 flex-1 resize-none rounded-lg border border-[#E2E4EC] bg-white px-3 py-2 text-[13px] text-[#060D26] placeholder-[#5B6A8E] transition-colors duration-200 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">{{ old($field) }}</textarea>
            <button type="submit" class="h-9 shrink-0 rounded-lg bg-[#DC2626] px-4 text-[13px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Confirm rejection</button>
        </div>
        @error($field)<p class="mt-1 text-[12.5px] text-[#DC2626]">{{ $message }}</p>@enderror
    </form>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-3">
        <p class="text-[13px] text-[#5B6A8E]">{{ $note }}</p>
        <div class="flex items-center gap-2">
            <button type="button" @click="showReject = ! showReject" :aria-expanded="showReject ? 'true' : 'false'"
                class="h-9 rounded-lg border border-[#EF4444]/30 bg-white px-4 text-[13px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] cursor-pointer">{{ $rejectLabel }}</button>
            <form method="POST" action="{{ $approveAction }}" data-confirm="{{ $approveConfirm }}" data-confirm-type="confirm"
                @if($approveMessage) data-confirm-message="{{ $approveMessage }}" @endif data-confirm-button="{{ $approveLabel }}">
                @csrf
                <button type="submit" class="h-9 rounded-lg bg-[#15803D] px-4 text-[13px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">{{ $approveLabel }}</button>
            </form>
        </div>
    </div>
</div>
