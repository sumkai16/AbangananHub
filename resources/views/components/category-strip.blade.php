{{--
    Property-type quick filters. Shared by the sticky header and the browse
    page so the two can't fall out of step on which types exist.

    No `variant` prop: both placements render identically, and a prop that
    changes nothing is just a lie about the component's surface.

    Active state is derived from the request here. The markup previously
    carried `category-link` + `data-type` hooks for JS that was never written,
    so the strip never showed which filter was on — clicking Bedspace looked
    identical to browsing everything. Server-side is the right home for it
    anyway: the state is already in the URL.
--}}

@php
    $activeType = request('type');
    $noFilter = ! $activeType;

    $items = [
        ['label' => 'All', 'url' => route('properties.index'), 'active' => $noFilter,
         'icon' => 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z'],
        ['label' => 'Apartment', 'url' => route('properties.index', ['type' => 'Apartment']), 'active' => $activeType === 'Apartment',
         'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['label' => 'Condominium', 'url' => route('properties.index', ['type' => 'Condominium']), 'active' => $activeType === 'Condominium',
         'icon' => 'M8 21V4a1 1 0 011-1h6a1 1 0 011 1v17M4 21V10a1 1 0 011-1h3M20 21V12a1 1 0 00-1-1h-3M3 21h18M11 7h2m-2 4h2m-2 4h2'],
        ['label' => 'House', 'url' => route('properties.index', ['type' => 'House']), 'active' => $activeType === 'House',
         'icon' => 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6'],
        ['label' => 'Boarding House', 'url' => route('properties.index', ['type' => 'Boarding House']), 'active' => $activeType === 'Boarding House',
         'icon' => 'M4 21h16M7 21V4a1 1 0 011-1h8a1 1 0 011 1v17M14 12h.01'],
        ['label' => 'Bedspace', 'url' => route('properties.index', ['type' => 'Bedspace']), 'active' => $activeType === 'Bedspace',
         'icon' => 'M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14'],
        ['label' => 'Saved', 'url' => route('favorites.index'), 'active' => false,
         'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ];
@endphp

{{-- Filter chips: icon + label inline, active one filled. "Saved" is a shortcut, not a property type,
     so it sits apart at the far end behind a divider. The row scrolls sideways on narrow screens. --}}
@php
    $typeItems = array_values(array_filter($items, fn ($i) => $i['label'] !== 'Saved'));
    $saved = collect($items)->firstWhere('label', 'Saved');


@endphp
<nav id="browse-category-strip" aria-label="Property type" class="flex items-center gap-3 py-3">
    {{-- pr-8 + the right-edge fade: chips that overflow dissolve before the Saved divider instead of
         being sliced against it. When everything fits, the padding keeps the last chip clear of the fade. --}}
    <div class="flex flex-1 items-center gap-2 overflow-x-auto min-w-0 pr-8 [mask-image:linear-gradient(to_right,#000_calc(100%-40px),transparent)] [-ms-overflow-style:none] [scrollbar-width:none]">
        @foreach($typeItems as $item)
            <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                class="flex-shrink-0 inline-flex items-center gap-2 h-10 px-4 rounded-full border text-[14px] font-semibold whitespace-nowrap transition-colors duration-200 {{ $item['active'] ? 'bg-[#060D26] border-[#060D26] text-white' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] hover:border-[#060D26]/40 hover:text-[#060D26]' }}">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="{{ $item['active'] ? 'text-[#FF8A66]' : '' }}" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach

    </div>

    {{-- Filters + Show map live here on the browse page (large screens, where this band is sticky) so they stay in
         reach while scrolling. They only dispatch window events; the page's Alpine root owns the state. Below `lg`
         the results toolbar keeps its own Filters button and the List/Map tabs cover the map. --}}
    @if(request()->routeIs('properties.index'))
        <div class="hidden lg:flex flex-shrink-0 items-center gap-2 pl-3 border-l border-[#E2E4EC]"
            x-data="{ mapVisible: window.browseMapVisible ? window.browseMapVisible() : false }" @browse-map-state.window="mapVisible = $event.detail">
            @php
                // Sort + Clear all fill the strip's spare width. Both are plain links to /properties, so browse-live.js
                // turns them into in-place updates (and they still work without JS).
                $sortOptions = [
                    'newest'     => 'Newest',
                    'price_low'  => 'Price: low to high',
                    'price_high' => 'Price: high to low',
                    'top_rated'  => 'Top rated',
                ];
                $currentSort = array_key_exists((string) request('sort'), $sortOptions) ? request('sort') : 'newest';
                $stripHasFilters = collect(array_merge(['location', 'type', 'price_min', 'price_max', 'verified', 'amenities'], \App\Support\BrowseFilters::KEYS))
                    ->contains(fn ($key) => request()->filled($key));
            @endphp
            @if($stripHasFilters)
                <a href="{{ route('properties.index', request()->only('sort')) }}"
                    class="inline-flex items-center h-10 px-3 rounded-full text-[14px] font-semibold text-[#DC2626] hover:bg-[#EF4444]/[0.07] transition-colors duration-200">
                    Clear all
                </a>
            @endif
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                    class="inline-flex items-center gap-2 h-10 px-4 rounded-full border border-[#E2E4EC] bg-white text-[14px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                    </svg>
                    <span class="hidden xl:inline text-[#5B6A8E] font-medium">Sort</span>
                    <span>{{ $sortOptions[$currentSort] }}</span>
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="text-[#5B6A8E] transition-transform duration-200" :class="open ? 'rotate-180' : ''" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                    class="absolute right-0 top-[calc(100%+8px)] z-50 w-56 rounded-xl bg-white py-1 shadow-[0_4px_24px_rgba(0,0,0,0.12)] ring-1 ring-black/5">
                    @foreach($sortOptions as $sortKey => $sortLabel)
                        <a href="{{ route('properties.index', array_merge(request()->except(['sort', 'page']), $sortKey === 'newest' ? [] : ['sort' => $sortKey])) }}"
                            @if($sortKey === $currentSort) aria-current="true" @endif
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-[14px] transition-colors duration-200 hover:bg-[#ECEEF6] {{ $sortKey === $currentSort ? 'font-semibold text-[#060D26]' : 'text-[#060D26]' }}">
                            {{ $sortLabel }}
                            @if($sortKey === $currentSort)
                                <svg class="w-4 h-4 text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
            <button type="button" @click="$dispatch('browse-open-filters')"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-full border border-[#E2E4EC] bg-white text-[14px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                Filters
                @php $stripFilterCount = \App\Support\BrowseFilters::activeCount(request()); @endphp
                @if($stripFilterCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-[#FF8A66] text-[#060D26] text-[11px] font-bold tabular-nums">{{ $stripFilterCount }}</span>
                @endif
            </button>
            <button type="button" @click="$dispatch('browse-toggle-map')" :aria-pressed="mapVisible.toString()"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-full border bg-white text-[14px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer"
                :class="mapVisible ? 'border-[#060D26]' : 'border-[#E2E4EC]'">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.007 0z" />
                </svg>
                <span x-text="mapVisible ? 'Hide map' : 'Show map'">Show map</span>
            </button>
        </div>
    @endif
    @if($saved)
        <a href="{{ $saved['url'] }}"
            class="flex-shrink-0 inline-flex items-center gap-2 h-10 pl-4 pr-1 sm:pr-2 border-l border-[#E2E4EC] text-[14px] font-semibold text-[#5B6A8E] hover:text-[#B35A3D] transition-colors duration-200">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $saved['icon'] }}" />
            </svg>
            <span class="hidden sm:inline">Saved</span><span class="sr-only sm:hidden">Saved</span>
        </a>
    @endif
</nav>
