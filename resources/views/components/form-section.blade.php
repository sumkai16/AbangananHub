@props([
    'title',
    'description' => null,
])

{{--
    One titled section of a create/edit form (DESIGN.md §6c): a flat card with the
    navy icon tile + title the page headers use, so every form reads the same way.
    Pass the tile glyph through the `icon` slot; leave it out for a plain heading.
--}}
<x-card {{ $attributes }}>
    <div class="flex items-start gap-3 mb-5">
        @isset($icon)
            <div class="w-8 h-8 rounded-lg bg-[#060D26] flex items-center justify-center shrink-0">
                {{ $icon }}
            </div>
        @endisset
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-[#060D26] leading-tight">{{ $title }}</h2>
            @if($description)
                <p class="text-[12.5px] text-[#5B6A8E] mt-1 leading-snug">{{ $description }}</p>
            @endif
        </div>
    </div>

    {{ $slot }}
</x-card>
