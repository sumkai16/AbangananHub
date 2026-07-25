{{--
    Camera shutter — the white ring-and-disc control a phone camera uses.

    Sits *inside* the video surface (bottom centre), not below it, because that's
    where a camera puts it. White rather than the coral CTA colour on purpose:
    this is a device control layered on a live feed, not a page-level call to
    action, and coral on video reads as a record button.

    The visible label lives in the hint pill at the top of the frame, so the
    control itself carries only an accessible name.

    @props ['label' => accessible name, e.g. "Capture the front of your ID"]
--}}
@props(['label'])

<button type="button" aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'group absolute bottom-5 left-1/2 -translate-x-1/2 z-10 grid place-items-center w-[68px] h-[68px] rounded-full border-[3px] border-white/95 transition-transform duration-150 hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/40 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 motion-reduce:transition-none motion-reduce:hover:scale-100 motion-reduce:active:scale-100']) }}>
    <span
        class="w-[54px] h-[54px] rounded-full bg-white shadow-[0_1px_6px_rgba(0,0,0,0.25)] transition-transform duration-150 group-active:scale-90 motion-reduce:transition-none motion-reduce:group-active:scale-100"></span>
</button>
