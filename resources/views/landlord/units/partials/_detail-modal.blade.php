{{--
    Unit detail modal, shared by the Units page and a property's own Units tab.

    Self-contained: it keeps its own Alpine state and opens when something on the page dispatches
    `open-unit-detail` with the payload from PropertyUnit::detailPayload():

        <button x-on:click="$dispatch('open-unit-detail', @js($unit->detailPayload(...)))">

    Teleported to <body> so an ancestor's backdrop-blur or transform can never trap the fixed overlay.
    Options: $showPropertyLink (default true) hides "View property" where the page already is that property.
--}}
<div x-data="{
        modal: null,
        show: false,
        peso(v) { return v ? '₱' + Number(v).toLocaleString('en-PH') : null; },
        open(u) { this.modal = u; this.$nextTick(() => this.show = true); },
        close() { this.show = false; setTimeout(() => this.modal = null, 200); },
    }"
    x-on:open-unit-detail.window="open($event.detail)"
    x-on:keydown.escape.window="if (modal) close()">
    <template x-teleport="body">
        <div x-show="modal" x-cloak>
        <template x-if="modal">
        <div class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm motion-reduce:transition-none" x-on:click="close()"
                x-show="show"
                x-transition:enter="transition-opacity ease-out duration-250"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div class="relative w-full max-w-md md:max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto md:grid md:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_4px_24px_rgba(0,0,0,0.12)] motion-reduce:transition-none"
                role="dialog" aria-modal="true" aria-labelledby="unit-modal-title"
                x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 scale-95">

                <button type="button" x-on:click="close()" aria-label="Close"
                    class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white/90 hover:bg-white border border-[#E2E4EC] flex items-center justify-center text-[#060D26] transition-colors duration-200 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                {{-- Photo panel: fills the full height of the details beside it on desktop. --}}
                <div class="relative aspect-[16/10] md:aspect-auto md:min-h-[380px] bg-[#ECEEF6] border-b md:border-b-0 md:border-r border-[#E2E4EC]">
                    <template x-if="modal.photo">
                        <img :src="modal.photo" :alt="modal.label" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!modal.photo">
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-[#5B6A8E]">
                            <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <p class="text-[12px] mt-1.5">No photos on this unit</p>
                        </div>
                    </template>

                    {{-- Thumbnails: only when the unit has more than one photo. --}}
                    <div x-show="modal.photos && modal.photos.length > 1" x-cloak class="absolute bottom-3 left-3 right-3 flex gap-2 overflow-x-auto">
                        <template x-for="(p, i) in (modal.photos || [])" :key="p">
                            <button type="button" x-on:click="modal.photo = p" :aria-label="'Show photo ' + (i + 1)"
                                :class="modal.photo === p ? 'ring-2 ring-[#FF8A66]' : 'ring-1 ring-white/70 opacity-90 hover:opacity-100'"
                                class="h-11 w-14 shrink-0 overflow-hidden rounded-lg bg-[#ECEEF6] shadow-sm transition-opacity duration-200 cursor-pointer">
                                <img :src="p" alt="" class="h-full w-full object-cover">
                            </button>
                        </template>
                    </div>
                </div>
                {{-- Body --}}
                <div class="p-5 sm:p-6 space-y-5">
                    <div class="flex items-start justify-between gap-3 md:pr-10">
                        <div class="min-w-0">
                            <h2 id="unit-modal-title" class="text-[18px] font-semibold leading-tight text-[#060D26] truncate" x-text="modal.label"></h2>
                            <p class="mt-1 text-[13px] text-[#5B6A8E] line-clamp-2" x-text="[modal.property, modal.floor].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[12px] font-semibold"
                            :class="modal.styles.tile + ' ' + modal.styles.text">
                            <span class="w-1.5 h-1.5 rounded-full" :class="modal.styles.dot"></span>
                            <span x-text="modal.status"></span>
                        </span>
                    </div>

                    <p class="flex items-baseline gap-1.5">
                        <span class="text-[26px] font-semibold leading-none tabular-nums text-[#060D26]" x-text="peso(modal.rent) || '₱—'"></span>
                        <span class="text-[13px] text-[#5B6A8E]">/ month</span>
                    </p>

                    <p x-show="modal.description" x-cloak class="text-[13px] leading-relaxed text-[#5B6A8E] line-clamp-3" x-text="modal.description"></p>

                    {{-- One list instead of a tile grid: any number of facts lines up, so a missing floor
                         area or furnishing can't strand an orphan tile. --}}
                    <dl class="rounded-xl border border-[#E2E4EC] text-[13px] [&>div~div]:border-t [&>div~div]:border-[#E2E4EC]">
                        <template x-for="row in modal.facts" :key="row[0]">
                            <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                                <dt class="text-[#5B6A8E]" x-text="row[0]"></dt>
                                <dd class="font-semibold text-[#060D26] text-right tabular-nums" x-text="row[1]"></dd>
                            </div>
                        </template>
                    </dl>

                    <div x-show="modal.amenities && modal.amenities.length">
                        <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Unit amenities</h3>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="a in modal.amenities" :key="a.name">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E2E4EC] bg-[#ECEEF6] px-2.5 py-1 text-[12px] font-medium text-[#060D26]">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
                                    </svg>
                                    <span x-text="a.name"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        @if($showPropertyLink ?? true)
                        <a :href="modal.property_url"
                            class="flex-1 h-11 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#060D26] text-[13px] font-semibold hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200">
                            View property
                        </a>
                        @endif
                        <a :href="modal.edit_url"
                            class="flex-1 h-11 inline-flex items-center justify-center rounded-full bg-[#FF8A66] text-[#060D26] text-[13px] font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                            Edit unit
                        </a>
                    </div>
                </div>

            </div>
        </div>
        </template>
        </div>
    </template>
</div>
