<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>AbangananHub — Find. Rent. Live with Confidence.</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-[#2A2523] bg-white">

    {{-- NAVBAR --}}
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/80 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shadow-md">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="font-bold text-lg text-[#2A2523]">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
            </a>
            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-[#2A2523] hover:text-[#61B2F0] transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-[#2A2523] hover:text-[#61B2F0] transition-colors">Login</a>
                    <a href="{{ route('register') }}" class="text-sm font-semibold px-4 py-2 bg-gradient-to-r from-[#286CD2] to-[#61B2F0] text-white rounded-lg shadow-md hover:opacity-90 transition-opacity">Get Started</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- HERO --}}
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('images/hero-bg.jpg') }}" class="w-full h-full object-cover" alt="" />
            <div class="absolute inset-0 bg-gradient-to-r from-[#2A2523]/85 via-[#2A2523]/60 to-transparent"></div>
        </div>
        <div class="relative z-10 max-w-7xl mx-auto px-6 py-32 w-full">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm border border-white/20 text-white text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#61B2F0] animate-pulse"></span>
                    Supporting SDG 16 — Peace, Justice & Strong Institutions
                </span>
                <h1 class="text-5xl md:text-6xl font-black text-white leading-tight tracking-tight mb-6">
                    Find. Rent.<br>
                    <span class="bg-gradient-to-r from-[#9cd4ff] to-[#61B2F0] bg-clip-text text-transparent">Live with Confidence.</span>
                </h1>
                <p class="text-white/80 text-lg font-medium mb-8 leading-relaxed">
                    AbangananHub connects tenants with verified landlords and quality properties. No scams, no fake listings — just transparent and accountable rentals.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('register') }}" class="px-6 py-3 bg-gradient-to-r from-[#286CD2] to-[#61B2F0] text-white font-bold text-sm rounded-xl shadow-lg hover:opacity-90 transition-opacity">
                        Find a Place
                    </a>
                    <a href="{{ route('register') }}" class="px-6 py-3 bg-white/10 backdrop-blur-sm border border-white/30 text-white font-bold text-sm rounded-xl hover:bg-white/20 transition-colors">
                        List Your Property
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <span class="text-[#61B2F0] font-bold text-sm uppercase tracking-widest">Simple Process</span>
                <h2 class="text-3xl md:text-4xl font-black text-[#2A2523] mt-2">How It Works</h2>
                <p class="text-[#9B9F98] mt-3 max-w-xl mx-auto">Three steps between you and your next home.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="relative text-center p-8 rounded-2xl bg-[#D7E8F3]/30 border border-[#D7E8F3]">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center mx-auto mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <div class="absolute top-6 right-6 text-5xl font-black text-[#61B2F0]/10">01</div>
                    <h3 class="text-lg font-black text-[#2A2523] mb-2">Search</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Browse verified listings by location, property type, price, and amenities.</p>
                </div>
                <div class="relative text-center p-8 rounded-2xl bg-[#D7E8F3]/30 border border-[#D7E8F3]">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center mx-auto mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div class="absolute top-6 right-6 text-5xl font-black text-[#61B2F0]/10">02</div>
                    <h3 class="text-lg font-black text-[#2A2523] mb-2">Connect</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Chat directly with landlords in real-time, ask questions, and schedule viewings.</p>
                </div>
                <div class="relative text-center p-8 rounded-2xl bg-[#D7E8F3]/30 border border-[#D7E8F3]">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center mx-auto mb-5 shadow-lg shadow-blue-500/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="absolute top-6 right-6 text-5xl font-black text-[#61B2F0]/10">03</div>
                    <h3 class="text-lg font-black text-[#2A2523] mb-2">Reserve</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Submit a reservation request and track its status until you're ready to move in.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- WHY ABANGANANHUB --}}
    <section class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <span class="text-[#61B2F0] font-bold text-sm uppercase tracking-widest">Why Us</span>
                <h2 class="text-3xl md:text-4xl font-black text-[#2A2523] mt-2">Built for Trust</h2>
                <p class="text-[#9B9F98] mt-3 max-w-xl mx-auto">Every feature exists to eliminate the risks of informal rental channels.</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-11 h-11 rounded-xl bg-[#D7E8F3] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#286CD2]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] mb-1">Verified Landlords</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Every landlord submits a government ID before listing a property.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-11 h-11 rounded-xl bg-[#D7E8F3] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#286CD2]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] mb-1">Approved Listings</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">All properties are reviewed and approved by admins before going live.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-11 h-11 rounded-xl bg-[#D7E8F3] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#286CD2]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] mb-1">Real-time Chat</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Talk directly with landlords without leaving the platform.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-11 h-11 rounded-xl bg-[#D7E8F3] flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-[#286CD2]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] mb-1">Secure Reservations</h3>
                    <p class="text-[#9B9F98] text-sm leading-relaxed">Submit and track reservation requests with full status visibility.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROPERTY TYPES --}}
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <span class="text-[#61B2F0] font-bold text-sm uppercase tracking-widest">What We Offer</span>
                <h2 class="text-3xl md:text-4xl font-black text-[#2A2523] mt-2">Property Types</h2>
                <p class="text-[#9B9F98] mt-3 max-w-xl mx-auto">From budget-friendly bedspaces to full apartments — find what fits your needs.</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="group text-center p-6 rounded-2xl border border-slate-200 hover:border-[#61B2F0] hover:bg-[#D7E8F3]/20 transition-all cursor-pointer">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 group-hover:bg-[#61B2F0] flex items-center justify-center mx-auto mb-4 transition-colors">
                        <svg class="w-6 h-6 text-[#5E6968] group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] text-sm">Bedspace</h3>
                    <p class="text-[#9B9F98] text-xs mt-1">Shared living, budget-friendly</p>
                </div>
                <div class="group text-center p-6 rounded-2xl border border-slate-200 hover:border-[#61B2F0] hover:bg-[#D7E8F3]/20 transition-all cursor-pointer">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 group-hover:bg-[#61B2F0] flex items-center justify-center mx-auto mb-4 transition-colors">
                        <svg class="w-6 h-6 text-[#5E6968] group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] text-sm">Room</h3>
                    <p class="text-[#9B9F98] text-xs mt-1">Private room, semi-furnished</p>
                </div>
                <div class="group text-center p-6 rounded-2xl border border-slate-200 hover:border-[#61B2F0] hover:bg-[#D7E8F3]/20 transition-all cursor-pointer">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 group-hover:bg-[#61B2F0] flex items-center justify-center mx-auto mb-4 transition-colors">
                        <svg class="w-6 h-6 text-[#5E6968] group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] text-sm">Apartment</h3>
                    <p class="text-[#9B9F98] text-xs mt-1">Self-contained unit</p>
                </div>
                <div class="group text-center p-6 rounded-2xl border border-slate-200 hover:border-[#61B2F0] hover:bg-[#D7E8F3]/20 transition-all cursor-pointer">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 group-hover:bg-[#61B2F0] flex items-center justify-center mx-auto mb-4 transition-colors">
                        <svg class="w-6 h-6 text-[#5E6968] group-hover:text-white transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] text-sm">House</h3>
                    <p class="text-[#9B9F98] text-xs mt-1">Whole house for rent</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA BANNER --}}
    <section class="py-24 bg-gradient-to-r from-[#286CD2] to-[#61B2F0]">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-black text-white mb-4">Ready to Find Your Next Home?</h2>
            <p class="text-white/80 text-lg mb-8">Join AbangananHub and access verified listings with confidence.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ route('register') }}" class="px-8 py-3 bg-white text-[#286CD2] font-black text-sm rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                    Create Free Account
                </a>
                <a href="{{ route('login') }}" class="px-8 py-3 bg-white/10 border border-white/30 text-white font-bold text-sm rounded-xl hover:bg-white/20 transition-colors">
                    Login
                </a>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="bg-[#2A2523] py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <span class="font-bold text-white">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
                </div>
                <div class="flex items-center gap-6 text-sm text-[#9B9F98]">
                    <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                    <a href="#" class="hover:text-white transition-colors">Contact</a>
                </div>
                <p class="text-[#9B9F98] text-xs">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>