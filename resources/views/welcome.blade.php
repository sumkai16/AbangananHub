<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AbangananHub — Find. Rent. Live with Confidence.</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .hero-bg {
            background-image: url('{{ asset('images/hero-bg.jpg') }}');
            background-size: cover;
            background-position: center;
        }
        .line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    </style>
</head>

<body class="font-sans antialiased text-[#2A2523] bg-white">

    {{-- ─── NAVBAR ─────────────────────────────────────────────── --}}
    <nav id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">

            {{-- Logo --}}
            <a href="/" class="flex items-center gap-2.5">
                <div id="logo-icon" class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span id="logo-text" class="font-black text-base text-white tracking-tight">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
            </a>

            {{-- Nav Links --}}
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="text-sm font-semibold text-white/90 hover:text-white px-4 py-2 rounded-lg hover:bg-white/10 transition-all">
                        Dashboard
                    </a>
              @else
                    <a href="{{ route('login') }}"
                    class="nav-link text-sm font-semibold text-white/90 hover:text-white px-4 py-2 rounded-lg hover:bg-white/10 transition-all">
                        Login
                    </a>
                    <a id="get-started-btn" href="{{ route('register') }}"
                        class="text-sm font-bold px-4 py-2 bg-white text-[#286CD2] rounded-lg hover:bg-[#D7E8F3] transition-colors shadow-sm">
                            Get Started
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ─── HERO ───────────────────────────────────────────────── --}}
    <section class="hero-bg relative min-h-screen flex flex-col items-center justify-center">

        {{-- Overlay --}}
        <div class="absolute inset-0 bg-gradient-to-b from-[#2A2523]/70 via-[#2A2523]/50 to-[#2A2523]/80"></div>

        {{-- Content --}}
        <div class="relative z-10 w-full max-w-3xl mx-auto px-6 text-center">

            {{-- SDG Badge --}}
            <span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur-sm border border-white/20 text-white/80 text-xs font-medium px-3 py-1.5 rounded-full mb-8">
                <span class="w-1.5 h-1.5 rounded-full bg-[#61B2F0] animate-pulse"></span>
                Supporting SDG 16 — Peace, Justice & Strong Institutions
            </span>

            {{-- Headline --}}
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-black text-white leading-[1.05] tracking-tight mb-4">
                Your next home<br>
                <span class="text-[#61B2F0]">is verified.</span>
            </h1>

            <p class="text-white/60 text-base md:text-lg font-medium mb-10 max-w-xl mx-auto leading-relaxed">
                Browse admin-verified rentals in Cebu. No scams, no fake listings — connect directly with trusted landlords.
            </p>

           {{-- Search Bar --}}
       <div class="flex items-center bg-white rounded-xl overflow-hidden max-w-lg mx-auto mb-10 focus-within:ring-0 focus-within:outline-none">
            <i class="ti ti-search ml-4" style="font-size: 16px; color: #9B9F98;"></i>
            <input type="text"
                placeholder="Search location or property..."
                class="flex-1 text-sm text-[#2A2523] placeholder-[#9B9F98] bg-transparent outline-none ring-0 focus:outline-none focus:ring-0 border-none font-medium py-3 px-3">
            <a href="{{ route('register') }}"
            class="bg-[#2A2523] hover:bg-[#3d3330] text-white text-xs font-bold px-5 py-4 transition-colors whitespace-nowrap">
                Search
            </a>
        </div>

            {{-- Trust stats --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-8 text-white/50 text-xs font-medium">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#61B2F0]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Admin-verified listings
                </span>
                <span class="hidden sm:block w-px h-3 bg-white/20"></span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#61B2F0]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Verified landlords only
                </span>
                <span class="hidden sm:block w-px h-3 bg-white/20"></span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#61B2F0]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Cebu coverage
                </span>
            </div>
        </div>

        {{-- Scroll hint --}}
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-white/30 text-xs font-medium">
            <span>Browse listings</span>
            <svg class="w-4 h-4 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </section>

    {{-- ─── BROWSE PROPERTIES ──────────────────────────────────── --}}
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-6">

            {{-- Filter bar --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-10">
                <div>
                    <h2 class="text-2xl font-black text-[#2A2523]">Available Properties</h2>
                    <p class="text-[#9B9F98] text-sm mt-0.5">{{ $properties->count() }} verified listings in Cebu</p>
                </div>

                {{-- Type filter chips --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button class="px-3.5 py-1.5 text-xs font-bold rounded-full bg-[#2A2523] text-white">All</button>
                    <button class="px-3.5 py-1.5 text-xs font-bold rounded-full border border-slate-200 text-[#9B9F98] hover:border-[#61B2F0] hover:text-[#61B2F0] transition-colors">Bedspace</button>
                    <button class="px-3.5 py-1.5 text-xs font-bold rounded-full border border-slate-200 text-[#9B9F98] hover:border-[#61B2F0] hover:text-[#61B2F0] transition-colors">Room</button>
                    <button class="px-3.5 py-1.5 text-xs font-bold rounded-full border border-slate-200 text-[#9B9F98] hover:border-[#61B2F0] hover:text-[#61B2F0] transition-colors">Apartment</button>
                    <button class="px-3.5 py-1.5 text-xs font-bold rounded-full border border-slate-200 text-[#9B9F98] hover:border-[#61B2F0] hover:text-[#61B2F0] transition-colors">House</button>
                </div>
            </div>

            {{-- Grid --}}
            @if($properties->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    @foreach($properties as $property)
                        <div class="group bg-white rounded-2xl border border-slate-100 hover:border-slate-200 hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col cursor-pointer">

                            {{-- Photo area --}}
                            <div class="relative h-48 bg-gradient-to-br from-[#D7E8F3] to-[#61B2F0]/20 overflow-hidden">

                                {{-- Placeholder --}}
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-[#61B2F0]/20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>

                                {{-- Type badge --}}
                                <span class="absolute bottom-3 left-3 text-xs font-bold px-2 py-1 rounded-lg backdrop-blur-sm
                                    @if($property->property_type === 'Bedspace') bg-purple-500/90 text-white
                                    @elseif($property->property_type === 'Room') bg-emerald-500/90 text-white
                                    @elseif($property->property_type === 'Apartment') bg-blue-500/90 text-white
                                    @else bg-orange-500/90 text-white
                                    @endif">
                                    {{ $property->property_type }}
                                </span>

                                {{-- Availability dot --}}
                                @if($property->availability_status === 'Available')
                                    <span class="absolute top-3 right-3 flex items-center gap-1 text-xs font-bold bg-white/90 backdrop-blur-sm text-emerald-600 px-2 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Available
                                    </span>
                                @else
                                    <span class="absolute top-3 right-3 flex items-center gap-1 text-xs font-bold bg-white/90 backdrop-blur-sm text-amber-600 px-2 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        {{ $property->availability_status }}
                                    </span>
                                @endif
                            </div>

                            {{-- Card body --}}
                            <div class="p-4 flex flex-col flex-1">

                                {{-- Price --}}
                                <div class="flex items-baseline gap-1 mb-2">
                                    <span class="text-xl font-black text-[#2A2523]">₱{{ number_format($property->rental_fee, 0) }}</span>
                                    <span class="text-xs text-[#9B9F98] font-medium">/month</span>
                                </div>

                                {{-- Title --}}
                                <h3 class="font-bold text-[#2A2523] text-sm leading-snug mb-1.5 line-clamp-2">
                                    {{ $property->title }}
                                </h3>

                                {{-- Address --}}
                                <div class="flex items-start gap-1 text-[#9B9F98] text-xs mt-auto pt-3 border-t border-slate-50">
                                    <svg class="w-3 h-3 mt-0.5 shrink-0 text-[#61B2F0]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="line-clamp-1">{{ $property->address }}</span>
                                </div>
                            </div>

                            {{-- CTA footer --}}
                            <div class="px-4 pb-4">
                                <a href="{{ route('login') }}"
                                   class="block w-full text-center text-xs font-bold py-2.5 rounded-xl border-2 border-[#61B2F0] text-[#61B2F0] hover:bg-[#61B2F0] hover:text-white transition-all duration-200">
                                    View Property
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- View more --}}
                <div class="text-center mt-10">
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center gap-2 px-6 py-3 border-2 border-[#2A2523] text-[#2A2523] font-bold text-sm rounded-xl hover:bg-[#2A2523] hover:text-white transition-all duration-200">
                        View all listings
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

            @else
                <div class="text-center py-24">
                    <div class="w-14 h-14 rounded-2xl bg-[#D7E8F3] flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-[#61B2F0]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <h3 class="font-black text-[#2A2523] mb-1">No listings yet</h3>
                    <p class="text-[#9B9F98] text-sm">Properties are being verified. Check back soon.</p>
                </div>
            @endif
        </div>
    </section>

        {{-- ─── HOW IT WORKS ───────────────────────────────────────── --}}
        <section class="py-20 bg-[#F8FAFC] border-t border-slate-100">
            <div class="max-w-4xl mx-auto px-6">
                <div class="text-center mb-14">
                    <h2 class="text-2xl font-black text-[#2A2523]">How it works</h2>
                    <p class="text-[#9B9F98] text-sm mt-2">From search to move-in in three steps.</p>
                </div>

                {{-- Mobile: vertical stack | Desktop: horizontal timeline --}}
                <div class="flex flex-col md:flex-row md:items-start md:gap-0 gap-8">

                    {{-- Step 1 --}}
                    <div class="flex md:flex-col md:text-center md:flex-1 items-start gap-5 md:gap-0 md:items-center md:px-6">
                        <div class="w-10 h-10 rounded-full bg-[#61B2F0] flex items-center justify-center shrink-0 md:mb-4">
                            <i class="ti ti-search text-white" style="font-size: 18px;"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-[#2A2523] mb-1">Search</p>
                            <p class="text-xs text-[#9B9F98] leading-relaxed">Browse verified listings by location, type, and price range.</p>
                        </div>
                    </div>

                    {{-- Connector — hidden on mobile, visible on desktop --}}
                    <div class="hidden md:block flex-1 h-px bg-slate-200 mt-5 self-start"></div>

                    {{-- Step 2 --}}
                    <div class="flex md:flex-col md:text-center md:flex-1 items-start gap-5 md:gap-0 md:items-center md:px-6">
                        <div class="w-10 h-10 rounded-full bg-white border-2 border-[#61B2F0] flex items-center justify-center shrink-0 md:mb-4">
                            <i class="ti ti-message text-[#61B2F0]" style="font-size: 18px;"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-[#2A2523] mb-1">Connect</p>
                            <p class="text-xs text-[#9B9F98] leading-relaxed">Chat directly with landlords and schedule a viewing.</p>
                        </div>
                    </div>

                    {{-- Connector --}}
                    <div class="hidden md:block flex-1 h-px bg-slate-200 mt-5 self-start"></div>

                    {{-- Step 3 --}}
                    <div class="flex md:flex-col md:text-center md:flex-1 items-start gap-5 md:gap-0 md:items-center md:px-6">
                        <div class="w-10 h-10 rounded-full bg-white border-2 border-[#61B2F0] flex items-center justify-center shrink-0 md:mb-4">
                            <i class="ti ti-circle-check text-[#61B2F0]" style="font-size: 18px;"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-[#2A2523] mb-1">Reserve</p>
                            <p class="text-xs text-[#9B9F98] leading-relaxed">Submit a request and track your status until move-in.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    {{-- ─── FOOTER ─────────────────────────────────────────────── --}}
    <footer class="bg-[#2A2523] py-8">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-[#61B2F0]/20 border border-[#61B2F0]/30 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-[#61B2F0]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="font-black text-sm text-white">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
            </div>
            <div class="flex items-center gap-6 text-xs text-[#9B9F98]">
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
                <a href="#" class="hover:text-white transition-colors">Contact</a>
            </div>
            <p class="text-[#9B9F98] text-xs">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
        </div>
    </footer>

    {{-- Navbar scroll behavior --}}
 <script>
        const navbar = document.getElementById('navbar');
        const logoText = document.getElementById('logo-text');
        const logoIcon = document.getElementById('logo-icon');
        const navLinks = document.querySelectorAll('.nav-link');
        const getStartedBtn = document.getElementById('get-started-btn');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 80) {
                navbar.classList.add('bg-white/95', 'backdrop-blur-md', 'border-b', 'border-slate-200/80', 'shadow-sm');

                logoText.innerHTML = 'Abanganan<span class="text-[#61B2F0]">Hub</span>';
                logoText.classList.remove('text-white');
                logoText.classList.add('text-[#2A2523]');

                logoIcon.classList.remove('bg-white/20', 'border-white/30');
                logoIcon.classList.add('bg-[#D7E8F3]', 'border-[#61B2F0]/30');
                logoIcon.querySelector('svg').classList.remove('text-white');
                logoIcon.querySelector('svg').classList.add('text-[#286CD2]');

                navLinks.forEach(l => {
                    l.classList.remove('text-white/90', 'text-white', 'hover:bg-white/10');
                    l.classList.add('text-[#2A2523]', 'hover:bg-slate-100');
                });

                if (getStartedBtn) {
                    getStartedBtn.classList.remove('bg-white', 'text-[#286CD2]', 'hover:bg-[#D7E8F3]');
                    getStartedBtn.classList.add('bg-[#286CD2]', 'text-white', 'hover:bg-[#61B2F0]');
                }
            } else {
                navbar.classList.remove('bg-white/95', 'backdrop-blur-md', 'border-b', 'border-slate-200/80', 'shadow-sm');

                logoText.innerHTML = 'Abanganan<span class="text-[#61B2F0]">Hub</span>';
                logoText.classList.remove('text-[#2A2523]');
                logoText.classList.add('text-white');

                logoIcon.classList.remove('bg-[#D7E8F3]', 'border-[#61B2F0]/30');
                logoIcon.classList.add('bg-white/20', 'border-white/30');
                logoIcon.querySelector('svg').classList.remove('text-[#286CD2]');
                logoIcon.querySelector('svg').classList.add('text-white');

                navLinks.forEach(l => {
                    l.classList.remove('text-[#2A2523]', 'hover:bg-slate-100');
                    l.classList.add('text-white/90', 'hover:bg-white/10');
                });

                if (getStartedBtn) {
                    getStartedBtn.classList.remove('bg-[#286CD2]', 'text-white', 'hover:bg-[#61B2F0]');
                    getStartedBtn.classList.add('bg-white', 'text-[#286CD2]', 'hover:bg-[#D7E8F3]');
                }
            }
        });
</script>

</body>
</html>