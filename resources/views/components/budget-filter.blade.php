{{--
    Monthly budget card for the Filters modal. Moved out of the search pill so the pill is just
    Where / Type / Search and every price control lives with the other filters.

    Nothing here is hardcoded to a price: the quick ranges are percentile breaks of the rents
    available right now, the slider's steps stop at the dearest available unit, the histogram
    counts real listings per step, and "Typical rent" is the live median. All of it comes from
    Property::budgetBands() / budgetStops() / budgetHistogram() / budgetMedian().

    The two number inputs are the real form fields (price_min / price_max); the chips, the
    slider and the typed values all edit the same two numbers. The slider moves along price
    stops, not linearly, because most rents sit low. Each change dispatches `price-changed`
    (true when a price is set) so the modal's "N selected" counter can include it.
--}}
@php
    $bands = \App\Models\Property::budgetBands();
    $stops = \App\Models\Property::budgetStops();
    $histogram = \App\Models\Property::budgetHistogram();
    $median = \App\Models\Property::budgetMedian();
    $points = \App\Models\Property::budgetPoints();

    // Native range thumbs can't be styled with plain classes.
    $thumb = 'absolute inset-0 h-6 w-full appearance-none bg-transparent pointer-events-none focus:outline-none [&::-moz-range-track]:bg-transparent'
        . ' [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-6 [&::-webkit-slider-thumb]:w-6 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-[3px] [&::-webkit-slider-thumb]:border-solid [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[#FF8A66] [&::-webkit-slider-thumb]:shadow-[0_1px_5px_rgba(6,13,38,0.4)] [&::-webkit-slider-thumb]:cursor-grab'
        . ' [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-6 [&::-moz-range-thumb]:w-6 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-[3px] [&::-moz-range-thumb]:border-solid [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[#FF8A66] [&::-moz-range-thumb]:shadow-[0_1px_5px_rgba(6,13,38,0.4)] [&::-moz-range-thumb]:cursor-grab'
        . ' focus-visible:[&::-webkit-slider-thumb]:ring-2 focus-visible:[&::-webkit-slider-thumb]:ring-[#FF8A66]/40';
    $field = 'mt-1 flex items-center rounded-xl border border-[#E2E4EC] bg-white px-3 h-10 focus-within:border-[#FF8A66] focus-within:ring-2 focus-within:ring-[#FF8A66]/25 transition-all duration-200';
    $number = 'p-0 border-none bg-transparent text-[14px] text-[#060D26] focus:ring-0 placeholder-[#5B6A8E]/70 w-full min-w-0 outline-none ml-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';
@endphp

<section {{ $attributes->merge(['class' => 'bg-white border border-[#E2E4EC] rounded-2xl p-3.5']) }}
    aria-label="Monthly budget"
    x-data="{
        min: @js((string) request('price_min', '')),
        max: @js((string) request('price_max', '')),
        bands: @js($bands),
        stops: @js($stops),
        histogram: @js($histogram),
        points: @js($points),
        get peak() { return Math.max(1, ...this.histogram); },
        get last() { return this.stops.length - 1; },
        money(v) { return '₱' + Number(v).toLocaleString('en-PH'); },
        barTitle(i) {
            const c = this.histogram[i];
            return c + (c === 1 ? ' listing' : ' listings') + ' · ' + this.money(this.stops[i]) + (i === this.histogram.length - 1 ? ' & up' : ' – ' + this.money(this.stops[i + 1]));
        },
        near(v) { let best = 1, gap = Infinity; for (let i = 1; i < this.last; i++) { const d = Math.abs(this.stops[i] - v); if (d < gap) { gap = d; best = i; } } return best; },
        get lo() { return this.min === '' ? 0 : this.near(+this.min); },
        get hi() { return this.max === '' ? this.last : this.near(+this.max); },
        setLo(e) { const i = Math.min(+e.target.value, this.hi - 1); e.target.value = i; this.min = i <= 0 ? '' : String(this.stops[i]); },
        setHi(e) { const i = Math.max(+e.target.value, this.lo + 1); e.target.value = i; this.max = i >= this.last ? '' : String(this.stops[i]); },
        pick(b) { if (this.isPicked(b)) { this.clear(); return; } this.min = b.min === null ? '' : String(b.min); this.max = b.max === null ? '' : String(b.max); },
        isPicked(b) { return this.min === (b.min === null ? '' : String(b.min)) && this.max === (b.max === null ? '' : String(b.max)); },
        tidy() { if (this.min !== '' && this.max !== '' && +this.min > +this.max) { [this.min, this.max] = [this.max, this.min]; } },
        clear() { this.min = ''; this.max = ''; },
        get any() { return this.min === '' && this.max === ''; },
        get summary() {
            if (this.any) return 'Any budget';
            if (this.min !== '' && this.max !== '') return this.money(this.min) + ' – ' + this.money(this.max);
            return this.min !== '' ? this.money(this.min) + ' & up' : 'Up to ' + this.money(this.max);
        },
        get matching() {
            if (this.any || ! this.points.length) return null;
            const lo = this.min === '' ? 0 : +this.min, hi = this.max === '' ? Infinity : +this.max;
            const hit = new Set();
            this.points.forEach((x) => { if (x.f >= lo && x.f <= hi) hit.add(x.p); });
            return hit.size;
        },
    }"
    x-effect="$dispatch('price-changed', ! any)">

    {{-- Header: what this is, what is picked, and a one-tap reset. --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-3">
        <span class="w-8 h-8 rounded-full bg-[#060D26] text-white flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </span>
        <div class="min-w-0">
            <h4 class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Monthly budget</h4>
            @if($median)
                <p class="text-[12px] text-[#5B6A8E]">Typical rent right now: <span class="font-semibold text-[#060D26]">&#8369;{{ number_format($median) }}</span> / month</p>
            @endif
        </div>
        <div class="ml-auto flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12.5px] font-semibold transition-colors duration-200"
                :class="any ? 'bg-[#ECEEF6] text-[#5B6A8E]' : 'bg-[#FF8A66]/15 text-[#060D26] ring-1 ring-[#FF8A66]/40'">
                <span x-text="summary"></span>
                <template x-if="matching !== null">
                    <span class="font-normal text-[#5B6A8E] tabular-nums" x-text="'· ' + matching + (matching === 1 ? ' listing' : ' listings')"></span>
                </template>
            </span>
            <button type="button" @click="clear()" x-show="! any" x-cloak
                class="text-[12.5px] font-semibold text-[#060D26] underline underline-offset-2 hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer">Clear</button>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2 lg:gap-8">
        {{-- Quick ranges: each holds roughly a fifth of what is available, with a live count. Tap again to unselect. --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Popular ranges</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="b in bands" :key="b.label">
                    <button type="button" @click="pick(b)" :disabled="b.count === 0 && ! isPicked(b)" :aria-pressed="isPicked(b)"
                        :class="isPicked(b) ? 'border-[#FF8A66] bg-[#FF8A66]/10' : 'border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] disabled:opacity-50 disabled:hover:bg-white'"
                        class="relative flex flex-col items-start rounded-xl border px-3.5 py-2 text-left transition-colors duration-200 cursor-pointer disabled:cursor-not-allowed">
                        <span class="text-[13px] font-semibold text-[#060D26]" x-text="b.label"></span>
                        <span class="flex items-center gap-1.5 text-[11.5px] text-[#5B6A8E] tabular-nums">
                            <span x-text="b.count + (b.count === 1 ? ' listing' : ' listings')"></span>
                            <span x-show="b.popular" x-cloak class="rounded-full bg-[#FF8A66]/20 px-1.5 text-[10px] font-bold uppercase tracking-wide text-[#B35A3D]">Most</span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Custom: histogram + slider + typed values. Bars inside the chosen range are coral. The rail and bars are
             inset by half a handle (12px) so they line up with where the handles actually travel. --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Or set your own</p>
            <div class="mt-2">
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

            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-[11.5px] font-semibold text-[#5B6A8E]">Min</span>
                    <span class="{{ $field }}">
                        <span class="text-[13px] text-[#5B6A8E]" aria-hidden="true">&#8369;</span>
                        <input type="number" inputmode="numeric" name="price_min" min="0" step="100" x-model="min" @change="tidy()" placeholder="No min" class="{{ $number }}">
                    </span>
                </label>
                <label class="block">
                    <span class="block text-[11.5px] font-semibold text-[#5B6A8E]">Max</span>
                    <span class="{{ $field }}">
                        <span class="text-[13px] text-[#5B6A8E]" aria-hidden="true">&#8369;</span>
                        <input type="number" inputmode="numeric" name="price_max" min="0" step="100" x-model="max" @change="tidy()" placeholder="No max" class="{{ $number }}">
                    </span>
                </label>
            </div>
        </div>
    </div>
</section>
