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

<body class="font-sans bg-[#F7F8FA] text-[#1A1A2E] min-h-screen flex flex-col" x-data="{}">

    <header
        class="bg-white/85 backdrop-blur-lg border-b border-gray-200/50 sticky top-0 z-[100] supports-[backdrop-filter]:bg-white/60">

        {{-- 1. Nav Row --}}
        <div class="flex items-center justify-between px-4 sm:px-6 lg:px-10 h-[72px]">

            {{-- Logo --}}
            <a href="{{ route('properties.index') }}"
                class="flex items-center gap-1.5 sm:gap-2.5 no-underline flex-shrink-0 group">
                <div
                    class="w-8 h-8 sm:w-10 sm:h-10 rounded-[10px] sm:rounded-[12px] bg-[#286CD2] flex items-center justify-center shadow-sm transition-transform group-hover:scale-105">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"
                        class="sm:w-[22px] sm:h-[22px]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7-7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="text-[16px] sm:text-[18px] font-extrabold text-[#1A1A2E] tracking-tight">
                    Abanganan<span class="text-[#286CD2]">Hub</span>
                </span>
            </a>

            {{-- Right Actions --}}
            <div class="flex items-center gap-3">
                @auth

                {{-- Become a Landlord / My Listings --}}
                <div class="relative hidden sm:block">
                    @if(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin'))
                        <a href="{{ route('landlord.listings.index') }}"
                            class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all">
                             My Listings
                        </a>
                    @else
                        <button id="landlord-btn" aria-expanded="false"
                            class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all focus:outline-none">
                            {{ auth()->user()->hasRole('Admin') ? 'Admin Actions' : 'Become a Landlord' }}
                        </button>
                        <div id="landlord-menu"
                            class="absolute top-[calc(100%+10px)] right-0 w-[232px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 py-2 hidden z-50">
                            
                            @if(auth()->user()->hasRole('Admin'))
                                <a href="{{ route('admin.listings.approval') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-bold text-[#286CD2] hover:bg-blue-50 border-b border-gray-100 mb-1">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Listing Approval
                                </a>
                            @endif

                                @if(!auth()->user()->hasRole('Landlord'))
                                    <a href="#"
                                        class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        Apply as Landlord
                                    </a>
                                @else
                                    <a href="{{ route('landlord.listings.index') }}"
                                        class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        My Listings
                                    </a>
                                @endif

                                <a href="{{ route('properties.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    Browse Properties
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Notifications --}}
                    @php $unread = auth()->user()->notifications()->where('is_read', false)->count(); @endphp
                    <a href="{{ route('notifications.index') }}"
                        class="relative flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white text-gray-600 hover:shadow-md transition-all">
                        @if($unread > 0)
                            <div
                                class="absolute top-[7px] right-[7px] w-2.5 h-2.5 rounded-full bg-[#286CD2] border-2 border-white">
                            </div>
                        @endif
                        <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </a>

                    {{-- Avatar Dropdown --}}
                    <div class="relative">
                        <button id="abg-avatar-btn" aria-expanded="false"
                            class="flex items-center justify-center w-10 h-10 rounded-full bg-[#286CD2] text-white text-[15px] font-bold shadow-sm hover:shadow-md transition-all focus:outline-none">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </button>

                        <div id="abg-avatar-menu"
                            class="absolute top-[calc(100%+10px)] right-0 w-[232px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 py-2 hidden z-50">
                            <div class="px-4 py-3 border-b border-gray-100 mb-1">
                                <div class="text-[13.5px] font-bold text-gray-900">
                                    {{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) }}
                                </div>
                                <div class="text-[12px] text-gray-400 mt-0.5 truncate">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>

                            <a href="{{ route('properties.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-semibold text-gray-800 hover:bg-gray-50">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Properties
                            </a>
                            <a href="{{ route('profile.edit') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-semibold text-gray-800 hover:bg-gray-50">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Account Settings
                            </a>
                            @php $userRoles = auth()->user()->roles->pluck('role'); @endphp

                            @if($userRoles->contains('Tenant'))
                                <a href="{{ route('reservations.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v12a2 2 0 002 2z" />
                                    </svg>
                                    My Reservations
                                </a>
                            @endif

                            @if($userRoles->contains('Landlord'))
                                <a href="{{ route('landlord.reservations.index') }}"
                                    class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v12a2 2 0 002 2z" />
                                    </svg>
                                    Reservation Requests
                                </a>
                            @endif
                            <a href="{{ route('favorites.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Saved Listings
                            </a>
                            <a href="{{ route('conversations.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-gray-700 hover:bg-gray-50">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Messages
                            </a>

                            <div class="h-px bg-gray-100 my-2"></div>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-[13.5px] text-red-500 hover:bg-red-50 font-semibold">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- Guest Actions --}}
                    <div class="flex items-center gap-1 sm:gap-2">
                        <a href="{{ route('login') }}"
                            class="text-[13px] sm:text-[14px] font-bold text-[#1A1A2E] hover:bg-gray-100 px-3 sm:px-4 py-2 rounded-full transition-colors focus:outline-none whitespace-nowrap no-underline">Log
                            in</a>
                        <a href="{{ route('register') }}"
                            class="text-[13px] sm:text-[14px] font-bold text-white bg-[#286CD2] hover:bg-[#1D4ED8] px-4 sm:px-5 py-2 rounded-full transition-all shadow-sm focus:outline-none whitespace-nowrap no-underline">Sign
                            up</a>
                    </div>
                @endauth
            </div>
        </div>

        {{-- 2. Search Pill + Category Strip --}}
        @if(($searchBar ?? true) && !View::hasSection('hide_search'))

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
                            class="w-8 h-8 sm:w-11 sm:h-11 rounded-full bg-[#286CD2] flex items-center justify-center text-white flex-shrink-0 hover:bg-[#1D4ED8] transition-colors ml-1 sm:ml-3 shadow-md">
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

        @endif

    </header>

    {{-- Flash Messages --}}
    @if(session('error'))
        <div class="max-w-[1280px] mx-auto mt-4 px-5 md:px-10 w-full">
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

    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div
            class="max-w-[1280px] mx-auto px-6 lg:px-10 py-5 flex flex-col md:flex-row items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="text-[14px] font-bold text-[#1A1A2E] tracking-tight no-underline">
                Abanganan<span class="text-[#286CD2]">Hub</span>
            </a>
            <div class="flex items-center gap-5 text-[12.5px] text-gray-500">
                <a href="{{ route('properties.index') }}" class="hover:text-gray-800 transition-colors">Properties</a>
                <span class="text-gray-300">|</span>
                <a href="#" class="hover:text-gray-800 transition-colors">Privacy</a>
                <span class="text-gray-300">|</span>
                <a href="#" class="hover:text-gray-800 transition-colors">Terms</a>
            </div>
            <span class="text-[11.5px] text-gray-400">&copy; {{ date('Y') }} AbangananHub &middot; Cebu, Philippines
                &middot; SDG 16</span>
        </div>
    </footer>

    {{-- ========================================== --}}
    {{-- UNIFIED DYNAMIC AUTH MODAL (AJAX/FETCH) --}}
    {{-- ========================================== --}}
    @guest
        <div id="auth-modal"
            class="hidden fixed inset-0 z-[9999] bg-[#1A1A2E]/40 backdrop-blur-sm items-center justify-center p-4">

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
                        <div class="w-10 h-10 bg-[#286CD2] rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25">
                                </path>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-[#1A1A2E]">Abanganan<span
                                class="text-[#286CD2]">Hub</span></span>
                    </div>

                    <h2 class="text-[24px] font-bold text-[#1A1A2E] tracking-tight leading-tight">Welcome Back!</h2>
                    <p class="text-[14px] text-gray-400 mt-1 mb-6">Login to continue to AbangananHub</p>

                    <form id="ajax-login-form" onsubmit="handleAuthSubmit(event, '{{ route('login') }}')">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Email Address</label>
                            <input type="email" name="email" required placeholder="Enter your email"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-login-email"></span>
                        </div>

                        <div class="mb-4">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Password</label>
                            <div class="relative">
                                <input type="password" name="password" required placeholder="Enter your password"
                                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
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
                                    class="w-4 h-4 rounded text-[#286CD2] border-gray-300 focus:ring-[#286CD2]">
                                Remember me
                            </label>
                            <a href="#" class="text-[#286CD2] font-semibold hover:underline">Forgot Password?</a>
                        </div>

                        <button type="submit"
                            class="w-full bg-[#286CD2] text-white font-bold py-3 rounded-xl hover:bg-[#1D4ED8] active:scale-[0.99] transition-all shadow-md shadow-[#286CD2]/10 text-[15px]">
                            Login
                        </button>
                    </form>

                    <p class="text-[13px] text-center text-gray-500 mt-6">
                        Don't have an account? <a href="#" onclick="openAuthModal('register')"
                            class="text-[#286CD2] font-bold hover:underline">Register here</a>
                    </p>

                    <div class="text-center mt-8 pt-2 text-[10px] tracking-wider text-gray-300 font-bold uppercase">
                        © 2026 ABANGANANHUB. ALL RIGHTS RESERVED.
                    </div>
                </div>

                {{-- Register View --}}
                <div id="register-form-view" class="hidden">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-[#286CD2] rounded-xl flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25">
                                </path>
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-[#1A1A2E]">Abanganan<span
                                class="text-[#286CD2]">Hub</span></span>
                    </div>

                    <h2 class="text-[24px] font-bold text-[#1A1A2E] tracking-tight leading-tight">Create an Account</h2>
                    <p class="text-[14px] text-gray-400 mt-1 mb-6">Join AbangananHub today</p>

                    <form id="ajax-register-form" onsubmit="handleAuthSubmit(event, '{{ route('register') }}')">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Name</label>
                            <input type="text" name="name" required placeholder="Enter your full name"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-register-name"></span>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Email Address</label>
                            <input type="email" name="email" required placeholder="Enter your email address"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-register-email"></span>
                        </div>

                        <div class="mb-3">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Password</label>
                            <input type="password" name="password" required placeholder="Create a password"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
                            <span class="text-xs text-red-500 mt-1 hidden error-field" id="error-register-password"></span>
                        </div>

                        <div class="mb-5">
                            <label class="block text-[13px] font-bold text-[#1A1A2E] mb-1.5">Confirm Password</label>
                            <input type="password" name="password_confirmation" required placeholder="Confirm your password"
                                class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-[14px] placeholder-gray-300 focus:border-[#286CD2] focus:ring-2 focus:ring-[#286CD2]/20 focus:outline-none transition-all">
                        </div>

                        <button type="submit"
                            class="w-full bg-[#286CD2] text-white font-bold py-3 rounded-xl hover:bg-[#1D4ED8] active:scale-[0.99] transition-all shadow-md shadow-[#286CD2]/10 text-[15px]">
                            Sign Up
                        </button>
                    </form>

                    <p class="text-[13px] text-center text-gray-500 mt-6">
                        Already have an account? <a href="#" onclick="openAuthModal('login')"
                            class="text-[#286CD2] font-bold hover:underline">Login here</a>
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

    @stack('scripts')

</body>

</html>