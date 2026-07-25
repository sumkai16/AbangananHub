@props(['variant' => 'header'])

{{--
    The Where/Type/Budget search pill. Rendered in two places — the sticky
    header (every public page) and the browse hero (properties.index) — so it
    lives here rather than being copied: a second copy would drift the moment a
    filter is added, and the controller reads all three names.

    Fields, names and submit target are identical across variants, because
    they have to be. `variant="hero"` additionally shows a per-field icon and
    a labelled teal Search button — an intentional one-off exception to the
    locked Soft Coral CTA color, scoped to this bar only. `variant="header"`
    keeps the original icon-less, coral icon-only button unchanged.
--}}

@php
    // The hero pill is only larger from `sm` up. At 375px the three fields plus
    // the button cannot absorb the extra padding, and the whole page picks up a
    // horizontal scroll — so below `sm` both variants use the same tight metrics.
    $isHero = $variant === 'hero';
    $pad = $isHero ? 'px-3 py-2 sm:px-6 sm:py-2.5' : 'px-3 py-2 sm:px-7 sm:py-3';
    $labelSize = 'text-[10px] sm:text-[11px]';
    $inputSize = $isHero ? 'text-[12px] sm:text-[13.5px]' : 'text-[12px] sm:text-[13.5px]';
    $btn = $isHero ? 'w-8 h-8 sm:w-12 sm:h-12' : 'w-8 h-8 sm:w-11 sm:h-11';
    $maxW = $isHero ? 'max-w-[880px]' : 'max-w-[820px]';
    $icon = 'w-4 h-4 sm:w-[18px] sm:h-[18px] text-[#2AA7A1] flex-shrink-0';
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
        class="flex-1 flex items-center gap-2.5 {{ $pad }} border-r border-[#E2E8F0] hover:bg-[#F7FCFC] rounded-l-full transition-colors w-[33%] overflow-hidden">
        @if($isHero)
            <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        @endif
        <div class="flex flex-col justify-center min-w-0 flex-1">
            <label for="search-where-{{ $variant }}"
                class="{{ $labelSize }} font-bold text-[#64748B] tracking-wide uppercase truncate cursor-pointer">Where</label>
            <input type="text" name="location" id="search-where-{{ $variant }}" value="{{ request('location') }}"
                placeholder="Barangay, area, or landmark"
                class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 placeholder-[#94A3B8] w-full outline-none mt-0.5 truncate">
        </div>
    </div>

    <div
        class="flex-1 flex items-center gap-2.5 {{ $pad }} border-r border-[#E2E8F0] hover:bg-[#F7FCFC] transition-colors w-[33%]">
        @if($isHero)
            <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        @endif
        <div class="flex flex-col justify-center min-w-0 flex-1">
            @if($isHero)
                {{-- Header's search pill sits inside an overflow-hidden collapse
                     wrapper (see #header-search-expanded), which would clip this
                     dropdown's open panel — so only the hero gets the styled
                     version. Header keeps the native select below. --}}
                <label id="search-type-label-{{ $variant }}"
                    class="{{ $labelSize }} font-bold text-[#64748B] tracking-wide uppercase truncate cursor-pointer">Type</label>
                <x-styled-select name="type" :options="['' => 'Any type', 'Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House']"
                    :selected="request('type', '')" placeholder="Any type" aria-labelledby="search-type-label-{{ $variant }}"
                    class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] w-full mt-0.5" />
            @else
                <label for="search-type-{{ $variant }}"
                    class="{{ $labelSize }} font-bold text-[#64748B] tracking-wide uppercase truncate cursor-pointer">Type</label>
                <select name="type" id="search-type-{{ $variant }}"
                    class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 w-full outline-none appearance-none cursor-pointer mt-0.5 truncate">
                    <option value="">Any type</option>
                    @foreach(['Bedspace', 'Room', 'Apartment', 'House'] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    <div
        class="flex-1 flex items-center justify-between {{ $isHero ? 'pl-3 pr-2 sm:pl-6 sm:pr-2 py-2 sm:py-2.5' : 'pl-3 pr-2 sm:pl-7 sm:pr-2 py-2' }} hover:bg-[#F7FCFC] rounded-r-full transition-colors w-[33%] overflow-hidden">
        <div class="flex items-center gap-2.5 min-w-0 flex-1">
            @if($isHero)
                <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            @endif
            <div class="flex flex-col justify-center min-w-0 flex-1">
                <label for="search-budget-{{ $variant }}"
                    class="{{ $labelSize }} font-bold text-[#64748B] tracking-wide uppercase truncate cursor-pointer">Budget</label>
                <input type="number" name="price_max" id="search-budget-{{ $variant }}" value="{{ request('price_max') }}"
                    placeholder="Max" min="0"
                    class="p-0 border-none bg-transparent {{ $inputSize }} text-[#1F2937] focus:ring-0 placeholder-[#94A3B8] w-full outline-none mt-0.5 truncate [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
            </div>
        </div>

        @if($isHero)
            <button type="submit" aria-label="Search properties"
                class="flex-shrink-0 flex items-center gap-1.5 rounded-full bg-[#2AA7A1] text-white font-semibold text-[13px] sm:text-[14px] px-4 sm:px-5 h-9 sm:h-11 hover:brightness-95 active:scale-[0.97] transition-all ml-1 sm:ml-3 shadow-md cursor-pointer">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                    class="flex-shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span class="hidden sm:inline">Search</span>
            </button>
        @else
            <button type="submit" aria-label="Search properties"
                class="{{ $btn }} rounded-full bg-[#FF8A65] flex items-center justify-center text-white flex-shrink-0 hover:brightness-95 transition-all ml-1 sm:ml-3 shadow-md cursor-pointer">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                    class="sm:w-[17px] sm:h-[17px]" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
        @endif
    </div>

</form>
