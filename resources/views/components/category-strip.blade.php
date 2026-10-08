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
         'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
        ['label' => 'Condominium', 'url' => route('properties.index', ['type' => 'Condominium']), 'active' => $activeType === 'Condominium',
         'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21'],
        ['label' => 'House', 'url' => route('properties.index', ['type' => 'House']), 'active' => $activeType === 'House',
         'icon' => 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6'],
        ['label' => 'Boarding House', 'url' => route('properties.index', ['type' => 'Boarding House']), 'active' => $activeType === 'Boarding House',
         'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z'],
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
    <div class="flex flex-1 items-center gap-2 overflow-x-auto min-w-0 pr-8 xl:pr-0 [mask-image:linear-gradient(to_right,#000_calc(100%-40px),transparent)] xl:[mask-image:none] [-ms-overflow-style:none] [scrollbar-width:none]">
        @foreach($typeItems as $item)
            <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                class="flex-shrink-0 grow inline-flex items-center justify-center gap-2 h-10 px-4 rounded-full border text-[14px] font-semibold whitespace-nowrap transition-colors duration-200 {{ $item['active'] ? 'bg-[#060D26] border-[#060D26] text-white' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] hover:border-[#060D26]/40 hover:text-[#060D26]' }}">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="{{ $item['active'] ? 'text-[#FF8A66]' : '' }}" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach

    </div>

    {{-- Filters + Show map live here on the browse page (large screens, where this band is sticky) so they stay in
         reach while scrolling. They only dispatch window events; the page's Alpine root owns the state. Below `lg`
         the results toolbar keeps its own Filters button and the List/Map tabs cover the map.

         Every control is an icon-only 40px circle so the type chips get the room; the name appears as a small
         tooltip on hover or keyboard focus (and as the aria-label, so screen readers always have it). The tooltip
         floats over the page rather than growing the button, so nothing beside it shifts. --}}
    @php
        $iconBtn = 'group relative inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border bg-white text-[#060D26] transition-colors duration-200 cursor-pointer hover:border-[#060D26]/40 hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2';
        $tip = 'pointer-events-none absolute top-[calc(100%+8px)] z-40 whitespace-nowrap rounded-lg bg-[#1e293b] px-2.5 py-1.5 text-[12px] font-medium text-white shadow-lg opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-hover:delay-200 group-focus-visible:opacity-100';
        $tipCenter = $tip . ' left-1/2 -translate-x-1/2';
        $tipEnd = $tip . ' right-0';
    @endphp
    @if(request()->routeIs('properties.index'))
        <div class="hidden lg:flex flex-shrink-0 items-center gap-2 pl-3 border-l border-[#E2E4EC]"
            x-data="{ mapVisible: window.browseMapVisible ? window.browseMapVisible() : false }" @browse-map-state.window="mapVisible = $event.detail">
            @php
                // Sort options are plain links to /properties, so browse-live.js turns them into in-place updates
                // (and they still work without JS). "Clear all" lives in the "Filtering by" row below, not here.
                $sortOptions = [
                    'newest'     => 'Newest',
                    'price_low'  => 'Price: low to high',
                    'price_high' => 'Price: high to low',
                    'top_rated'  => 'Top rated',
                ];
                $currentSort = array_key_exists((string) request('sort'), $sortOptions) ? request('sort') : 'newest';
            @endphp
            {{-- Sort --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                    aria-label="Sort: {{ $sortOptions[$currentSort] }}"
                    class="{{ $iconBtn }} {{ $currentSort !== 'newest' ? 'border-[#060D26]' : 'border-[#E2E4EC]' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                    </svg>
                    @if($currentSort !== 'newest')
                        <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[#FF8A66] ring-2 ring-white" aria-hidden="true"></span>
                    @endif
                    <span class="{{ $tipCenter }}" :class="open ? '!hidden' : ''" aria-hidden="true">Sort: {{ $sortOptions[$currentSort] }}</span>
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

            {{-- Filters --}}
            @php $stripFilterCount = \App\Support\BrowseFilters::activeCount(request()); @endphp
            <button type="button" @click="$dispatch('browse-open-filters')"
                aria-label="Filters{{ $stripFilterCount > 0 ? ', '.$stripFilterCount.' active' : '' }}"
                class="{{ $iconBtn }} {{ $stripFilterCount > 0 ? 'border-[#060D26]' : 'border-[#E2E4EC]' }}">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                @if($stripFilterCount > 0)
                    <span class="absolute -right-1.5 -top-1.5 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[#FF8A66] px-1.5 text-[11px] font-bold tabular-nums text-[#060D26] ring-2 ring-white">{{ $stripFilterCount }}</span>
                @endif
                <span class="{{ $tipCenter }}" aria-hidden="true">Filters{{ $stripFilterCount > 0 ? ' · '.$stripFilterCount.' active' : '' }}</span>
            </button>

            {{-- Map --}}
            <button type="button" @click="$dispatch('browse-toggle-map')" :aria-pressed="mapVisible.toString()"
                :aria-label="mapVisible ? 'Hide map' : 'Show map'"
                class="{{ $iconBtn }}"
                :class="mapVisible ? 'border-[#060D26] bg-[#ECEEF6]' : 'border-[#E2E4EC]'">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.007 0z" />
                </svg>
                <span class="{{ $tipCenter }}" x-text="mapVisible ? 'Hide map' : 'Show map'" aria-hidden="true">Show map</span>
            </button>
        </div>
    @endif
    @if($saved)
        <div class="flex-shrink-0 border-l border-[#E2E4EC] pl-3">
            <a href="{{ $saved['url'] }}" aria-label="Saved"
                class="group relative inline-flex h-10 w-10 items-center justify-center rounded-full text-[#5B6A8E] transition-colors duration-200 hover:bg-[#ECEEF6] hover:text-[#B35A3D] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $saved['icon'] }}" />
                </svg>
                <span class="{{ $tipEnd }}" aria-hidden="true">Saved</span>
            </a>
        </div>
    @endif
</nav>
