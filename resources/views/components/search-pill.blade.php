@props(['variant' => 'header'])

{{--
    The Where/Type/Budget search pill. Rendered in two places — the sticky
    header (every public page) and the browse hero (properties.index) — so it
    lives here rather than being copied: a second copy would drift the moment a
    filter is added, and the controller reads all three names.

    `variant` only changes scale and max width. The fields, names and submit
    target are identical, because they have to be.
--}}

@php
    // The hero pill is only larger from `sm` up. At 375px the three fields plus
    // the button cannot absorb the extra padding, and the whole page picks up a
    // horizontal scroll — so below `sm` both variants use the same tight metrics.
    $isHero = $variant === 'hero';
    $pad = $isHero ? 'px-3 py-2 sm:px-8 sm:py-4' : 'px-3 py-2 sm:px-7 sm:py-3';
    $labelSize = 'text-[10px] sm:text-[11px]';
    $inputSize = $isHero ? 'text-[12px] sm:text-[14px]' : 'text-[12px] sm:text-[13.5px]';
    $btn = $isHero ? 'w-8 h-8 sm:w-12 sm:h-12' : 'w-8 h-8 sm:w-11 sm:h-11';
    $maxW = $isHero ? 'max-w-[880px]' : 'max-w-[820px]';
@endphp

<form action="{{ route('properties.index') }}" method="GET"
    class="flex items-center w-full {{ $maxW }} bg-white rounded-full border border-[#E2E8F0] transition-all duration-300 {{ $isHero ? 'shadow-[0_18px_50px_rgba(15,23,42,0.28)]' : 'shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:shadow-[0_12px_40px_rgb(0,0,0,0.12)]' }}">

    {{-- Filters the pill doesn't expose are carried through, so running a
         search doesn't silently drop the verified toggle or the chosen sort. --}}
    @if(request()->boolean('verified'))
        <input type="hidden" name="verified" value="1">
    @endif
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    <div
        class="flex-1 flex flex-col justify-center {{ $pad }} border-r border-[#E2E8F0] hover:bg-[#F7FCFC] rounded-l-full transition-colors w-[33%] overflow-hidden">
        <label for="search-where-{{ $variant }}"
            class="{{ $labelSize }} font-bold text-[#1F2937] tracking-wide uppercase truncate cursor-pointer">Where</label>
        <input type="text" name="location" id="search-where-{{ $variant }}" value="{{ request('location') }}"
            placeholder="Barangay, area, or landmark..."
            class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 placeholder-[#94A3B8] w-full outline-none mt-0.5 truncate">
    </div>

    <div
        class="flex-1 flex flex-col justify-center {{ $pad }} border-r border-[#E2E8F0] hover:bg-[#F7FCFC] transition-colors w-[33%] overflow-hidden">
        <label for="search-type-{{ $variant }}"
            class="{{ $labelSize }} font-bold text-[#1F2937] tracking-wide uppercase truncate cursor-pointer">Type</label>
        <select name="type" id="search-type-{{ $variant }}"
            class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 w-full outline-none appearance-none cursor-pointer mt-0.5 truncate">
            <option value="">Any type</option>
            @foreach(['Bedspace', 'Room', 'Apartment', 'House'] as $type)
                <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>
    </div>

    <div
        class="flex-1 flex items-center justify-between {{ $isHero ? 'pl-3 pr-2 sm:pl-8 sm:pr-2 py-2 sm:py-3' : 'pl-3 pr-2 sm:pl-7 sm:pr-2 py-2' }} hover:bg-[#F7FCFC] rounded-r-full transition-colors w-[33%] overflow-hidden">
        <div class="flex flex-col justify-center w-[calc(100%-36px)] sm:w-auto overflow-hidden">
            <label for="search-budget-{{ $variant }}"
                class="{{ $labelSize }} font-bold text-[#1F2937] tracking-wide uppercase truncate cursor-pointer">Budget</label>
            <input type="number" name="price_max" id="search-budget-{{ $variant }}" value="{{ request('price_max') }}"
                placeholder="Max ₱" min="0"
                class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 placeholder-[#94A3B8] w-full outline-none mt-0.5 truncate">
        </div>
        <button type="submit" aria-label="Search properties"
            class="{{ $btn }} rounded-full bg-[#FF8A65] flex items-center justify-center text-white flex-shrink-0 hover:brightness-95 transition-all ml-1 sm:ml-3 shadow-md cursor-pointer">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                class="sm:w-[17px] sm:h-[17px]" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </button>
    </div>

</form>
