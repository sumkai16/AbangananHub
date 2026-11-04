@props(['href', 'label', 'icon', 'active' => false, 'filled' => false])
{{-- One admin sidebar row. Needs the layout's Alpine `sidebarCollapsed`; collapsed, the label becomes a tooltip. --}}
<a href="{{ $href }}" @if($active) aria-current="page" @endif
    {{ $attributes->class([
        'group relative flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors duration-200',
        'bg-white/[0.10] text-white' => $active,
        'text-white/60 hover:bg-white/[0.06] hover:text-white' => ! $active,
    ]) }}>
    @if($filled)
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="shrink-0 {{ $active ? 'text-[#FF8A66]' : '' }}">
            <path d="{{ $icon }}" />
        </svg>
    @else
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" class="shrink-0 {{ $active ? 'text-[#FF8A66]' : '' }}">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
        </svg>
    @endif
    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap flex-1">{{ $label }}</span>
    <span x-show="sidebarCollapsed" x-cloak
        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#060D26] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity z-50">
        {{ $label }}
    </span>
</a>
