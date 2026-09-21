{{--
    Last tile of a swipeable row ("View all areas", "View all popular properties").
    A 2x2 mosaic of real photos from the row sits behind a navy wash, so the tile
    previews what's inside instead of being a blank box with an arrow. Sizing
    (width/height) comes from the caller's `class`, since the two rows differ.

    Props: href, label, sub (small caption), photos (array of image URLs, up to 4).
--}}
@props(['href', 'label', 'sub' => null, 'photos' => []])

@php $photos = array_slice(array_values(array_filter($photos)), 0, 4); @endphp

<a href="{{ $href }}"
    {{ $attributes->class('relative shrink-0 snap-start rounded-2xl overflow-hidden group isolate bg-[#060D26] border border-[#060D26] flex flex-col items-center justify-center text-center gap-2.5 px-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_10px_24px_rgba(6,13,38,0.28)] motion-reduce:hover:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2') }}>
    @if($photos)
        <div class="absolute inset-0 -z-10 grid grid-cols-2 grid-rows-2 gap-0.5" aria-hidden="true">
            @foreach($photos as $photo)
                <img src="{{ \App\Support\Images::resize($photo, 320) }}" alt="" loading="lazy" decoding="async"
                    class="w-full h-full object-cover opacity-60 transition-transform duration-500 group-hover:scale-110 motion-reduce:group-hover:scale-100">
            @endforeach
        </div>
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-[#060D26]/95 via-[#060D26]/70 to-[#060D26]/55" aria-hidden="true"></div>

    <span class="w-11 h-11 rounded-full bg-[#FF8A66] text-[#060D26] flex items-center justify-center shadow-[0_8px_20px_rgba(255,138,102,0.45)] transition-transform duration-300 group-hover:translate-x-1 motion-reduce:group-hover:translate-x-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" /></svg>
    </span>
    <span class="font-jakarta text-[15px] font-extrabold text-white leading-tight text-balance">{{ $label }}</span>
    @if($sub)
        <span class="text-[11.5px] font-medium text-white/75">{{ $sub }}</span>
    @endif
</a>
