@props(['title', 'note' => null, 'compact' => false])
{{-- Nothing to list. A short line inside the card, not a tall panel: this is a work surface, the absence is the whole message. --}}
<div {{ $attributes->class(['px-4 text-center', $compact ? 'py-5' : 'py-10']) }}>
    <p class="{{ $compact ? 'text-[13px] font-medium text-[#5B6A8E]' : 'text-[14px] font-semibold text-[#060D26]' }}">{{ $title }}</p>
    @if($note)
        <p class="mt-0.5 text-[13px] text-[#5B6A8E]">{{ $note }}</p>
    @endif
</div>
