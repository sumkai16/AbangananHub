{{--
    Decorative background for a landing-page section: soft colour glows, a dot grid, diagonal
    hairlines and flowing curves. Purely visual — low contrast, not clickable, painted behind
    the content.

    The patterned layers (dots, hairlines, curves) live ONLY in the side gutters, i.e. the space
    left and right of the 1400px content column, so they never run underneath cards or text. On
    screens narrower than the column there is no gutter and only the soft glows remain.

    The parent must be `relative isolate overflow-hidden` so the layer sits above the section's
    own background but below its content.

    Props: compact — for short sections (How it works, landlord CTA): smaller glows, and the
    strips fill the section height instead of a fixed size.
--}}
@props(['compact' => false])

@php
    // Full class names spelled out on purpose: Tailwind only generates classes it can read
    // literally in source, so these can't be assembled from a direction variable.
    $fades = [
        'right' => '[mask-image:linear-gradient(to_right,#000,transparent)] [-webkit-mask-image:linear-gradient(to_right,#000,transparent)]',
        'left'  => '[mask-image:linear-gradient(to_left,#000,transparent)] [-webkit-mask-image:linear-gradient(to_left,#000,transparent)]',
    ];
    $dots = '[background-image:radial-gradient(#5B6A8E_1.2px,transparent_1.2px)] [background-size:20px_20px]';
    $blob = $compact ? 'w-[320px] h-[320px]' : 'w-[520px] h-[520px]';
@endphp

<div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
    {{-- Soft glows: blurred washes of colour, fine to sit behind content. --}}
    <div class="absolute -left-40 {{ $compact ? '-top-16' : 'top-8' }} {{ $blob }} rounded-full bg-[#FF8A66]/20 blur-3xl"></div>
    <div class="absolute -right-48 {{ $compact ? 'bottom-[-6rem]' : 'top-[38%]' }} {{ $compact ? 'w-[360px] h-[360px]' : 'w-[560px] h-[560px]' }} rounded-full bg-[#7C8CE0]/20 blur-3xl"></div>
    @unless($compact)
        <div class="absolute -left-32 bottom-16 w-[460px] h-[460px] rounded-full bg-[#FFC9B5]/30 blur-3xl"></div>
    @endunless

    {{-- Left gutter --}}
    <div class="absolute inset-y-0 left-0 w-[max(0px,calc((100%-1400px)/2))] overflow-hidden">
        <div class="absolute left-0 top-0 w-full {{ $compact ? 'h-full' : 'h-[560px]' }} opacity-70 {{ $dots }} {{ $fades['right'] }}"></div>
        <div class="absolute left-0 w-full {{ $compact ? 'bottom-0 h-[70%]' : 'top-[46%] h-[420px]' }} opacity-50 [background-image:repeating-linear-gradient(135deg,rgba(255,138,102,.55)_0_1px,transparent_1px_16px)] {{ $fades['right'] }}"></div>
        <svg class="absolute left-0 w-full {{ $compact ? 'top-[8%] h-[45%]' : 'top-[18%] h-[200px]' }} text-[#FF8A66]" viewBox="0 0 420 200" fill="none" preserveAspectRatio="none">
            <path d="M0 150C70 150 90 60 170 70S290 160 420 60" stroke="currentColor" stroke-opacity=".45" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            <path d="M0 176C80 176 110 96 190 104S300 184 420 96" stroke="currentColor" stroke-opacity=".25" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        </svg>
    </div>

    {{-- Right gutter --}}
    <div class="absolute inset-y-0 right-0 w-[max(0px,calc((100%-1400px)/2))] overflow-hidden">
        <div class="absolute right-0 bottom-0 w-full {{ $compact ? 'h-full' : 'h-[520px]' }} opacity-60 {{ $dots }} {{ $fades['left'] }}"></div>
        <div class="absolute right-0 top-0 w-full {{ $compact ? 'h-[70%]' : 'h-[360px]' }} opacity-40 [background-image:repeating-linear-gradient(135deg,rgba(6,13,38,.35)_0_1px,transparent_1px_16px)] {{ $fades['left'] }}"></div>
        <svg class="absolute right-0 w-full {{ $compact ? 'bottom-[8%] h-[45%]' : 'top-[62%] h-[200px]' }} text-[#5B6A8E]" viewBox="0 0 420 200" fill="none" preserveAspectRatio="none">
            <path d="M420 60C350 60 330 150 250 140S130 50 0 150" stroke="currentColor" stroke-opacity=".4" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            <path d="M420 34C340 34 320 118 240 108S120 24 0 118" stroke="currentColor" stroke-opacity=".22" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        </svg>
    </div>
</div>
