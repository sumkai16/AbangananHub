@props([
    'title',
    'message',
    'href'  => null,
    'cta'   => 'Get started',
])

<div class="text-center py-16 px-8 border-2 border-dashed border-gray-200 rounded-[24px] bg-gray-50">
    <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-gray-400 shadow-sm border border-gray-100">
        {{ $icon ?? '' }}
    </div>
    <div class="text-[16px] font-bold text-gray-800 mb-2">{{ $title }}</div>
    <div class="text-[14px] text-gray-500 mb-6">{{ $message }}</div>
    @if($href)
<<<<<<< HEAD
        <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#FF8A65] text-white rounded-lg text-[15px] font-bold shadow-md hover:bg-[#1e5bb8] transition-colors">
=======
        <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#2AA7A1] text-white rounded-lg text-[15px] font-bold shadow-md hover:brightness-95 transition-colors">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
            {{ $cta }}
        </a>
    @endif
</div>