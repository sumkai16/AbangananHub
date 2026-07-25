{{--
    Corner brackets marking the capture target area.

    Replaces the thin white rectangle the camera steps used to draw. Teal is the
    resting state; pass a bound colour where the page actually knows whether the
    shot is good (the liveness ring does, ID framing doesn't — so ID capture
    stays teal rather than faking a "ready" signal).

    @props ['style' => null]  inline style expression for the border colour
--}}
@props(['style' => null])

<div class="absolute inset-0 pointer-events-none" aria-hidden="true">
    <div class="absolute inset-y-[11%] inset-x-[8%]">
        @foreach ([
            'top-0 left-0 border-r-0 border-b-0 rounded-tl-lg',
            'top-0 right-0 border-l-0 border-b-0 rounded-tr-lg',
            'bottom-0 left-0 border-r-0 border-t-0 rounded-bl-lg',
            'bottom-0 right-0 border-l-0 border-t-0 rounded-br-lg',
        ] as $corner)
            <span class="absolute w-7 h-7 border-[2.5px] border-[#2AA7A1] transition-colors duration-300 {{ $corner }}"
                @if ($style) style="{{ $style }}" @endif></span>
        @endforeach
    </div>
</div>
