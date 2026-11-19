@props(['title', 'subtitle' => null, 'iconPath' => null])
{{--
    The one title row on admin pages: title, status chips and a short line on the left, actions on the right, all in a
    single band so the work starts straight under it. iconPath is accepted and ignored: the sidebar already names the page.
--}}
<div {{ $attributes->class('mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2') }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
            <h1 class="text-[20px] font-semibold leading-7 tracking-tight text-[#060D26]">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @if($subtitle)
            <p class="text-[13px] leading-5 text-[#5B6A8E]">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
