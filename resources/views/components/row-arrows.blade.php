{{-- Prev/next arrows overlaid on the edges of a swipeable row. Must sit inside a `relative`
     wrapper that lives in an x-data scope providing canPrev, canNext and scrollByPage(dir).
     $top positions them vertically (default: centre of the row). Touch devices swipe instead.
     Colours are inline on purpose: they sit on photos, so they stay white in dark mode too
     (the html.dark remap of bg-white / text-[#060D26] would turn them navy and lose contrast). --}}
@props(['top' => 'top-1/2'])

@foreach([['dir' => -1, 'side' => 'left-2', 'flag' => 'canPrev', 'label' => 'Scroll left', 'd' => 'M15 19l-7-7 7-7'], ['dir' => 1, 'side' => 'right-2', 'flag' => 'canNext', 'label' => 'Scroll right', 'd' => 'M9 5l7 7-7 7']] as $a)
    <button type="button" @click="scrollByPage({{ $a['dir'] }})" aria-label="{{ $a['label'] }}"
        x-show="{{ $a['flag'] }}" x-cloak
        x-transition.opacity.duration.150ms
        style="background-color:#fff;color:#060D26"
        class="hidden sm:flex absolute {{ $top }} {{ $a['side'] }} -translate-y-1/2 z-10 w-10 h-10 rounded-full items-center justify-center shadow-[0_4px_14px_rgba(6,13,38,0.22)] ring-1 ring-[#E2E4EC] transition-transform duration-200 hover:scale-105 motion-reduce:hover:scale-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['d'] }}" /></svg>
    </button>
@endforeach
