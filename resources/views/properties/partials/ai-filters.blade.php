{{--
    Filters panel for the AI search results page. Opened by the header's Filters button (`browse-open-filters`).
    Lives inside the aiSearch Alpine scope (resources/js/ai-search.js): it edits `draft`, a copy of the search's
    filters prefilled from the current intent, and "Show results" applies it and searches again in place
    (no new AI call). The place is not editable here: remove its chip to change it.
    Options come from the same lists the AI and IntentValidator use, so nothing here can be refused server-side.
--}}
@php
    $chip = 'inline-flex items-center gap-1.5 px-3 h-9 rounded-full border font-jakarta text-[12.5px] font-semibold transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50';
    $chipOn = 'border-[#FF8A66] bg-[#FF8A66] text-[#060D26]';
    $chipOff = 'border-[#E2E4EC] bg-white text-[#5B6A8E] hover:border-[#FF8A66]';
    $bands = \App\Models\Property::budgetBands();
    $facts = [
        ['Living arrangement', 'living', array_combine(array_keys(\App\Support\PropertyPolicies::LIVING_ARRANGEMENTS), array_keys(\App\Support\PropertyPolicies::LIVING_ARRANGEMENTS))],
        ['Suitable for', 'for', ['Men' => 'Men', 'Women' => 'Women']],
        ['Furnishing', 'furnishing', array_combine(\App\Support\BrowseFilters::FURNISHING, \App\Support\BrowseFilters::FURNISHING)],
    ];
@endphp
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
                {{-- Budget --}}
                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-4" aria-labelledby="aif-budget">
                    <h4 id="aif-budget" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Budget <span class="font-medium normal-case tracking-normal text-[#5B6A8E]">(per month)</span></h4>
                    <div class="mt-3 flex items-center gap-3">
                        <label class="flex-1 min-w-0">
                            <span class="block mb-1 text-[12px] font-semibold text-[#5B6A8E]">Min</span>
                            <span class="flex items-center h-11 px-3 rounded-xl border border-[#E2E4EC] bg-white focus-within:border-[#FF8A66]">
                                <span class="mr-1.5 font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
                                <input type="text" inputmode="numeric" placeholder="No minimum" x-model="draft.price_min" x-on:input="draft.price_min = draft.price_min.toString().replace(/[^0-9]/g, '')"
                                    class="w-full min-w-0 p-0 border-0 shadow-none bg-transparent text-[14px] text-[#060D26] focus:ring-0 focus:outline-none placeholder:text-[#98A0B8]">
                            </span>
                        </label>
                        <label class="flex-1 min-w-0">
                            <span class="block mb-1 text-[12px] font-semibold text-[#5B6A8E]">Max</span>
                            <span class="flex items-center h-11 px-3 rounded-xl border border-[#E2E4EC] bg-white focus-within:border-[#FF8A66]">
                                <span class="mr-1.5 font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
                                <input type="text" inputmode="numeric" placeholder="No maximum" x-model="draft.price_max" x-on:input="draft.price_max = draft.price_max.toString().replace(/[^0-9]/g, '')"
                                    class="w-full min-w-0 p-0 border-0 shadow-none bg-transparent text-[14px] text-[#060D26] focus:ring-0 focus:outline-none placeholder:text-[#98A0B8]">
                            </span>
                        </label>
                    </div>
                    @if($bands)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($bands as $band)
                                <button type="button" x-on:click="setBudget(@js($band['min']), @js($band['max']))"
                                    :aria-pressed="(String(draft.price_min) === @js((string) ($band['min'] ?? '')) && String(draft.price_max) === @js((string) ($band['max'] ?? ''))).toString()"
                                    :class="(String(draft.price_min) === @js((string) ($band['min'] ?? '')) && String(draft.price_max) === @js((string) ($band['max'] ?? ''))) ? '{{ $chipOn }}' : '{{ $chipOff }}'"
                                    class="{{ $chip }}">{{ $band['label'] }} <span class="font-medium opacity-70">{{ $band['count'] }}</span></button>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Type --}}
                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-4" aria-labelledby="aif-type">
                    <h4 id="aif-type" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Property type</h4>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(\App\Services\AiSearch\Vocabulary::propertyTypes() as $type)
                            <button type="button" x-on:click="pickDraft('property_type', @js($type))" :aria-pressed="(draft.property_type === @js($type)).toString()"
                                :class="draft.property_type === @js($type) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">{{ $type }}</button>
                        @endforeach
                    </div>
                </section>

                {{-- The place + house rules --}}
                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-4" aria-labelledby="aif-place">
                    <h4 id="aif-place" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">The place</h4>
                    <div class="mt-1 divide-y divide-[#E2E4EC]">
                        @foreach($facts as [$title, $field, $options])
                            <div class="py-3 sm:flex sm:items-center sm:gap-4">
                                <p class="mb-2 sm:mb-0 sm:w-40 shrink-0 text-[12px] font-semibold uppercase tracking-wide text-[#5B6A8E]">{{ $title }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($options as $value => $label)
                                        <button type="button" x-on:click="pickDraft(@js($field), @js($value))" :aria-pressed="(draft.{{ $field }} === @js($value)).toString()"
                                            :class="draft.{{ $field }} === @js($value) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-4" aria-labelledby="aif-rules">
                    <h4 id="aif-rules" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">House rules</h4>
                    <p class="mt-1 text-[12px] text-[#5B6A8E]">Show only places that allow…</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(\App\Support\BrowseFilters::RULES as $key => [$label])
                            <button type="button" x-on:click="toggleDraft('rules', @js($key))" :aria-pressed="draft.rules.includes(@js($key)).toString()"
                                :class="draft.rules.includes(@js($key)) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                {{-- Amenities --}}
                <section class="bg-white border border-[#E2E4EC] rounded-2xl p-4" aria-labelledby="aif-amenities">
                    <h4 id="aif-amenities" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Amenities</h4>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(\App\Services\AiSearch\Vocabulary::amenities() as $name)
                            <button type="button" x-on:click="toggleDraft('amenities', @js($name))" :aria-pressed="draft.amenities.includes(@js($name)).toString()"
                                :class="draft.amenities.includes(@js($name)) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">
                                <x-amenity-icon :name="$name" class="w-3.5 h-3.5" />
                                {{ $name }}
                            </button>
                        @endforeach
                    </div>
                </section>
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
