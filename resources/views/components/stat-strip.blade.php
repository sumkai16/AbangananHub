@props([
    // Each cell: ['label' => string, 'value' => int|string, 'note' => string, 'dot' => '#hex'|null, 'href' => string|null, 'active' => bool]
    'cells',
    // Force the column count on lg+ (2 = a 2x2 block). Null = one column per cell, up to 5.
    'columns' => null,
])

{{--
    One card, one row of label / big number / short note, columns split by hairlines.
    Used where a page needs a few headline numbers (Units, Dashboard) instead of a
    grid of separate stat tiles. The gap-px grid draws the dividers, so the cells
    wrap cleanly to 2 per row on phones without extra border logic. A cell with an
    `href` is a link (hover + optional active state), otherwise a plain block.
--}}
@php
    $cols = [2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][$columns ?? min(max(count($cells), 2), 5)];
@endphp

<x-card flush {{ $attributes }}>
    <div class="grid grid-cols-2 gap-px bg-[#E2E4EC] {{ $cols }}">
        @foreach($cells as $cell)
            @php
                $tag = ! empty($cell['href']) ? 'a' : 'div';
                $isActive = ! empty($cell['active']);
            @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ $cell['href'] }}" @endif @if($isActive) aria-current="true" @endif
                class="block px-5 py-4 sm:px-6 sm:py-5 {{ $isActive ? 'bg-[#F7F8FC]' : 'bg-white' }} {{ $tag === 'a' ? 'transition-colors duration-200 hover:bg-[#F7F8FC]' : '' }}">
                <p class="flex items-center gap-2 text-[13px] font-semibold text-[#5B6A8E]">
                    @if(! empty($cell['dot']))<span class="h-2 w-2 rounded-full" style="background: {{ $cell['dot'] }}" aria-hidden="true"></span>@endif
                    {{ $cell['label'] }}
                </p>
                <p class="mt-1.5 text-[30px] font-semibold leading-none tabular-nums text-[#060D26]">{{ $cell['value'] }}</p>
                @if(! empty($cell['note']))
                    <p class="mt-2 text-[13px] text-[#5B6A8E]">{{ $cell['note'] }}</p>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</x-card>
