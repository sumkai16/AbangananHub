{{--
    Quick changes under the chips of the AI search results page (plans/ai-search-maps-and-haiku.md, Phase 3): "Cheaper",
    "Add parking", "Pets OK" change the search the tenant already has in one tap. They are worked out by the server and
    need no AI call. There is NO second text box here: the header search bar is the one place to type (Nov 20 2026, the
    old "Refine" box repeated it). Reads `quick`, `history`, `refining`, `refine()` and `back()` from the aiSearch scope.
    Phone first: chips on one scrolling row.
--}}
@if(\App\Services\AiSearch\AiFeatures::on('refine'))
    <section x-show="phase === 'done' && (quick.length || history.length)" x-cloak aria-label="Quick changes to your search" class="mt-5 max-w-3xl">
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap sm:overflow-visible">
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
        </div>
    </section>
@endif
