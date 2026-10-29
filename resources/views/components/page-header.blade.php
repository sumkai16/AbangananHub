@props([
    'title',
    'subtitle' => null,
    'iconPath' => null,
])

@push('mobile-title')
    @if($iconPath)
        <x-page-icon :path="$iconPath" class="!h-7 !w-7 sm:!h-7 sm:!w-7 !rounded-lg" />
    @elseif(isset($icon))
        <x-page-icon class="!h-7 !w-7 sm:!h-7 sm:!w-7 !rounded-lg">{{ $icon }}</x-page-icon>
    @endif
    <span class="truncate text-[15px] font-semibold tracking-tight text-[#060D26]">{{ $title }}</span>
@endpush

<div {{ $attributes->merge(['class' => (isset($actions) || isset($badge) ? '' : 'page-header-bare ').'flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-4 sm:mb-6']) }}>
    <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
        @if($iconPath)
            <x-page-icon :path="$iconPath" class="page-header-title" />
        @elseif(isset($icon))
            <x-page-icon class="page-header-title">{{ $icon }}</x-page-icon>
        @endif
        <div>
            @isset($badge)
                {{-- Status pill(s) ride beside the title, not out at the far edge with the actions. --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <h1 class="page-header-title text-xl sm:text-2xl font-semibold text-[#060D26] tracking-tight">{{ $title }}</h1>
                    {{ $badge }}
                </div>
            @else
                <h1 class="page-header-title text-xl sm:text-2xl font-semibold text-[#060D26] tracking-tight">{{ $title }}</h1>
            @endisset
            @if($subtitle)
                <p class="page-header-title hidden sm:block text-sm text-[#5B6A8E] mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex items-center gap-2.5 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
