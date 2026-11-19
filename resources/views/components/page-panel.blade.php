{{--
    The page panel: a link marked `data-page-panel` opens its page over the current one instead of navigating away.
    A bottom sheet on a phone; from `lg` up it fills the work area and leaves the sidebar visible (DESIGN.md §6u).
    It starts at the sidebar's edge (left-64, or 5rem when the sidebar is collapsed: see the style block in
    layouts/landlord) and sits under the sidebar (z 45 against 50) so the sidebar's own dropdowns still open over it.
    Mounted once in layouts/landlord.
    The page loads in an iframe with `?embed=1` (App\Http\Middleware\EmbeddedPage), so everything on it (record,
    void, deposit) works as on the full page. Logic: resources/js/page-panel.js.

    Link markup:  <a href="{{ route(...) }}" data-page-panel data-page-panel-back="Payments">…</a>
    The href stays a real address: middle-click, copy link and "no JavaScript" still open the full page.
--}}
<div data-page-panel-root x-data="pagePanel()" @keydown.escape.window="open && close()">

    {{-- Phone only: dim the list behind the sheet. From lg the panel sits beside the sidebar, so nothing is dimmed. --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms @click="close()"
        class="fixed inset-0 z-[44] bg-[#060D26]/50 lg:hidden" aria-hidden="true"></div>

    <section data-page-panel-section x-show="open" x-cloak role="dialog" aria-modal="true" :aria-label="label"
        x-transition:enter="transition duration-300 ease-out"
        x-transition:enter-start="translate-y-full lg:translate-y-3 lg:opacity-0"
        x-transition:enter-end="translate-y-0 lg:opacity-100"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-start="translate-y-0 lg:opacity-100"
        x-transition:leave-end="translate-y-full lg:translate-y-3 lg:opacity-0"
        class="fixed inset-x-0 bottom-0 top-[5vh] z-[45] flex flex-col overflow-hidden rounded-t-3xl bg-[#F7F8FC] shadow-[0_-12px_40px_rgba(6,13,38,0.25)] lg:inset-y-0 lg:left-64 lg:right-0 lg:rounded-none lg:shadow-none">

        <div class="flex shrink-0 justify-center pt-2 lg:hidden" aria-hidden="true">
            <span class="h-1.5 w-10 rounded-full bg-[#E2E4EC]"></span>
        </div>

        <div class="flex h-14 shrink-0 items-center gap-2 border-b border-[#E2E4EC] bg-white px-3 sm:px-5 lg:h-16">
            <button type="button" x-ref="back" @click="close()"
                class="inline-flex h-11 items-center gap-1.5 rounded-xl border border-[#E2E4EC] bg-white pl-2.5 pr-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span x-text="backLabel"></span>
            </button>

            <span class="min-w-0 flex-1"></span>

            <a :href="fullUrl" aria-label="Open as a full page"
                class="hidden h-11 items-center gap-1.5 rounded-xl px-3 text-[13px] font-semibold text-[#5B6A8E] transition-colors duration-200 hover:text-[#060D26] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] sm:inline-flex">
                Open full page
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
                </svg>
            </a>

            <button type="button" @click="close()" aria-label="Close"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <div class="relative min-h-0 flex-1">
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center" role="status">
                <span class="h-7 w-7 animate-spin rounded-full border-2 border-[#5B6A8E] border-t-transparent" aria-hidden="true"></span>
                <span class="sr-only">Loading</span>
            </div>
            <template x-if="src">
                <iframe :src="src" @load="loaded()" :title="label"
                    class="h-full w-full border-0 transition-opacity duration-200"
                    :class="loading ? 'opacity-0' : 'opacity-100'"></iframe>
            </template>
        </div>
    </section>
</div>
