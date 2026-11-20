{{--
    "Refine" bar under the chips of the AI search results page (plans/ai-search-maps-and-haiku.md, Phase 3): change the
    search the tenant already has instead of starting over. Quick chips ("Cheaper", "Closer", "Add aircon") are
    worked out by the server and need no AI call; typed text ("mas duol sa school", "may aircon") goes to the model.
    Reads `quick`, `history`, `refineText`, `refining`, `refine()` and `back()` from the aiSearch Alpine scope.
    Phone first: a 44px input and button, chips on one scrolling row.
--}}
@if(\App\Services\AiSearch\AiFeatures::on('refine'))
    <section x-show="phase === 'done'" x-cloak aria-label="Refine your search" class="mt-5 max-w-3xl">
        <form class="flex items-center gap-2" x-on:submit.prevent="refine()">
            <label for="ai-refine" class="sr-only">Refine your search</label>
            <input id="ai-refine" type="text" x-model="refineText" maxlength="300" autocomplete="off" :disabled="refining"
                placeholder="Refine: cheaper, closer to school, may aircon…"
                class="flex-1 min-w-0 h-11 rounded-full border border-[#E2E4EC] bg-white px-4 text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 outline-none focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 disabled:opacity-60">
            <button type="submit" :disabled="refining || !refineText.trim()"
                class="h-11 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13.5px] font-bold transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!refining">Refine</span><span x-show="refining" x-cloak>Updating…</span>
            </button>
        </form>

        <div class="mt-2 flex items-center gap-1.5 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap sm:overflow-visible">
            <template x-for="item in quick" :key="item.label">
                <button type="button" x-on:click="refine(item.patch)" :disabled="refining"
                    class="shrink-0 inline-flex items-center h-9 px-3.5 rounded-full border border-[#060D26]/25 bg-white text-[13px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] transition-colors cursor-pointer disabled:opacity-50">
                    + <span x-text="item.label" class="ml-1"></span>
                </button>
            </template>
            <button type="button" x-show="history.length" x-cloak x-on:click="back()" :disabled="refining"
                class="shrink-0 inline-flex items-center h-9 px-3.5 rounded-full text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] cursor-pointer">
                ← Back
            </button>
            <a href="{{ route('properties.index') }}" class="shrink-0 inline-flex items-center h-9 px-3.5 rounded-full text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26]">New search</a>
        </div>
    </section>
@endif
