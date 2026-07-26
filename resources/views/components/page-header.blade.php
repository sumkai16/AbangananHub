@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6']) }}>
    <div class="flex items-center gap-3.5">
        @isset($icon)
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                {{ $icon }}
            </div>
        @endisset
        <div>
            <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-[#64748B] mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex items-center gap-2.5 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
