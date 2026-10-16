{{--
    The filter controls of the AI search results page, shared by the Filters dialog (ai-filters) and the desktop
    left rail (ai-rail). Both edit the same `draft` in the aiSearch Alpine scope (resources/js/ai-search.js).
    $compact: rail styling (flat sections, smaller chips). $idp: id prefix, so the two copies never share an id.
    Options come from the same lists the AI and IntentValidator use, so nothing here can be refused server-side.
--}}
@php
    $compact = $compact ?? false;
    $idp = $idp ?? 'aif-';
    $card = $compact ? 'py-4 border-t border-[#E2E4EC] first:border-t-0 first:pt-0' : 'bg-white border border-[#E2E4EC] rounded-2xl p-4';
    $chip = 'inline-flex items-center gap-1.5 px-3 '.($compact ? 'h-8 text-[12px]' : 'h-9 text-[12.5px]').' rounded-full border font-jakarta font-semibold transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50';
    $chipOn = 'border-[#FF8A66] bg-[#FF8A66] text-[#060D26]';
    $chipOff = 'border-[#E2E4EC] bg-white text-[#5B6A8E] hover:border-[#FF8A66]';
    $bands = \App\Models\Property::budgetBands();
    $facts = [
        ['Living arrangement', 'living', array_combine(array_keys(\App\Support\PropertyPolicies::LIVING_ARRANGEMENTS), array_keys(\App\Support\PropertyPolicies::LIVING_ARRANGEMENTS))],
        ['Suitable for', 'for', ['Men' => 'Men', 'Women' => 'Women']],
        ['Furnishing', 'furnishing', array_combine(\App\Support\BrowseFilters::FURNISHING, \App\Support\BrowseFilters::FURNISHING)],
    ];
@endphp
{{-- Budget --}}
<section class="{{ $card }}" aria-labelledby="{{ $idp }}budget">
    <h4 id="{{ $idp }}budget" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Budget <span class="font-medium normal-case tracking-normal text-[#5B6A8E]">(per month)</span></h4>
    <div class="mt-3 flex items-center gap-3">
        <label class="flex-1 min-w-0">
            <span class="block mb-1 text-[12px] font-semibold text-[#5B6A8E]">Min</span>
            <span class="flex items-center h-11 px-3 rounded-xl border border-[#E2E4EC] bg-white focus-within:border-[#FF8A66]">
                <span class="mr-1.5 font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
                <input type="text" inputmode="numeric" placeholder="{{ $compact ? 'No min' : 'No minimum' }}" x-model="draft.price_min" x-on:input="draft.price_min = draft.price_min.toString().replace(/[^0-9]/g, '')"
                    class="w-full min-w-0 p-0 border-0 shadow-none bg-transparent text-[14px] text-[#060D26] focus:ring-0 focus:outline-none placeholder:text-[#98A0B8]">
            </span>
        </label>
        <label class="flex-1 min-w-0">
            <span class="block mb-1 text-[12px] font-semibold text-[#5B6A8E]">Max</span>
            <span class="flex items-center h-11 px-3 rounded-xl border border-[#E2E4EC] bg-white focus-within:border-[#FF8A66]">
                <span class="mr-1.5 font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
                <input type="text" inputmode="numeric" placeholder="{{ $compact ? 'No max' : 'No maximum' }}" x-model="draft.price_max" x-on:input="draft.price_max = draft.price_max.toString().replace(/[^0-9]/g, '')"
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
<section class="{{ $card }}" aria-labelledby="{{ $idp }}type">
    <h4 id="{{ $idp }}type" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Property type</h4>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach(\App\Services\AiSearch\Vocabulary::propertyTypes() as $type)
            <button type="button" x-on:click="pickDraft('property_type', @js($type))" :aria-pressed="(draft.property_type === @js($type)).toString()"
                :class="draft.property_type === @js($type) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">{{ $type }}</button>
        @endforeach
    </div>
</section>

{{-- The place + house rules --}}
<section class="{{ $card }}" aria-labelledby="{{ $idp }}place">
    <h4 id="{{ $idp }}place" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">The place</h4>
    <div class="mt-1 divide-y divide-[#E2E4EC]">
        @foreach($facts as [$title, $field, $options])
            <div class="py-3 {{ $compact ? '' : 'sm:flex sm:items-center sm:gap-4' }}">
                <p class="mb-2 {{ $compact ? '' : 'sm:mb-0 sm:w-40' }} shrink-0 text-[12px] font-semibold uppercase tracking-wide text-[#5B6A8E]">{{ $title }}</p>
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

<section class="{{ $card }}" aria-labelledby="{{ $idp }}rules">
    <h4 id="{{ $idp }}rules" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">House rules</h4>
    <p class="mt-1 text-[12px] text-[#5B6A8E]">Show only places that allow…</p>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach(\App\Support\BrowseFilters::RULES as $key => [$label])
            <button type="button" x-on:click="toggleDraft('rules', @js($key))" :aria-pressed="draft.rules.includes(@js($key)).toString()"
                :class="draft.rules.includes(@js($key)) ? '{{ $chipOn }}' : '{{ $chipOff }}'" class="{{ $chip }}">{{ $label }}</button>
        @endforeach
    </div>
</section>

{{-- Amenities --}}
<section class="{{ $card }}" aria-labelledby="{{ $idp }}amenities">
    <h4 id="{{ $idp }}amenities" class="font-jakarta text-[13px] font-bold uppercase tracking-[0.06em] text-[#060D26]">Amenities</h4>
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
