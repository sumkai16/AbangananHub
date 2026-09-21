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
        ['label' => 'Bedspace', 'url' => route('properties.index', ['type' => 'Bedspace']), 'active' => $activeType === 'Bedspace',
         'icon' => 'M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14'],
        ['label' => 'Room', 'url' => route('properties.index', ['type' => 'Room']), 'active' => $activeType === 'Room',
         'icon' => 'M4 21h16M7 21V4a1 1 0 011-1h8a1 1 0 011 1v17M14 12h.01'],
        ['label' => 'Apartment', 'url' => route('properties.index', ['type' => 'Apartment']), 'active' => $activeType === 'Apartment',
         'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['label' => 'House', 'url' => route('properties.index', ['type' => 'House']), 'active' => $activeType === 'House',
         'icon' => 'M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6'],
        ['label' => 'Saved', 'url' => route('favorites.index'), 'active' => false,
         'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
    ];
@endphp

{{-- Filter chips: icon + label inline, active one filled. "Saved" is a shortcut, not a property type,
     so it sits apart at the far end behind a divider. The row scrolls sideways on narrow screens. --}}
@php
    $typeItems = array_values(array_filter($items, fn ($i) => $i['label'] !== 'Saved'));
    $saved = collect($items)->firstWhere('label', 'Saved');

    // Amenity shortcuts: the handful tenants filter on first. They drive the
    // same `amenities[]` param as the filters modal, so a chip and the modal
    // stay in sync. Ids are looked up by the (UNIQUE) name and cached — the
    // strip renders on every page and the ids never change.
    $amenityIcons = [
        'Wi-Fi'            => 'M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z',
        'Air Conditioning' => 'M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9',
        'Private Bathroom' => 'M12 3s6 6.5 6 11a6 6 0 11-12 0c0-4.5 6-11 6-11z',
        'Parking Space'    => 'M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2zM9 17V7h3.5a2.5 2.5 0 010 5H9',
        '24/7 Security'    => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
    ];
    $amenityIds = \Illuminate\Support\Facades\Cache::remember('category_strip_amenity_ids', 3600, fn () =>
        \App\Models\Amenity::whereIn('amenity_name', array_keys($amenityIcons))->pluck('amenity_id', 'amenity_name')->all());
    $activeAmenities = array_map('intval', (array) request('amenities', []));
    $amenityItems = [];
    foreach ($amenityIcons as $name => $icon) {
        if (! isset($amenityIds[$name])) {
            continue;
        }
        $id = (int) $amenityIds[$name];
        $active = in_array($id, $activeAmenities, true);
        $next = $active ? array_values(array_diff($activeAmenities, [$id])) : [...$activeAmenities, $id];
        $query = array_merge(request()->except(['page', 'amenities']), $next ? ['amenities' => $next] : []);
        $amenityItems[] = ['label' => $name, 'icon' => $icon, 'active' => $active, 'url' => route('properties.index', $query)];
    }
@endphp
<nav aria-label="Property type" class="flex items-center gap-3 py-3">
    {{-- pr-8 + the right-edge fade: chips that overflow dissolve before the Saved divider instead of
         being sliced against it. When everything fits, the padding keeps the last chip clear of the fade. --}}
    <div class="flex items-center gap-2 overflow-x-auto min-w-0 pr-8 [mask-image:linear-gradient(to_right,#000_calc(100%-40px),transparent)] [-ms-overflow-style:none] [scrollbar-width:none]">
        @foreach($typeItems as $item)
            <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                class="flex-shrink-0 inline-flex items-center gap-2 h-10 px-4 rounded-full border text-[14px] font-semibold whitespace-nowrap transition-colors duration-200 {{ $item['active'] ? 'bg-[#060D26] border-[#060D26] text-white' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] hover:border-[#060D26]/40 hover:text-[#060D26]' }}">
                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="{{ $item['active'] ? 'text-[#FF8A66]' : '' }}" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach

        @if($amenityItems)
            <span class="flex-shrink-0 h-6 w-px bg-[#E2E4EC] mx-1" aria-hidden="true"></span>
            @foreach($amenityItems as $item)
                <a href="{{ $item['url'] }}" aria-pressed="{{ $item['active'] ? 'true' : 'false' }}"
                    class="flex-shrink-0 inline-flex items-center gap-2 h-10 px-4 rounded-full border text-[14px] font-semibold whitespace-nowrap transition-colors duration-200 {{ $item['active'] ? 'bg-[#FF8A66] border-[#FF8A66] text-[#060D26]' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] hover:border-[#060D26]/40 hover:text-[#060D26]' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endif
    </div>

    @if($saved)
        <a href="{{ $saved['url'] }}"
            class="ml-auto flex-shrink-0 inline-flex items-center gap-2 h-10 pl-4 pr-1 sm:pr-2 border-l border-[#E2E4EC] text-[14px] font-semibold text-[#5B6A8E] hover:text-[#B35A3D] transition-colors duration-200">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $saved['icon'] }}" />
            </svg>
            <span class="hidden sm:inline">Saved</span><span class="sr-only sm:hidden">Saved</span>
        </a>
    @endif
</nav>
