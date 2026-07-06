<!DOCTYPE html>
<html lang="en">
<meta name="user-authenticated" content="{{ auth()->check() ? '1' : '0' }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
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

<body class="font-sans bg-[#F7FCFC] text-[#156F8C] min-h-screen flex flex-col" x-data="{}">

    <header id="site-header"
        class="bg-white/85 backdrop-blur-lg border-b border-gray-200/50 sticky top-0 z-[100] supports-[backdrop-filter]:bg-white/60 transition-all duration-300">

        {{-- 1. Nav Row --}}
        <div class="flex items-center justify-between px-4 sm:px-6 lg:px-10 h-[72px] relative">

            {{-- Logo --}}
            <a href="{{ route('properties.index') }}"
                class="flex items-center gap-1.5 sm:gap-2.5 no-underline flex-shrink-0 group">
                <div
                    class="w-8 h-8 sm:w-10 sm:h-10 rounded-[10px] sm:rounded-[12px] bg-[#2AA7A1] flex items-center justify-center shadow-sm transition-transform group-hover:scale-105">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"
                        class="sm:w-[22px] sm:h-[22px]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7-7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="text-[16px] sm:text-[18px] font-extrabold text-[#156F8C] tracking-tight">
                    Abanganan<span class="text-[#156F8C]">Hub</span>
                </span>
            </a>

            {{-- Collapsed search pill (shown on scroll) --}}
            @if(($searchBar ?? true) && !View::hasSection('hide_search'))
                <div id="nav-search-collapsed"
                    class="absolute left-1/2 -translate-x-1/2 hidden opacity-0 transition-all duration-300 pointer-events-none">
                    <button type="button" id="nav-search-collapsed-btn"
                        class="flex items-center gap-2 h-[42px] pl-4 pr-2 bg-white rounded-full shadow-[0_2px_16px_rgba(0,0,0,0.12)] border border-gray-100 hover:shadow-[0_4px_20px_rgba(0,0,0,0.16)] transition-all duration-200 group"
                        onclick="document.getElementById('site-header').classList.remove('is-scrolled'); window.scrollTo({top:0,behavior:'smooth'})">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                            class="text-gray-500 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span class="text-[13.5px] font-semibold text-gray-800 pr-1">Search</span>
                        <span class="text-gray-300">·</span>
                        <span class="text-[13px] text-gray-500 px-1">Any type</span>
                        <span class="text-gray-300">·</span>
                        <span class="text-[13px] text-gray-500 pl-1 pr-2">Any price</span>
                        <span class="w-8 h-8 rounded-full bg-[#FF8A65] flex items-center justify-center flex-shrink-0">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </button>
                </div>
            @endif

            {{-- Right Actions --}}
            <div class="flex items-center gap-3">
                @auth

                    {{-- Become a Landlord / My Listings / Admin Actions --}}
                    <div class="relative hidden sm:block">
                        @if(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin'))
                            <a href="{{ route('landlord.properties.index') }}"
                                class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all">
                                Landlord Dashboard
                            </a>
                        @elseif(auth()->user()->hasRole('Admin'))
                            <button id="landlord-btn" aria-expanded="false"
                                class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all focus:outline-none">
                                Admin Actions
                            </button>

                            <div id="landlord-menu"
                                class="absolute top-[calc(100%+10px)] right-0 w-[232px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 py-2 hidden z-50">
                                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.listings.approval') ? route('admin.listings.approval') : '#' }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-bold text-[#156F8C] hover:bg-[#EEF8F8] border-b border-gray-100 mb-1">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Listing Approval
                                </a>
                                <a href="{{ route('admin.verifications.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-bold text-[#156F8C] hover:bg-[#EEF8F8]">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Verification Requests
                                </a>
                                <a href="{{ route('admin.users.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-bold text-[#156F8C] hover:bg-[#EEF8F8] border-t border-gray-100 mt-1">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Manage Users
                                </a>
                            </div>
                        @else
                            <a href="{{ route('landlord.verification.create') }}"
                                class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all">
                                Become a Landlord
                            </a>
                        @endif
                    </div>

                    {{-- Notifications Dropdown --}}
                    <div class="relative" x-data="notificationDropdown()" @click.away="close()"
                        @keydown.escape.window="close()">
                        <button type="button" @click="toggle()"
                            class="relative flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white text-gray-600 hover:shadow-md transition-all focus:outline-none">
                            <span x-show="unreadCount > 0" x-cloak
                                class="absolute top-[7px] right-[7px] w-2.5 h-2.5 rounded-full bg-[#2AA7A1] border-2 border-white"></span>
                            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-[calc(100%+10px)] right-0 w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 z-50 overflow-hidden">
                            <div x-ref="dropdownBody">
                                <div class="px-4 py-8 text-center">
                                    <div
                                        class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin mx-auto">
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
                    <div class="relative">
                        <button id="abg-avatar-btn" aria-expanded="false"
                            class="flex items-center gap-2.5 pl-1 pr-3 py-1 rounded-full hover:bg-gray-50 transition-colors focus:outline-none">
                            <span class="w-9 h-9 rounded-full bg-[#2AA7A1] text-white text-[14px] font-bold flex items-center justify-center shrink-0">
                                {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:flex flex-col items-start leading-tight">
                                <span class="text-[13px] font-semibold text-[#1F2937]">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                                <span class="text-[11px] text-[#64748B]">{{ $abgRoleLabel }}</span>
                            </span>
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-[#64748B] hidden sm:block">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div id="abg-avatar-menu"
                            class="absolute top-[calc(100%+10px)] right-0 w-[256px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#E2E8F0] py-1 hidden z-50">

                            {{-- User header --}}
                            <div class="px-4 py-3.5 border-b border-[#E2E8F0]">
                                <div class="text-[14px] font-bold text-[#1F2937]">
                                    {{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) }}
                                </div>
                                <div class="text-[12px] text-[#64748B] mt-0.5 truncate">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>

                            {{-- Section: Activity --}}
                            @php $userRoles = auth()->user()->roles->pluck('role'); @endphp
                            <div class="py-1">
                                <p class="px-4 pt-2.5 pb-1 text-[11px] font-bold text-[#64748B] uppercase tracking-wider">
                                    Activity</p>

                                @if($userRoles->contains('Tenant'))
                                    <a href="{{ route('reservations.index') }}"
                                        class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        My Reservations
                                    </a>
                                @endif

                                @if($userRoles->contains('Landlord'))
                                    <a href="{{ route('landlord.reservations.index') }}"
                                        class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        Reservation Requests
                                    </a>
                                @endif

                                <a href="{{ route('conversations.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    Messages
                                </a>

                                <a href="{{ route('notifications.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    Notifications
                                </a>

                                <a href="{{ route('favorites.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    Saved Listings
                                </a>
                            </div>

                            <div class="h-px bg-[#E2E8F0] mx-3"></div>

                            {{-- Section: Account --}}
                            <div class="py-1">
                                <p class="px-4 pt-2.5 pb-1 text-[11px] font-bold text-[#64748B] uppercase tracking-wider">
                                    Account</p>

                                <a href="{{ route('profile.show') }}"
                                    class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    Profile
                                </a>

                                <a href="{{ route('profile.edit') }}"
                                    class="flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#1F2937] hover:bg-[#E2E8F0]/60 transition-colors">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.43.991a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Account Settings
                                </a>
                            </div>

                            <div class="h-px bg-[#E2E8F0] mx-3"></div>

                            {{-- Sign out --}}
                            <div class="py-1">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-2 text-[13.5px] font-medium text-[#EF4444] hover:bg-[#E2E8F0]/60 transition-colors">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Sign out
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                @else
                    {{-- Guest Actions --}}
                    <div class="flex items-center gap-1 sm:gap-2">
                        <button type="button" onclick="openAuthModal('login')"
                            class="text-[13px] sm:text-[14px] font-bold text-[#156F8C] hover:bg-gray-100 px-3 sm:px-4 py-2 rounded-full transition-colors focus:outline-none whitespace-nowrap no-underline">
                            Log in
                        </button>

                        <button type="button" onclick="openAuthModal('register')"
                            class="text-[13px] sm:text-[14px] font-bold text-white bg-[#2AA7A1] hover:brightness-95 px-4 sm:px-5 py-2 rounded-full transition-all shadow-sm focus:outline-none whitespace-nowrap no-underline">
                            Sign up
                        </button>
                    </div>
                @endauth
            </div>
        </div>

        {{-- 2. Search Pill + Category Strip --}}
        @if(($searchBar ?? true) && !View::hasSection('hide_search'))

            <div id="header-search-expanded" class="transition-all duration-300 overflow-hidden"
                style="max-height: 200px; opacity: 1;">

                <div class="flex justify-center pb-4 pt-1 px-4 sm:px-6">
                    <form action="{{ route('properties.index') }}" method="GET"
                        class="flex items-center w-full max-w-[820px] bg-white rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:shadow-[0_12px_40px_rgb(0,0,0,0.12)] border border-gray-100 transition-all duration-300">

                        <div
                            class="flex-1 flex flex-col justify-center px-3 py-2 sm:px-7 sm:py-3 border-r border-gray-200 cursor-pointer hover:bg-gray-50 rounded-l-full transition-colors w-[33%] overflow-hidden">
                            <span
                                class="text-[10px] sm:text-[11px] font-bold text-gray-800 tracking-wide uppercase truncate">Where</span>
                            <input type="text" name="location" placeholder="Search..."
                                class="p-0 border-none bg-transparent text-[12px] sm:text-[13.5px] text-gray-600 focus:ring-0 placeholder-gray-400 w-full outline-none mt-0.5 truncate">
                        </div>

                        <div
                            class="flex-1 flex flex-col justify-center px-3 py-2 sm:px-7 sm:py-3 border-r border-gray-200 hover:bg-gray-50 transition-colors w-[33%] overflow-hidden">
                            <span
                                class="text-[10px] sm:text-[11px] font-bold text-gray-800 tracking-wide uppercase truncate">Type</span>
                            <select name="type"
                                class="p-0 border-none bg-transparent text-[12px] sm:text-[13.5px] text-gray-600 focus:ring-0 w-full outline-none appearance-none cursor-pointer mt-0.5 truncate">
                                <option value="">Any type</option>
                                <option value="Bedspace">Bedspace</option>
                                <option value="Room">Room</option>
                                <option value="Apartment">Apartment</option>
                                <option value="House">House</option>
                            </select>
                        </div>

                        <div
                            class="flex-1 flex items-center justify-between pl-3 pr-2 sm:pl-7 sm:pr-2 py-2 hover:bg-gray-50 rounded-r-full transition-colors w-[33%] overflow-hidden">
                            <div class="flex flex-col justify-center w-[calc(100%-36px)] sm:w-auto overflow-hidden">
                                <span
                                    class="text-[10px] sm:text-[11px] font-bold text-gray-800 tracking-wide uppercase truncate">Budget</span>
                                <input type="number" name="price_max" placeholder="Max ₱"
                                    class="p-0 border-none bg-transparent text-[12px] sm:text-[13.5px] text-gray-600 focus:ring-0 placeholder-gray-400 w-full outline-none mt-0.5 truncate">
                            </div>
                            <button type="submit"
                                class="w-8 h-8 sm:w-11 sm:h-11 rounded-full bg-[#FF8A65] flex items-center justify-center text-white flex-shrink-0 hover:brightness-95 transition-colors ml-1 sm:ml-3 shadow-md">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="3" class="sm:w-[17px] sm:h-[17px]">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>

                    </form>
                </div>

                <div class="flex items-center justify-start md:justify-center gap-4 sm:gap-6 md:gap-8 px-4 sm:px-6 overflow-x-auto pb-1"
                    style="-ms-overflow-style:none; scrollbar-width:none;">
                    <a href="{{ route('properties.index') }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link"
                        data-type="all">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-[12px] font-semibold">All</span>
                    </a>
                    <a href="{{ route('properties.index', ['type' => 'Bedspace']) }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link"
                        data-type="Bedspace">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 7h18M3 7v10m0-10V5m18 2v10m0-10V5M3 17h18M6 12h12M5 5h14" />
                        </svg>
                        <span class="text-[12px] font-semibold">Bedspace</span>
                    </a>
                    <a href="{{ route('properties.index', ['type' => 'Room']) }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link"
                        data-type="Room">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4" />
                        </svg>
                        <span class="text-[12px] font-semibold">Room</span>
                    </a>
                    <a href="{{ route('properties.index', ['type' => 'Apartment']) }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link"
                        data-type="Apartment">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-[12px] font-semibold">Apartment</span>
                    </a>
                    <a href="{{ route('properties.index', ['type' => 'House']) }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link"
                        data-type="House">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 21h18M3 10.5L12 3l9 7.5M5 21V10.5M19 21V10.5M9 21v-6h6v6" />
                        </svg>
                        <span class="text-[12px] font-semibold">House</span>
                    </a>
                    <a href="{{ route('favorites.index') }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span class="text-[12px] font-semibold">Saved</span>
                    </a>
                    <a href="{{ route('properties.index', ['verified' => 1]) }}"
                        class="flex flex-col items-center gap-1.5 pb-3 border-b-2 border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-all min-w-[56px] category-link">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-[12px] font-semibold">Verified</span>
                    </a>
                </div>

            </div>{{-- /#header-search-expanded --}}

        @endif

    </header>

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="w-full mx-auto mt-4 px-[50px]">
            <div
                class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium flex items-center justify-between shadow-sm">
                <span>{{ session('error') }}</span>
                <button class="opacity-60 hover:opacity-100 pl-3 focus:outline-none"
                    onclick="this.parentElement.remove()">✕</button>
            </div>
        </div>
    @endif

    <main class="flex-grow">
        @yield('content')
    </main>

    <footer class="bg-[#156F8C] border-t border-white/10 mt-auto">
        <div class="w-full px-[50px] py-5 flex flex-col md:flex-row items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="text-[14px] font-bold text-white tracking-tight no-underline">
                Abanganan<span class="text-[#69D2C6]">Hub</span>
            </a>
            <div class="flex items-center gap-5 text-[12.5px] text-white/70">
                <a href="{{ route('properties.index') }}" class="hover:text-white transition-colors">Properties</a>
                <span class="text-white/30">|</span>
                <a href="{{ route('about') }}" class="hover:text-white transition-colors">About</a>
                <span class="text-white/30">|</span>
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <span class="text-white/30">|</span>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
            </div>
            <span class="text-[11.5px] text-white/60">&copy; {{ date('Y') }} AbangananHub &middot; Cebu, Philippines
                &middot; SDG 16</span>
        </div>
    </footer>

    {{-- ========================================== --}}
    {{-- UNIFIED DYNAMIC AUTH MODAL (AJAX/FETCH) --}}
    {{-- ========================================== --}}
    @guest
        <div id="auth-modal"
            class="hidden fixed inset-0 z-[9999] bg-[#156F8C]/40 backdrop-blur-sm items-center justify-center p-4">

            <div class="bg-white rounded-[24px] shadow-2xl max-w-md w-full p-6 md:p-8 relative transition-all transform scale-100 opacity-100 duration-300 max-h-[calc(100vh-2rem)] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]"
                id="auth-modal-content">

                <button type="button" onclick="closeAuthModal()"
                    class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <div id="modal-error-bag"
                    class="hidden mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-sm border border-red-100"></div>

                {{-- Login View --}}
                <div id="login-form-view" class="hidden">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-[#2AA7A1] rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25">
                                </path>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-[#156F8C]">Abanganan<span
                                class="text-[#156F8C]">Hub</span></span>
                    </div>

                    <h2 class="text-[24px] font-bold text-[#156F8C] tracking-tight leading-tight">Welcome Back!</h2>
                    <p class="text-[14px] text-gray-400 mt-1 mb-6">Login to continue to AbangananHub</p>

                    <form id="ajax-login-form" onsubmit="handleAuthSubmit(event, '{{ route('login') }}')">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Email Address</label>
                            <input type="email" name="email" required placeholder="Enter your email"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-login-email"></span>
                        </div>

                        <div class="mb-4">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Password</label>
                            <div class="relative">
                                <input type="password" name="password" required placeholder="Enter your password"
                                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                                <button type="button"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
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
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-login-password"></span>
                        </div>

                        <div class="flex items-center justify-between text-[13px] mb-6">
                            <label class="flex items-center gap-2 text-gray-500 cursor-pointer select-none">
                                <input type="checkbox" name="remember"
                                    class="w-4 h-4 rounded text-[#2AA7A1] border-gray-300 focus:ring-[#2AA7A1]">
                                Remember me
                            </label>
                            <a href="#" class="text-[#156F8C] font-semibold hover:underline">Forgot Password?</a>
                        </div>

                        <button type="submit"
                            class="w-full bg-[#2AA7A1] text-white font-bold py-3 rounded-xl hover:brightness-95 active:scale-[0.99] transition-all shadow-md shadow-[#2AA7A1]/10 text-[15px]">
                            Login
                        </button>
                    </form>

                    <p class="text-[13px] text-center text-gray-500 mt-6">
                        Don't have an account? <a href="#" onclick="openAuthModal('register')"
                            class="text-[#156F8C] font-bold hover:underline">Register here</a>
                    </p>

                    <div class="text-center mt-8 pt-2 text-[10px] tracking-wider text-gray-300 font-bold uppercase">
                        © 2026 ABANGANANHUB. ALL RIGHTS RESERVED.
                    </div>
                </div>

                {{-- Register View --}}
                <div id="register-form-view" class="hidden">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-[#2AA7A1] rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25">
                                </path>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-[#156F8C]">Abanganan<span
                                class="text-[#156F8C]">Hub</span></span>
                    </div>

                    <h2 class="text-[24px] font-bold text-[#156F8C] tracking-tight leading-tight">Create an Account</h2>
                    <p class="text-[14px] text-gray-400 mt-1 mb-6">Join AbangananHub today</p>

                    <form id="ajax-register-form" onsubmit="handleAuthSubmit(event, '{{ route('register') }}')">
                        @csrf
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">First Name</label>
                                <input type="text" name="first_name" required placeholder="First name"
                                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                                <span class="text-xs text-red-500 mt-1 hidden error-field"
                                    id="error-register-first_name"></span>
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Last Name</label>
                                <input type="text" name="last_name" required placeholder="Last name"
                                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                                <span class="text-xs text-red-500 mt-1 hidden error-field"
                                    id="error-register-last_name"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Contact Number</label>
                            <input type="text" name="contact_number" required placeholder="Enter your contact number"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field"
                                id="error-register-contact_number"></span>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Email Address</label>
                            <input type="email" name="email" required placeholder="Enter your email address"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-register-email"></span>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Password</label>
                            <input type="password" name="password" required placeholder="Create a password"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-register-password"></span>
                        </div>

                        <div class="mb-5">
                            <label class="block text-[13px] font-bold text-[#156F8C] mb-1.5">Confirm Password</label>
                            <input type="password" name="password_confirmation" required placeholder="Confirm your password"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                        </div>

                        <button type="submit"
                            class="w-full bg-[#2AA7A1] text-white font-bold py-3 rounded-xl hover:brightness-95 active:scale-[0.99] transition-all shadow-md shadow-[#2AA7A1]/10 text-[15px]">
                            Sign Up
                        </button>
                    </form>

                    <p class="text-[13px] text-center text-gray-500 mt-6">
                        Already have an account? <a href="#" onclick="openAuthModal('login')"
                            class="text-[#156F8C] font-bold hover:underline">Login here</a>
                    </p>

                    <div class="text-center mt-8 pt-2 text-[10px] tracking-wider text-gray-300 font-bold uppercase">
                        © 2026 ABANGANANHUB. ALL RIGHTS RESERVED.
                    </div>
                </div>

            </div>
        </div>

        <script>
            function openAuthModal(mode) {
                const modal = document.getElementById('auth-modal');
                const loginView = document.getElementById('login-form-view');
                const registerView = document.getElementById('register-form-view');

                if (!modal || !loginView || !registerView) return;

                modal.classList.remove('hidden');
                modal.classList.add('flex');

                if (mode === 'login') {
                    loginView.classList.remove('hidden');
                    registerView.classList.add('hidden');
                } else {
                    registerView.classList.remove('hidden');
                    loginView.classList.add('hidden');
                }
            }

            function closeAuthModal() {
                const modal = document.getElementById('auth-modal');
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            window.addEventListener('click', function (event) {
                const modal = document.getElementById('auth-modal');
                if (event.target === modal) closeAuthModal();
            });

            window.handleAuthSubmit = async function (event, endpoint) {
                event.preventDefault();

                const form = event.target;
                const errorBag = document.getElementById('modal-error-bag');
                errorBag?.classList.add('hidden');
                errorBag && (errorBag.innerText = '');

                const formData = new FormData(form);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
                }
            };
        </script>
    @endguest

    {{-- Interactive Layout Dropdown Handler Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const landlordBtn = document.getElementById('landlord-btn');
            const landlordMenu = document.getElementById('landlord-menu');
            const avatarBtn = document.getElementById('abg-avatar-btn');
            const avatarMenu = document.getElementById('abg-avatar-menu');

            function toggleDropdown(btn, menu) {
                if (!btn || !menu) return;
                const isExpanded = btn.getAttribute('aria-expanded') === 'true';

                // Automatically close alternate menus to prevent stacking layout bugs
                if (landlordMenu && landlordMenu !== menu) landlordMenu.classList.add('hidden');
                if (landlordBtn && landlordBtn !== btn) landlordBtn.setAttribute('aria-expanded', 'false');
                if (avatarMenu && avatarMenu !== menu) avatarMenu.classList.add('hidden');
                if (avatarBtn && avatarBtn !== btn) avatarBtn.setAttribute('aria-expanded', 'false');

                // Toggle targeted menu view status
                btn.setAttribute('aria-expanded', !isExpanded);
                menu.classList.toggle('hidden');
            }

            if (landlordBtn && landlordMenu) {
                landlordBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleDropdown(landlordBtn, landlordMenu);
                });
            }

            if (avatarBtn && avatarMenu) {
                avatarBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleDropdown(avatarBtn, avatarMenu);
                });
            }

            // Document click listener to clear active menus when navigating outside layout controls
            document.addEventListener('click', (e) => {
                if (landlordMenu && !landlordMenu.classList.contains('hidden') && !landlordBtn.contains(e.target) && !landlordMenu.contains(e.target)) {
                    landlordMenu.classList.add('hidden');
                    landlordBtn.setAttribute('aria-expanded', 'false');
                }
                if (avatarMenu && !avatarMenu.classList.contains('hidden') && !avatarBtn.contains(e.target) && !avatarMenu.contains(e.target)) {
                    avatarMenu.classList.add('hidden');
                    avatarBtn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    </script>

    {{-- Airbnb-style scroll-collapse search bar --}}
    <script>
        (function () {
            const SCROLL_THRESHOLD = 80;
            const header = document.getElementById('site-header');
            const expanded = document.getElementById('header-search-expanded');
            const collapsed = document.getElementById('nav-search-collapsed');

            if (!header || !expanded || !collapsed) return;

            let ticking = false;

            function applyScrollState() {
                const scrolled = window.scrollY > SCROLL_THRESHOLD;

                if (scrolled) {
                    // Collapse expanded search
                    expanded.style.maxHeight = '0px';
                    expanded.style.opacity = '0';
                    expanded.style.pointerEvents = 'none';

                    // Show compact pill
                    collapsed.classList.remove('hidden');
                    // Force reflow so transition fires
                    collapsed.offsetHeight;
                    collapsed.classList.remove('opacity-0');
                    collapsed.classList.add('opacity-100');
                    collapsed.classList.remove('pointer-events-none');
                    collapsed.classList.add('pointer-events-auto');

                    header.classList.add('is-scrolled');
                } else {
                    // Restore expanded search
                    expanded.style.maxHeight = '200px';
                    expanded.style.opacity = '1';
                    expanded.style.pointerEvents = '';

                    // Hide compact pill
                    collapsed.classList.remove('opacity-100');
                    collapsed.classList.add('opacity-0');
                    collapsed.classList.add('pointer-events-none');
                    collapsed.classList.remove('pointer-events-auto');
                    // Hide after transition
                    setTimeout(() => {
                        if (window.scrollY <= SCROLL_THRESHOLD) {
                            collapsed.classList.add('hidden');
                        }
                    }, 300);

                    header.classList.remove('is-scrolled');
                }

                ticking = false;
            }

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    requestAnimationFrame(applyScrollState);
                    ticking = true;
                }
            }, { passive: true });

            // Run once on load in case page is restored mid-scroll
            applyScrollState();
        })();
    </script>
    @auth
        <script>
            function notificationDropdown() {
                return {
                    open: false,
                    unreadCount: {{ auth()->user()->notifications()->where('is_read', false)->count() }},
                    loaded: false,

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
                            this.$refs.dropdownBody.innerHTML = '<div class="px-4 py-6 text-center text-[13px] text-[#64748B]">Failed to load notifications.</div>';
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
    @stack('scripts')

</body>

</html>