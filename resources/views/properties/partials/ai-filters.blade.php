{{--
    Filters panel for the AI search results page. Opened by the header's Filters button (`browse-open-filters`).
    Lives inside the aiSearch Alpine scope (resources/js/ai-search.js): it edits `draft`, a copy of the search's
    filters prefilled from the current intent, and "Show results" applies it and searches again in place
    (no new AI call). The place is not editable here: remove its chip to change it.
    The controls themselves live in ai-filter-sections (shared with the desktop rail).
--}}
<template x-teleport="body">
    <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center sm:p-6"
        x-on:keydown.escape.window="if (filtersOpen) closeFilters()">
        <div class="absolute inset-0 bg-[#060D26]/50" x-on:click="closeFilters()"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="ai-filters-title" tabindex="-1" x-ref="filtersDialog"
            x-on:keydown.tab="trapTab($event)" x-show="filtersOpen" x-transition
            class="relative w-full sm:max-w-3xl h-[92vh] sm:h-auto sm:max-h-[90vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden focus:outline-none">

            <div class="flex items-start justify-between gap-4 px-5 sm:px-6 pt-5 pb-4 border-b border-[#E2E4EC] flex-shrink-0">
                <div>
                    <span class="inline-flex items-center gap-2 font-jakarta text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>Refine this search</span>
                    <h3 id="ai-filters-title" class="mt-1 font-jakarta text-[22px] font-extrabold tracking-tight text-[#060D26]">Filters</h3>
                </div>
                <button type="button" x-on:click="closeFilters()" aria-label="Close filters"
                    class="w-10 h-10 flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#5B6A8E] hover:text-[#060D26] hover:border-[#FF8A66] transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 sm:px-6 py-4 space-y-3 bg-[#F7F8FC]">
                @include('properties.partials.ai-filter-sections')
            </div>

            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-3.5 border-t border-[#E2E4EC] bg-white flex-shrink-0">
                <button type="button" x-on:click="clearDraft()" x-bind:disabled="draftCount === 0"
                    class="h-11 px-3 text-[13.5px] font-semibold text-[#060D26] underline underline-offset-4 decoration-[#060D26]/30 hover:decoration-[#060D26] disabled:opacity-40 disabled:no-underline cursor-pointer">Clear all</button>
                <button type="button" x-on:click="applyFilters()"
                    class="h-11 px-6 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-jakarta text-[14px] font-bold transition-colors cursor-pointer">Show results<span x-show="draftCount > 0" x-cloak x-text="' · ' + draftCount + ' on'"></span></button>
            </div>
        </div>
    </div>
</template>
