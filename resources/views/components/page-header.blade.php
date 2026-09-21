@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6']) }}>
    <div class="flex items-center gap-3.5">
        @isset($icon)
            <div class="w-11 h-11 rounded-xl bg-[#060D26] flex items-center justify-center shrink-0">
                {{ $icon }}
            </div>
        @endisset
        <div>
            @isset($badge)
                {{-- Status pill(s) ride beside the title, not out at the far edge with the actions. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <h1 class="text-2xl font-semibold text-[#060D26] tracking-tight">{{ $title }}</h1>
                    {{ $badge }}
                </div>
            @else
                <h1 class="text-2xl font-semibold text-[#060D26] tracking-tight">{{ $title }}</h1>
            @endisset
            @if($subtitle)
                <p class="text-sm text-[#5B6A8E] mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex items-center gap-2.5 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
