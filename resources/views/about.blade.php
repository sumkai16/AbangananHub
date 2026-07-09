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
    .feature-card:hover { box-shadow: 0 12px 40px rgba(42,167,161,0.10); }
    .step-line::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 100%;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #2AA7A1 0%, #E2E8F0 100%);
        transform: translateY(-50%);
    }
</style>
@endpush

@section('content')

{{-- ── HERO ─────────────────────────────────────────────────────────────────── --}}
<section class="relative overflow-hidden bg-gradient-to-br from-[#156F8C] via-[#1F2937] to-[#156F8C] text-white">

    {{-- decorative circles --}}
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-[#2AA7A1]/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-16 -left-16 w-72 h-72 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>

    <div class="relative max-w-5xl mx-auto px-6 py-24 text-center">

        <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-[12.5px] font-semibold tracking-wide uppercase mb-6 fade-up">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            About AbangananHub
        </div>

        <h1 class="text-[38px] sm:text-[52px] font-extrabold leading-tight tracking-tight mb-5 fade-up" style="transition-delay:.08s">
            Renting in Cebu,<br class="hidden sm:block"> <span class="text-[#156F8C]">done right.</span>
        </h1>

        <p class="text-[16px] sm:text-[18px] text-white/75 max-w-2xl mx-auto leading-relaxed fade-up" style="transition-delay:.16s">
            AbangananHub is a verified rental marketplace built for Cebu's tenants and landlords —
            making every listing transparent, every landlord accountable, and every move stress-free.
        </p>

        <div class="flex flex-wrap justify-center gap-3 mt-8 fade-up" style="transition-delay:.24s">
            <a href="{{ route('properties.index') }}"
               class="h-11 px-7 bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-[14px] rounded-full transition-all shadow-lg shadow-[#2AA7A1]/30 flex items-center gap-2">
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
<section class="bg-white border-b border-gray-100">
    <div class="max-w-5xl mx-auto px-6 py-10 grid grid-cols-2 md:grid-cols-4 gap-6">
        @foreach([
            ['label' => 'Verified Listings', 'value' => '100+', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Registered Landlords', 'value' => '50+', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Cities Covered', 'value' => 'Cebu', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
            ['label' => 'SDG Aligned', 'value' => 'SDG 16', 'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064'],
        ] as $stat)
        <div class="stat-card text-center transition-transform duration-300 fade-up">
            <div class="w-11 h-11 rounded-2xl bg-[#2AA7A1]/10 flex items-center justify-center mx-auto mb-3">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/>
                </svg>
            </div>
            <div class="text-[24px] font-extrabold text-[#156F8C]">{{ $stat['value'] }}</div>
            <div class="text-[12.5px] text-gray-500 font-medium mt-0.5">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>
</section>

{{-- ── MISSION & VISION ─────────────────────────────────────────────────────── --}}
<section class="bg-[#F7FCFC] py-20">
    <div class="max-w-5xl mx-auto px-6">

        <div class="text-center mb-14 fade-up">
            <span class="text-[12px] font-bold uppercase tracking-widest text-[#156F8C]">Our Purpose</span>
            <h2 class="text-[30px] sm:text-[36px] font-extrabold text-[#156F8C] mt-2 tracking-tight">Mission & Vision</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            <div class="feature-card bg-white rounded-3xl p-8 border border-gray-100 shadow-sm transition-all duration-300 fade-up">
                <div class="w-12 h-12 rounded-2xl bg-[#2AA7A1] flex items-center justify-center mb-5 shadow-md shadow-[#2AA7A1]/30">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-[19px] font-extrabold text-[#156F8C] mb-3">Our Mission</h3>
                <p class="text-[14.5px] text-gray-500 leading-relaxed">
                    To provide a safe, transparent, and efficient platform where tenants in Cebu can discover
                    verified rental properties and connect directly with trusted landlords — eliminating
                    scams, hidden fees, and outdated listings from the rental experience.
                </p>
            </div>

            <div class="feature-card bg-white rounded-3xl p-8 border border-gray-100 shadow-sm transition-all duration-300 fade-up" style="transition-delay:.1s">
                <div class="w-12 h-12 rounded-2xl bg-[#156F8C] flex items-center justify-center mb-5 shadow-md shadow-[#156F8C]/20">
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="text-[19px] font-extrabold text-[#156F8C] mb-3">Our Vision</h3>
                <p class="text-[14.5px] text-gray-500 leading-relaxed">
                    To become Cebu's most trusted rental ecosystem — where every tenant finds a home they
                    can rely on, every landlord grows a reputable portfolio, and the entire process is
                    governed by accountability, fairness, and digital accessibility.
                </p>
            </div>

        </div>
    </div>
</section>

{{-- ── HOW IT WORKS ─────────────────────────────────────────────────────────── --}}
<section class="bg-white py-20">
    <div class="max-w-5xl mx-auto px-6">

        <div class="text-center mb-14 fade-up">
            <span class="text-[12px] font-bold uppercase tracking-widest text-[#156F8C]">The Process</span>
            <h2 class="text-[30px] sm:text-[36px] font-extrabold text-[#156F8C] mt-2 tracking-tight">How AbangananHub Works</h2>
            <p class="text-[14.5px] text-gray-400 mt-3 max-w-xl mx-auto">From finding a rental to moving in — the whole journey in one platform.</p>
        </div>

        {{-- Tenant Flow --}}
        <div class="mb-12 fade-up">
            <div class="flex items-center gap-2 mb-6">
                <div class="w-7 h-7 rounded-full bg-[#2AA7A1]/10 flex items-center justify-center">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <span class="text-[13px] font-bold text-[#156F8C] uppercase tracking-wider">For Tenants</span>
            </div>
            <div class="grid sm:grid-cols-4 gap-4">
                @foreach([
                    ['n'=>'1','title'=>'Create Account','desc'=>'Sign up and verify your email to unlock all tenant features.'],
                    ['n'=>'2','title'=>'Search & Filter','desc'=>'Browse by location, type, and budget. View verified listings only if you prefer.'],
                    ['n'=>'3','title'=>'Reserve','desc'=>'Submit a reservation request directly to the landlord with one click.'],
                    ['n'=>'4','title'=>'Move In','desc'=>'Once approved, coordinate with your landlord and settle your new home.'],
                ] as $step)
                <div class="bg-[#F7FCFC] rounded-2xl p-5 border border-gray-100">
                    <div class="w-8 h-8 rounded-full bg-[#2AA7A1] text-white text-[13px] font-extrabold flex items-center justify-center mb-3 shadow-sm">{{ $step['n'] }}</div>
                    <div class="text-[14px] font-bold text-[#156F8C] mb-1">{{ $step['title'] }}</div>
                    <div class="text-[12.5px] text-gray-500 leading-relaxed">{{ $step['desc'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Landlord Flow --}}
        <div class="fade-up">
            <div class="flex items-center gap-2 mb-6">
                <div class="w-7 h-7 rounded-full bg-[#156F8C]/10 flex items-center justify-center">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="text-[13px] font-bold text-[#156F8C] uppercase tracking-wider">For Landlords</span>
            </div>
            <div class="grid sm:grid-cols-4 gap-4">
                @foreach([
                    ['n'=>'1','title'=>'Apply & Verify','desc'=>'Submit your ID and documents to become a verified landlord on the platform.'],
                    ['n'=>'2','title'=>'List Property','desc'=>'Add your property details, photos, pricing, and available units.'],
                    ['n'=>'3','title'=>'Review Requests','desc'=>'Receive tenant reservation requests and approve or reject them from your dashboard.'],
                    ['n'=>'4','title'=>'Manage Listings','desc'=>'Keep listings up to date, track reservations, and build your reputation.'],
                ] as $step)
                <div class="bg-[#F7FCFC] rounded-2xl p-5 border border-gray-100">
                    <div class="w-8 h-8 rounded-full bg-[#156F8C] text-white text-[13px] font-extrabold flex items-center justify-center mb-3 shadow-sm">{{ $step['n'] }}</div>
                    <div class="text-[14px] font-bold text-[#156F8C] mb-1">{{ $step['title'] }}</div>
                    <div class="text-[12.5px] text-gray-500 leading-relaxed">{{ $step['desc'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</section>

{{-- ── KEY FEATURES ─────────────────────────────────────────────────────────── --}}
<section class="bg-[#F7FCFC] py-20">
    <div class="max-w-5xl mx-auto px-6">

        <div class="text-center mb-14 fade-up">
            <span class="text-[12px] font-bold uppercase tracking-widest text-[#156F8C]">Platform Features</span>
            <h2 class="text-[30px] sm:text-[36px] font-extrabold text-[#156F8C] mt-2 tracking-tight">Built for Trust & Transparency</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Verified Landlords', 'desc'=>'Every landlord is vetted through a document verification process before their listings go live.', 'color'=>'#2AA7A1'],
                ['icon'=>'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 'title'=>'Smart Search & Filter', 'desc'=>'Find rentals by location, property type (Bedspace, Room, Apartment, House), and maximum budget.', 'color'=>'#2AA7A1'],
                ['icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'title'=>'Direct Reservations', 'desc'=>'Tenants can reserve a unit in real time. Landlords approve or reject from their dashboard instantly.', 'color'=>'#156F8C'],
                ['icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'title'=>'In-App Messaging', 'desc'=>'Communicate directly between tenant and landlord without sharing personal contact info upfront.', 'color'=>'#156F8C'],
                ['icon'=>'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'title'=>'Saved Listings', 'desc'=>'Bookmark your favourite properties and revisit them any time from your Saved Listings tab.', 'color'=>'#2AA7A1'],
                ['icon'=>'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'title'=>'Real-time Notifications', 'desc'=>'Get notified on reservation approvals, rejections, messages, and listing status updates instantly.', 'color'=>'#156F8C'],
            ] as $i => $feature)
            <div class="feature-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm transition-all duration-300 fade-up" style="transition-delay:{{ $i * 0.07 }}s">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background:{{ $feature['color'] }}1a">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="{{ $feature['color'] }}" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="text-[15px] font-bold text-[#156F8C] mb-1.5">{{ $feature['title'] }}</h3>
                <p class="text-[13px] text-gray-500 leading-relaxed">{{ $feature['desc'] }}</p>
            </div>
            @endforeach
        </div>

    </div>
</section>

{{-- ── SDG SECTION ──────────────────────────────────────────────────────────── --}}
<section class="bg-white py-20">
    <div class="max-w-5xl mx-auto px-6">
        <div class="bg-gradient-to-br from-[#156F8C] to-[#156F8C] rounded-3xl p-10 md:p-14 text-white overflow-hidden relative fade-up">

            <div class="absolute top-0 right-0 w-64 h-64 rounded-full bg-white/5 blur-3xl pointer-events-none -translate-y-1/2 translate-x-1/3"></div>

            <div class="relative grid md:grid-cols-2 gap-10 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-widest mb-5">
                        UN Sustainable Development Goal
                    </div>
                    <h2 class="text-[26px] sm:text-[32px] font-extrabold leading-tight mb-4">
                        Aligned with <span class="text-[#69D2C6]">SDG 16</span>
                    </h2>
                    <p class="text-[14.5px] text-white/75 leading-relaxed">
                        SDG 16 — <strong class="text-white">Peace, Justice, and Strong Institutions</strong> — calls for
                        transparent, accountable systems at all levels. AbangananHub embodies this by enforcing
                        landlord verification, providing an admin-moderated approval process, and giving tenants
                        a safe space to find housing free from exploitation or deception.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['title'=>'Transparency','desc'=>'All listings pass admin review before going live.'],
                        ['title'=>'Accountability','desc'=>'Landlords are identity-verified before listing.'],
                        ['title'=>'Accessibility','desc'=>'Open to any tenant searching in Cebu — free to browse.'],
                        ['title'=>'Fair Process','desc'=>'Reservation and approval workflows protect both parties.'],
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
<section class="bg-[#F7FCFC] py-20">
    <div class="max-w-2xl mx-auto px-6 text-center fade-up">
        <h2 class="text-[28px] sm:text-[34px] font-extrabold text-[#156F8C] tracking-tight mb-4">
            Ready to find your next home?
        </h2>
        <p class="text-[15px] text-gray-500 mb-8 leading-relaxed">
            Join hundreds of tenants and landlords who trust AbangananHub to make renting in Cebu simpler, safer, and smarter.
        </p>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="{{ route('properties.index') }}"
               class="h-12 px-8 bg-[#2AA7A1] hover:brightness-95 text-white font-bold text-[14.5px] rounded-full transition-all shadow-lg shadow-[#2AA7A1]/25 flex items-center gap-2">
                Browse Listings
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @guest
            <button type="button" onclick="openAuthModal('register')"
               class="h-12 px-8 bg-white border border-gray-200 text-[#156F8C] hover:shadow-md font-bold text-[14.5px] rounded-full transition-all flex items-center gap-2">
                Create Free Account
            </button>
            @endguest
        </div>
    </div>
</section>

@endsection
