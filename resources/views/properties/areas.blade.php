@extends('layouts.app', ['searchBar' => false])

@section('title', 'All areas — AbangananHub')

@section('content')
    @php
        // Filtering and sorting run client-side over the server-rendered tiles, so the full
        // grid is in the HTML (no JS needed to see every area) and Alpine only hides/reorders.
        $alphaRank = $areas->pluck('name')->sort()->values()->flip();
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-16 min-h-[60vh]"
        x-data="{
            q: '',
            sort: 'popular',
            names: @js($areas->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::lower($n))->values()),
            get needle() { return this.q.trim().toLowerCase(); },
            get shown() { return this.names.filter(n => n.includes(this.needle)).length; },
            matches(i) { return this.names[i].includes(this.needle); },
        }">

        {{-- Title on the left, the tools on the right of the same row, so the grid starts sooner. --}}
        <div class="mb-5 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-2 font-jakarta text-[12px] font-semibold uppercase tracking-[0.08em] text-[#5B6A8E]"><span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>All areas</span>
                <h1 class="mt-2 font-jakarta text-[24px] sm:text-[32px] font-bold leading-tight text-[#060D26]">Every neighborhood, <span class="text-[#5B6A8E]">one place.</span></h1>
                <p class="mt-1.5 text-[13.5px] text-[#5B6A8E] tabular-nums" aria-live="polite">
                    <span x-show="!needle">{{ $areas->count() }} {{ Str::plural('area', $areas->count()) }} &middot; {{ $areas->sum('count') }} listings across Cebu</span>
                    <span x-show="needle" x-cloak><span x-text="shown"></span> of {{ $areas->count() }} areas</span>
                </p>
            </div>

            {{-- Find + order: 45 tiles is too many to scan by eye. --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 lg:shrink-0">
                <div class="relative sm:w-72 xl:w-80">
                    <label for="area-search" class="sr-only">Search areas</label>
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#5B6A8E] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" /></svg>
                    <input id="area-search" type="search" x-model="q" autocomplete="off" placeholder="Search an area, e.g. Moalboal"
                        class="w-full h-11 pl-10 pr-10 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200">
                    <button type="button" x-show="q" x-cloak @click="q = ''" aria-label="Clear search"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full flex items-center justify-center text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="inline-flex p-1 rounded-xl bg-[#ECEEF6] self-start sm:self-auto" role="group" aria-label="Sort areas">
                    @foreach (['popular' => 'Most listings', 'alpha' => 'A–Z'] as $key => $text)
                        <button type="button" @click="sort = '{{ $key }}'" :aria-pressed="sort === '{{ $key }}'"
                            :class="sort === '{{ $key }}' ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                            class="h-9 px-3.5 rounded-lg text-[13px] font-semibold transition-colors duration-200 cursor-pointer">{{ $text }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
            @foreach($areas as $area)
                <a href="{{ $area['url'] }}"
                    x-show="matches({{ $loop->index }})"
                    :style="{ order: sort === 'alpha' ? {{ $alphaRank[$area['name']] }} : {{ $loop->index }} }"
                    class="relative h-36 sm:h-40 rounded-2xl overflow-hidden group border border-[#E2E4EC] bg-[#ECEEF6] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#FF8A66] hover:shadow-[0_10px_24px_rgba(6,13,38,0.14)] motion-reduce:hover:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                    @if($area['photo'])
                        {{-- Tiles are ~280px wide at most; the controller hands over an 800px URL for the landing strip. --}}
                        <img src="{{ \App\Support\Images::resize($area['photo'], 480) }}" alt="" loading="lazy" decoding="async"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 motion-reduce:group-hover:scale-100">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/90 via-[#060D26]/35 to-transparent" aria-hidden="true"></div>
                    <div class="absolute bottom-3 left-3 right-3">
                        <p class="text-white font-jakarta text-[15px] sm:text-[16px] font-bold leading-tight truncate [text-shadow:0_1px_6px_rgba(6,13,38,0.5)]">{{ $area['name'] }}</p>
                        <p class="mt-1 inline-flex font-jakarta text-[12px] font-semibold text-white px-2.5 py-0.5 rounded-full border border-[#FF8A66]/70 bg-[#060D26]/40">
                            {{ $area['count'] }} {{ Str::plural('listing', $area['count']) }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Nothing matches the search. --}}
        <div x-show="shown === 0" x-cloak class="py-16 text-center">
            <p class="text-[16px] font-semibold text-[#060D26]">No area matches “<span x-text="q.trim()"></span>”</p>
            <p class="mt-1.5 text-[13.5px] text-[#5B6A8E]">Check the spelling, or clear the search to see every area.</p>
            <button type="button" @click="q = ''"
                class="mt-5 h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#E2E4EC] bg-white hover:bg-[#ECEEF6] text-[#060D26] text-sm font-semibold transition-colors duration-200 cursor-pointer">
                Clear search
            </button>
        </div>
    </div>
@endsection
