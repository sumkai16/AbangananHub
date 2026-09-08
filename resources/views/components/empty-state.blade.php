@props([
    'title',
    'message',
    'href'  => null,
    'cta'   => 'Get started',
])

<div class="text-center py-16 px-8 border-2 border-dashed border-[#E2E4EC] rounded-[24px] bg-[#F7F8FC]">
    <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-[#94A3B8] shadow-sm border border-[#E2E4EC]">
        {{ $icon ?? '' }}
    </div>
    <div class="text-[16px] font-bold text-[#060D26] mb-2">{{ $title }}</div>
    <div class="text-[14px] text-[#5B6A8E] mb-6">{{ $message }}</div>
    @if($href)
        <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#060D26] text-[#F7F4ED] rounded-lg text-[15px] font-bold shadow-md hover:brightness-95 transition-colors">
            {{ $cta }}
        </a>
    @endif
</div>