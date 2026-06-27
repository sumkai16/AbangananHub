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
        <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#286CD2] text-white rounded-lg text-[15px] font-bold shadow-md hover:bg-[#1e5bb8] transition-colors">
            {{ $cta }}
        </a>
    @endif
</div>