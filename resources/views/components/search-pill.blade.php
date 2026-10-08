@props(['variant' => 'header'])

{{--
    The Where / Type search pill (budget lives in the Filters modal, <x-budget-filter>), shared by the browse hero and the
    search band on filtered pages so the controller's field names live in
    one place. The header variant has no Type field (the category chips under it
    are the type filter). `variant` only changes the size and the id suffix (ids must be
    unique when a page ever renders two pills).

    `text-left` is deliberate: the landing hero is `text-center`, and labels and
    inputs inherit that, which used to float each label above its own field.
--}}

@php
    $locations = \App\Models\Property::searchLocations();
    $isHero = $variant === 'hero';
    $pad = $isHero ? 'px-4 py-2 sm:px-6 sm:py-2.5' : 'px-4 py-1.5 sm:px-6 sm:py-2';
    $maxW = $isHero ? 'max-w-[900px]' : 'max-w-[860px]';
    $btn = $isHero ? 'h-10 sm:h-12' : 'h-9 sm:h-11';
    $icon = 'w-[18px] h-[18px] text-[#B35A3D] flex-shrink-0';
    $label = 'text-[11.5px] sm:text-[12px] font-semibold text-[#5B6A8E] truncate cursor-pointer';
    $input = 'p-0 border-none bg-transparent text-[13.5px] sm:text-[14.5px] text-[#060D26] focus:ring-0 placeholder-[#5B6A8E]/70 w-full min-w-0 outline-none';
@endphp

<form action="{{ route('properties.index') }}" method="GET"
    class="relative flex items-stretch w-full {{ $maxW }} text-left bg-white rounded-full border border-[#E2E4EC] transition-shadow duration-300 focus-within:border-[#FF8A66] focus-within:ring-4 focus-within:ring-[#FF8A66]/15 {{ $isHero ? 'shadow-[0_18px_50px_rgba(6,13,38,0.28)]' : 'shadow-[0_8px_30px_rgba(0,0,0,0.10)]' }}">

    {{-- Filters the pill doesn't expose are carried through, so running a
         search doesn't silently drop the verified toggle or the chosen sort. --}}
    @if(request()->boolean('verified'))
        <input type="hidden" name="verified" value="1">
    @endif
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif
    @foreach(['price_min', 'price_max'] as $priceKey)
        @if(request()->filled($priceKey))
            <input type="hidden" name="{{ $priceKey }}" value="{{ request($priceKey) }}">
        @endif
    @endforeach

    {{-- Where --}}
    <div class="flex-[1.35_1_0%] min-w-0 flex items-center gap-3 {{ $pad }} rounded-l-full hover:bg-[#ECEEF6] focus-within:bg-[#ECEEF6] transition-colors">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        {{-- Suggestions come from the areas that have bookable listings (Property::searchLocations), matched as
             you type: prefix first, then any word, then anywhere. Typing "car" offers Carcar City. Picking one
             fills the box; Search still does the searching. --}}
        <div class="min-w-0 flex-1"
            x-data="{
                q: @js((string) request('location', '')),
                open: false,
                active: -1,
                items: @js($locations),
                get needle() { return this.q.trim().toLowerCase(); },
                get results() {
                    const n = this.needle;
                    if (! n) return [];
                    const rank = (i) => {
                        const l = i.label.toLowerCase(), v = i.value.toLowerCase();
                        if (l.startsWith(n)) return 0;
                        if (v.startsWith(n)) return 1;
                        if (l.split(/[\s,]+/).some((w) => w.startsWith(n))) return 2;
                        return v.includes(n) ? 3 : 9;
                    };
                    return this.items.map((i) => ({ i, r: rank(i) })).filter((x) => x.r < 9)
                        .sort((a, b) => a.r - b.r || (a.i.type === b.i.type ? 0 : a.i.type === 'city' ? -1 : 1))
                        .slice(0, 6).map((x) => x.i);
                },
                parts(t) {
                    const n = this.needle, at = t.toLowerCase().indexOf(n);
                    return (! n || at < 0) ? [t, '', ''] : [t.slice(0, at), t.slice(at, at + n.length), t.slice(at + n.length)];
                },
                move(d) {
                    if (! this.results.length) return;
                    this.open = true;
                    this.active = (this.active + d + this.results.length) % this.results.length;
                },
                pick(i) { this.q = i.value; this.open = false; this.active = -1; },
            }"
            @click.outside="open = false">
            <label for="search-where-{{ $variant }}" class="block {{ $label }}">Where</label>
            <input type="text" name="location" id="search-where-{{ $variant }}" x-model="q" autocomplete="off"
                placeholder="Barangay, area, or landmark" class="block mt-0.5 truncate {{ $input }}"
                role="combobox" aria-autocomplete="list" aria-controls="where-suggest-{{ $variant }}" :aria-expanded="open && results.length > 0"
                @input="open = true; active = -1" @focus="open = true"
                @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.escape="open = false"
                @keydown.enter="if (open && active >= 0) { $event.preventDefault(); pick(results[active]); }">

            <ul id="where-suggest-{{ $variant }}" role="listbox" x-show="open && results.length" x-cloak
                class="absolute left-0 top-[calc(100%+8px)] z-50 w-full max-w-[440px] overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white py-1.5 shadow-[0_16px_48px_-12px_rgba(6,13,38,0.25)]">
                <template x-for="(item, i) in results" :key="item.value">
                    <li role="option" :aria-selected="active === i">
                        <button type="button" @mousedown.prevent="pick(item)" @mouseenter="active = i"
                            :class="active === i ? 'bg-[#ECEEF6]' : ''"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors duration-150 cursor-pointer">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#ECEEF6] text-[#B35A3D]" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-[14px] font-medium text-[#060D26]">
                                    <span x-text="parts(item.label)[0]"></span><span class="font-bold" x-text="parts(item.label)[1]"></span><span x-text="parts(item.label)[2]"></span>
                                </span>
                                <span class="block truncate text-[12px] text-[#5B6A8E]" x-text="item.sub"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Type: hero only. On the Browse page the category chips right below this pill already filter by
         type, so a second dropdown just offered the same choice twice. The chosen type still rides along
         as a hidden field, so running a search doesn't drop the chip that's selected. --}}
    @if($isHero)
        <div class="w-px my-2.5 bg-[#E2E4EC]" aria-hidden="true"></div>

        <div class="flex-[0.9_1_0%] min-w-0 flex items-center gap-3 {{ $pad }} hover:bg-[#ECEEF6] focus-within:bg-[#ECEEF6] transition-colors">
            <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <div class="min-w-0 flex-1">
                <label id="search-type-label-{{ $variant }}" class="block {{ $label }}">Type</label>
                <x-styled-select name="type" :options="['' => 'Any type', 'Apartment' => 'Apartment', 'Condominium' => 'Condominium', 'House' => 'House', 'Boarding House' => 'Boarding House', 'Bedspace' => 'Bedspace']"
                    :selected="request('type', '')" placeholder="Any type" aria-labelledby="search-type-label-{{ $variant }}"
                    class="p-0 border-none bg-transparent text-[13.5px] sm:text-[14.5px] text-[#060D26] w-full mt-0.5" />
            </div>
        </div>
    @elseif(request('type'))
        <input type="hidden" name="type" value="{{ request('type') }}">
    @endif

    {{-- Search. The budget moved into the Filters modal (<x-budget-filter>); a price chosen there rides along as hidden fields above. --}}
    <div class="flex flex-shrink-0 items-center pl-2 pr-2 py-2">
        <button type="submit" aria-label="Search properties"
            class="flex-shrink-0 self-center inline-flex items-center justify-center gap-2 rounded-full bg-[#FF8A66] text-[#060D26] font-semibold text-[14px] {{ $btn }} w-10 sm:w-auto sm:px-6 hover:bg-[#E96F4F] active:scale-[0.97] transition-colors duration-200 cursor-pointer">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" class="flex-shrink-0" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span class="hidden sm:inline">Search</span>
        </button>
    </div>

</form>
