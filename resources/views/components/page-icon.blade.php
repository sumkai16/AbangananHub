{{--
    The navy rounded tile beside a page title (Messages, My Reservations, Notifications...).
    One place for its size and colour so every page header matches. Pass the icon as an
    outline path (24x24 viewBox) in `path`, or a custom <svg> as the slot.
--}}
@props(['path' => null])
<div {{ $attributes->class('w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-[#060D26] flex items-center justify-center shrink-0') }} aria-hidden="true">
    @if($path)
        <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" class="w-4 h-4 sm:w-[19px] sm:h-[19px]">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
        </svg>
    @else
        {{ $slot }}
    @endif
</div>
