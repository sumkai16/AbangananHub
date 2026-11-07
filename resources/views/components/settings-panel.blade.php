@props([
    'id',
    'title',
    'subtitle' => null,
    'open' => false,     // server-side: open on load (e.g. the section's form came back with errors)
    'tone' => 'default', // 'default' | 'danger'
])

{{--
    One Account Settings section as a collapsible row: the header (icon, title, one-line summary, chevron) is a
    button, the form sits below it. Closed by default, so the page reads as a short list instead of a wall of
    forms. Opens when: `open` is true, the URL hash points at the section or at anything inside it (profile.edit#payments, #visibility),
    or something dispatches `open-settings-section` with this id. Slot `icon` is the 17px stroke icon.

    Motion: the body's height slides with a grid-template-rows 0fr <-> 1fr transition (no plugin, no measuring),
    while the content fades. It clips its content only while moving: once open (`settled`), overflow is visible
    again so a dropdown inside (x-styled-select) is not cut off. Reduced motion: no slide, it just appears.
--}}
@php
    $danger = $tone === 'danger';
@endphp
<section id="{{ $id }}" class="scroll-mt-28 rounded-2xl bg-white {{ $danger ? 'border border-[#EF4444]/20' : 'ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)]' }}"
    x-data="{
        open: @js((bool) $open),
        settled: @js((bool) $open),
        timer: null,
        setOpen(value) {
            clearTimeout(this.timer);
            this.open = value;
            // Clip while moving; release once the slide (360ms) is done, unless it was closed again meanwhile.
            this.settled = false;
            if (value) this.timer = setTimeout(() => { this.settled = this.open; }, 380);
        },
        // Open when the URL points at this section or at something inside it (e.g. profile.edit#visibility).
        follow() {
            if (location.hash.length < 2) return;
            let target = null;
            try { target = document.querySelector(location.hash); } catch (e) { return; }
            if (target && (target === this.$el || this.$el.contains(target))) {
                if (! this.open) this.setOpen(true);
                if (target !== this.$el) setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'center' }), 200);
            }
        },
    }"
    x-init="follow()"
    x-on:hashchange.window="follow()"
    x-on:open-settings-section.window="if ($event.detail === @js($id) && ! open) setOpen(true)">
    <h2>
        <button type="button" x-on:click="setOpen(! open)" :aria-expanded="open.toString()" aria-controls="{{ $id }}-body"
            class="group flex min-h-[72px] w-full cursor-pointer items-center gap-3 rounded-2xl px-4 py-3.5 text-left transition-colors hover:bg-[#F7F8FC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50 sm:px-6"
            :class="open && 'rounded-b-none'">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $danger ? 'bg-[#EF4444]/[0.07]' : 'bg-[#ECEEF6]' }}" aria-hidden="true">{{ $icon }}</span>
            <span class="min-w-0 flex-1">
                <span class="block text-[15px] font-semibold text-[#060D26]">{{ $title }}</span>
                @if ($subtitle)
                    <span class="mt-0.5 block truncate text-[13px] text-[#5B6A8E]">{{ $subtitle }}</span>
                @endif
            </span>
            <svg class="h-5 w-5 shrink-0 text-[#5B6A8E] transition-transform duration-[360ms] ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none group-hover:text-[#060D26]" :class="open && 'rotate-180'"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
    </h2>
    {{-- The server sets the first frame (style + inert) so nothing jumps before Alpine starts. Closed = inert, so its
         fields are out of the tab order and hidden from screen readers. --}}
    <div id="{{ $id }}-body" class="grid transition-[grid-template-rows] duration-[360ms] ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
        style="grid-template-rows: {{ $open ? '1fr' : '0fr' }}" :style="{ gridTemplateRows: open ? '1fr' : '0fr' }"
        @unless ($open) inert @endunless :inert="! open">
        <div class="min-h-0" style="overflow: {{ $open ? 'visible' : 'hidden' }}" :style="{ overflow: settled ? 'visible' : 'hidden' }">
            <div class="border-t {{ $danger ? 'border-[#EF4444]/20' : 'border-[#E2E4EC]' }} px-4 pb-5 pt-5 transition-opacity duration-300 motion-reduce:transition-none sm:px-8 sm:pb-8 sm:pt-6"
                style="opacity: {{ $open ? 1 : 0 }}" :style="{ opacity: open ? 1 : 0 }">
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
