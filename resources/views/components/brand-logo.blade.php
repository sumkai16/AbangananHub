{{--
    The AbangananHub logo: house mark + wordmark. The one place it is drawn, so every page
    shows the same lockup.

    tone="auto"    for light surfaces; swaps to the white-outline mark in dark mode (html.dark),
                   because the default mark is a navy outline that vanishes on navy.
    tone="on-dark" for surfaces that are always dark (footer, auth panel): white-outline mark and
                   a white wordmark in both themes.
    The caller supplies the link (<a>) so each page keeps its own destination.
--}}
@props([
    'tone' => 'auto',
    'mark' => 'w-9 h-9',
    'text' => 'text-[18px]',
    'gap' => 'gap-2.5',
    'wordmark' => true,
])
@php
    $onDark = $tone === 'on-dark';
    // The wordmark already says the name, so the mark is decorative next to it.
    $alt = $wordmark ? '' : 'AbangananHub';
    $imgBase = $mark.' object-contain shrink-0 transition-transform group-hover:scale-105 motion-reduce:transition-none';
@endphp
<span {{ $attributes->class(['inline-flex items-center', $gap]) }}>
    @if($onDark)
        <img src="{{ asset('images/brand/logo-mark-on-dark.svg') }}" alt="{{ $alt }}" class="{{ $imgBase }}">
    @else
        <img src="{{ asset('images/brand/logo-mark.svg') }}" alt="{{ $alt }}" class="dark:hidden {{ $imgBase }}">
        <img src="{{ asset('images/brand/logo-mark-on-dark.svg') }}" alt="{{ $alt }}" class="hidden dark:block {{ $imgBase }}">
    @endif
    @if($wordmark)
        <span class="{{ $text }} font-extrabold leading-none tracking-tight {{ $onDark ? 'text-white' : 'text-[#060D26]' }}">Abanganan<span class="text-[#FF8A66]">Hub</span></span>
    @endif
</span>
