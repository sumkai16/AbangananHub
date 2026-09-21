@props(['variant' => 'header'])

{{--
    The Where / Type / Budget search pill, shared by the browse hero and the
    search band on filtered pages so the controller's three field names live in
    one place. The header variant has no Type field (the category chips under it
    are the type filter). `variant` only changes the size and the id suffix (ids must be
    unique when a page ever renders two pills).

    `text-left` is deliberate: the landing hero is `text-center`, and labels and
    inputs inherit that, which used to float each label above its own field.
--}}

@php
    $locations = \App\Models\Property::searchLocations();
    $bands = \App\Models\Property::budgetBands();
    $histogram = \App\Models\Property::budgetHistogram();
    // Native range thumbs can't be styled with plain classes, so the variants below style the thumb.
    $thumb = 'absolute inset-0 h-6 w-full appearance-none bg-transparent pointer-events-none focus:outline-none [&::-moz-range-track]:bg-transparent'
        . ' [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-6 [&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-[3px] [&::-webkit-slider-thumb]:border-solid [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[#FF8A66] [&::-webkit-slider-thumb]:shadow-[0_1px_5px_rgba(6,13,38,0.4)] [&::-webkit-slider-thumb]:cursor-grab'
        . ' [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-6 [&::-moz-range-thumb]:w-6 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-[3px] [&::-moz-range-thumb]:border-solid [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[#FF8A66] [&::-moz-range-thumb]:shadow-[0_1px_5px_rgba(6,13,38,0.4)] [&::-moz-range-thumb]:cursor-grab'
        . ' focus-visible:[&::-webkit-slider-thumb]:ring-2 focus-visible:[&::-webkit-slider-thumb]:ring-[#FF8A66]/40';
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
    class="relative flex items-stretch w-full {{ $maxW }} text-left bg-white rounded-full border border-[#E2E4EC] transition-shadow duration-300 focus-within:border-[#FF8A66] focus-within:ring-4 focus-within:ring-[#FF8A66]/15 {{ $isHero ? 'shadow-[0_18px_50px_rgba(6,13,38,0.28)]' : 'shadow-[0_8px_30px_rgba(0,0,0,0.10)]' }}">

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

    <div class="w-px my-2.5 bg-[#E2E4EC]" aria-hidden="true"></div>

    {{-- Type: hero only. On the Browse page the category chips right below this pill already filter by
         type, so a second dropdown just offered the same choice twice. The chosen type still rides along
         as a hidden field, so running a search doesn't drop the chip that's selected. --}}
    @if($isHero)
        <div class="flex-[0.9_1_0%] min-w-0 flex items-center gap-3 {{ $pad }} hover:bg-[#F7F8FC] focus-within:bg-[#F7F8FC] transition-colors">
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


        <div class="w-px my-2.5 bg-[#E2E4EC]" aria-hidden="true"></div>
    @elseif(request('type'))
        <input type="hidden" name="type" value="{{ request('type') }}">
    @endif

    {{-- Budget + submit --}}
    <div class="flex-[1.5_1_0%] min-w-0 flex items-center gap-2 pl-4 pr-2 sm:pl-6 py-2 rounded-r-full hover:bg-[#F7F8FC] focus-within:bg-[#F7F8FC] transition-colors">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{-- Budget: a summary button that opens a popover with quick price ranges (with live listing counts),
             a two-handle slider, and typed Min/Max as the exact fallback. The two number inputs are the real form
             fields (price_min / price_max); the slider, chips and inputs all edit the same two values. The slider
             moves along price stops, not linearly, because most rents sit low (median ~₱3,000, max ~₱22,000). --}}
        <div class="min-w-0 flex-1"
            x-data="{
                min: @js((string) request('price_min', '')),
                max: @js((string) request('price_max', '')),
                open: false,
                bands: @js($bands),
                stops: @js(\App\Models\Property::SEARCH_PRICE_STOPS),
                histogram: @js($histogram),
                get peak() { return Math.max(1, ...this.histogram); },
                barTitle(i) {
                    const f = (v) => '₱' + v.toLocaleString('en-PH');
                    const c = this.histogram[i];
                    return c + (c === 1 ? ' listing' : ' listings') + ' · ' + f(this.stops[i]) + (i === this.histogram.length - 1 ? ' & up' : ' – ' + f(this.stops[i + 1]));
                },
                get last() { return this.stops.length - 1; },
                near(v) { let best = 1, gap = Infinity; for (let i = 1; i < this.last; i++) { const d = Math.abs(this.stops[i] - v); if (d < gap) { gap = d; best = i; } } return best; },
                get lo() { return this.min === '' ? 0 : this.near(+this.min); },
                get hi() { return this.max === '' ? this.last : this.near(+this.max); },
                setLo(e) { const i = Math.min(+e.target.value, this.hi - 1); e.target.value = i; this.min = i <= 0 ? '' : String(this.stops[i]); },
                setHi(e) { const i = Math.max(+e.target.value, this.lo + 1); e.target.value = i; this.max = i >= this.last ? '' : String(this.stops[i]); },
                pick(b) { this.min = b.min === null ? '' : String(b.min); this.max = b.max === null ? '' : String(b.max); },
                isPicked(b) { return this.min === (b.min === null ? '' : String(b.min)) && this.max === (b.max === null ? '' : String(b.max)); },
                tidy() { if (this.min !== '' && this.max !== '' && +this.min > +this.max) { [this.min, this.max] = [this.max, this.min]; } },
                clear() { this.min = ''; this.max = ''; },
                get any() { return this.min === '' && this.max === ''; },
                get summary() {
                    const f = (v) => '₱' + Number(v).toLocaleString('en-PH');
                    if (this.any) return 'Any budget';
                    if (this.min !== '' && this.max !== '') return f(this.min) + ' – ' + f(this.max);
                    return this.min !== '' ? f(this.min) + ' & up' : 'Up to ' + f(this.max);
                },
            }"
            @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="dialog" aria-controls="budget-panel-{{ $variant }}"
                class="block w-full text-left cursor-pointer focus:outline-none">
                <span class="block {{ $label }}">Budget (&#8369; / month)</span>
                <span class="mt-0.5 block truncate text-[13.5px] sm:text-[14.5px]" :class="any ? 'text-[#5B6A8E]/70' : 'text-[#060D26]'" x-text="summary"></span>
            </button>

            <div id="budget-panel-{{ $variant }}" role="dialog" aria-label="Budget range" x-show="open" x-cloak
                class="absolute right-0 top-[calc(100%+8px)] z-50 w-full sm:w-[400px] rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-5 shadow-[0_16px_48px_-12px_rgba(6,13,38,0.25)] text-left">

                <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Popular ranges</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <template x-for="b in bands" :key="b.label">
                        <button type="button" @click="pick(b)" :disabled="b.count === 0 && ! isPicked(b)" :aria-pressed="isPicked(b)"
                            :class="isPicked(b) ? 'border-[#FF8A66] bg-[#ECEEF6]' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] disabled:opacity-50 disabled:hover:bg-white'"
                            class="flex flex-col items-start rounded-xl border px-3 py-2 text-left transition-colors duration-200 cursor-pointer disabled:cursor-not-allowed">
                            <span class="text-[13px] font-semibold text-[#060D26]" x-text="b.label"></span>
                            <span class="text-[11.5px] text-[#5B6A8E] tabular-nums" x-text="b.count + (b.count === 1 ? ' listing' : ' listings')"></span>
                        </button>
                    </template>
                </div>

                <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Or set your own</p>
                {{-- Histogram: one bar per slider step, taller = more listings there. Bars inside the chosen range are
                     coral. The rail and the bars are inset by half a handle (12px) so they line up with where the
                     handles actually travel. --}}
                <div class="mt-3">
                    <div class="mx-3 flex h-14 items-end gap-px" aria-hidden="true">
                        <template x-for="(c, i) in histogram" :key="i">
                            <div class="flex-1 rounded-t-[3px] transition-colors duration-200"
                                :title="barTitle(i)"
                                :class="i >= lo && i < hi ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]'"
                                :style="`height: ${c === 0 ? '2px' : Math.max(c / peak * 100, 10) + '%'}`"></div>
                        </template>
                    </div>
                    <div class="relative h-6">
                        <div class="absolute inset-x-3 top-1/2 h-1.5 -translate-y-1/2">
                            <div class="absolute inset-0 rounded-full bg-[#E2E4EC]"></div>
                            <div class="absolute inset-y-0 rounded-full bg-[#FF8A66]" :style="`left: ${lo / last * 100}%; right: ${100 - hi / last * 100}%`"></div>
                        </div>
                        <input type="range" min="0" :max="last" step="1" :value="lo" @input="setLo($event)" aria-label="Minimum budget" class="{{ $thumb }}">
                        <input type="range" min="0" :max="last" step="1" :value="hi" @input="setHi($event)" aria-label="Maximum budget" class="{{ $thumb }}">
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="block text-[11.5px] font-semibold text-[#5B6A8E]">Min</span>
                        <span class="mt-1 flex items-center rounded-xl border border-[#E2E4EC] bg-white px-3 h-10 focus-within:border-[#FF8A66] focus-within:ring-2 focus-within:ring-[#FF8A66]/25 transition-all duration-200">
                            <span class="text-[13px] text-[#5B6A8E]" aria-hidden="true">&#8369;</span>
                            <input type="number" inputmode="numeric" name="price_min" min="0" step="100" x-model="min" @change="tidy()" placeholder="No min" id="search-budget-min-{{ $variant }}"
                                class="{{ $number }} ml-1.5">
                        </span>
                    </label>
                    <label class="block">
                        <span class="block text-[11.5px] font-semibold text-[#5B6A8E]">Max</span>
                        <span class="mt-1 flex items-center rounded-xl border border-[#E2E4EC] bg-white px-3 h-10 focus-within:border-[#FF8A66] focus-within:ring-2 focus-within:ring-[#FF8A66]/25 transition-all duration-200">
                            <span class="text-[13px] text-[#5B6A8E]" aria-hidden="true">&#8369;</span>
                            <input type="number" inputmode="numeric" name="price_max" min="0" step="100" x-model="max" @change="tidy()" placeholder="No max"
                                class="{{ $number }} ml-1.5">
                        </span>
                    </label>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <button type="button" @click="clear()" :disabled="any"
                        class="text-[13px] font-semibold text-[#060D26] underline underline-offset-2 hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed">Clear</button>
                    <button type="button" @click="open = false"
                        class="h-10 px-5 rounded-full border border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] text-[#060D26] text-[13px] font-semibold transition-colors duration-200 cursor-pointer">Done</button>
                </div>
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
