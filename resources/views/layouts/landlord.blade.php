<!DOCTYPE html>
<html lang="en">
<meta name="user-authenticated" content="{{ auth()->check() ? '1' : '0' }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            if (localStorage.getItem('landlordSidebarCollapsed') === 'true') {
                document.documentElement.classList.add('sidebar-pre-collapsed');
            }
        })();
    </script>
    <style>
        @media (min-width: 1024px) {
            .sidebar-pre-collapsed #landlord-sidebar {
                width: 5rem;
            }

            .sidebar-pre-collapsed #landlord-main {
                margin-left: 5rem;
            }

            .sidebar-pre-collapsed #landlord-sidebar nav a,
            .sidebar-pre-collapsed #landlord-sidebar nav div.group {
                justify-content: center;
            }

            .sidebar-pre-collapsed #landlord-sidebar [data-sidebar-label] {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans bg-[#F7FCFC] text-[#1F2937] min-h-screen" x-data="{
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('landlordSidebarCollapsed') === 'true'
    }" x-init="
        document.documentElement.classList.toggle('sidebar-pre-collapsed', sidebarCollapsed);
        $watch('sidebarCollapsed', value => {
            localStorage.setItem('landlordSidebarCollapsed', value);
            document.documentElement.classList.toggle('sidebar-pre-collapsed', value);
        });
    ">

    <div class="flex min-h-screen">

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden">
        </div>

        {{-- ============ SIDEBAR ============ --}}
        <aside id="landlord-sidebar"
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-64']"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-[#0F172A] border-r border-white/[0.06] flex flex-col transition-all duration-300 lg:translate-x-0">

            {{-- Collapse toggle --}}
            <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex absolute top-5 -right-3 w-6 h-6 rounded-full bg-[#2AA7A1] text-white items-center justify-center shadow-md hover:brightness-95 transition-all duration-200 z-10"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    :class="sidebarCollapsed ? 'rotate-180' : ''" class="transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            {{-- Logo + Notification bell --}}
            @php $unread = auth()->user()->notifications()->where('is_read', false)->count(); @endphp
            <div class="flex items-center justify-between h-[72px] border-b border-white/[0.06] shrink-0 px-5">
                <a href="{{ route('landlord.dashboard') }}"
                    class="flex items-center gap-2.5 overflow-hidden no-underline">
                    <div class="w-9 h-9 rounded-xl bg-[#2AA7A1] flex items-center justify-center shadow-sm shrink-0">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="text-[16px] font-extrabold text-white tracking-tight whitespace-nowrap">
                        Abanganan<span class="text-[#2AA7A1]">Hub</span>
                    </span>
                </a>

                {{-- Notification bell (expanded state) --}}
                <div data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="relative"
                    x-data="notificationDropdown()" @click.away="close()" @keydown.escape.window="close()">
                    <button type="button" @click="toggle()"
                        class="relative w-8 h-8 flex items-center justify-center rounded-lg text-white/40 hover:text-white/80 hover:bg-white/[0.06] transition-colors shrink-0">
                        <span x-show="unreadCount > 0" x-cloak
                            class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#0F172A]"></span>
                        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>

                    {{-- Notification dropdown panel --}}
                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="absolute top-[calc(100%+10px)] right-0 w-[calc(100vw-2rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#64748B]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            @php $current = request()->route()?->getName() ?? ''; @endphp

            <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-5 scrollbar-thin">

                {{-- Notification bell when collapsed (icon only) --}}
                <div x-show="sidebarCollapsed" x-cloak class="relative mb-2" x-data="notificationDropdown()"
                    @click.away="close()" @keydown.escape.window="close()">
                    <button type="button" @click="toggle()"
                        class="group relative w-full flex items-center justify-center px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-200 text-white/60 hover:bg-white/[0.06] hover:text-white/90">
                        <div class="relative">
                            <span x-show="unreadCount > 0" x-cloak
                                class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#0F172A]"></span>
                            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <span
                            class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity z-50 shadow-lg">
                            Notifications
                        </span>
                    </button>

                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="absolute top-0 left-full ml-3 w-[320px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#64748B]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 text-[11px] font-bold text-white/30 uppercase tracking-widest mb-2 whitespace-nowrap">
                    Main</p>

                {{-- Dashboard --}}
                <a href="{{ route('landlord.dashboard') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                        {{ $current === 'landlord.dashboard' ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Dashboard</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Dashboard
                    </span>
                </a>

                {{-- Properties --}}
                <a href="{{ route('landlord.properties.index') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ str_starts_with($current, 'landlord.properties') && !str_contains($current, 'units') ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Properties</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Properties
                    </span>
                </a>

                {{-- Units --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Units</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Via
                        Property</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Units — open a property to manage units
                    </span>
                </div>

                {{-- Occupancy --}}
                <a href="{{ route('landlord.occupancy.index') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ str_starts_with($current, 'landlord.occupancy') ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Occupancy</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Occupancy
                    </span>
                </a>

                {{-- Reservations --}}
                <a href="{{ route('landlord.reservations.index') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ str_starts_with($current, 'landlord.reservations') ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Reservations</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Reservations
                    </span>
                </a>

                {{-- Tenants --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Tenants</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Soon</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Tenants
                    </span>
                </div>

                {{-- Messages --}}
                <a href="{{ route('conversations.index') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ str_starts_with($current, 'conversations') ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <circle cx="12" cy="12" r="9" />
                        <circle cx="8.5" cy="12" r="0.75" fill="currentColor" stroke="none" />
                        <circle cx="12" cy="12" r="0.75" fill="currentColor" stroke="none" />
                        <circle cx="15.5" cy="12" r="0.75" fill="currentColor" stroke="none" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Messages</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Messages
                    </span>
                </a>

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 text-[11px] font-bold text-white/30 uppercase tracking-widest mb-2 mt-6 whitespace-nowrap">
                    Insights</p>

                {{-- Occupancy (Insights - disabled duplicate, keeping from original) --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .649.275 1.234.717 1.65a2.49 2.49 0 0 0-.717 1.65c0 .231.035.454.1.664-1.708.293-3.05 1.635-3.343 3.343-.21-.065-.433-.1-.664-.1a2.49 2.49 0 0 0-1.65.717 2.49 2.49 0 0 0-1.65-.717c-.231 0-.454.035-.664.1-.293-1.708-1.635-3.05-3.343-3.343" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Occupancy</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Soon</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Occupancy
                    </span>
                </div>

                {{-- Analytics --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Analytics</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Soon</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Analytics
                    </span>
                </div>

                {{-- Reports --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Reports</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Soon</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Reports
                    </span>
                </div>

                {{-- Reviews --}}
                <div :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 text-white/30 cursor-not-allowed select-none">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Reviews</span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="ml-auto text-[9px] font-bold uppercase tracking-wider bg-white/[0.06] text-white/25 px-1.5 py-0.5 rounded-full whitespace-nowrap">Soon</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Reviews
                    </span>
                </div>

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 text-[11px] font-bold text-white/30 uppercase tracking-widest mb-2 mt-6 whitespace-nowrap">
                    Account</p>

                {{-- My Profile --}}
                <a href="{{ route('landlord.profile.me') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ $current === 'landlord.profile.me' || $current === 'landlord.profile.edit' ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">My
                        Profile</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        My Profile
                    </span>
                </a>

                {{-- Report a Problem --}}
                <a href="{{ route('reports.create') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ $current === 'reports.create' ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="whitespace-nowrap">Report a
                        Problem</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Report a Problem
                    </span>
                </a>

                {{-- Settings --}}
                <a href="{{ route('profile.edit') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium mb-1 transition-colors duration-200
                          {{ $current === 'profile.edit' ? 'bg-[#2AA7A1] text-white font-semibold' : 'text-white/60 hover:bg-white/[0.06] hover:text-white/90' }}">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                        class="whitespace-nowrap">Settings</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        Settings
                    </span>
                </a>
            </nav>

            {{-- ── USER SECTION (sidebar bottom) ── --}}
            <div class="border-t border-white/[0.06] shrink-0">
                <div class="flex items-center gap-2.5 px-4 py-3">
                    <span
                        class="w-9 h-9 rounded-full bg-[#2AA7A1] text-white text-[14px] font-bold flex items-center justify-center shrink-0">
                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                    </span>
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="flex-1 min-w-0">
                        <span
                            class="block text-[12px] font-semibold text-white truncate">{{ auth()->user()->first_name }}
                            {{ auth()->user()->last_name }}</span>
                        <span class="block text-[10px] text-white/40">Landlord</span>
                    </span>
                    <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                        @csrf
                        <button type="submit" title="Sign out"
                            class="group/so relative w-7 h-7 flex items-center justify-center rounded-lg text-white/30 hover:text-red-400 hover:bg-white/[0.06] transition-colors">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            <span x-show="sidebarCollapsed" x-cloak
                                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover/so:opacity-100 transition-opacity z-50 shadow-xl">
                                Sign out
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- ============ MAIN ============ --}}
        <div id="landlord-main" :class="sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'"
            class="flex-1 flex flex-col min-w-0 transition-all duration-300">

            {{-- Mobile-only slim bar --}}
            <div
                class="lg:hidden flex items-center justify-between px-4 py-3 bg-white border-b border-[#E2E8F0] sticky top-0 z-30">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-[#E2E8F0] text-[#1F2937] transition-colors duration-200">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>

                <div class="relative" x-data="notificationDropdown()" @click.away="close()"
                    @keydown.escape.window="close()">
                    <button type="button" @click="toggle()"
                        class="relative flex items-center justify-center w-9 h-9 rounded-lg border border-[#E2E8F0] bg-white text-[#1F2937]/70 hover:bg-[#E2E8F0] transition-colors duration-200">
                        <span x-show="unreadCount > 0" x-cloak
                            class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-white"></span>
                        <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="absolute top-[calc(100%+10px)] right-0 w-[calc(100vw-2rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#64748B]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#64748B] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Flash --}}
            @if(session('error'))
                <div class="px-4 sm:px-8 lg:px-[50px] pt-4">
                    <div
                        class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium flex items-center justify-between shadow-sm">
                        <span>{{ session('error') }}</span>
                        <button class="opacity-60 hover:opacity-100 pl-3 focus:outline-none"
                            onclick="this.parentElement.remove()">&#10005;</button>
                    </div>
                </div>
            @endif

            <main class="flex-1 overflow-x-hidden">
                @yield('content')
            </main>
        </div>
    </div>

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
    @include('partials.message-notifications')

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @if(session('status') || session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('show-modal', {
                    detail: {
                        type: 'success',
                        title: 'Done',
                        message: @json(session('status') ?? session('success')),
                    }
                }));
            });
        </script>
    @endif

    @stack('scripts')

</body>

</html>