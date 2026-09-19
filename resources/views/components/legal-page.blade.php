@props(['title', 'eyebrow', 'intro', 'updated', 'sections'])

{{--
    Shared shell for the Privacy Policy and Terms of Service. Content is passed
    as data so the two pages stay identical in structure and the TOC is always
    generated from the same list that renders the body.

    Each section: ['title' => string, 'body' => [ 'paragraph' | ['list' => [...]] ]]
--}}
@php
    $font = "font-['Plus_Jakarta_Sans',_Inter,_sans-serif]";
@endphp

<div class="bg-[#F7F8FC]">
    <div class="max-w-[1100px] mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 {{ $font }} text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#DA8E77] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>{{ $eyebrow }}
            </span>
            <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-[#DA8E77] via-[#DA8E77]/70 to-[#DA8E77]/10" aria-hidden="true"></span>
            <span class="hidden sm:inline {{ $font }} text-[10.5px] font-bold uppercase tracking-[0.1em] text-[#5B6A8E] whitespace-nowrap">Last updated {{ $updated }}</span>
        </div>

        <h1 class="mt-3 {{ $font }} text-[32px] sm:text-[44px] font-extrabold leading-[1.1] tracking-tight text-[#060D26]">
            {{ $title }}
        </h1>
        <p class="mt-4 max-w-2xl text-[14.5px] leading-relaxed text-[#5B6A8E]">{{ $intro }}</p>

        <div class="mt-10 grid lg:grid-cols-[240px_1fr] gap-8 lg:gap-12 items-start">

            {{-- Table of contents --}}
            <nav aria-label="On this page" class="hidden lg:block sticky top-[88px] bg-white border border-[#E2E4EC] rounded-2xl p-4">
                <p class="{{ $font }} text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E] mb-2.5">On this page</p>
                <ol class="space-y-0.5">
                    @foreach($sections as $i => $section)
                        <li>
                            <a href="#section-{{ $i + 1 }}"
                                class="flex gap-2 rounded-lg px-2 py-1.5 text-[12.5px] leading-snug text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors">
                                <span class="{{ $font }} font-bold text-[#A8573F] w-5 shrink-0">{{ $i + 1 }}.</span>
                                <span>{{ $section['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            {{-- Body --}}
            <div class="space-y-4 min-w-0">
                @foreach($sections as $i => $section)
                    <section id="section-{{ $i + 1 }}" class="scroll-mt-[88px] bg-white border border-[#E2E4EC] rounded-2xl p-5 sm:p-7">
                        <h2 class="flex items-center gap-3 {{ $font }} text-[18px] sm:text-[20px] font-extrabold tracking-tight text-[#060D26]">
                            <span class="inline-flex items-center justify-center min-w-8 h-8 px-2 rounded-full border border-[#DA8E77]/60 bg-[#DA8E77]/10 text-[12px] font-bold text-[#060D26]">{{ $i + 1 }}</span>
                            {{ $section['title'] }}
                        </h2>
                        <div class="mt-3 space-y-3 text-[14px] leading-relaxed text-[#5B6A8E]">
                            @foreach($section['body'] as $block)
                                @if(is_array($block))
                                    <ul class="space-y-1.5">
                                        @foreach($block['list'] as $item)
                                            <li class="flex gap-2.5">
                                                <span class="mt-2 w-1.5 h-1.5 rounded-full bg-[#DA8E77] shrink-0" aria-hidden="true"></span>
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

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#DA8E77]/60 bg-gradient-to-br from-[#DA8E77]/15 via-[#DA8E77]/5 to-white p-5">
                    <p class="text-[13.5px] text-[#5B6A8E]">Questions about this page? Reach the AbangananHub team through <span class="font-semibold text-[#060D26]">Report a Problem</span> in your account.</p>
                    <a href="{{ route('home') }}"
                        class="inline-flex items-center gap-2 {{ $font }} text-[13px] font-bold text-[#060D26] bg-[#DA8E77] hover:bg-[#C97A61] px-5 py-2.5 rounded-full shadow-[0_8px_20px_rgba(255,138,102,0.35)] transition-colors">
                        Back to listings
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
