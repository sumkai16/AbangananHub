{{--
    The removable "Searching for" chips of the AI search results page, shared by the phone/tablet layout (ai.blade.php)
    and the desktop rail (ai-rail). Reads `chips`, `phase` and `removeChip()` from the aiSearch Alpine scope.
    $stack: one chip per row, full width (the rail). The "+ Add" link is left out there; the rail has the filters.
--}}
@php($stack = $stack ?? false)
<div class="{{ $stack ? 'flex flex-col items-stretch gap-1.5' : 'flex flex-wrap gap-1.5' }}" aria-live="polite">
    <template x-for="chip in chips" :key="chip.label">
        <span class="inline-flex items-center h-8 pl-2.5 pr-1 rounded-full text-[13px] gap-1.5 border {{ $stack ? 'w-full' : '' }}"
            :class="chip.state === 'miss' ? 'bg-[#FEF6E4] border-[#D99A00] text-[#6B4100] font-semibold' : 'bg-white border-[#060D26] text-[#060D26] font-medium'">
            <svg x-show="chip.state === 'miss'" class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l9 16H3z" /><path d="M12 10v4M12 17v.01" /></svg>
            <span x-show="chip.state !== 'miss' && chip.icon === 'peso'" class="font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
            <svg x-show="chip.state !== 'miss' && chip.icon === 'pin'" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
            <svg x-show="chip.state !== 'miss' && chip.icon === 'home'" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V9l8-6 8 6v12z" /></svg>
            <svg x-show="chip.state !== 'miss' && ['amenity', 'rule', 'people'].includes(chip.icon)" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            <span x-text="chip.label" class="{{ $stack ? 'flex-1 min-w-0 truncate' : '' }}"></span>
            <button type="button" x-on:click="removeChip(chip)" :aria-label="'Remove ' + chip.label"
                class="w-7 h-7 grid place-items-center rounded-full text-[#5B6A8E] hover:text-[#060D26] hover:bg-black/5 cursor-pointer">
                <svg class="w-[11px] h-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </span>
    </template>
    <span x-show="phase === 'reading'" class="h-8 w-24 rounded-full bg-[#ECEEF6] animate-pulse" aria-hidden="true"></span>
    @unless($stack)
    <a href="{{ route('properties.index') }}" x-show="chips.length" class="inline-flex items-center h-8 px-3 rounded-full border border-dashed border-[#5B6A8E] text-[13px] text-[#060D26] hover:bg-white" title="Add more with the full filter panel">+ Add</a>
    @endunless
</div>
