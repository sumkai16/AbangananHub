@props(['variant' => 'header'])

{{--
    The Where / Type / Budget search pill, shared by the browse hero and the
    search band on filtered pages so the controller's three field names live in
    one place. `variant` only changes the size and the id suffix (ids must be
    unique when a page ever renders two pills).

    `text-left` is deliberate: the landing hero is `text-center`, and labels and
    inputs inherit that, which used to float each label above its own field.
--}}

@php
    $isHero = $variant === 'hero';
    $pad = $isHero ? 'px-4 py-2 sm:px-6 sm:py-2.5' : 'px-4 py-1.5 sm:px-6 sm:py-2';
    $maxW = $isHero ? 'max-w-[900px]' : 'max-w-[860px]';
    $btn = $isHero ? 'h-10 sm:h-12' : 'h-9 sm:h-11';
    $icon = 'w-[18px] h-[18px] text-[#B35A3D] flex-shrink-0';
    $label = 'text-[11.5px] sm:text-[12px] font-semibold text-[#5B6A8E] truncate cursor-pointer';
    $input = 'p-0 border-none bg-transparent text-[13.5px] sm:text-[14.5px] text-[#060D26] focus:ring-0 placeholder-[#5B6A8E]/70 w-full min-w-0 outline-none';
    $number = $input . ' [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';
@endphp

<form action="{{ route('properties.index') }}" method="GET"
    class="flex items-stretch w-full {{ $maxW }} text-left bg-white rounded-full border border-[#E2E4EC] transition-shadow duration-300 focus-within:border-[#FF8A66] focus-within:ring-4 focus-within:ring-[#FF8A66]/15 {{ $isHero ? 'shadow-[0_18px_50px_rgba(6,13,38,0.28)]' : 'shadow-[0_8px_30px_rgba(0,0,0,0.10)]' }}">

    {{-- Filters the pill doesn't expose are carried through, so running a
         search doesn't silently drop the verified toggle or the chosen sort. --}}
    @if(request()->boolean('verified'))
        <input type="hidden" name="verified" value="1">
    @endif
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    {{-- Where --}}
    <div class="flex-[1.35_1_0%] min-w-0 flex items-center gap-3 {{ $pad }} rounded-l-full hover:bg-[#F7F8FC] focus-within:bg-[#F7F8FC] transition-colors">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <div class="min-w-0 flex-1">
            <label for="search-where-{{ $variant }}" class="block {{ $label }}">Where</label>
            <input type="text" name="location" id="search-where-{{ $variant }}" value="{{ request('location') }}"
                placeholder="Barangay, area, or landmark" class="block mt-0.5 truncate {{ $input }}">
        </div>
    </div>

    <div class="w-px my-2.5 bg-[#E2E4EC]" aria-hidden="true"></div>

    {{-- Type --}}
    <div class="flex-[0.9_1_0%] min-w-0 flex items-center gap-3 {{ $pad }} hover:bg-[#F7F8FC] focus-within:bg-[#F7F8FC] transition-colors">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <div class="min-w-0 flex-1">
            <label id="search-type-label-{{ $variant }}" class="block {{ $label }}">Type</label>
            <x-styled-select name="type" :options="['' => 'Any type', 'Bedspace' => 'Bedspace', 'Room' => 'Room', 'Apartment' => 'Apartment', 'House' => 'House']"
                :selected="request('type', '')" placeholder="Any type" aria-labelledby="search-type-label-{{ $variant }}"
                class="p-0 border-none bg-transparent text-[13.5px] sm:text-[14.5px] text-[#060D26] w-full mt-0.5" />
        </div>
    </div>

    <div class="w-px my-2.5 bg-[#E2E4EC]" aria-hidden="true"></div>

    {{-- Budget + submit --}}
    <div class="flex-[1.5_1_0%] min-w-0 flex items-center gap-2 pl-4 pr-2 sm:pl-6 py-2 rounded-r-full hover:bg-[#F7F8FC] focus-within:bg-[#F7F8FC] transition-colors">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="min-w-0 flex-1">
            <label for="search-budget-min-{{ $variant }}" class="block {{ $label }}">Budget (&#8369; / month)</label>
            <div class="mt-0.5 flex items-center gap-1.5">
                <input type="number" inputmode="numeric" name="price_min" id="search-budget-min-{{ $variant }}" value="{{ request('price_min') }}"
                    placeholder="Min" min="0" aria-label="Minimum budget" class="{{ $number }}">
                <span class="text-[#5B6A8E]/70 shrink-0" aria-hidden="true">&ndash;</span>
                <input type="number" inputmode="numeric" name="price_max" value="{{ request('price_max') }}"
                    placeholder="Max" min="0" aria-label="Maximum budget" class="{{ $number }}">
            </div>
        </div>

        <button type="submit" aria-label="Search properties"
            class="flex-shrink-0 self-center inline-flex items-center justify-center gap-2 rounded-full bg-[#FF8A66] text-[#060D26] font-semibold text-[14px] {{ $btn }} w-10 sm:w-auto sm:px-6 hover:bg-[#E96F4F] active:scale-[0.97] transition-colors duration-200 cursor-pointer">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" class="flex-shrink-0" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span class="hidden sm:inline">Search</span>
        </button>
    </div>

</form>
