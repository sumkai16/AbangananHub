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
@endphp

@if($total === 0)
    <section class="rounded-2xl border border-[#E2E4EC] bg-white px-5 py-8 text-center max-w-3xl">
        <p class="font-jakarta text-[17px] font-bold text-[#060D26]">Nothing matches yet</p>
        <p class="mt-1.5 text-[14px] text-[#5B6A8E]">{{ $summary }}</p>
        <a href="{{ $filtersUrl }}" class="mt-4 inline-flex items-center h-10 px-5 rounded-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-[13.5px] font-bold transition-colors">Browse with filters</a>
    </section>
@else
    <section aria-label="Summary" class="rounded-2xl bg-[#060D26] px-4 py-4 sm:px-5 text-white max-w-3xl"
        x-data="{ english: false }">
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
        <p class="mt-2 text-[14.5px] leading-[1.55] text-[#E7EAF3]" x-show="!english">{{ $summary }}</p>
        @if($showEnglishToggle)
            <p class="mt-2 text-[14.5px] leading-[1.55] text-[#E7EAF3]" x-show="english" x-cloak>{{ $summary_en }}</p>
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
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
        @if($ai_summary)
            <p class="mt-3 text-[11.5px] text-[#8C95B0]">AI summary · check the details on each listing</p>
        @endif
    </section>

    <div class="mt-5 mb-3 flex items-center justify-between gap-3">
        <span class="text-[13px] text-[#5B6A8E]">{{ $total }} {{ \Illuminate\Support\Str::plural('result', $total) }} · {{ $exact_count > 0 ? 'best fit first' : 'nearest fit first' }}</span>
        <a href="{{ $filtersUrl }}" class="text-[13px] font-semibold text-[#B35A3D] hover:underline">Refine with filters</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($rows as $i => $row)
            <x-property-card :property="$row['property']" :favorited-ids="$favoritedIds"
                :fit="['rank' => $i + 1, 'exact' => empty($row['misses']), 'hits' => $row['hits'], 'misses' => $row['misses']]" />
        @endforeach
    </div>
@endif
