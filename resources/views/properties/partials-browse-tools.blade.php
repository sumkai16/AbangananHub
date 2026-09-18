@php $toolFilterCount = count((array) request('amenities', [])); @endphp
<div x-data="{ mapVisible: false }" @browse-map-state.window="mapVisible = $event.detail" class="flex items-center gap-2">
    <button type="button" @click="$dispatch('browse-toggle-map')"
        class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-sm hover:border-[#FF8A66] transition-colors cursor-pointer">
        <svg class="w-4 h-4 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
        </svg>
        <span x-text="mapVisible ? 'Hide map' : 'Show map'">Show map</span>
    </button>
    <button type="button" @click="$dispatch('browse-open-filters')"
        class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold shadow-sm hover:border-[#FF8A66] transition-colors cursor-pointer">
        <svg class="w-4 h-4 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
        </svg>
        Filters
        @if($toolFilterCount > 0)
            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[#FF8A66] text-[#060D26] text-[10px] font-bold">{{ $toolFilterCount }}</span>
        @endif
    </button>
</div>
