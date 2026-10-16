{{--
    Desktop filter rail of the AI search results page (lg and up; below lg the header's Filters dialog does this job).
    What the search understood (removable chips) on top, then the same controls as the dialog, edited into the same
    `draft` and applied with "Show results". Sticky under the header + search band, scrolls on its own when tall.
    DESIGN.md §36.
--}}
<aside aria-label="Search filters" x-ref="filtersRail"
    class="hidden lg:flex flex-col sticky top-[var(--browse-sticky-top,240px)] max-h-[calc(100vh-var(--browse-sticky-top,240px)-16px)] rounded-2xl border border-[#E2E4EC] bg-white overflow-hidden">
    <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4">
        <div x-show="chips.length || phase === 'reading'" x-cloak class="mb-4">
            <span class="block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-[#5B6A8E] mb-2">Searching for</span>
            @include('properties.partials.ai-chips', ['stack' => true])
        </div>
        @include('properties.partials.ai-filter-sections', ['compact' => true, 'idp' => 'rail-'])
    </div>
    <div class="flex items-center justify-between gap-2 px-4 py-3 border-t border-[#E2E4EC] bg-white shrink-0">
        <button type="button" x-on:click="clearDraft()" x-bind:disabled="draftCount === 0"
            class="h-10 px-1 text-[13px] font-semibold text-[#060D26] underline underline-offset-4 decoration-[#060D26]/30 hover:decoration-[#060D26] disabled:opacity-40 disabled:no-underline cursor-pointer">Clear all</button>
        <button type="button" x-on:click="applyFilters()" x-bind:disabled="loading"
            class="h-10 px-4 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-jakarta text-[13px] font-bold transition-colors cursor-pointer disabled:opacity-60">Show results<span x-show="draftCount > 0" x-cloak x-text="' · ' + draftCount + ' on'"></span></button>
    </div>
</aside>
