@props(['href', 'label', 'icon', 'active' => false, 'filled' => false, 'badge' => 0, 'badgeAttr' => ''])
{{-- One admin sidebar row. Needs the layout's Alpine `sidebarCollapsed`; collapsed, the label becomes a tooltip and
     a waiting count moves onto the icon. `badge` is how many things wait on the admin here (0 shows nothing). --}}
<a href="{{ $href }}" @if($active) aria-current="page" @endif data-page-swap="nav" @click="sidebarOpen = false"
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
    @if($badge > 0)
        <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
            class="min-w-[20px] h-5 px-1.5 rounded-full bg-[#FF8A66] text-[#060D26] text-[11px] font-bold flex items-center justify-center tabular-nums">
            <span class="sr-only">{{ $badge }} waiting</span><span aria-hidden="true" {{ $badgeAttr }}>{{ $badge > 99 ? '99+' : $badge }}</span>
        </span>
        <span x-show="sidebarCollapsed" x-cloak aria-hidden="true"
            class="absolute top-0.5 right-1 min-w-[16px] h-4 px-1 rounded-full bg-[#FF8A66] text-[#060D26] text-[10px] font-bold flex items-center justify-center tabular-nums border-2 border-[#060D26] box-content">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
    <span x-show="sidebarCollapsed" x-cloak
        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#060D26] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity z-50">
        {{ $label }}{{ $badge > 0 ? " ($badge)" : '' }}
    </span>
</a>
