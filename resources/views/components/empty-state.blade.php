@props([
    'title',
    'message',
    'href'  => null,
    'cta'   => 'Get started',
])

<div class="text-center py-16 px-8 border-2 border-dashed border-[#E2E8F0] rounded-[24px] bg-[#F7FCFC]">
    <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-[#94A3B8] shadow-sm border border-[#E2E8F0]">
        {{ $icon ?? '' }}
    </div>
    <div class="text-[16px] font-bold text-[#1F2937] mb-2">{{ $title }}</div>
    <div class="text-[14px] text-[#64748B] mb-6">{{ $message }}</div>
    @if($href)
        <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#2AA7A1] text-white rounded-lg text-[15px] font-bold shadow-md hover:brightness-95 transition-colors">
            {{ $cta }}
        </a>
    @endif
</div>