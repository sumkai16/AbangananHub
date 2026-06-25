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
                        {{-- Force dropdown logic instead of a direct link if the user is an admin --}}
                        @if(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin'))
                            <a href="{{ route('landlord.listings.index') }}"
                                class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all">
                                <span class="text-base leading-none"></span> My Listings
                            </a>
                        @else
                            <button id="landlord-btn" aria-expanded="false"
                                class="flex items-center gap-2 h-10 px-5 border border-gray-200 rounded-full bg-white text-[13.5px] font-semibold text-gray-800 hover:shadow-md transition-all focus:outline-none">
                                <span class="text-base leading-none"></span>
                                {{ auth()->user()->hasRole('Admin') ? 'Admin Actions' : 'Become a Landlord' }}
                            </button>
                            <div id="landlord-menu"
                                class="absolute top-[calc(100%+10px)] right-0 w-[232px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-gray-100 py-2 hidden z-50">

                                {{-- Admin Only Option --}}
                                @if(auth()->user()->hasRole('Admin'))
                                    <a href="{{ route('admin.listings.approval') }}" {{-- NOTE: Ensure 'admin.listings.approval'
                                        matches your actual route name --}}
                                        class="flex items-center gap-3 px-4 py-2.5 text-[13.5px] font-bold text-[#286CD2] hover:bg-blue-50 border-b border-gray-100 mb-1">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                    <div class="relative" id="notif-wrapper" data-unread="{{ $unread }}">
                        <button type="button" id="notif-bell"
                            class="relative flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white text-gray-600 hover:shadow-md transition-all">
                            @if($unread > 0)
                                <span id="notif-dot"
                                    class="absolute top-[7px] right-[7px] w-2.5 h-2.5 rounded-full bg-[#61B2F0] border-2 border-white">
                                </span>
                            @endif
                            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </button>

                        <div id="notif-panel"
                            class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-gray-100 shadow-xl z-50 overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                <span class="text-sm font-semibold text-[#2A2523]">Notifications</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" id="notif-mark-all"
                                        class="text-xs font-semibold text-[#2A2523] hover:underline">
                                        Mark all read
                                    </button>
                                    <a href="{{ route('notifications.index') }}"
                                        class="text-xs font-semibold text-[#2A2523] hover:underline">
                                        See all
                                    </a>
                                </div>
                            </div>
                            <div id="notif-list" class="max-h-96 overflow-y-auto">
                                <div class="px-4 py-8 text-center text-sm text-[#9B9F98]">Loading…</div>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function () {
                            const wrapper = document.getElementById('notif-wrapper');
                            const bell = document.getElementById('notif-bell');
                            const panel = document.getElementById('notif-panel');
                            const list = document.getElementById('notif-list');
                            const dot = document.getElementById('notif-dot');
                            const markAllBtn = document.getElementById('notif-mark-all');
                            const csrf = document.querySelector('meta[name="csrf-token"]').content;
                            let loaded = false;
                            let unreadCount = parseInt(wrapper.dataset.unread || '0', 10);

                            bell.addEventListener('click', function (e) {
                                e.stopPropagation();
                                panel.classList.toggle('hidden');
                                if (!panel.classList.contains('hidden') && !loaded) {
                                    fetch("{{ route('notifications.recent') }}")
                                        .then(r => r.text())
                                        .then(html => {
                                            list.innerHTML = html;
                                            loaded = true;
                                            attachItemHandlers();
                                        });
                                }
                            });

                            document.addEventListener('click', function (e) {
                                if (!wrapper.contains(e.target)) {
                                    panel.classList.add('hidden');
                                }
                            });

                            function attachItemHandlers() {
                                list.querySelectorAll('.notif-item').forEach(function (item) {
                                    item.addEventListener('click', function () {
                                        const id = item.dataset.id;
                                        const href = item.dataset.href;
                                        const wasUnread = item.dataset.read === '0';

                                        fetch(`/notifications/${id}/read`, {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': csrf }
                                        });

                                        if (wasUnread) {
                                            item.dataset.read = '1';
                                            unreadCount = Math.max(0, unreadCount - 1);
                                            if (unreadCount === 0 && dot) dot.remove();
                                        }

                                        if (href) window.location.href = href;
                                    });
                                });
                            }

                            if (markAllBtn) {
                                markAllBtn.addEventListener('click', function () {
                                    fetch("{{ route('notifications.readAll') }}", {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': csrf }
                                    }).then(() => {
                                        unreadCount = 0;
                                        if (dot) dot.remove();
                                        list.querySelectorAll('.notif-item').forEach(function (item) {
                                            item.dataset.read = '1';
                                            item.classList.remove('bg-[#D7E8F3]/20');
                                            const itemDot = item.querySelector('.unread-dot');
                                            if (itemDot) {
                                                itemDot.classList.remove('bg-[#61B2F0]');
                                                itemDot.classList.add('bg-transparent');
                                            }
                                        });
                                    });
                                });
                            }
                        })();
                    </script>

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

        {{-- 2. Search Pill + Category Strip (Modified to check for hide_search flag overrides) --}}
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

    <script>
        (function () {
            const btn = document.getElementById('abg-avatar-btn');
            const menu = document.getElementById('abg-avatar-menu');
            const landlordBtn = document.getElementById('landlord-btn');
            const landlordMenu = document.getElementById('landlord-menu');

            if (btn && menu) {
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    const isHidden = menu.classList.contains('hidden');
                    menu.classList.toggle('hidden', !isHidden);
                    btn.setAttribute('aria-expanded', String(!isHidden));
                });
                btn.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
                });
            }

            if (landlordBtn && landlordMenu) {
                landlordBtn.setAttribute('aria-expanded', 'false');
                landlordBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    const isHidden = landlordMenu.classList.contains('hidden');
                    landlordMenu.classList.toggle('hidden', !isHidden);
                    landlordBtn.setAttribute('aria-expanded', String(!isHidden));
                });
                landlordBtn.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); landlordBtn.click(); }
                });
            }

            document.addEventListener('click', () => {
                if (menu) {
                    menu.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                }
                if (landlordMenu) {
                    landlordMenu.classList.add('hidden');
                    landlordBtn.setAttribute('aria-expanded', 'false');
                }
            });

            if (menu) menu.addEventListener('click', e => e.stopPropagation());
            if (landlordMenu) landlordMenu.addEventListener('click', e => e.stopPropagation());

            const currentType = new URLSearchParams(window.location.search).get('type');
            document.querySelectorAll('.category-link[data-type]').forEach(link => {
                const isAll = !currentType && link.dataset.type === 'all';
                const isMatch = currentType && link.dataset.type === currentType;
                if (isAll || isMatch) {
                    link.classList.remove('text-gray-400', 'border-transparent');
                    link.classList.add('text-[#286CD2]', 'border-[#286CD2]', 'font-bold');
                }
            });
        })();
    </script>

    @stack('scripts')
</body>

</html>