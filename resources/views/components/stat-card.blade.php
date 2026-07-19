@props([
    'label',
    'value',
    'sub',
    'color',
    'bgColor',
    'size' => 'default',
])

@if($size === 'hero')
<div class="bg-white rounded-2xl p-6 sm:p-8 shadow-[0_2px_12px_rgba(0,0,0,0.04)] border border-gray-100 relative overflow-hidden">
    <div class="flex items-center gap-5">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background-color: {{ $bgColor }};">
            {{ $icon }}
        </div>
        <div>
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ $label }}</div>
            <div class="text-4xl sm:text-5xl font-black leading-none" style="color: {{ $color }};">{{ $value }}</div>
            <div class="text-[13px] text-gray-400 font-medium mt-1">{{ $sub }}</div>
        </div>
    </div>
</div>
@else
<div class="bg-white rounded-2xl p-4 sm:p-5 shadow-[0_2px_8px_rgba(0,0,0,0.03)] border border-gray-100 relative overflow-hidden">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background-color: {{ $bgColor }};">
        {{ $icon }}
    </div>
    <div>
        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ $label }}</div>
        <div class="text-2xl sm:text-3xl font-black leading-none mb-1" style="color: {{ $color }};">{{ $value }}</div>
        <div class="text-[12px] text-gray-400 font-medium">{{ $sub }}</div>
    </div>
</div>
@endif