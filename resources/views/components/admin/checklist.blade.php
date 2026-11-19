@props(['title' => 'Before you approve', 'items', 'note' => null])
{{--
    The review checklist as a button with a popover, so it is one glance away on a queue page instead of a card that takes
    a third of the screen. $items is a list of sentences; $note is the line under them. Opens on mouse hover (the panel
    starts flush under the button, so the pointer can travel into it), on keyboard focus, and on tap for touch screens.
    Closes when the pointer or focus leaves, on Esc, or on a tap outside.
--}}
<div {{ $attributes->class('relative') }} x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false"
    @pointerenter="if ($event.pointerType === 'mouse') open = true" @pointerleave="if ($event.pointerType === 'mouse') open = false"
    @focusin="open = true" @focusout="if (! $el.contains($event.relatedTarget)) open = false">
    <button type="button" @click="if ($event.pointerType !== 'mouse') open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true"
        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] cursor-pointer">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        {{ $title }}
    </button>
    {{-- pt-2 is the bridge: part of the panel's hit area, so the pointer never leaves the root between button and card. --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute right-0 top-full z-30 w-[22rem] max-w-[calc(100vw-2rem)] pt-2">
        <div class="rounded-xl border border-[#E2E4EC] bg-white p-4 shadow-[0_16px_48px_-12px_rgba(6,13,38,0.20)]">
            <ol class="space-y-2.5 text-[13px] leading-5 text-[#060D26]">
                @foreach($items as $i => $line)
                    <li class="flex gap-2.5"><span class="w-3 shrink-0 font-bold tabular-nums text-[#B35A3D]">{{ $i + 1 }}</span><span>{{ $line }}</span></li>
                @endforeach
            </ol>
            @if($note)<p class="mt-3 text-[12.5px] text-[#5B6A8E]">{{ $note }}</p>@endif
        </div>
    </div>
</div>
