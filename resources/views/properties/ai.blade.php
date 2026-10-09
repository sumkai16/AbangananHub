@extends('layouts.app', ['title' => 'Search results — AbangananHub'])

{{--
    A described search ("room near USC under 5k"). The shell renders at once; the aiSearch Alpine
    component (resources/js/ai-search.js) reads the sentence, fills the "Searching for" chips, then
    swaps in properties/partials/ai-results. Layout: DESIGN.md §32, canvas "A+".
--}}
@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16 min-h-[60vh]"
        x-data="aiSearch({ query: @js($query), interpretUrl: @js(route('search.interpret')), resultsUrl: @js(route('search.results')) })">
        <h1 class="sr-only">Search results</h1>

        {{-- The sentence being searched --}}
        <div class="flex items-start gap-2.5 px-3.5 py-3 bg-white border border-[#E2E4EC] rounded-2xl max-w-3xl">
            <svg class="w-4 h-4 mt-0.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.6L18.5 9.4l-4.7 1.8L12 16l-1.8-4.8L5.5 9.4l4.7-1.8z" /></svg>
            <p class="flex-1 min-w-0 text-[14px] leading-[1.45] text-[#060D26] line-clamp-3">{{ $query }}</p>
            <button type="button" x-show="loading" x-cloak x-on:click="cancel()"
                class="h-8 px-2.5 text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26] cursor-pointer">Cancel</button>
        </div>

        {{-- Searching for: what was understood. A removable chip per filter; amber = nothing matched it. --}}
        <div class="mt-4" x-show="chips.length || phase === 'reading'" x-cloak>
            <span class="block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-[#5B6A8E] mb-2">Searching for</span>
            <div class="flex flex-wrap gap-1.5" aria-live="polite">
                <template x-for="chip in chips" :key="chip.label">
                    <span class="inline-flex items-center h-8 pl-2.5 pr-1 rounded-full text-[13px] gap-1.5 border"
                        :class="chip.state === 'miss' ? 'bg-[#FEF6E4] border-[#D99A00] text-[#6B4100] font-semibold' : 'bg-white border-[#060D26] text-[#060D26] font-medium'">
                        <svg x-show="chip.state === 'miss'" class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l9 16H3z" /><path d="M12 10v4M12 17v.01" /></svg>
                        <span x-show="chip.state !== 'miss' && chip.icon === 'peso'" class="font-bold text-[#B35A3D]" aria-hidden="true">₱</span>
                        <svg x-show="chip.state !== 'miss' && chip.icon === 'pin'" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
                        <svg x-show="chip.state !== 'miss' && chip.icon === 'home'" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V9l8-6 8 6v12z" /></svg>
                        <svg x-show="chip.state !== 'miss' && ['amenity', 'rule', 'people'].includes(chip.icon)" class="w-3.5 h-3.5 text-[#B35A3D] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                        <span x-text="chip.label"></span>
                        <button type="button" x-on:click="removeChip(chip)" :aria-label="'Remove ' + chip.label"
                            class="w-7 h-7 grid place-items-center rounded-full text-[#5B6A8E] hover:text-[#060D26] hover:bg-black/5 cursor-pointer">
                            <svg class="w-[11px] h-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </span>
                </template>
                <span x-show="phase === 'reading'" class="h-8 w-24 rounded-full bg-[#ECEEF6] animate-pulse" aria-hidden="true"></span>
                <a href="{{ route('properties.index') }}" x-show="chips.length" class="inline-flex items-center h-8 px-3 rounded-full border border-dashed border-[#5B6A8E] text-[13px] text-[#060D26] hover:bg-white" title="Add more with the full filter panel">+ Add</a>
            </div>
        </div>

        {{-- Keyword results are already showing; the AI is still reading the full sentence --}}
        <p x-show="upgrading" x-cloak role="status" class="mt-3 inline-flex items-center gap-2 text-[13px] text-[#5B6A8E]">
            <span class="w-3.5 h-3.5 rounded-full border-2 border-[#FF8A66] border-r-[#FF8A66]/25 animate-spin" aria-hidden="true"></span>
            Reading your full request with AI… these are quick matches for now.
        </p>

        {{-- Smart search unavailable / limited: keyword results below --}}
        <section x-show="notice" x-cloak role="status" class="mt-4 max-w-3xl rounded-2xl border border-[#F5D78A] bg-[#FEF6E4] px-4 py-3.5">
            <div class="flex items-start gap-2.5">
                <svg class="w-[18px] h-[18px] mt-px text-[#8A5300] flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16.5v.01" /></svg>
                <div class="min-w-0">
                    <p class="text-[14px] font-semibold text-[#060D26]" x-text="notice?.[0]"></p>
                    <p class="mt-1 text-[13px] leading-[1.5] text-[#5B5440]" x-text="notice?.[1]"></p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" x-on:click="retry()" class="h-10 px-4 rounded-full bg-[#060D26] text-white text-[13px] font-semibold cursor-pointer">Retry</button>
                        <a href="{{ route('properties.index') }}" class="inline-flex items-center h-10 px-4 rounded-full border border-[#060D26] bg-white text-[#060D26] text-[13px] font-semibold">Use Filters</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Loading: same shape as the results so nothing jumps --}}
        <div x-show="loading" x-cloak class="mt-5" aria-hidden="true">
            <div class="rounded-2xl bg-[#060D26] px-4 py-4 max-w-3xl">
                <span class="flex items-center gap-2 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-[#FF8A66]">
                    <span class="w-3.5 h-3.5 rounded-full border-2 border-[#FF8A66] border-r-[#FF8A66]/25 animate-spin"></span>
                    <span x-text="phase === 'reading' ? 'Reading your request…' : 'Checking listings…'"></span>
                </span>
                <div class="mt-3 h-3 w-11/12 rounded-md bg-white/15"></div>
                <div class="mt-2 h-3 w-8/12 rounded-md bg-white/10"></div>
            </div>
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach(range(1, 3) as $n)
                    <div class="rounded-2xl border border-[#E2E4EC] bg-white overflow-hidden animate-pulse">
                        <div class="aspect-[4/3] bg-[#ECEEF6]"></div>
                        <div class="p-4 space-y-2.5"><div class="h-4 w-20 rounded-full bg-[#ECEEF6]"></div><div class="h-4 w-4/5 rounded-md bg-[#ECEEF6]"></div><div class="h-3 w-3/5 rounded-md bg-[#F1F2F7]"></div></div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Results, rendered by the server --}}
        <div x-show="phase === 'done'" x-cloak x-html="html" class="mt-5"></div>

        <div x-show="phase === 'error' && !notice" x-cloak class="mt-6 text-[14px] text-[#5B6A8E]">Search cancelled. <button type="button" class="font-semibold text-[#B35A3D] underline cursor-pointer" x-on:click="retry()">Search again</button></div>
    </div>

@push('scripts')
    @include('properties.partials.favorite-toggle-script')
@endpush
@endsection
