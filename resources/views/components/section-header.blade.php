@props([
    'title',
    'sub' => null,
    'href' => null,
    'cta' => 'View all',
])

<div class="flex items-end justify-between mb-6">
    <div>
        <h2 class="text-[22px] font-extrabold text-[#1A1A2E] tracking-tight">{{ $title }}</h2>
    @if($sub)

           <p class="text-[14px] text-gray-500 mt-1">{{ $sub }}</p>
    @endif
    </div>
    @if($href)
<<<<<<< HEAD
        <a href="{{ $href }}" class="text-[14px] font-semibold text-[#FF8A65] px-4 py-2 border border-blue-200 rounded-full hover:bg-blue-50 transition-colors">
=======
        <a href="{{ $href }}" class="text-[14px] font-semibold text-[#156F8C] px-4 py-2 border border-blue-200 rounded-full hover:bg-blue-50 transition-colors">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
            {{ $cta }}
        </a>
    @endif
</div>