@extends('layouts.app')

@section('title', 'About — AbangananHub')
@section('hide_search')@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    });
</script>
<style>
    .fade-up {
        opacity: 0;
        transform: translateY(28px);
        transition: opacity 0.55s ease, transform 0.55s ease;
    }
    .fade-up.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .stat-card:hover { transform: translateY(-4px); }
    .feature-card:hover { box-shadow: 0 12px 40px rgba(255, 138, 102,0.10); }
    .step-line::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 100%;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #FF8A66 0%, #E2E4EC 100%);
        transform: translateY(-50%);
    }
</style>
@endpush

@section('content')

{{-- ── HERO ─────────────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-gradient-to-br from-[#060D26] via-[#060D26] to-[#060D26] text-white">


    <div class="relative max-w-5xl mx-auto px-6 py-24 text-center">

        <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-[12.5px] font-semibold tracking-wide uppercase mb-6 fade-up">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            About AbangananHub
        </div>

        <h1 class="font-jakarta text-[36px] sm:text-[52px] font-extrabold leading-[1.1] tracking-tight mb-5 fade-up" style="transition-delay:.08s">
            Renting in Cebu,<br class="hidden sm:block"> <span class="text-[#060D26]">done right.</span>
        </h1>

        <p class="text-[16px] sm:text-[18px] text-white/75 max-w-2xl mx-auto leading-relaxed fade-up" style="transition-delay:.16s">
            AbangananHub is a verified rental marketplace built for Cebu's tenants and landlords —
            making every listing transparent, every landlord accountable, and every move stress-free.
        </p>

        <div class="flex flex-wrap justify-center gap-3 mt-8 fade-up" style="transition-delay:.24s">
            <a href="{{ route('properties.index') }}"
               class="h-11 px-7 bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-[14px] rounded-full transition-all shadow-lg shadow-[#FF8A66]/30 flex items-center gap-2">
                Browse Properties
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="{{ route('landlord.verification.create') }}"
               class="h-11 px-7 bg-white/10 hover:bg-white/20 border border-white/25 text-white font-bold text-[14px] rounded-full transition-all flex items-center gap-2">
                List Your Property
            </a>
        </div>

    </div>
</section>

{{-- ── STATS BAR ────────────────────────────────────────────────────────────── --}}
<section class="bg-white border-b border-[#E2E4EC]">
    <div class="max-w-5xl mx-auto px-6 py-10 grid grid-cols-2 md:grid-cols-4 gap-6">
        @foreach([
            ['label' => 'Verified Listings', 'value' => '100+', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Registered Landlords', 'value' => '50+', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Cities Covered', 'value' => 'Cebu', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
            ['label' => 'SDG Aligned', 'value' => 'SDG 11', 'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064'],
        ] as $stat)
        <div class="stat-card text-center transition-transform duration-300 fade-up">
            <div class="w-11 h-11 rounded-2xl bg-[#FF8A66]/10 flex items-center justify-center mx-auto mb-3">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#B35A3D" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/>
                </svg>
            </div>
            <div class="text-[24px] font-extrabold text-[#060D26]">{{ $stat['value'] }}</div>
            <div class="text-[12.5px] text-[#5B6A8E] font-medium mt-0.5">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>
</section>

{{-- ── MISSION & VISION ─────────────────────────────────────────────────────── --}}
<section class="bg-[#F7F8FC] py-20">
    <div class="max-w-5xl mx-auto px-6">

        <div class="text-center mb-14 fade-up">
            <span class="font-jakarta text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]">Our Purpose</span>
            <h2 class="font-jakarta text-[30px] sm:text-[36px] font-extrabold text-[#060D26] mt-2 tracking-tight">Mission & Vision</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            <div class="feature-card bg-white rounded-3xl p-8 border border-[#E2E4EC] shadow-sm transition-all duration-300 fade-up">
                <div class="w-12 h-12 rounded-2xl bg-[#060D26] flex items-center justify-center mb-5 shadow-md shadow-[#FF8A66]/30">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="font-jakarta text-[19px] font-bold text-[#060D26] mb-3">Our Mission</h3>
                <p class="text-[14.5px] text-[#5B6A8E] leading-relaxed">
                    To provide a safe, transparent, and efficient platform where tenants in Cebu can discover
                    verified rental properties and connect directly with trusted landlords — eliminating
                    scams, hidden fees, and outdated listings from the rental experience.
                </p>
            </div>

            <div class="feature-card bg-white rounded-3xl p-8 border border-[#E2E4EC] shadow-sm transition-all duration-300 fade-up" style="transition-delay:.1s">
                <div class="w-12 h-12 rounded-2xl bg-[#060D26] flex items-center justify-center mb-5 shadow-md shadow-[#060D26]/20">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="font-jakarta text-[19px] font-bold text-[#060D26] mb-3">Our Vision</h3>
                <p class="text-[14.5px] text-[#5B6A8E] leading-relaxed">
                    To become Cebu's most trusted rental ecosystem — where every tenant finds a home they
                    can rely on, every landlord grows a reputable portfolio, and the entire process is
                    governed by accountability, fairness, and digital accessibility.
                </p>
            </div>

        </div>
    </div>
</section>

{{-- ── HOW IT WORKS ───────────────────────────────────────────────────────────
     The id is the target of the header nav's "How it works" link. `scroll-mt-[72px]`
     keeps the heading clear of the sticky header when jumped to.

     A guide to actually using the system, one audience at a time. Each step says
     what to do, WHERE in the app to do it (the real menu/button names), and what
     happens next — so a first-time user can follow it with the app open.

     Motion is scroll-driven: each step lights up as it reaches the reader and the
     connector line grows down to the latest step reached. Only transform/opacity
     animate; every moving class has a motion-reduce counterpart so reduced-motion
     users get the finished page immediately. --}}
@php
    $hiwFlows = [
        'tenant' => ['label' => 'For tenants', 'steps' => [
            ['title' => 'Create your account',
             'desc' => 'Sign up with your email and verify it. Browsing is free without an account, but you need one to save, message or reserve.',
             'where' => 'Sign up, top right of any page',
             'next' => 'You can save listings and message landlords straight away.',
             'icon' => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z'],
            ['title' => 'Find a place that fits',
             'desc' => 'Filter by area, property type, budget and amenities like Wi-Fi or parking. Tap the heart to save listings you want to compare, and check the map for what is nearby.',
             'where' => 'Browse Rentals, then Filters',
             'next' => 'Saved listings collect under Saved so you can come back to them.',
             'icon' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
            ['title' => 'Ask the landlord first',
             'desc' => 'Have questions about the room, the rules or the move-in date? Message the landlord from the listing. You do not have to share your phone number.',
             'where' => 'Listing page, then Messages',
             'next' => 'Replies show up in Messages and as a notification.',
             'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
            ['title' => 'Send a reservation request',
             'desc' => 'Pick the unit you want and reserve it. Nothing is charged yet. The request goes to the landlord, who approves or declines it.',
             'where' => 'Listing page, then your unit',
             'next' => 'Track the status under My Reservations: Pending, then Approved or Rejected.',
             'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['title' => 'Sign the agreement and pay',
             'desc' => 'Once the landlord moves your request forward, read the rental agreement, sign it online, then pay through the platform with GCash or QR Ph.',
             'where' => 'My Reservations, then Sign Agreement and Pay Now',
             'next' => 'Your payment is held by AbangananHub and is not handed to the landlord yet.',
             'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['title' => 'Move in and confirm',
             'desc' => 'The landlord hands over the keys and marks the unit as turned over. Check the place, then confirm your move-in. If something is wrong, report it instead.',
             'where' => 'My Reservations, on your reservation',
             'next' => 'Confirming releases your payment to the landlord. After that, pay monthly rent online and leave a review when you are done.',
             'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ]],
        'landlord' => ['label' => 'For landlords', 'steps' => [
            ['title' => 'Apply and verify your identity',
             'desc' => 'Take a live photo of your government ID and a selfie right in the app. Uploads are not accepted, which keeps fake accounts out. An admin reviews it.',
             'where' => 'Become a Landlord, in your account menu',
             'next' => 'You get a notification when you are approved. Then the landlord tools unlock.',
             'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['title' => 'Add your property',
             'desc' => 'A short guided form: property info, location on the map, amenities, documents, then your units with rent, deposit and photos. Progress saves automatically, so you can leave and come back.',
             'where' => 'Properties, then Add Property',
             'next' => 'An admin checks it before it goes live. You are notified either way.',
             'icon' => 'M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['title' => 'Answer requests',
             'desc' => 'Tenants message you and send reservation requests. Reply in Messages, then approve or decline each request from your dashboard.',
             'where' => 'Reservations and Messages',
             'next' => 'Approved requests move on to the agreement.',
             'icon' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4'],
            ['title' => 'Agree terms and get the deposit secured',
             'desc' => 'Move the request to the agreement stage. The tenant signs and pays through the platform, so the money is held safely before anyone moves in.',
             'where' => 'Reservations, on the request',
             'next' => 'You see the payment as held. You are not asked to chase transfers.',
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
            ['title' => 'Hand over the keys',
             'desc' => 'Meet the tenant, hand over the unit, then mark it as turned over. The tenant confirms the move-in on their side.',
             'where' => 'Reservations, then Mark as turned over',
             'next' => 'When the tenant confirms, the payment is released to you. An admin sends it on to you.',
             'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z'],
            ['title' => 'Run the tenancy',
             'desc' => 'Record cash or GCash rent payments and print receipts, see who is overdue, and rate your tenants. Renting to someone offline? Add them as a walk-in tenant.',
             'where' => 'Rent & Payments, Tenants and Analytics',
             'next' => 'When a tenancy ends, the unit goes back to available for the next tenant.',
             'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ]],
    ];
@endphp
<section id="how-it-works" class="bg-white py-20 scroll-mt-[72px]"
    x-data="{
        who: 'tenant',
        revealed: {},
        io: null,
        // Highest step index reached so far in the current audience (-1 = none yet).
        get last() { const k = Object.keys(this.revealed).filter(k => k.startsWith(this.who + '-')).map(k => Number(k.split('-')[1])); return k.length ? Math.max(...k) : -1; },
        watchSteps() {
            if (this.io) this.io.disconnect();
            this.io = new IntersectionObserver((entries) => {
                entries.forEach((e) => { if (e.isIntersecting) this.revealed[e.target.dataset.hiwStep] = true; });
            }, { threshold: 0.4, rootMargin: '0px 0px -8% 0px' });
            this.$root.querySelectorAll(`[data-hiw-panel='${this.who}'] [data-hiw-step]`).forEach((el) => this.io.observe(el));
        },
        pick(w) {
            if (w === this.who) return;
            this.who = w;
            this.revealed = {};
            this.$nextTick(() => this.watchSteps());
        },
    }"
    x-init="watchSteps()">
    <div class="max-w-5xl mx-auto px-6">

        <div class="mb-10 fade-up">
            <span class="inline-flex items-center gap-2 font-jakarta text-[12px] font-semibold uppercase tracking-[0.08em] text-[#5B6A8E]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66] shadow-[0_0_0_3px_rgba(255,138,102,0.25)]"></span>How to use AbangananHub
            </span>
            <h2 class="mt-3 font-jakarta text-[28px] sm:text-[36px] font-bold leading-[1.15] text-[#060D26]">
                Step by step,
                <span class="block text-[#5B6A8E]">start to move-in.</span>
            </h2>
            <p class="text-[15px] sm:text-[16px] text-[#5B6A8E] mt-4 max-w-xl">Pick your role to see what to do, where to find it in the app, and what happens next.</p>
        </div>

        {{-- Audience switch --}}
        <div class="fade-up">
            <div role="tablist" aria-label="Choose your role" class="relative inline-flex p-1 rounded-full bg-[#ECEEF6]">
                @foreach($hiwFlows as $key => $flow)
                    <button type="button" role="tab" @click="pick('{{ $key }}')"
                        :aria-selected="who === '{{ $key }}'"
                        :class="who === '{{ $key }}' ? 'bg-[#060D26] text-white shadow-[0_1px_3px_rgba(6,13,38,0.25)]' : 'text-[#5B6A8E] hover:text-[#060D26]'"
                        class="h-10 px-5 rounded-full text-[14px] font-semibold transition-colors duration-300 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                        {{ $flow['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-12">
            @foreach($hiwFlows as $key => $flow)
                @php $count = count($flow['steps']); @endphp
                <div x-show="who === '{{ $key }}'" @if(! $loop->first) x-cloak @endif role="tabpanel" aria-label="{{ $flow['label'] }}" data-hiw-panel="{{ $key }}">
                    <ol class="relative pl-14 sm:pl-16">
                        {{-- Rail: the coral fill grows down to the latest step the reader has reached. --}}
                        <span class="absolute left-[21px] top-[22px] bottom-[22px] w-px bg-[#E2E4EC]" aria-hidden="true">
                            <span class="block h-full w-full origin-top bg-[#FF8A66] transition-transform duration-700 ease-out motion-reduce:transition-none motion-reduce:scale-y-100"
                                :style="`transform: scaleY(${who === '{{ $key }}' ? Math.max(last, 0) / {{ $count - 1 }} : 0})`"></span>
                        </span>

                        @foreach($flow['steps'] as $i => $step)
                            <li class="group/step relative {{ $loop->last ? '' : 'pb-8 sm:pb-10' }}" data-hiw-step="{{ $key }}-{{ $i }}">
                                {{-- Node --}}
                                <span class="absolute -left-14 sm:-left-16 top-0 flex h-11 w-11 items-center justify-center rounded-full border-2 transition-all duration-500 motion-reduce:transition-none"
                                    :class="revealed['{{ $key }}-{{ $i }}'] ? 'bg-[#FF8A66] border-[#FF8A66] text-[#060D26] scale-100' : 'bg-white border-[#E2E4EC] text-[#5B6A8E] scale-90'"
                                    aria-hidden="true">
                                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}" />
                                    </svg>
                                </span>

                                <div class="rounded-2xl border border-[#E2E4EC] bg-white p-5 sm:p-6 transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:opacity-100 motion-reduce:translate-y-0 hover:border-[#FF8A66]/60 hover:shadow-[0_12px_28px_rgba(6,13,38,0.08)]"
                                    :class="revealed['{{ $key }}-{{ $i }}'] ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                                    <p class="font-jakarta text-[12px] font-semibold uppercase tracking-[0.08em] text-[#B35A3D] tabular-nums">Step {{ $i + 1 }} of {{ $count }}</p>
                                    <h3 class="mt-1 font-jakarta text-[18px] sm:text-[20px] font-bold leading-snug text-[#060D26]">{{ $step['title'] }}</h3>
                                    <p class="mt-2 text-[14px] text-[#5B6A8E] leading-relaxed max-w-[60ch]">{{ $step['desc'] }}</p>

                                    <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                                        <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                            <p class="text-[12px] font-semibold uppercase tracking-[0.06em] text-[#5B6A8E]">Where</p>
                                            <p class="mt-1 text-[14px] font-semibold leading-snug text-[#060D26]">{{ $step['where'] }}</p>
                                        </div>
                                        <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3">
                                            <p class="text-[12px] font-semibold uppercase tracking-[0.06em] text-[#5B6A8E]">What happens next</p>
                                            <p class="mt-1 text-[14px] leading-snug text-[#060D26]">{{ $step['next'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>

        {{-- Applies to both roles --}}
        <div class="mt-14 grid gap-4 sm:grid-cols-3 fade-up">
            @foreach([
                ['Your money is protected', 'Payments go through the platform. The deposit is held until the tenant confirms move-in.'],
                ['Everything in one place', 'Messages, reservations and notifications stay in your account, so nothing gets lost in chat threads.'],
                ['Stuck at any step?', 'Use Report a Problem in your account and the AbangananHub team will look into it.'],
            ] as [$tipTitle, $tipBody])
                <div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-5">
                    <h3 class="font-jakarta text-[16px] font-semibold text-[#060D26]">{{ $tipTitle }}</h3>
                    <p class="mt-1.5 text-[14px] leading-relaxed text-[#5B6A8E]">{{ $tipBody }}</p>
                </div>
            @endforeach
        </div>

    </div>
</section>

{{-- ── KEY FEATURES ─────────────────────────────────────────────────────────── --}}
<section class="bg-[#F7F8FC] py-20">
    <div class="max-w-5xl mx-auto px-6">

        <div class="text-center mb-14 fade-up">
            <span class="font-jakarta text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#5B6A8E]">Platform Features</span>
            <h2 class="font-jakarta text-[30px] sm:text-[36px] font-extrabold text-[#060D26] mt-2 tracking-tight">Built for Trust & Transparency</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Verified Landlords', 'desc'=>'Every landlord is vetted through a document verification process before their listings go live.', 'color'=>'#FF8A66'],
                ['icon'=>'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 'title'=>'Smart Search & Filter', 'desc'=>'Find rentals by location, property type (Apartment, Condominium, House, Boarding House, Bedspace), and maximum budget.', 'color'=>'#FF8A66'],
                ['icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'title'=>'Direct Reservations', 'desc'=>'Tenants can reserve a unit in real time. Landlords approve or reject from their dashboard instantly.', 'color'=>'#060D26'],
                ['icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'title'=>'In-App Messaging', 'desc'=>'Communicate directly between tenant and landlord without sharing personal contact info upfront.', 'color'=>'#060D26'],
                ['icon'=>'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'title'=>'Saved Listings', 'desc'=>'Bookmark your favourite properties and revisit them any time from your Saved Listings tab.', 'color'=>'#FF8A66'],
                ['icon'=>'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'title'=>'Real-time Notifications', 'desc'=>'Get notified on reservation approvals, rejections, messages, and listing status updates instantly.', 'color'=>'#060D26'],
            ] as $i => $feature)
            <div class="feature-card bg-white rounded-2xl p-6 border border-[#E2E4EC] shadow-sm transition-all duration-300 fade-up" style="transition-delay:{{ $i * 0.07 }}s">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background:{{ $feature['color'] }}1a">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="{{ $feature['color'] }}" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="font-jakarta text-[15px] font-bold text-[#060D26] mb-1.5">{{ $feature['title'] }}</h3>
                <p class="text-[13px] text-[#5B6A8E] leading-relaxed">{{ $feature['desc'] }}</p>
            </div>
            @endforeach
        </div>

    </div>
</section>

{{-- ── SDG SECTION ──────────────────────────────────────────────────────────── --}}
<section class="bg-white py-20">
    <div class="max-w-5xl mx-auto px-6">
        <div class="bg-gradient-to-br from-[#060D26] to-[#060D26] rounded-3xl p-10 md:p-14 text-white overflow-hidden relative fade-up">


            <div class="relative grid md:grid-cols-2 gap-10 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-widest mb-5">
                        UN Sustainable Development Goal
                    </div>
                    <h2 class="font-jakarta text-[26px] sm:text-[32px] font-extrabold leading-tight mb-4">
                        Aligned with <span class="text-[#FF8A66]">SDG 11</span>
                    </h2>
                    <p class="text-[14.5px] text-white/75 leading-relaxed">
                        SDG 11 — <strong class="text-white">Sustainable Cities and Communities</strong> — calls for
                        access to safe, affordable housing for everyone. AbangananHub supports this by verifying
                        landlords, reviewing every listing before it goes live, and helping tenants in Cebu find
                        a place to stay that fits their budget without the risk of scams.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['title'=>'Safe Housing','desc'=>'Landlords are identity-verified and every listing passes admin review.'],
                        ['title'=>'Affordable','desc'=>'Filter by budget and see the monthly rent up front on every listing.'],
                        ['title'=>'Inclusive','desc'=>'Open to any tenant searching in Cebu — free to browse.'],
                        ['title'=>'Trusted Communities','desc'=>'Tenant reviews and digital agreements protect both parties.'],
                    ] as $sdg)
                    <div class="bg-white/10 border border-white/15 rounded-2xl p-4">
                        <div class="text-[13px] font-bold mb-1">{{ $sdg['title'] }}</div>
                        <div class="text-[12px] text-white/65 leading-relaxed">{{ $sdg['desc'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────────────────────────────── --}}
<section class="bg-[#F7F8FC] py-20">
    <div class="max-w-2xl mx-auto px-6 text-center fade-up">
        <h2 class="font-jakarta text-[28px] sm:text-[34px] font-extrabold text-[#060D26] tracking-tight mb-4">
            Ready to find your next home?
        </h2>
        <p class="text-[15px] text-[#5B6A8E] mb-8 leading-relaxed">
            Join hundreds of tenants and landlords who trust AbangananHub to make renting in Cebu simpler, safer, and smarter.
        </p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ route('properties.index') }}"
               class="h-12 px-8 bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-[14.5px] rounded-full transition-all shadow-lg shadow-[#FF8A66]/25 flex items-center gap-2">
                Browse Listings
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @guest
            <button type="button" onclick="openAuthModal('register')"
               class="h-12 px-8 bg-white border border-[#E2E4EC] text-[#060D26] hover:shadow-md font-bold text-[14.5px] rounded-full transition-all flex items-center gap-2">
                Create Free Account
            </button>
            @endguest
        </div>
    </div>
</section>

@endsection
