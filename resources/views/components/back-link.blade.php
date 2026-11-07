{{--
    The one page-level "go back" link: left chevron + "Back" (DESIGN.md: one back control everywhere).
    `to` is the destination, read aloud for screen readers only ("Back to reports").
    `tone="light"` is for use on a dark photo or navy ground (the auth panel).
    Wizard "Previous" buttons and pagination are a different action and do not use this.
--}}
@props(['href', 'to' => null, 'tone' => 'default'])

<a href="{{ $href }}"
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 w-fit text-[13px] font-semibold transition-colors duration-200 cursor-pointer',
        'text-[#5B6A8E] hover:text-[#060D26]' => $tone === 'default',
        'text-white/90 hover:text-white' => $tone === 'light',
    ]) }}>
    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
    <span>Back</span>@if($to)<span class="sr-only"> to {{ $to }}</span>@endif
</a>
