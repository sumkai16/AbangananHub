{{--
    AI search results: summary card + property cards. Rendered by AiSearchController::results() and
    injected by resources/js/ai-search.js. Variables: see AiSearchService::results() (+ favoritedIds, filtersUrl).
    Hits / gaps on each card are computed by MatchExplainer from real data; only the summary text may come from the AI.
--}}
@php
    $total = $rows->count();
    $close = $total - $exact_count;
    $showEnglishToggle = $language !== 'en' && filled($summary_en) && $summary_en !== $summary;

    // One-tap fix: if the best result is only over budget, offer its price as the new budget (rounded up to ₱100).
    $first = $rows->first();
    $overBudget = $exact_count === 0 && $first && collect($first['misses'])->contains('facet', 'price') && $intent['price_max'] !== null;
    $raiseTo = $overBudget ? (int) (ceil(((float) $first['property']->min_rental_fee) / 100) * 100) : null;

    // Other property types that fit the budget and area: a suggestion strip, never mixed into the main list.
    $alternatives = ($alternatives ?? collect())->take(3);
    $alt_note = $alt_note ?? '';

    // Every listing the summary may name becomes a link; prices and distances are bold (SummaryFormatter).
    $linkable = $rows->pluck('property')->concat($alternatives->pluck('property'));
@endphp

{{-- "Near me" (plans/near-me-search.md): which spot was used so the tenant can correct it, or why there is none.
     Only the area name reaches the page; the coordinates stay in the browser. `changeArea` and `useMyLocation`
     live in resources/js/ai-search.js. --}}
@php
    $where = $location_notice ?? null;
    $areaField = 'h-10 flex-1 min-w-0 rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 outline-none focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20';
@endphp
@if(($where['kind'] ?? null) === 'using')
    <section role="status" aria-label="Your location" x-data="{ editing: false, area: '' }" class="mb-4 max-w-3xl rounded-2xl border border-[#E2E4EC] bg-white px-4 py-3">
        <p class="text-[13.5px] leading-[1.5] text-[#060D26]">
            Using your location, near <strong class="font-bold">{{ $where['area'] }}</strong>.
            <button type="button" x-on:click="editing = !editing" x-bind:aria-expanded="editing"
                class="font-semibold text-[#B35A3D] underline underline-offset-2 cursor-pointer">Not here? Type your area</button>
        </p>
        <form x-show="editing" x-cloak x-on:submit.prevent="changeArea(area)" class="mt-3 flex gap-2">
            <label for="near-area" class="sr-only">Your area</label>
            <input id="near-area" type="text" x-model="area" maxlength="80" placeholder="e.g. Mandaue or Talamban" class="{{ $areaField }}">
            <button type="submit" class="h-10 px-4 rounded-full bg-[#060D26] text-white text-[13px] font-semibold cursor-pointer">Search here</button>
        </form>
    </section>
@elseif($where)
    <section role="status" aria-label="Your location" x-data="{ area: '' }" class="mb-4 max-w-3xl rounded-2xl border border-[#F5D78A] bg-[#FEF6E4] px-4 py-3.5">
        <p class="text-[14px] font-semibold text-[#060D26]">
            @if($where['kind'] === 'outside')
                Your location looks to be outside Cebu.
            @else
                We couldn&rsquo;t get your location.
            @endif
        </p>
        <p class="mt-1 text-[13px] leading-[1.5] text-[#5B5440]">
            @if($where['kind'] === 'outside')
                Type the area you want to search near.
            @else
                Allow location in your browser, or type the area you want to search near.
            @endif
        </p>
        <form x-on:submit.prevent="changeArea(area)" class="mt-3 flex gap-2">
            <label for="near-area" class="sr-only">Your area</label>
            <input id="near-area" type="text" x-model="area" maxlength="80" placeholder="e.g. Mandaue or Talamban" class="{{ $areaField }}">
            <button type="submit" class="h-10 px-4 rounded-full bg-[#060D26] text-white text-[13px] font-semibold cursor-pointer">Search here</button>
        </form>
        @if($where['kind'] !== 'outside')
            <button type="button" x-on:click="useMyLocation()"
                class="mt-2 inline-flex h-10 items-center px-4 rounded-full border border-[#060D26] bg-white text-[#060D26] text-[13px] font-semibold cursor-pointer">Use my location</button>
        @endif
    </section>
@endif

{{-- What they typed names a business, listing or place we do not have. Never says "no landlord named ...":
     personal names are not searchable, and this must not confirm or deny that a person exists. --}}
@php
    $notice = $name_notice ?? null;
    $noticeName = $notice ? \Illuminate\Support\Str::of($notice['name'])->squish()->title()->toString() : null;
@endphp
@if($notice && $total === 0)
    <section role="status" class="rounded-2xl border border-[#F5D78A] bg-[#FEF6E4] px-5 py-8 text-center max-w-3xl">
        <p class="font-jakarta text-[17px] font-bold text-[#060D26]">
            @if($notice['kind'] === 'name')
                No listing or business called &ldquo;{{ $noticeName }}&rdquo; on AbangananHub.
            @else
                &ldquo;{{ $noticeName }}&rdquo; doesn&rsquo;t match any place or listing name we have.
            @endif
        </p>
        <p class="mt-1.5 text-[14px] leading-[1.55] text-[#5B5440]">Only verified, approved listings appear here. Check the spelling, or browse all places.</p>
        <a href="{{ route('properties.index') }}" class="mt-4 inline-flex items-center h-10 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13.5px] font-bold transition-colors">Browse all places</a>
    </section>
@elseif($total === 0)
    <section class="rounded-2xl border border-[#E2E4EC] bg-white px-5 py-8 text-center max-w-3xl">
        <p class="font-jakarta text-[17px] font-bold text-[#060D26]">Nothing matches yet</p>
        <p class="mt-1.5 text-[14px] leading-[1.55] text-[#5B6A8E]">{{ \App\Services\AiSearch\SummaryFormatter::html($summary, $linkable, false) }}</p>
        <a href="{{ $filtersUrl }}" class="mt-4 inline-flex items-center h-10 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13.5px] font-bold transition-colors">Browse with filters</a>
    </section>
@else
    @if($notice && $notice['kind'] === 'name')
        <section role="status" class="mb-4 max-w-3xl rounded-2xl border border-[#F5D78A] bg-[#FEF6E4] px-4 py-3.5">
            <p class="text-[14px] font-semibold text-[#060D26]">No listing or business called &ldquo;{{ $noticeName }}&rdquo; on AbangananHub.</p>
            <p class="mt-1 text-[13px] leading-[1.5] text-[#5B5440]">Other places that match the rest of your search:</p>
        </section>
    @endif
    <section aria-label="Summary" class="rounded-2xl bg-[#060D26] px-4 py-4 sm:px-5 text-white max-w-3xl lg:max-w-none lg:flex lg:items-center lg:justify-between lg:gap-6"
        x-data="{ english: false }">
        <div class="min-w-0 lg:max-w-3xl">
        <span class="flex items-center gap-1.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] {{ $exact_count > 0 ? 'text-[#FF8A66]' : 'text-[#FBBF24]' }}">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9.4l-4.7 1.8L12 16l-1.8-4.8L5.5 9.4l4.7-1.8z" /></svg>
            @if($exact_count === 0)
                No exact match
            @elseif($close === 0)
                {{ $exact_count }} exact {{ \Illuminate\Support\Str::plural('match', $exact_count) }}
            @else
                {{ $exact_count }} exact, {{ $close }} close
            @endif
        </span>
        <p class="mt-2 text-[14.5px] leading-[1.55] text-[#E7EAF3]" x-show="!english">{{ \App\Services\AiSearch\SummaryFormatter::html($summary, $linkable) }}</p>
        @if($showEnglishToggle)
            <p class="mt-2 text-[14.5px] leading-[1.55] text-[#E7EAF3]" x-show="english" x-cloak>{{ \App\Services\AiSearch\SummaryFormatter::html($summary_en, $linkable) }}</p>
        @endif
        @if($ai_summary)
            <p class="mt-3 text-[11.5px] text-[#8C95B0]">AI summary · check the details on each listing</p>
        @endif
        </div>
        <div class="mt-3 lg:mt-0 flex flex-wrap lg:flex-col lg:items-stretch gap-2 shrink-0">
            @if($raiseTo)
                <button type="button" x-on:click="raiseBudget({{ $raiseTo }})"
                    class="h-10 px-4 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13px] font-bold transition-colors cursor-pointer">Raise budget to ₱{{ number_format($raiseTo) }}</button>
            @endif
            @if($showEnglishToggle)
                <button type="button" x-on:click="english = !english"
                    class="h-10 px-4 rounded-full border border-white/35 text-white text-[13px] font-semibold hover:bg-white/10 cursor-pointer"
                    x-text="english ? 'Show original' : 'Show in English'"></button>
            @endif
        </div>
    </section>

    <div class="mt-5 mb-3 flex items-center justify-between gap-3">
        <span class="text-[13px] text-[#5B6A8E]">{{ $total }} {{ \Illuminate\Support\Str::plural('result', $total) }} · {{ $exact_count > 0 ? 'best fit first' : 'nearest fit first' }}</span>
        <a href="{{ $filtersUrl }}" class="lg:hidden text-[13px] font-semibold text-[#B35A3D] hover:underline">Refine with filters</a>
    </div>

    <div class="ai-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($rows as $i => $row)
            <x-property-card :property="$row['property']" :favorited-ids="$favoritedIds"
                :fit="['rank' => $i + 1, 'exact' => empty($row['misses']), 'hits' => $row['hits'], 'misses' => $row['misses']]" />
        @endforeach
    </div>
@endif

{{-- The business the sentence named: its other places, under the best fits. Not filtered, so a tenant who asked
     for one area still sees everything the business lists. --}}
@php
    $more = $more_from_host ?? null;
@endphp
@if($more)
    <section aria-label="More from {{ $more['name'] }}" class="{{ $total === 0 ? 'mt-6' : 'mt-8' }}">
        <h2 class="font-jakarta text-[17px] font-bold text-[#060D26]">More from {{ $more['name'] }}</h2>
        <p class="mt-0.5 mb-3 text-[13px] text-[#5B6A8E] max-w-3xl">
            {{ $more['total'] }} other {{ \Illuminate\Support\Str::plural('place', $more['total']) }} they list{{ $more['distances'] ? ', nearest to you first' : '' }}.
        </p>
        <div class="ai-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($more['properties'] as $property)
                <div>
                    <x-property-card :property="$property" :favorited-ids="$favoritedIds" />
                    @if(isset($more['distances'][$property->property_id]))
                        <p class="mt-1.5 px-1 text-[12.5px] font-medium text-[#5B6A8E]">{{ number_format($more['distances'][$property->property_id], 1) }} km from you</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if($more['total'] > $more['properties']->count())
            <a href="{{ route('landlord.profile.show', $more['landlord_id']) }}#properties"
                class="mt-4 inline-flex items-center h-10 px-5 rounded-full border border-[#060D26] bg-white text-[#060D26] text-[13.5px] font-semibold hover:bg-[#F7F8FC] transition-colors">
                See all places by {{ $more['name'] }} &rarr;
            </a>
        @endif
    </section>
@endif

@if($alternatives->isNotEmpty())
    <section aria-label="Other options" class="{{ $total === 0 ? 'mt-6' : 'mt-8' }}">
        <h2 class="font-jakarta text-[17px] font-bold text-[#060D26]">You might also like</h2>
        @if(filled($alt_note))
            <p class="mt-0.5 mb-3 text-[13px] text-[#5B6A8E] max-w-3xl">{{ $alt_note }}</p>
        @endif
        <div class="ai-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 {{ filled($alt_note) ? '' : 'mt-3' }}">
            @foreach($alternatives as $row)
                <x-property-card :property="$row['property']" :favorited-ids="$favoritedIds"
                    :fit="['rank' => null, 'exact' => false, 'hits' => $row['hits'], 'misses' => $row['misses'], 'suggestion' => true]" />
            @endforeach
        </div>
    </section>
@endif
