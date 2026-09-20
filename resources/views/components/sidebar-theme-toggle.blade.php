{{-- Light/dark row for the dark Landlord/Admin sidebars. Must sit inside the layout's Alpine scope
     (`sidebarCollapsed`). Behaviour is wired in partials/theme-init.blade.php. --}}
<button type="button" data-theme-toggle aria-label="Switch between light and dark theme"
    :class="sidebarCollapsed ? 'justify-center' : ''"
    class="group relative flex w-[calc(100%-1rem)] items-center gap-3 mx-2 mt-2 px-3 py-2.5 rounded-xl text-[14px] font-medium text-white/65 hover:bg-white/[0.06] hover:text-[#FF8A66] transition-colors duration-200 cursor-pointer">
    <svg class="theme-icon-moon shrink-0" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
    </svg>
    <svg class="theme-icon-sun shrink-0" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
    </svg>
    <span data-sidebar-label x-show="!sidebarCollapsed" class="whitespace-nowrap">
        <span class="theme-label-light">Dark mode</span><span class="theme-label-dark">Light mode</span>
    </span>
    <span x-show="sidebarCollapsed" x-cloak
        class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-[#1e293b] border border-white/10 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 group-hover:opacity-100 transition-opacity duration-150 z-50 shadow-lg">
        Switch theme
    </span>
</button>
