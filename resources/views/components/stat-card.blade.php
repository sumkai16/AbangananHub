@props([
    'label',
    'value',
    'sub',
    'color',
    'bgColor',
])

<div class="bg-white rounded-[24px] p-4 sm:p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_32px_rgba(0,0,0,0.1)] transition-all relative overflow-hidden group">
    <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full opacity-80 group-hover:scale-110 transition-transform" style="background-color: {{ $bgColor }};"></div>
    <div class="w-12 h-12 rounded-[14px] flex items-center justify-center mb-6 relative z-10" style="background-color: {{ $bgColor }};">
        {{ $icon }}
    </div>
    <div class="relative z-10">
        <div class="text-[12px] font-bold text-gray-500 uppercase tracking-widest mb-2">{{ $label }}</div>
        <div class="text-3xl sm:text-4xl font-black leading-none mb-2" style="color: {{ $color }};">{{ $value }}</div>
        <div class="text-[13px] text-gray-400 font-medium">{{ $sub }}</div>
    </div>
</div>