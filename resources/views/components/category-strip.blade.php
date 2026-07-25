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
    $onVerified = request()->boolean('verified');
    $noFilter = ! $activeType && ! $onVerified;

    $items = [
        ['label' => 'All', 'url' => route('properties.index'), 'active' => $noFilter,
         'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['label' => 'Bedspace', 'url' => route('properties.index', ['type' => 'Bedspace']), 'active' => $activeType === 'Bedspace',
         'icon' => 'M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14'],
        ['label' => 'Room', 'url' => route('properties.index', ['type' => 'Room']), 'active' => $activeType === 'Room',
         'icon' => 'M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4'],
        ['label' => 'Apartment', 'url' => route('properties.index', ['type' => 'Apartment']), 'active' => $activeType === 'Apartment',
         'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['label' => 'House', 'url' => route('properties.index', ['type' => 'House']), 'active' => $activeType === 'House',
         'icon' => 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6'],
        ['label' => 'Verified', 'url' => route('properties.index', ['verified' => 1]), 'active' => $onVerified,
         'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Saved', 'url' => route('favorites.index'), 'active' => false,
         'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ];
@endphp

{{-- Centred from `md` up. It stays `justify-start` below that because the row
     scrolls on narrow screens, and centring overflowing content pins the first
     item off the left edge where it can't be scrolled back to. --}}
<div class="flex items-center justify-start md:justify-center gap-4 sm:gap-6 md:gap-8 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none]">
    @foreach($items as $item)
        <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
            class="flex flex-col items-center gap-1.5 pb-3 border-b-2 transition-all min-w-[56px] cursor-pointer {{ $item['active'] ? 'border-[#1F2937] text-[#1F2937]' : 'border-transparent text-[#94A3B8] hover:text-[#1F2937] hover:border-[#E2E8F0]' }}">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="{{ $item['active'] ? '2.4' : '2' }}" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
            </svg>
            <span class="text-[12px] font-semibold whitespace-nowrap">{{ $item['label'] }}</span>
        </a>
    @endforeach
</div>
