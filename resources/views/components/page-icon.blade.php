{{--
    The navy rounded tile beside a page title (Messages, My Reservations, Notifications...).
    One place for its size and colour so every page header matches. Pass the icon as an
    outline path (24x24 viewBox) in `path`, or a custom <svg> as the slot.
--}}
@props(['path' => null])
<div {{ $attributes->class('w-11 h-11 rounded-xl bg-[#060D26] flex items-center justify-center shrink-0') }} aria-hidden="true">
    @if($path)
        <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
        </svg>
    @else
        {{ $slot }}
    @endif
</div>
