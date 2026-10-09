@props(['variant' => 'header'])

{{--
    The one search box (replaces <x-search-pill>): describe the place in a sentence, in English,
    Tagalog or Bisaya. A plain GET form to /properties?q=..., so it works as a normal link and the
    result page is shareable; resources/js/ai-search.js does the reading. `hero` is the large
    landing-page box with example sentences and recent searches; `header` is the one-line version in
    the dark search band. See plans/ai-search.md and DESIGN.md §32.
--}}
@php
    $isHero = $variant === 'hero';
    $examples = [
        'Condo near IT Park, max ₱18k',
        'Bedspace para sa babae, may aircon',
        'Apartment sa Mandaue ubos 4k',
    ];
    $placeholder = 'e.g. Room near USC Talamban, Wi-Fi, under ₱5,000. I have a cat.';
@endphp

@if($isHero)
    <div class="w-full max-w-[760px] text-left" x-data="aiSearchBox(@js(request('q', '')))">
        <form action="{{ route('properties.index') }}" method="GET" role="search"
            class="bg-white rounded-[20px] border-[1.5px] border-[#FF8A66] ring-4 ring-[#FF8A66]/15 shadow-[0_18px_50px_rgba(6,13,38,0.28)] flex flex-col">
            <label for="ai-q-hero" class="flex items-center gap-1.5 px-4 pt-3.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-[#B35A3D]">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9.4l-4.7 1.8L12 16l-1.8-4.8L5.5 9.4l4.7-1.8z" /></svg>
                Describe your ideal place
            </label>
            <textarea id="ai-q-hero" name="q" rows="3" maxlength="300" required x-model="q" placeholder="{{ $placeholder }}"
                x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
                class="resize-none border-0 bg-transparent px-4 pt-2 pb-1 text-[16px] leading-[1.45] text-[#060D26] placeholder-[#5B6A8E]/70 focus:ring-0 focus:outline-none"></textarea>
            <div class="flex items-center justify-between gap-2 pl-4 pr-2.5 pb-2.5 pt-2">
                <span class="flex gap-1" role="img" aria-label="Works in English, Tagalog and Bisaya">
                    @foreach(['EN', 'TL', 'CEB'] as $lang)
                        <span class="text-[10.5px] font-bold px-1.5 py-[3px] rounded-md bg-[#ECEEF6] text-[#5B6A8E]">{{ $lang }}</span>
                    @endforeach
                </span>
                <button type="submit"
                    class="h-11 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-jakarta font-bold text-[14.5px] inline-flex items-center gap-2 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                    Search
                </button>
            </div>
        </form>

        <div class="mt-3.5 flex flex-wrap gap-2" aria-label="Try asking">
            @foreach($examples as $example)
                <button type="button" x-on:click="use(@js($example))"
                    class="h-9 px-3 rounded-full border border-white/35 bg-white/10 hover:bg-white/20 text-white text-[13px] transition-colors cursor-pointer">{{ $example }}</button>
            @endforeach
        </div>

        <div x-show="recent.length" x-cloak class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="text-[12px] font-semibold uppercase tracking-[0.06em] text-white/70">Recent</span>
            <template x-for="item in recent" :key="item">
                <button type="button" x-on:click="use(item)" x-text="item"
                    class="max-w-[260px] truncate text-[13px] text-white/90 hover:text-white underline-offset-2 hover:underline cursor-pointer"></button>
            </template>
            <button type="button" x-on:click="clearRecent()" class="text-[12px] text-white/60 hover:text-white cursor-pointer">Clear</button>
        </div>
    </div>
@else
    <form action="{{ route('properties.index') }}" method="GET" role="search" x-data="aiSearchBox(@js(request('q', '')))"
        class="w-full max-w-[860px] flex items-center gap-2 bg-white rounded-full border-[1.5px] border-[#FF8A66] ring-4 ring-[#FF8A66]/15 pl-4 pr-1.5 py-1.5">
        <svg class="w-[18px] h-[18px] text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9.4l-4.7 1.8L12 16l-1.8-4.8L5.5 9.4l4.7-1.8z" /></svg>
        <label for="ai-q-header" class="sr-only">Describe your ideal place</label>
        <input id="ai-q-header" type="text" name="q" maxlength="300" required x-model="q" autocomplete="off"
            placeholder="Describe the place you want…"
            class="flex-1 min-w-0 border-0 bg-transparent p-0 h-10 text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:ring-0 focus:outline-none">
        <button type="submit" aria-label="Search"
            class="flex-shrink-0 h-10 sm:h-11 px-3.5 sm:px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-jakarta font-bold text-[14px] inline-flex items-center gap-2 transition-colors cursor-pointer">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
            <span class="hidden sm:inline">Search</span>
        </button>
    </form>
@endif
