<!DOCTYPE html>
<html lang="en">
<meta name="user-authenticated" content="{{ auth()->check() ? '1' : '0' }}">

<head>
    @include('partials.theme-init')
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'AbangananHub' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AbangananHub-icon-256.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            if (localStorage.getItem('landlordSidebarCollapsed') === 'true') {
                document.documentElement.classList.add('sidebar-pre-collapsed');
            }
        })();
    </script>
    <style>
        /* Paint the sidebar and content offset on first byte at desktop width, before Alpine boots. */
        @media (min-width: 1024px) {
            #landlord-sidebar[x-cloak] {
                display: flex !important;
            }
        }

        /* Cross-fade between landlord pages; the sidebar keeps its own layer so it never blinks. */
        @view-transition {
            navigation: auto;
        }

        #landlord-sidebar {
            view-transition-name: landlord-sidebar;
        }

        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation-duration: 140ms;
        }

        @media (prefers-reduced-motion: reduce) {
            @view-transition {
                navigation: none;
            }
        }

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

    {{-- Chromium starts loading a sidebar page when the pointer rests on its link, so the click lands on a ready page.
         Read-only pages only: Messages is left out because opening a thread marks messages as read. --}}
    @php
        $speculativeUrls = array_map(fn ($name) => route($name, [], false), [
            'landlord.dashboard', 'landlord.properties.index', 'landlord.reservations.index',
            'landlord.tenants.index', 'landlord.payments.index', 'landlord.payouts.index',
            'landlord.reviews.index', 'landlord.analytics.index', 'landlord.complaints.index',
            'landlord.profile.me',
        ]);
        $speculationRules = ['prerender' => [['urls' => $speculativeUrls, 'eagerness' => 'moderate']]];
    @endphp
    <script type="speculationrules">
        @json($speculationRules)
    </script>
</head>

<body class="font-sans bg-[#F7F8FC] text-[#060D26] min-h-screen" x-data="{
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('landlordSidebarCollapsed') === 'true'
    }" x-init="
        document.documentElement.classList.toggle('sidebar-pre-collapsed', sidebarCollapsed);
        $watch('sidebarCollapsed', value => {
            localStorage.setItem('landlordSidebarCollapsed', value);
            document.documentElement.classList.toggle('sidebar-pre-collapsed', value);
        });
    ">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#060D26] focus:text-white focus:font-semibold">Skip to main content</a>

    <div class="flex min-h-screen">

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-40 lg:hidden">
        </div>

        {{-- ============ SIDEBAR ============ --}}
        <aside id="landlord-sidebar" x-cloak
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 lg:w-64 bg-[#060D26] border-r border-white/[0.06] flex flex-col transition-all duration-300 lg:translate-x-0">

            {{-- Collapse toggle --}}
            <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex absolute top-5 -right-3 w-6 h-6 rounded-full bg-[#060D26] text-white items-center justify-center shadow-md hover:brightness-95 transition-all duration-200 z-10"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    :class="sidebarCollapsed ? 'rotate-180' : ''" class="transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            {{-- Logo + Notification bell --}}
            @php $unread = $unreadNotificationCount; @endphp
            <div class="flex items-center justify-between h-[64px] border-b border-white/[0.06] shrink-0 px-5">
                <a href="{{ route('landlord.dashboard') }}"
                    class="flex items-center gap-2.5 overflow-hidden no-underline">
                    <img src="{{ asset('images/AbangananHub-icon-256.png') }}" alt="AbangananHub"
                        class="w-9 h-9 object-contain shrink-0">
                    <span data-sidebar-label x-show="!sidebarCollapsed"
                        class="text-[16px] font-extrabold text-white tracking-tight whitespace-nowrap">
                        Abanganan<span class="text-[#FF8A66]">Hub</span>
                    </span>
                </a>

                {{-- Notification bell (expanded state) --}}
                <div data-sidebar-label x-show="!sidebarCollapsed" class="relative"
                    x-data="notificationDropdown()" @click.away="close()" @keydown.escape.window="close()">
                    <button type="button" @click="toggle()" aria-label="Notifications"
                        class="relative w-10 h-10 flex items-center justify-center rounded-lg text-white/40 hover:text-white/80 hover:bg-white/[0.06] transition-colors shrink-0">
                        <span x-show="unreadCount > 0" x-cloak
                            class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#060D26]"></span>
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
                        class="absolute top-[calc(100%+10px)] left-0 w-[calc(100vw-2rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#5B6A8E]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#5B6A8E] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Nav --}}
            @php $current = request()->route()?->getName() ?? ''; @endphp

            {{-- Notification bell when collapsed (icon only).
                 Deliberately a direct child of <aside>, NOT inside <nav>: the
                 nav carries `overflow-x-hidden`, which clipped this panel at
                 the sidebar edge since it opens rightward via `left-full`. --}}
            <div x-show="sidebarCollapsed" x-cloak class="relative px-3 pt-5" x-data="notificationDropdown()"
                @click.away="close()" @keydown.escape.window="close()">
                    <button type="button" @click="toggle()"
                        class="group relative w-full flex items-center justify-center px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-200 text-white/60 hover:bg-white/[0.06] hover:text-white/90">
                        <div class="relative">
                            <span x-show="unreadCount > 0" x-cloak
                                class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#060D26]"></span>
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
                        class="absolute top-0 left-full ml-3 w-[320px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#5B6A8E]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#5B6A8E] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @php
                // One list drives every link, so a design change (or a new item) is one edit, not fourteen.
                // `icon` is static SVG inner markup defined here, never user input.
                $navUnreadMessages = \App\Models\Message::whereHas('conversation', fn ($q) => $q->where('landlord_id', auth()->id()))
                    ->where('sender_id', '!=', auth()->id())->where('is_read', false)->count();
                $navInquiries = \App\Models\Reservation::whereHas('property', fn ($q) => $q->where('landlord_id', auth()->id()))
                    ->where('rental_status', 'Inquiry')->count();

                $navSections = [
                    'Main' => [
                        ['label' => 'Dashboard', 'href' => route('landlord.dashboard'), 'active' => $current === 'landlord.dashboard',
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />'],
                        ['label' => 'Properties', 'href' => route('landlord.properties.index'), 'active' => str_starts_with($current, 'landlord.properties') && ! str_contains($current, 'units'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21" />'],
                        // All units across properties. Also lit for the per-property unit pages, which Properties skips.
                        ['label' => 'Units', 'href' => route('landlord.units.index'), 'active' => str_starts_with($current, 'landlord.units') || str_contains($current, 'properties.units'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />'],
                        ['label' => 'Reservations', 'href' => route('landlord.reservations.index'), 'active' => str_starts_with($current, 'landlord.reservations'), 'badge' => $navInquiries,
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />'],
                        // Also lit for the per-tenancy management page.
                        ['label' => 'Tenants', 'href' => route('landlord.tenants.index'), 'active' => str_starts_with($current, 'landlord.tenants') || str_starts_with($current, 'landlord.tenancies'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />'],
                        ['label' => 'Rent & Payments', 'href' => route('landlord.payments.index'), 'active' => str_starts_with($current, 'landlord.payments'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />'],
                        ['label' => 'Payouts', 'href' => route('landlord.payouts.index'), 'active' => str_starts_with($current, 'landlord.payouts'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />'],
                        ['label' => 'Messages', 'href' => route('conversations.index'), 'active' => str_starts_with($current, 'conversations'), 'badge' => $navUnreadMessages,
                            'icon' => '<circle cx="12" cy="12" r="9" /><circle cx="8.5" cy="12" r="0.75" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="0.75" fill="currentColor" stroke="none" /><circle cx="15.5" cy="12" r="0.75" fill="currentColor" stroke="none" />'],
                    ],
                    'Insights' => [
                        ['label' => 'Reviews', 'href' => route('landlord.reviews.index'), 'active' => str_starts_with($current, 'landlord.reviews'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z" />'],
                        ['label' => 'Analytics', 'href' => route('landlord.analytics.index'), 'active' => str_starts_with($current, 'landlord.analytics'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />'],
                    ],
                    'Account' => [
                        // Reports this landlord filed: support history, not insight. Reports filed against anyone stay admin-only.
                        ['label' => 'My Complaints', 'href' => route('landlord.complaints.index'), 'active' => str_starts_with($current, 'landlord.complaints'),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />'],
                        ['label' => 'My Profile', 'href' => route('landlord.profile.me'), 'active' => in_array($current, ['landlord.profile.me', 'landlord.profile.edit'], true),
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />'],
                        ['label' => 'Report a Problem', 'href' => route('reports.create'), 'active' => $current === 'reports.create',
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />'],
                        ['label' => 'Settings', 'href' => route('profile.edit'), 'active' => $current === 'profile.edit',
                            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />'],
                    ],
                ];
            @endphp

            <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-5 scrollbar-thin" aria-label="Landlord navigation">
                @foreach($navSections as $sectionTitle => $navItems)
                    <p data-sidebar-label x-show="!sidebarCollapsed"
                        class="px-3 text-[11px] font-semibold text-white/50 uppercase tracking-widest mb-2 whitespace-nowrap {{ $loop->first ? '' : 'mt-6' }}">
                        {{ $sectionTitle }}</p>
                    @unless($loop->first)
                        <div x-show="sidebarCollapsed" x-cloak class="mx-3 mb-3 mt-4 border-t border-white/[0.08]"></div>
                    @endunless

                    @foreach($navItems as $item)
                        @php $badge = $item['badge'] ?? 0; @endphp
                        <a href="{{ $item['href'] }}" @if($item['active']) aria-current="page" @endif
                            :class="sidebarCollapsed ? 'justify-center' : ''"
                            class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-[14px] font-medium mb-0.5 transition-colors duration-200
                                {{ $item['active'] ? 'bg-white/[0.08] text-white font-semibold' : 'text-white/65 hover:bg-white/[0.06] hover:text-[#FF8A66]' }}">
                            @if($item['active'])
                                <span class="absolute left-0 top-2 bottom-2 w-[3px] rounded-r-full bg-[#FF8A66]" aria-hidden="true"></span>
                            @endif
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                                class="shrink-0 {{ $item['active'] ? 'text-[#FF8A66]' : '' }}" aria-hidden="true">{!! $item['icon'] !!}</svg>
                            <span data-sidebar-label x-show="!sidebarCollapsed" class="flex-1 whitespace-nowrap">{{ $item['label'] }}</span>
                            @if($badge > 0)
                                <span data-sidebar-label x-show="!sidebarCollapsed"
                                    class="min-w-[20px] h-5 px-1.5 rounded-full bg-[#FF8A66] text-[#060D26] text-[11px] font-bold flex items-center justify-center tabular-nums">
                                    <span class="sr-only">{{ $badge }} waiting</span><span aria-hidden="true">{{ $badge > 99 ? '99+' : $badge }}</span>
                                </span>
                                <span x-show="sidebarCollapsed" x-cloak class="absolute top-1.5 right-3 w-2 h-2 rounded-full bg-[#FF8A66] border-2 border-[#060D26]" aria-hidden="true"></span>
                            @endif
                            <span x-show="sidebarCollapsed" x-cloak
                                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            {{-- ── USER SECTION (sidebar bottom) ── --}}
            <div class="border-t border-white/[0.06] shrink-0">
                {{-- View public rentals — switch to the tenant-facing browse view --}}
                <a href="{{ route('properties.index') }}" :class="sidebarCollapsed ? 'justify-center' : ''"
                    class="group relative flex items-center gap-3 mx-2 mt-2 px-3 py-2.5 rounded-xl text-[14px] font-medium text-white/65 hover:bg-white/[0.06] hover:text-[#FF8A66] transition-colors duration-200">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span data-sidebar-label x-show="!sidebarCollapsed" class="whitespace-nowrap">View public rentals</span>
                    <span x-show="sidebarCollapsed" x-cloak
                        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
                        View public rentals
                    </span>
                </a>

                <div class="flex items-center gap-2.5 px-4 py-3">
                    @if(auth()->user()->profile_picture)
                        <img loading="lazy" decoding="async" src="{{ auth()->user()->profile_picture }}" alt="{{ auth()->user()->first_name }}"
                            class="w-9 h-9 rounded-full object-cover shrink-0">
                    @else
                        <span
                            class="w-9 h-9 rounded-full bg-white/10 text-white text-[14px] font-bold flex items-center justify-center shrink-0">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </span>
                    @endif
                    <span data-sidebar-label x-show="!sidebarCollapsed" class="flex-1 min-w-0">
                        <span
                            class="block text-[13px] font-semibold text-white truncate">{{ auth()->user()->first_name }}
                            {{ auth()->user()->last_name }}</span>
                        <span class="block text-[12px] text-white/50">Landlord</span>
                    </span>
                    <form action="{{ route('logout') }}" method="POST" class="shrink-0"
                        data-confirm="Sign out?" data-confirm-message="You'll need to log in again to continue." data-confirm-button="Sign out">
                        @csrf
                        <button type="submit" title="Sign out" aria-label="Sign out"
                            class="group/so relative w-10 h-10 flex items-center justify-center rounded-lg text-white/30 hover:text-[#DC2626] hover:bg-white/[0.06] transition-colors">
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
        <div id="landlord-main"
            class="lg:ml-64 flex-1 flex flex-col min-w-0 transition-all duration-300">

            {{-- Mobile-only slim bar --}}
            <div
                class="lg:hidden flex items-center justify-between px-4 py-3 bg-white border-b border-[#E2E4EC] sticky top-0 z-30">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-[#E2E4EC] text-[#060D26] transition-colors duration-200">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>

                <div class="flex items-center gap-2">
                    <x-theme-toggle class="w-9 h-9 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#ECEEF6]" />

                    {{-- Messages — mobile equivalent of the floating chat bubble,
                         which is desktop-only (see partials.message-notifications).
                         Dispatches the same event that bubble's panel listens for. --}}
                    @auth
                        @php
                            $mobileUnreadMsgCount = $unreadMessageCount;
                        @endphp
                        <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('open-messages-panel'))"
                            class="relative flex items-center gap-1.5 h-9 px-3 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#ECEEF6] transition-colors duration-200">
                            Messages
                            @if($mobileUnreadMsgCount > 0)
                                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-[#EF4444] text-white text-[10px] font-bold">{{ $mobileUnreadMsgCount > 99 ? '99+' : $mobileUnreadMsgCount }}</span>
                            @endif
                        </button>
                    @endauth

                    <div class="relative" x-data="notificationDropdown()" @click.away="close()"
                    @keydown.escape.window="close()">
                    <button type="button" @click="toggle()"
                        class="relative flex items-center justify-center w-9 h-9 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26]/70 hover:bg-[#E2E4EC] transition-colors duration-200">
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
                        class="absolute top-[calc(100%+10px)] right-0 w-[calc(100vw-2rem)] max-w-[360px] bg-white rounded-2xl shadow-[0_4px_24px_rgba(0,0,0,0.12)] border border-[#5B6A8E]/15 z-50 overflow-hidden">
                        <div x-ref="dropdownBody">
                            <div class="px-4 py-8 text-center">
                                <div
                                    class="w-6 h-6 border-2 border-[#5B6A8E] border-t-transparent rounded-full animate-spin mx-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

            <main id="main" class="flex-1 overflow-x-clip">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function notificationDropdown() {
            return {
                open: false,
                unreadCount: {{ $unreadNotificationCount }},
                loaded: false,

                init() {
                    // Live bell. Reuses the private user.{id} channel the inbox
                    // already subscribes to. Guarded so a down Reverb leaves the
                    // click-to-load dropdown fully working.
                    if (!window.Echo) return;

                    window.Echo.private('user.{{ auth()->id() }}')
                        .listen('.NotificationCreated', () => {
                            this.unreadCount++;
                            // Force a refetch next open; if it is already open,
                            // pull the new row in immediately.
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
    @include('partials.message-notifications')

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')

    @stack('scripts')

</body>

</html>