@props([
    'label',
    'value',
    'sub' => null,
    'valueColor' => '#060D26',
    'iconBg' => '#ECEEF6',
    'percent' => null,
    'barColor' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-4 {{ $href ? 'block transition-all duration-200 hover:shadow-lg' : '' }} {{ $attributes->get('class') }}">
    <div class="flex items-center justify-between mb-3">
        <span class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">{{ $label }}</span>
        @isset($icon)
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: {{ $iconBg }};">
                {{ $icon }}
            </div>
        @endisset
    </div>
    <span class="text-2xl font-extrabold" style="color: {{ $valueColor }};">{{ $value }}</span>
    @if($percent !== null)
        <div class="w-full h-1.5 rounded-full bg-[#E2E4EC] mt-2.5 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-300" style="width: {{ $percent }}%; background-color: {{ $barColor ?? $valueColor }};"></div>
        </div>
    @endif
    @if($sub)
        <p class="text-[11px] text-[#5B6A8E] {{ $percent !== null ? 'mt-1.5' : 'mt-1' }}">{{ $sub }}</p>
    @endif
</{{ $tag }}>
