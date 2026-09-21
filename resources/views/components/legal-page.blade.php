@props(['title', 'eyebrow', 'intro', 'updated', 'sections'])

{{--
    Shared shell for the Privacy Policy and Terms of Service. Content is passed
    as data so the two pages stay identical in structure and the TOC is always
    generated from the same list that renders the body.

    Each section: ['title' => string, 'body' => [ 'paragraph' | ['list' => [...]] ]]

    Reading aids (these are long documents people scroll and jump around in):
      - a thin progress bar across the top of the viewport
      - a table of contents that follows the reader (scroll-spy) — sticky rail on
        desktop, a sticky "jump to" bar on phones
      - sections rise in once as they enter view
    Only transform/opacity animate, and each moving class has a motion-reduce
    counterpart so reduced-motion readers get the finished page with no movement.
--}}
@php
    $font = "font-jakarta";

    // Rough read time from the same data that renders the body.
    $words = collect($sections)->sum(fn ($s) => str_word_count($s['title'])
        + collect($s['body'])->sum(fn ($b) => is_array($b)
            ? collect($b['list'])->sum(fn ($li) => str_word_count($li))
            : str_word_count($b)));
    $minutes = max(1, (int) ceil($words / 200));
@endphp

<div class="bg-[#F7F8FC]"
    x-data="{
        active: 1,
        progress: 0,
        tocOpen: false,
        revealed: {},
        titles: @js(collect($sections)->pluck('title')->values()),
        onScroll() {
            const max = document.documentElement.scrollHeight - window.innerHeight;
            this.progress = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
        },
        go(n) {
            const el = document.getElementById('section-' + n);
            if (!el) return;
            const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            el.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', '#section-' + n);
            this.tocOpen = false;
        },
        init() {
            this.onScroll();
            const sections = [...this.$root.querySelectorAll('[data-legal-section]')];
            // Reveal each section the first time it enters view.
            const reveal = new IntersectionObserver((entries) => {
                entries.forEach((e) => { if (e.isIntersecting) { this.revealed[e.target.dataset.legalSection] = true; reveal.unobserve(e.target); } });
            }, { threshold: 0.08 });
            // Scroll-spy: the section crossing the upper third of the viewport is 'current'.
            const spy = new IntersectionObserver((entries) => {
                entries.forEach((e) => { if (e.isIntersecting) this.active = Number(e.target.dataset.legalSection); });
            }, { rootMargin: '-25% 0px -65% 0px' });
            sections.forEach((s) => { reveal.observe(s); spy.observe(s); });
        },
    }"
    @scroll.window.passive="onScroll()">

    {{-- Reading progress --}}
    <div class="fixed top-0 inset-x-0 z-[70] h-0.5 pointer-events-none" aria-hidden="true">
        <div class="h-full w-full origin-left bg-[#FF8A66]" :style="`transform: scaleX(${progress})`"></div>
    </div>

    <div class="max-w-[1100px] mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 {{ $font }} text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>{{ $eyebrow }}
            </span>
            <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#FF8A66] via-[#FF8A66]/70 to-[#FF8A66]/10" aria-hidden="true"></span>
        </div>

        <h1 class="mt-3 {{ $font }} text-[32px] sm:text-[44px] font-extrabold leading-[1.1] tracking-tight text-[#060D26] text-balance max-w-3xl">
            {{ $title }}
        </h1>
        <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-[#5B6A8E]">{{ $intro }}</p>

        {{-- At a glance: what a reader wants to know before committing to a long page. --}}
        <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-3 text-[13px]">
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Last updated</dt>
                <dd class="mt-0.5 font-semibold text-[#060D26]">{{ $updated }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Sections</dt>
                <dd class="mt-0.5 font-semibold text-[#060D26] tabular-nums">{{ count($sections) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Read time</dt>
                <dd class="mt-0.5 font-semibold text-[#060D26] tabular-nums">About {{ $minutes }} {{ Str::plural('minute', $minutes) }}</dd>
            </div>
        </dl>

        {{-- Phones: sticky "jump to" bar (the desktop rail below is lg-only) --}}
        <div class="lg:hidden sticky top-[64px] z-30 mt-8 -mx-4 sm:-mx-6 px-4 sm:px-6 py-2 bg-[#F7F8FC]">
            <div class="relative">
                <button type="button" @click="tocOpen = !tocOpen" :aria-expanded="tocOpen"
                    class="w-full h-11 flex items-center gap-3 rounded-xl border border-[#E2E4EC] bg-white px-4 text-left cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#B35A3D] tabular-nums shrink-0" x-text="active + '/{{ count($sections) }}'"></span>
                    <span class="flex-1 min-w-0 truncate text-[13.5px] font-semibold text-[#060D26]" x-text="titles[active - 1]"></span>
                    <svg class="w-4 h-4 shrink-0 text-[#5B6A8E] transition-transform duration-200 motion-reduce:transition-none" :class="tocOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <ol x-show="tocOpen" x-cloak @click.outside="tocOpen = false"
                    x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute inset-x-0 top-12 max-h-[60vh] overflow-y-auto rounded-xl border border-[#E2E4EC] bg-white p-2 shadow-[0_4px_24px_rgba(0,0,0,0.12)]">
                    @foreach($sections as $i => $section)
                        <li>
                            <a href="#section-{{ $i + 1 }}" @click.prevent="go({{ $i + 1 }})"
                                :class="active === {{ $i + 1 }} ? 'bg-[#ECEEF6] text-[#060D26] font-semibold' : 'text-[#5B6A8E]'"
                                class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-[13px] leading-snug">
                                <span class="tabular-nums w-5 shrink-0">{{ $i + 1 }}.</span>{{ $section['title'] }}
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="mt-6 lg:mt-10 grid lg:grid-cols-[240px_1fr] gap-8 lg:gap-12 items-start">

            {{-- Table of contents (desktop rail) --}}
            <nav aria-label="On this page" class="hidden lg:block sticky top-[88px] bg-white border border-[#E2E4EC] rounded-2xl p-4">
                <p class="{{ $font }} text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E] mb-2.5">On this page</p>
                <ol class="space-y-0.5">
                    @foreach($sections as $i => $section)
                        <li class="relative">
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-[#FF8A66] transition-opacity duration-300 motion-reduce:transition-none"
                                :class="active === {{ $i + 1 }} ? 'opacity-100' : 'opacity-0'" aria-hidden="true"></span>
                            <a href="#section-{{ $i + 1 }}" @click.prevent="go({{ $i + 1 }})"
                                :aria-current="active === {{ $i + 1 }} ? 'location' : null"
                                :class="active === {{ $i + 1 }} ? 'bg-[#ECEEF6] text-[#060D26] font-semibold' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                                class="flex items-center gap-2 rounded-lg pl-3 pr-2 py-1.5 text-[12.5px] leading-snug transition-colors duration-200">
                                <svg class="w-4 h-4 text-[#B35A3D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                                </svg>
                                <span>{{ $i + 1 }}. {{ $section['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            {{-- Body --}}
            <div class="space-y-4 min-w-0">
                @foreach($sections as $i => $section)
                    <section id="section-{{ $i + 1 }}" data-legal-section="{{ $i + 1 }}"
                        :class="revealed[{{ $i + 1 }}] ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                        class="scroll-mt-[128px] lg:scroll-mt-[88px] bg-white border border-[#E2E4EC] rounded-2xl p-5 sm:p-7 transition-all duration-500 ease-out motion-reduce:transition-none motion-reduce:opacity-100 motion-reduce:translate-y-0">
                        <h2 class="flex items-center gap-3 {{ $font }} text-[18px] sm:text-[20px] font-extrabold tracking-tight text-[#060D26]">
                            <span class="inline-flex items-center justify-center w-10 h-10 shrink-0 rounded-xl border border-[#FF8A66]/60 bg-[#FF8A66]/10">
                                <svg class="w-[18px] h-[18px] text-[#B35A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                                </svg>
                            </span>
                            {{ $i + 1 }}. {{ $section['title'] }}
                        </h2>
                        <div class="mt-4 space-y-3 text-[14.5px] leading-[1.75] text-[#5B6A8E] max-w-[68ch]">
                            @foreach($section['body'] as $block)
                                @if(is_array($block))
                                    <ul class="space-y-2">
                                        @foreach($block['list'] as $item)
                                            <li class="flex gap-2.5">
                                                <span class="mt-[0.7em] w-1.5 h-1.5 rounded-full bg-[#FF8A66] shrink-0" aria-hidden="true"></span>
                                                <span>{{ $item }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>{{ $block }}</p>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#FF8A66]/60 bg-gradient-to-br from-[#FF8A66]/15 via-[#FF8A66]/5 to-white p-5">
                    <p class="text-[13.5px] text-[#5B6A8E]">Questions about this page? Reach the AbangananHub team through <span class="font-semibold text-[#060D26]">Report a Problem</span> in your account.</p>
                    <a href="{{ route('home') }}"
                        class="inline-flex items-center gap-2 {{ $font }} text-[13px] font-bold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] px-5 py-2.5 rounded-full shadow-[0_8px_20px_rgba(255,138,102,0.35)] transition-colors duration-200">
                        Back to listings
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
