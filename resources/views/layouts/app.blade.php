<!DOCTYPE html>
<html lang="en" @if(View::hasSection('themeable')) data-themeable="1" @endif>
<meta name="user-authenticated" content="{{ auth()->check() ? '1' : '0' }}">

<head>
    {{-- Apply the saved theme before first paint (no light->dark flash). Only pages
         that opt in with @section('themeable') ever go dark. --}}
    <script>
        try {
            if (document.documentElement.dataset.themeable === '1' && localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AbangananHub-icon-256.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.Echo.channel('test-channel')
            .listen('.TestBroadcast', (e) => {
                console.log('Broadcast received:', e);
            });
    });
</script>

<body class="font-sans bg-[#F7F8FC] text-[#060D26] min-h-screen flex flex-col" x-data="{}">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#FF8A66] focus:text-[#060D26] focus:font-semibold">Skip to main content</a>

    <header id="site-header" x-data="{ mobileNavOpen: false }"
        class="bg-white border-b border-[#E2E4EC] sticky top-0 z-[100] transition-all duration-300">

        {{-- 1. Nav Row --}}
        <div class="flex items-center justify-between px-4 sm:px-6 lg:px-10 h-[64px] relative">

            {{-- Logo --}}
            <a href="{{ route('home') }}"
                class="flex items-center gap-1.5 sm:gap-2.5 no-underline flex-shrink-0 group">
                <img src="{{ asset('images/AbangananHub-icon-256.png') }}" alt="AbangananHub"
                    class="w-8 h-8 sm:w-10 sm:h-10 object-contain transition-transform group-hover:scale-105">
                <span class="text-[16px] sm:text-[18px] font-extrabold text-[#060D26] tracking-tight">
                    Abanganan<span class="text-[#FF8A66]">Hub</span>
                </span>
            </a>

            {{-- Primary nav — sits beside the logo, deliberately not centred: the
                 collapsed search pill below is `absolute left-1/2`, and a centred
                 nav would land underneath it on scroll. Hidden below `lg`; phones
                 get the same links via the `mobileNavOpen` hamburger panel instead
                 (see `#mobile-nav-panel` further down). --}}
            <nav aria-label="Primary" class="hidden lg:flex items-center gap-1 ml-8 mr-auto">
                {{-- Block form, not the inline parenthesised one: that emitted an
                     unterminated PHP open tag here and swallowed the rest of the
                     header. Never write a literal PHP open tag in a Blade comment
                     either — Blade tokenises with token_get_all(), so one inside a
                     comment still opens a PHP block and silently drops the markup
                     that follows it. --}}
                @php
                    $onHome = request()->routeIs('home');
                    $onBrowse = request()->routeIs('properties.index');
                    $onAreas = request()->routeIs('properties.areas');
                @endphp

                <a href="{{ route('home') }}" @if($onHome) aria-current="page" @endif
                    class="px-3.5 py-2 rounded-full text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer {{ $onHome ? 'text-[#B35A3D] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC] hover:text-[#B35A3D]' }}">
                    Home
                </a>


                <a href="{{ route('properties.index') }}" @if($onBrowse) aria-current="page" @endif
                    class="px-3.5 py-2 rounded-full text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer {{ $onBrowse ? 'text-[#B35A3D] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC] hover:text-[#B35A3D]' }}">
                    Browse Rentals
                </a>

                <a href="{{ route('properties.areas') }}" @if($onAreas) aria-current="page" @endif
                    class="px-3.5 py-2 rounded-full text-[13.5px] font-semibold transition-colors duration-200 cursor-pointer {{ $onAreas ? 'text-[#B35A3D] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC] hover:text-[#B35A3D]' }}">
                    Areas
                </a>

                <a href="{{ route('about') }}#how-it-works"
                    class="px-3.5 py-2 rounded-full text-[13.5px] font-semibold text-[#060D26] hover:bg-[#F7F8FC] hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer">
                    How it works
                </a>
            </nav>

            {{-- Right Actions --}}
            <div class="flex items-center gap-3">

                @hasSection('themeable')
                    <button type="button" id="theme-toggle" aria-label="Switch between light and dark theme" title="Switch theme"
                        class="flex items-center justify-center w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] hover:border-[#FF8A66] hover:text-[#B35A3D] transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50">
                        {{-- Moon: shown in the light theme (click → dark) --}}
                        <svg class="theme-icon-moon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        {{-- Sun: shown in the dark theme (click → light) --}}
                        <svg class="theme-icon-sun w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                    </button>
                    <script>
                        document.getElementById('theme-toggle').addEventListener('click', function () {
                            const dark = document.documentElement.classList.toggle('dark');
                            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
                        });
                    </script>
                @endif

                {{-- Mobile nav toggle — this header has no `lg:flex` primary nav
                     below `lg` (see the comment above), so this is the only way a
                     phone visitor reaches Browse/Areas/How it works. --}}
                <button type="button" @click="mobileNavOpen = !mobileNavOpen" aria-label="Menu"
                    :aria-expanded="mobileNavOpen ? 'true' : 'false'" aria-haspopup="true" aria-controls="mobile-nav-panel"
                    :class="mobileNavOpen ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#B35A3D]'"
                    class="lg:hidden flex items-center justify-center w-10 h-10 rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 cursor-pointer">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path x-show="!mobileNavOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        <path x-show="mobileNavOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                {{-- Messages — mobile equivalent of the floating chat bubble,
                     which is desktop-only (see partials.message-notifications).
                     Dispatches the same event that bubble's panel listens for. --}}
                @auth
                    @php
                        $mobileUnreadMsgCount = $unreadMessageCount;
                    @endphp
                    <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('open-messages-panel'))"
                        class="lg:hidden relative flex items-center gap-1.5 h-10 px-3 rounded-full border border-[#E2E4EC] text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] transition-colors cursor-pointer">
                        Messages
                        @if($mobileUnreadMsgCount > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-[#EF4444] text-white text-[10px] font-bold">{{ $mobileUnreadMsgCount > 99 ? '99+' : $mobileUnreadMsgCount }}</span>
                        @endif
                    </button>
                @endauth

                @auth

                    {{-- Become a Landlord / My Listings / Admin Actions --}}
                    <div class="hidden sm:block">
                        @if(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin'))
                            <a href="{{ route('landlord.properties.index') }}"
                                class="flex items-center gap-2 h-10 px-5 rounded-full bg-[#ECEEF6] text-[13.5px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">
                                Landlord Dashboard
                            </a>
                        @elseif(auth()->user()->hasRole('Admin'))
                            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                                <button type="button" @click="open = !open" @click.outside="open = false"
                                    :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true"
                                    class="flex items-center gap-2 h-10 px-5 rounded-full bg-[#ECEEF6] text-[13.5px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">
                                    Admin Actions
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5" class="transition-transform duration-200 motion-reduce:transition-none"
                                        :class="open && 'rotate-180'" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open" x-cloak
                                    x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-200"
                                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1 motion-reduce:scale-100 motion-reduce:translate-y-0"
                                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 scale-95 -translate-y-1 motion-reduce:scale-100 motion-reduce:translate-y-0"
                                    class="absolute top-[calc(100%+10px)] right-0 w-[236px] bg-white rounded-2xl shadow-[0_16px_48px_-12px_rgba(6,13,38,0.20)] ring-1 ring-[#E2E4EC] p-1.5 z-50">
                                    @php
                                        $adminLinks = [
                                            ['route' => \Illuminate\Support\Facades\Route::has('admin.listings.approval') ? route('admin.listings.approval') : '#', 'label' => 'Listing Approval', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                                            ['route' => route('admin.verifications.index'), 'label' => 'Verification Requests', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                                            ['route' => route('admin.users.index'), 'label' => 'Manage Users', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                                        ];
                                    @endphp
                                    @foreach($adminLinks as $link)
                                        <a href="{{ $link['route'] }}"
                                            class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="1.9" class="text-[#5B6A8E] group-hover:text-[#B35A3D] transition-colors duration-200" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                                            </svg>
                                            {{ $link['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a href="{{ route('landlord.verification.create') }}"
                                class="flex items-center gap-2 h-10 px-5 rounded-full bg-[#ECEEF6] text-[13.5px] font-semibold text-[#060D26] hover:brightness-95 transition-all cursor-pointer">
                                Become a Landlord
                            </a>
                        @endif
                    </div>

                    {{-- Notifications Dropdown --}}
                    <div class="relative" x-data="notificationDropdown()" @click.away="close()"
                        @keydown.escape.window="close()">
                        <button type="button" @click="toggle()" aria-label="Notifications"
                            :class="open ? 'bg-[#ECEEF6] text-[#060D26]' : 'text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#B35A3D]'"
                            class="relative flex items-center justify-center w-10 h-10 rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 cursor-pointer">
                            <span x-show="unreadCount > 0" x-cloak
                                class="absolute top-[7px] right-[8px] w-2.5 h-2.5 rounded-full bg-[#060D26] ring-2 ring-white"></span>
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-[calc(100%+10px)] right-0 w-[calc(100vw-2rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#E2E4EC] z-50 overflow-hidden">
                            <div x-ref="dropdownBody">
                                <div class="px-4 py-8 text-center">
                                    <div
                                        class="w-6 h-6 border-2 border-[#5B6A8E] border-t-transparent rounded-full animate-spin mx-auto">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Avatar Dropdown --}}
                    @php
                        $abgRoleLabel = auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin')
                            ? 'Landlord'
                            : (auth()->user()->hasRole('Admin') ? 'Administrator' : 'Tenant');
                    @endphp
                    @php
                        $userRoles = auth()->user()->roles->pluck('role');
                        $abgFullName = trim(auth()->user()->first_name . ' ' . auth()->user()->last_name);
                        // One shared row style so every item lines up and hovers identically.
                        $menuRow = 'group flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] transition-colors';
                        $menuIcon = 'text-[#5B6A8E] group-hover:text-[#B35A3D] transition-colors duration-200 shrink-0';
                        $menuLabel = 'px-3 pt-2.5 pb-1 text-[11px] font-bold text-[#94A3B8] uppercase tracking-wider';
                    @endphp
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                            :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true"
                            :class="open ? 'bg-[#ECEEF6]' : 'hover:bg-[#F7F8FC]'"
                            class="flex items-center gap-2.5 pl-1 pr-2.5 py-1 rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 cursor-pointer">
                            @if(auth()->user()->profile_picture)
                                <img loading="lazy" decoding="async" src="{{ auth()->user()->profile_picture }}" alt="{{ $abgFullName }}"
                                    class="w-9 h-9 rounded-full object-cover shrink-0">
                            @else
                                <span
                                    class="w-9 h-9 rounded-full bg-[#060D26] text-white text-[14px] font-bold flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                                </span>
                            @endif
                            <span class="hidden sm:flex flex-col items-start leading-tight">
                                <span class="text-[13px] font-semibold text-[#060D26]">{{ $abgFullName }}</span>
                                <span class="text-[11px] text-[#5B6A8E]">{{ $abgRoleLabel }}</span>
                            </span>
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2" class="text-[#94A3B8] hidden sm:block transition-transform duration-200 motion-reduce:transition-none"
                                :class="open && 'rotate-180'" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="open" x-cloak
                            x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-200"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-1 motion-reduce:scale-100 motion-reduce:translate-y-0"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 -translate-y-1 motion-reduce:scale-100 motion-reduce:translate-y-0"
                            class="absolute top-[calc(100%+10px)] right-0 w-[264px] bg-white rounded-2xl shadow-[0_16px_48px_-12px_rgba(6,13,38,0.20)] ring-1 ring-[#E2E4EC] p-1.5 z-50">

                            {{-- Account header — the one distinctive touch: a mist band that
                                 turns the menu into an identity surface, not a flat list. --}}
                            <div class="flex items-center gap-3 rounded-xl bg-gradient-to-br from-[#ECEEF6] to-[#F7F8FC] px-3 py-3 mb-1">
                                @if(auth()->user()->profile_picture)
                                    <img loading="lazy" decoding="async" src="{{ auth()->user()->profile_picture }}" alt="{{ $abgFullName }}"
                                        class="w-11 h-11 rounded-full object-cover shrink-0 ring-2 ring-white">
                                @else
                                    <span class="w-11 h-11 rounded-full bg-[#060D26] text-white text-[16px] font-bold flex items-center justify-center shrink-0 ring-2 ring-white">
                                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[13.5px] font-bold text-[#060D26] truncate">{{ $abgFullName }}</p>
                                        <span class="shrink-0 inline-flex items-center h-4 px-1.5 rounded-full bg-white text-[9.5px] font-bold uppercase tracking-wide text-[#060D26] ring-1 ring-[#FF8A66]/25">{{ $abgRoleLabel }}</span>
                                    </div>
                                    <p class="text-[12px] text-[#5B6A8E] truncate mt-0.5">{{ auth()->user()->email }}</p>
                                </div>
                            </div>

                            {{-- Section: Activity --}}
                            <p class="{{ $menuLabel }}">Activity</p>

                            @if($userRoles->contains('Tenant'))
                                <a href="{{ route('reservations.index') }}" class="{{ $menuRow }}">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    My Reservations
                                </a>
                            @endif

                            @if($userRoles->contains('Landlord'))
                                <a href="{{ route('landlord.reservations.index') }}" class="{{ $menuRow }}">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    Reservation Requests
                                </a>
                            @endif

                            <a href="{{ route('conversations.index') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Messages
                            </a>

                            <a href="{{ route('notifications.index') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                                Notifications
                            </a>

                            <a href="{{ route('favorites.index') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                                Saved Listings
                            </a>

                            <div class="h-px bg-[#E2E4EC] mx-2 my-1.5"></div>

                            {{-- Section: Account --}}
                            <p class="{{ $menuLabel }}">Account</p>

                            <a href="{{ auth()->user()->hasRole('Landlord') ? route('landlord.profile.me') : route('tenant.profile.show') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                My Profile
                            </a>
                            <a href="{{ route('profile.edit') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.43.991a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Account Settings
                            </a>
                            <a href="{{ route('reports.create') }}" class="{{ $menuRow }}">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="{{ $menuIcon }}" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                                Report a Problem
                            </a>

                            <div class="h-px bg-[#E2E4EC] mx-2 my-1.5"></div>

                            {{-- Sign out --}}
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="w-full group flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-[#EF4444] hover:bg-[#EF4444]/[0.07] transition-colors cursor-pointer">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" class="shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- Guest Actions --}}
                    <div class="flex items-center gap-1 sm:gap-2">
                        <button type="button" onclick="openAuthModal('login')"
                            class="text-[13px] sm:text-[14px] font-bold text-[#060D26] hover:bg-[#ECEEF6] px-3 sm:px-4 py-2 rounded-full transition-colors focus:outline-none whitespace-nowrap no-underline">
                            Log in
                        </button>

                        <button type="button" onclick="openAuthModal('register')"
                            class="text-[13px] sm:text-[14px] font-bold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] px-4 sm:px-5 py-2 rounded-full transition-all shadow-sm focus:outline-none whitespace-nowrap no-underline">
                            Sign up
                        </button>
                    </div>
                @endauth
            </div>
        </div>

        {{-- 3. Mobile nav panel — the `lg:hidden` counterpart to the primary
             nav at the top of this file, which is `hidden` below `lg`. Same
             transition timing as the Areas/Avatar dropdowns above it, so it
             reads as one design system rather than a bolted-on menu. --}}
        <div x-show="mobileNavOpen" x-cloak id="mobile-nav-panel" @click.outside="mobileNavOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="lg:hidden border-t border-[#E2E4EC] bg-white px-4 py-3">

            <a href="{{ route('home') }}" @click="mobileNavOpen = false"
                @if($onHome) aria-current="page" @endif
                class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold {{ $onHome ? 'text-[#060D26] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC]' }}">
                Home
            </a>


            <a href="{{ route('properties.index') }}" @click="mobileNavOpen = false"
                @if($onBrowse) aria-current="page" @endif
                class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold {{ $onBrowse ? 'text-[#060D26] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC]' }}">
                Browse Rentals
            </a>

            <a href="{{ route('properties.areas') }}" @click="mobileNavOpen = false"
                @if($onAreas) aria-current="page" @endif
                class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold {{ $onAreas ? 'text-[#060D26] bg-[#ECEEF6]' : 'text-[#060D26] hover:bg-[#F7F8FC]' }}">
                Areas
            </a>

            <a href="{{ route('about') }}#how-it-works" @click="mobileNavOpen = false"
                class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold text-[#060D26] hover:bg-[#F7F8FC]">
                How it works
            </a>

            @auth
                <div class="h-px bg-[#E2E4EC] my-2"></div>

                @if(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin'))
                    <a href="{{ route('landlord.properties.index') }}" @click="mobileNavOpen = false"
                        class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold text-[#060D26] bg-[#ECEEF6] hover:brightness-95">
                        Landlord Dashboard
                    </a>
                @elseif(auth()->user()->hasRole('Admin'))
                    @php
                        $mobileAdminLinks = [
                            ['route' => \Illuminate\Support\Facades\Route::has('admin.listings.approval') ? route('admin.listings.approval') : '#', 'label' => 'Listing Approval'],
                            ['route' => route('admin.verifications.index'), 'label' => 'Verification Requests'],
                            ['route' => route('admin.users.index'), 'label' => 'Manage Users'],
                        ];
                    @endphp
                    @foreach($mobileAdminLinks as $link)
                        <a href="{{ $link['route'] }}" @click="mobileNavOpen = false"
                            class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold text-[#060D26] bg-[#ECEEF6] hover:brightness-95 {{ !$loop->first ? 'mt-1.5' : '' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ route('landlord.verification.create') }}" @click="mobileNavOpen = false"
                        class="block px-3.5 py-2.5 rounded-xl text-[14px] font-semibold text-[#060D26] bg-[#ECEEF6] hover:brightness-95">
                        Become a Landlord
                    </a>
                @endif
            @endauth
        </div>

    </header>

    {{-- 2. Search + category band. Deliberately OUTSIDE the sticky <header>: only the nav row follows
         the visitor down the page; this band scrolls away with the content. The Browse page opts in
         (@section('sticky_search')) so search + filters stay put on desktop while only the listings scroll. z-[60] keeps the search
         pill's dropdowns above the listings but under the sticky header (z-[100]). --}}
    @if(($searchBar ?? true) && !View::hasSection('hide_search'))
        <div id="header-search-expanded" class="{{ View::hasSection('sticky_search') ? 'relative lg:sticky lg:top-[64px]' : 'relative' }} z-[60] bg-white border-b border-[#E2E4EC]">
            <div class="bg-[#060D26]">
                <div class="max-w-[1400px] mx-auto flex justify-center px-4 sm:px-6 py-4">
                    <x-search-pill variant="header" />
                </div>
            </div>

            <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
                <x-category-strip />
            </div>
        </div>
    @endif

    <main id="main" class="flex-grow">
        @yield('content')
    </main>

    <footer class="bg-[#060D26] mt-auto">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-8">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 no-underline mb-10">
                <img src="{{ asset('images/AbangananHub-icon-256.png') }}" alt="AbangananHub" class="w-8 h-8 object-contain shrink-0">
                <span class="text-[16px] font-bold text-white tracking-tight">Abanganan<span class="text-[#FF8A66]">Hub</span></span>
            </a>

            {{-- Link columns --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-x-8 gap-y-10 pb-12">

                <div>
                    <p class="text-[12px] font-semibold text-white/55 uppercase tracking-widest mb-4">Explore</p>
                    <ul class="space-y-3 text-[14px]">
                        <li><a href="{{ route('home') }}" class="text-white/75 hover:text-white transition-colors">Home</a></li>
                        <li><a href="{{ route('properties.index') }}" class="text-white/75 hover:text-white transition-colors">Browse Properties</a></li>
                        <li><a href="{{ route('about') }}" class="text-white/75 hover:text-white transition-colors">About Us</a></li>
                    </ul>
                </div>

                @auth
                    <div>
                        <p class="text-[12px] font-semibold text-white/55 uppercase tracking-widest mb-4">For Tenants</p>
                        <ul class="space-y-3 text-[14px]">
                            <li><a href="{{ route('favorites.index') }}" class="text-white/75 hover:text-white transition-colors">Saved Listings</a></li>
                            <li><a href="{{ route('conversations.index') }}" class="text-white/75 hover:text-white transition-colors">Messages</a></li>
                            <li><a href="{{ route('reservations.index') }}" class="text-white/75 hover:text-white transition-colors">My Reservations</a></li>
                            <li><a href="{{ route('reports.create') }}" class="text-white/75 hover:text-white transition-colors">Report a Problem</a></li>
                        </ul>
                    </div>
                @endauth

                @auth
                    <div>
                        <p class="text-[12px] font-semibold text-white/55 uppercase tracking-widest mb-4">For Landlords</p>
                        <ul class="space-y-3 text-[14px]">
                            <li><a href="{{ route('landlord.verification.create') }}" class="text-white/75 hover:text-white transition-colors">Become a Landlord</a></li>
                            <li><a href="{{ route('landlord.dashboard') }}" class="text-white/75 hover:text-white transition-colors">Landlord Dashboard</a></li>
                            <li><a href="{{ route('landlord.analytics.index') }}" class="text-white/75 hover:text-white transition-colors">Analytics &amp; Occupancy</a></li>
                        </ul>
                    </div>
                @endauth

                <div>
                    <p class="text-[12px] font-semibold text-white/55 uppercase tracking-widest mb-4">Company</p>
                    <ul class="space-y-3 text-[14px]">
                        <li><a href="{{ route('privacy') }}" class="text-white/75 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" class="text-white/75 hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="text-white/75 hover:text-white transition-colors">Help Center</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-[12px] font-semibold text-white/55 uppercase tracking-widest mb-4">Social</p>
                    <ul class="space-y-3 text-[14px]">
                        <li><a href="#" class="text-white/75 hover:text-white transition-colors">Facebook</a></li>
                        <li><a href="#" class="text-white/75 hover:text-white transition-colors">Instagram</a></li>
                        <li><a href="#" class="text-white/75 hover:text-white transition-colors">X (Twitter)</a></li>
                    </ul>
                </div>
            </div>

            {{-- Bottom bar --}}
            <div class="pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-[13px] text-white/60 text-center sm:text-left">
                    &copy; {{ date('Y') }} AbangananHub. All rights reserved.
                </p>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/10 text-[12px] font-semibold text-white/70">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#FF8A66]"></span>
                    Supporting UN SDG 11 &middot; Cebu, Philippines
                </span>
            </div>
        </div>
    </footer>

    {{-- ========================================== --}}
    {{-- UNIFIED DYNAMIC AUTH MODAL (AJAX/FETCH) --}}
    {{-- ========================================== --}}
    @guest
        <div id="auth-modal"
            class="hidden fixed inset-0 z-[9999] bg-[#060D26]/40 backdrop-blur-sm items-center justify-center p-4 opacity-0 transition-opacity duration-300">

            <div class="bg-white rounded-[24px] shadow-2xl max-w-[820px] w-full relative transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] opacity-0 scale-95 translate-y-4 motion-reduce:transform-none max-h-[calc(100vh-2rem)] overflow-hidden flex flex-col md:flex-row"
                id="auth-modal-content" role="dialog" aria-modal="true" aria-label="Log in or create an account">

                {{-- Left brand panel (split) — photo + navy overlay, same language as the landing hero --}}
                <div class="hidden md:flex md:w-[42%] shrink-0 relative overflow-hidden bg-[#060D26] p-8 flex-col justify-end text-white">
                    <img src="{{ asset('images/auth-bg-1600.jpg') }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-b from-[#060D26]/25 via-[#060D26]/45 to-[#060D26]/90"></div>

                    <div class="relative z-10">
                        <h3 id="auth-side-title" class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[28px] font-extrabold tracking-tight leading-[1.1]">Welcome back</h3>
                        <p id="auth-side-subtitle" class="text-white/75 text-[13px] mt-3 leading-relaxed max-w-[17rem]">
                            Pick up where you left off.
                        </p>
                    </div>

                </div>

                {{-- Right form panel (split) --}}
                <div class="w-full md:w-[58%] relative bg-white p-6 sm:p-8 md:p-10 max-h-[calc(100vh-2rem)] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">

                    <button type="button" onclick="closeAuthModal()"
                        class="absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full border border-[#E2E4EC] text-[#5B6A8E] hover:text-[#060D26] hover:border-[#FF8A66] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] transition-colors cursor-pointer" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>

                    <div id="modal-error-bag" role="alert"
                        class="hidden mb-4 p-3 bg-[#EF4444]/[0.07] text-[#DC2626] rounded-xl text-sm border border-[#EF4444]/20"></div>

                    {{-- Login View --}}
                    <div id="login-form-view" class="hidden">
                        <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[26px] font-extrabold text-[#060D26] tracking-tight leading-tight mb-6">Log in</h2>

                        <form id="ajax-login-form" onsubmit="handleAuthSubmit(event, '{{ route('login') }}')">
                            @csrf
                            <div class="mb-4">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Email address</label>
                                <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address" autocomplete="username"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field" id="error-login-email"></span>
                            </div>

                            <div class="mb-4">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Password</label>
                                <div class="relative">
                                    <input type="password" name="password" id="modal-login-password" required placeholder="Your password" aria-label="Password" autocomplete="current-password"
                                        class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                    <button type="button" onclick="toggleModalPassword('modal-login-password', this)"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-[#5B6A8E] hover:text-[#060D26]" aria-label="Show password">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z">
                                            </path>
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field" id="error-login-password"></span>
                            </div>

                            <div class="flex items-center justify-between text-[13px] mb-6">
                                <label class="flex items-center gap-2 text-[#5B6A8E] cursor-pointer select-none">
                                    <input type="checkbox" name="remember"
                                        class="w-4 h-4 rounded text-[#B35A3D] border-[#E2E4EC] focus:ring-[#FF8A66]">
                                    Remember me
                                </label>
                                <a href="#" onclick="openAuthModal('forgot-password'); return false;" class="text-[#060D26] font-semibold hover:underline">Forgot password?</a>
                            </div>

                            <button type="submit"
                                class="w-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] bg-[#FF8A66] text-[#060D26] font-bold py-3 rounded-full hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer">
                                Log in
                            </button>
                        </form>

                        <x-social-login-buttons />

                        <p class="text-[13px] text-center text-[#5B6A8E] mt-6">
                            Don't have an account? <a href="#" onclick="openAuthModal('register'); return false;"
                                class="text-[#060D26] font-bold hover:underline">Sign up</a>
                        </p>
                    </div>

                    {{-- Register View --}}
                    <div id="register-form-view" class="hidden">
                        <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[26px] font-extrabold text-[#060D26] tracking-tight leading-tight mb-6">Create your account</h2>

                        <form id="ajax-register-form" onsubmit="handleAuthSubmit(event, '{{ route('register') }}')">
                            @csrf
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">First name</label>
                                    <input type="text" name="first_name" required placeholder="Maria" aria-label="First name" autocomplete="given-name"
                                        class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                    <span class="text-xs text-[#DC2626] mt-1 hidden error-field"
                                        id="error-register-first_name"></span>
                                </div>
                                <div>
                                    <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Last name</label>
                                    <input type="text" name="last_name" required placeholder="Santos" aria-label="Last name" autocomplete="family-name"
                                        class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                    <span class="text-xs text-[#DC2626] mt-1 hidden error-field"
                                        id="error-register-last_name"></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Contact number</label>
                                <input type="tel" name="contact_number" required placeholder="e.g. 0917 123 4567" aria-label="Contact number" autocomplete="tel" inputmode="tel"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field"
                                    id="error-register-contact_number"></span>
                            </div>

                            <div class="mb-3">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Email address</label>
                                <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address" autocomplete="email"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field" id="error-register-email"></span>
                            </div>

                            <div class="mb-3">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Password</label>
                                <div class="relative">
                                    <input type="password" name="password" id="modal-register-password" autocomplete="new-password" required placeholder="At least 8 characters" aria-label="Password"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all pr-11">
                                    <button type="button" onclick="toggleModalPassword('modal-register-password', this)"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-[#5B6A8E] hover:text-[#060D26]" aria-label="Show password"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></button>
                                </div>
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field" id="error-register-password"></span>
                                <p class="text-[11.5px] text-[#5B6A8E] mt-1">Use at least 8 characters.</p>
                            </div>

                            <div class="mb-5">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Confirm Password</label>
                                <div class="relative">
                                    <input type="password" name="password_confirmation" id="modal-register-password-confirm" autocomplete="new-password" required placeholder="Repeat your password" aria-label="Confirm password"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all pr-11">
                                    <button type="button" onclick="toggleModalPassword('modal-register-password-confirm', this)"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-[#5B6A8E] hover:text-[#060D26]" aria-label="Show password"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></button>
                                </div>
                            </div>

                            <button type="submit"
                                class="w-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] bg-[#FF8A66] text-[#060D26] font-bold py-3 rounded-full hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer">
                                Sign Up
                            </button>
                        </form>

                        <x-social-login-buttons />

                        <p class="text-[13px] text-center text-[#5B6A8E] mt-6">
                            Already have an account? <a href="#" onclick="openAuthModal('login'); return false;"
                                class="text-[#060D26] font-bold hover:underline">Log in</a>
                        </p>
                    </div>

                    {{-- Forgot Password View --}}
                    <div id="forgot-password-form-view" class="hidden">
                        <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[26px] font-extrabold text-[#060D26] tracking-tight leading-tight">Forgot your password?</h2>
                        <p class="text-[13.5px] text-[#5B6A8E] mt-1.5 mb-6">No problem. We'll email you a reset link.</p>

                        <form id="ajax-forgot-password-form" onsubmit="handleForgotPasswordSubmit(event, '{{ route('password.email') }}')">
                            @csrf
                            <div class="mb-5">
                                <label class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[12.5px] font-bold text-[#060D26] mb-1.5">Email address</label>
                                <input type="email" name="email" required placeholder="you@example.com" aria-label="Email address" autocomplete="email"
                                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[14px] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all">
                                <span class="text-xs text-[#DC2626] mt-1 hidden error-field" id="error-forgot-password-email"></span>
                            </div>

                            <button type="submit"
                                class="w-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] bg-[#FF8A66] text-[#060D26] font-bold py-3 rounded-full hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer">
                                Email Password Reset Link
                            </button>
                        </form>

                        <p class="text-[13px] text-center text-[#5B6A8E] mt-6">
                            Remembered your password? <a href="#" onclick="openAuthModal('login'); return false;"
                                class="text-[#060D26] font-bold hover:underline">Log in</a>
                        </p>
                    </div>

                    {{-- Forgot Password: Email Sent View --}}
                    <div id="forgot-password-sent-view" class="hidden">
                        <h2 class="font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[26px] font-extrabold text-[#060D26] tracking-tight leading-tight">Check your email</h2>
                        <p id="forgot-password-sent-message" class="text-sm text-[#5B6A8E] mt-1 mb-6 leading-relaxed">
                            We've emailed you a link to reset your password. It'll expire in 60 minutes.
                        </p>

                        <button type="button" onclick="openAuthModal('login')"
                            class="w-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] bg-[#FF8A66] text-[#060D26] font-bold py-3 rounded-full hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer">
                            Back to login
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <script>
            let authModalCloseTimer = null;

            function openAuthModal(mode) {
                const modal = document.getElementById('auth-modal');
                const panel = document.getElementById('auth-modal-content');
                const views = {
                    'login': document.getElementById('login-form-view'),
                    'register': document.getElementById('register-form-view'),
                    'forgot-password': document.getElementById('forgot-password-form-view'),
                    'forgot-password-sent': document.getElementById('forgot-password-sent-view'),
                };

                if (!modal || !views[mode]) return;

                // Cancel a pending close so reopening mid-animation doesn't hide the modal.
                clearTimeout(authModalCloseTimer);

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');

                const errorBag = document.getElementById('modal-error-bag');
                errorBag?.classList.add('hidden');
                errorBag && (errorBag.innerText = '');

                Object.values(views).forEach(view => view?.classList.add('hidden'));
                views[mode].classList.remove('hidden');

                // Swap the split-panel side copy to match the active view.
                const sideCopy = {
                    'login': ['Welcome back', 'Pick up where you left off.'],
                    'register': ['Find your place', 'Create a free account to save listings and reserve.'],
                    'forgot-password': ['Reset password', "We'll email you a link to set a new one."],
                    'forgot-password-sent': ['Check your inbox', 'Follow the link we sent to set a new password.'],
                };
                const sideTitle = document.getElementById('auth-side-title');
                const sideSubtitle = document.getElementById('auth-side-subtitle');
                if (sideTitle && sideSubtitle && sideCopy[mode]) {
                    sideTitle.textContent = sideCopy[mode][0];
                    sideSubtitle.textContent = sideCopy[mode][1];
                }

                // Double rAF: lets the browser paint the starting state before
                // transitioning, otherwise the class changes batch and nothing animates.
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    modal.classList.remove('opacity-0');
                    panel?.classList.remove('opacity-0', 'scale-95', 'translate-y-4');
                    views[mode].querySelector('input:not([type=hidden]):not([type=checkbox])')?.focus({ preventScroll: true });
                }));
            }

            function toggleModalPassword(fieldId, btn) {
                const input = document.getElementById(fieldId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                btn.innerHTML = isPassword
                    ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`
                    : `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>`;
            }

            function closeAuthModal() {
                const modal = document.getElementById('auth-modal');
                const panel = document.getElementById('auth-modal-content');
                if (!modal) return;

                modal.classList.add('opacity-0');
                document.body.classList.remove('overflow-hidden');
                panel?.classList.add('opacity-0', 'scale-95', 'translate-y-4');

                clearTimeout(authModalCloseTimer);
                authModalCloseTimer = setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }, 300);
            }

            window.addEventListener('click', function (event) {
                const modal = document.getElementById('auth-modal');
                if (event.target === modal) closeAuthModal();
            });

            window.addEventListener('keydown', function (event) {
                const modal = document.getElementById('auth-modal');
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeAuthModal();
            });

            window.handleAuthSubmit = async function (event, endpoint) {
                event.preventDefault();

                const form = event.target;
                const errorBag = document.getElementById('modal-error-bag');
                errorBag?.classList.add('hidden');
                errorBag && (errorBag.innerText = '');

                const formData = new FormData(form);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const submitBtn = form.querySelector('button[type="submit"]');
                const idleLabel = submitBtn?.textContent.trim();
                if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = form.id === 'ajax-register-form' ? 'Creating account…' : 'Signing in…'; }

                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        if (data?.errors) {
                            errorBag.classList.remove('hidden');
                            errorBag.innerText = Object.values(data.errors).flat().join(' ');
                            return;
                        }
                        errorBag.classList.remove('hidden');
                        errorBag.innerText = data?.message || 'Authentication failed.';
                        return;
                    }

                    closeAuthModal();
                    if (data?.redirect_url) window.location.href = data.redirect_url;
                    else window.location.reload();
                } catch (e) {
                    errorBag?.classList.remove('hidden');
                    errorBag && (errorBag.innerText = 'Network error. Please try again.');
                } finally {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = idleLabel; }
                }
            };

            window.handleForgotPasswordSubmit = async function (event, endpoint) {
                event.preventDefault();

                const form = event.target;
                const emailError = document.getElementById('error-forgot-password-email');
                emailError?.classList.add('hidden');
                emailError && (emailError.innerText = '');

                const formData = new FormData(form);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        const message = data?.errors?.email?.[0] || data?.message || 'Something went wrong. Please try again.';
                        if (emailError) {
                            emailError.innerText = message;
                            emailError.classList.remove('hidden');
                        }
                        return;
                    }

                    const sentMessage = document.getElementById('forgot-password-sent-message');
                    if (sentMessage) {
                        sentMessage.innerText = data?.status || "We've emailed you a link to reset your password.";
                    }
                    openAuthModal('forgot-password-sent');
                } catch (e) {
                    if (emailError) {
                        emailError.innerText = 'Network error. Please try again.';
                        emailError.classList.remove('hidden');
                    }
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            };
        </script>
    @endguest

    {{-- The avatar and Admin Actions menus are Alpine-driven (x-data / @click.outside),
         matching the Areas and notification dropdowns — no bespoke JS handler needed. --}}

    @auth
        <script>
            function notificationDropdown() {
                return {
                    open: false,
                    unreadCount: {{ $unreadNotificationCount }},
                    loaded: false,

                    init() {
                        // Live bell over the existing private user.{id} channel.
                        // Guarded so a down Reverb still leaves click-to-load working.
                        if (!window.Echo) return;

                        window.Echo.private('user.{{ auth()->id() }}')
                            .listen('.NotificationCreated', () => {
                                this.unreadCount++;
                                this.loaded = false;
                                if (this.open) this.fetchRecent();
                            });
                    },

                    toggle() {
                        this.open = !this.open;
                        if (this.open && !this.loaded) {
                            this.fetchRecent();
                        }
                    },

                    close() {
                        this.open = false;
                    },

                    async fetchRecent() {
                        try {
                            const res = await fetch('{{ route("notifications.recent") }}', {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            this.$refs.dropdownBody.innerHTML = await res.text();
                            this.loaded = true;
                        } catch (e) {
                            this.$refs.dropdownBody.innerHTML = '<div class="px-4 py-6 text-center text-[13px] text-[#5B6A8E]">Failed to load notifications.</div>';
                        }
                    },

                    async reload() {
                        this.loaded = false;
                        await this.fetchRecent();
                    }
                }
            }

            async function handleNotificationClick(id, url) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                try {
                    await fetch('/notifications/' + id + '/read', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                } catch (e) { }
                window.location.href = url;
            }

            async function markAllNotificationsRead() {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                try {
                    await fetch('{{ route("notifications.readAll") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const dropdown = document.querySelector('[x-data*="notificationDropdown"]');
                    if (dropdown && dropdown.__x) {
                        dropdown.__x.$data.unreadCount = 0;
                        dropdown.__x.$data.loaded = false;
                        dropdown.__x.$data.fetchRecent();
                    }
                } catch (e) { }
            }
        </script>
    @endauth
    @include('partials.message-notifications')

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')
    @if(session('suspended'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('show-modal', {
                    detail: {
                        type: 'error',
                        title: 'Account suspended',
                        message: 'Your account has been suspended due to a policy violation. If you believe this is a mistake, please contact our support team for assistance.',
                    }
                }));
            });
        </script>
    @endif
    @stack('scripts')

    {{-- Navigation progress bar: appears the instant a navigation starts (link, form,
         card click, redirect) and is reset when the next page shows. --}}
    <div id="nav-progress" aria-hidden="true"></div>
    <script>
        (function () {
            var bar = document.getElementById('nav-progress');
            if (!bar) return;
            window.addEventListener('beforeunload', function () { bar.classList.add('is-active'); });
            // Back/forward cache restores a frozen page — clear the bar so it isn't stuck.
            window.addEventListener('pageshow', function () { bar.classList.remove('is-active'); });
        })();
    </script>
</body>

</html>