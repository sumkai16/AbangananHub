@props(['class' => 'w-10 h-10 rounded-full border border-[#E2E4EC] bg-white text-[#060D26] hover:border-[#FF8A66] hover:text-[#B35A3D]'])

{{-- Light/dark switch. Behaviour is wired once in partials/theme-init.blade.php; the icons swap via
     .theme-icon-* in css/app.css. Pass `class` to restyle it for a dark sidebar or a floating spot. --}}
<button type="button" data-theme-toggle aria-label="Switch between light and dark theme" title="Switch theme"
    {{ $attributes->merge(['class' => 'flex items-center justify-center shrink-0 transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50 ' . $class]) }}>
    {{-- Moon: shown in the light theme (click → dark) --}}
    <svg class="theme-icon-moon w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
    </svg>
    {{-- Sun: shown in the dark theme (click → light) --}}
    <svg class="theme-icon-sun w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
    </svg>
</button>
