<!DOCTYPE html>
<html lang="en">
<meta name="user-authenticated" content="{{ auth()->check() ? '1' : '0' }}">

<head>
    @include('partials.theme-init')
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ ($title ?? 'Admin') }} · AbangananHub</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/logo-app-icon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            if (localStorage.getItem('adminSidebarCollapsed') === 'true') {
                document.documentElement.classList.add('admin-sidebar-pre-collapsed');
            }
        })();
    </script>
    <style>
        /* Paint the sidebar and the content offset on first byte at desktop width, before Alpine boots, so nothing
           moves when a page loads (same as the landlord layout). The collapse toggle still animates. */
        @media (min-width: 1024px) {
            #admin-sidebar[x-cloak] { display: flex !important; }
            .admin-sidebar-pre-collapsed #admin-sidebar { width: 5rem; }
            .admin-sidebar-pre-collapsed #admin-main { margin-left: 5rem; }
            .admin-sidebar-pre-collapsed #admin-sidebar [data-sidebar-label] { display: none; }
        }
    </style>
</head>

<body class="font-sans bg-[#F7F8FC] text-[#060D26] min-h-screen" x-data="{
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('adminSidebarCollapsed') === 'true',
        userMenuOpen: false
    }" x-init="
        document.documentElement.classList.toggle('admin-sidebar-pre-collapsed', sidebarCollapsed);
        $watch('sidebarCollapsed', value => {
            localStorage.setItem('adminSidebarCollapsed', value);
            document.documentElement.classList.toggle('admin-sidebar-pre-collapsed', value);
        });
    ">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#060D26] focus:text-white focus:font-semibold">Skip to main content</a>

    <div class="flex min-h-screen">

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/50 z-40 lg:hidden backdrop-blur-sm"></div>

        {{-- ===================== SIDEBAR ===================== --}}
        <aside id="admin-sidebar" x-cloak
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="print:hidden fixed inset-y-0 left-0 z-50 w-64 lg:w-64 bg-[#060D26] border-r border-white/[0.06] flex flex-col transition-all duration-300 lg:translate-x-0">

            {{-- Collapse toggle --}}
            <button @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex absolute top-5 -right-3 w-6 h-6 rounded-full bg-[#060D26] text-white items-center justify-center shadow-md hover:brightness-95 transition-all duration-200 z-10"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    :class="sidebarCollapsed ? 'rotate-180' : ''" class="transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            {{-- Logo + Notification bell --}}
            <div class="flex items-center justify-between h-[64px] border-b border-white/[0.06] shrink-0 px-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 overflow-hidden no-underline">
                    <img src="{{ asset('images/brand/logo-mark-on-dark.svg') }}" alt="AbangananHub"
                        class="w-8 h-8 object-contain shrink-0">
                    <div data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="overflow-hidden">
                        <p class="text-[15px] font-extrabold text-white tracking-tight whitespace-nowrap leading-tight">
                            Abanganan<span class="text-[#FF8A66]">Hub</span>
                        </p>
                        <p class="text-[10px] font-semibold text-white/40 uppercase tracking-widest whitespace-nowrap">Admin Panel</p>
                    </div>
                </a>

                @php $unread = $unreadNotificationCount; @endphp
                <a href="{{ route('notifications.index') }}" data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    aria-label="Notifications"
                    class="relative w-10 h-10 flex items-center justify-center rounded-lg text-white/40 hover:text-white/80 hover:bg-white/[0.06] transition-colors shrink-0">
                    @if($unread > 0)
                        <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#060D26]"></span>
                    @endif
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </a>
            </div>

            {{-- Nav --}}
            @php $cur = request()->route()?->getName() ?? ''; @endphp

            <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 space-y-0.5 scrollbar-thin">

                {{-- Notification bell when collapsed (icon only) --}}
                <a href="{{ route('notifications.index') }}" x-show="sidebarCollapsed" x-cloak
                    class="group relative flex items-center justify-center px-3 py-2 rounded-xl text-[13px] font-medium transition-all duration-200 mb-1
                        text-white/60 hover:bg-white/[0.06] hover:text-white/90">
                    <div class="relative">
                        @if($unread > 0)
                            <span class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-[#060D26]"></span>
                        @endif
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" class="shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </div>
                    <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#060D26] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity z-50">
                        Notifications{{ $unread > 0 ? " ($unread)" : '' }}
                    </span>
                </a>

                <x-admin.nav-item label="Dashboard" class="mb-3" :href="route('admin.dashboard')" :active="$cur === 'admin.dashboard'"
                    icon="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-2 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    User Management</p>

                <x-admin.nav-item label="Users" :href="route('admin.users.index')" :active="str_starts_with($cur, 'admin.users.')"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0Z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Verification &amp; Approvals</p>

                <x-admin.nav-item label="Landlord Verifications" :href="route('admin.verifications.index')" :active="str_starts_with($cur, 'admin.verifications')"
                    icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                <x-admin.nav-item label="Property Verifications" :href="route('admin.listings.approval')" :active="$cur === 'admin.listings.approval'"
                    icon="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                <x-admin.nav-item label="Property Documents" :href="route('admin.documents.index')" :active="str_starts_with($cur, 'admin.documents')"
                    icon="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Property Management</p>

                <x-admin.nav-item label="Properties" :href="route('admin.catalogue.properties.index')" :active="str_starts_with($cur, 'admin.catalogue.properties')"
                    icon="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                <x-admin.nav-item label="Units" :href="route('admin.catalogue.units.index')" :active="str_starts_with($cur, 'admin.catalogue.units') || str_starts_with($cur, 'admin.units')"
                    icon="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Rentals</p>

                <x-admin.nav-item label="Reservations" :href="route('admin.reservations.index')" :active="str_starts_with($cur, 'admin.reservations')"
                    icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                <x-admin.nav-item label="Payments" :href="route('admin.payments.index')" :active="str_starts_with($cur, 'admin.payments')"
                    icon="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                <x-admin.nav-item label="Payouts" :href="route('admin.payouts.index')" :active="str_starts_with($cur, 'admin.payouts')"
                    icon="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                <x-admin.nav-item label="Deposit Charges" :href="route('admin.deposit-charges.index')" :active="str_starts_with($cur, 'admin.deposit-charges')"
                    icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Content &amp; Reviews</p>

                <x-admin.nav-item label="Reviews" :href="route('admin.reviews.index')" :active="str_starts_with($cur, 'admin.reviews')"
                    icon="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                <x-admin.nav-item label="Reports" :href="route('admin.reports.index')" :active="str_starts_with($cur, 'admin.reports')"
                    icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                <x-admin.nav-item label="Conversations" :href="route('admin.conversations.index')" :active="str_starts_with($cur, 'admin.conversations')"
                    icon="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Insights</p>

                <x-admin.nav-item label="Analytics" :href="route('admin.report-analytics.index')" :active="str_starts_with($cur, 'admin.report-analytics')"
                    icon="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                <x-admin.nav-item label="Ratings" filled :href="route('admin.ratings.index')" :active="str_starts_with($cur, 'admin.ratings')"
                    icon="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    System Management</p>

                <x-admin.nav-item label="System Settings" :href="route('admin.settings.edit')" :active="str_starts_with($cur, 'admin.settings')"
                    icon="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <x-admin.nav-item label="Audit Logs" :href="route('admin.audit-logs.index')" :active="str_starts_with($cur, 'admin.audit-logs')"
                    icon="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />

                <p data-sidebar-label x-show="!sidebarCollapsed" x-cloak
                    class="px-3 pt-4 pb-1.5 text-[10px] font-bold text-white/50 uppercase tracking-widest whitespace-nowrap">
                    Account</p>

                <x-admin.nav-item label="My Profile" :href="route('admin.profile.edit')" :active="str_starts_with($cur, 'admin.profile')"
                    icon="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />

            </nav>

            {{-- ── USER SECTION (sidebar bottom) ── --}}
            <div class="border-t border-white/[0.06] shrink-0">
                <x-sidebar-theme-toggle />

                <div class="flex items-center gap-2.5 px-4 py-3">
                    @if(auth()->user()->profile_picture)
                        <img loading="lazy" decoding="async" src="{{ auth()->user()->profile_picture }}" alt="{{ auth()->user()->first_name }}"
                            class="w-8 h-8 rounded-full object-cover shrink-0">
                    @else
                        <span class="w-8 h-8 rounded-full bg-white/10 text-white text-[13px] font-bold flex items-center justify-center shrink-0">
                            {{ strtoupper(substr(auth()->user()->first_name ?? 'A', 0, 1)) }}
                        </span>
                    @endif
                    <span data-sidebar-label x-show="!sidebarCollapsed" x-cloak class="flex-1 min-w-0">
                        <span class="block text-[12px] font-semibold text-white truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                        <span class="block text-[10px] text-white/40">Administrator</span>
                    </span>
                    <form action="{{ route('logout') }}" method="POST" class="shrink-0"
                        data-confirm="Sign out?" data-confirm-message="You'll need to log in again to continue." data-confirm-button="Sign out">
                        @csrf
                        <button type="submit" title="Sign out"
                            class="group/so relative w-7 h-7 flex items-center justify-center rounded-lg text-white/30 hover:text-[#DC2626] hover:bg-white/[0.06] transition-colors">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            <span x-show="sidebarCollapsed" x-cloak
                                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#060D26] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover/so:opacity-100 transition-opacity z-50">
                                Sign out
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- ===================== MAIN ===================== --}}
        <div id="admin-main"
            class="lg:ml-64 print:!ml-0 flex-1 flex flex-col min-w-0 transition-all duration-300">

            {{-- Mobile-only slim bar (hamburger + notif) --}}
            <div class="lg:hidden print:hidden flex items-center justify-between px-4 py-3 bg-white border-b border-[#E2E4EC] sticky top-0 z-30">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-[#ECEEF6] text-[#5B6A8E] transition-colors">
                    <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>

                @hasSection('page-title')
                    <h1 class="text-[14px] font-semibold text-[#060D26]">@yield('page-title')</h1>
                @endif

                <div class="flex items-center gap-2">
                <x-theme-toggle class="w-9 h-9 rounded-xl border border-[#E2E4EC] bg-white text-[#5B6A8E] hover:bg-[#F7F8FC]" />
                <a href="{{ route('notifications.index') }}"
                    class="relative w-9 h-9 flex items-center justify-center rounded-xl border border-[#E2E4EC] bg-white text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-all">
                    @if($unread > 0)
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#EF4444] border-2 border-white"></span>
                    @endif
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </a>
                </div>
            </div>

            <main id="main" class="flex-1 overflow-x-hidden p-4 sm:p-6 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')

    @stack('scripts')
</body>
</html>