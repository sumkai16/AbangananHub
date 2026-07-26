{{-- Divider + "Continue with Google/Facebook" button row, shared by the
     AJAX auth modal (layouts/app.blade.php) and the full-page login/register
     fallbacks. Plain links, not AJAX — OAuth needs a real browser redirect. --}}
<div class="relative my-5">
    <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-[#E2E8F0]"></div>
    </div>
    <div class="relative flex justify-center text-[11px]">
        <span class="bg-white px-3 text-[#94A3B8] font-bold uppercase tracking-wider">or continue with</span>
    </div>
</div>
<div class="grid grid-cols-2 gap-3">
    <a href="{{ route('social.redirect', 'google') }}"
        class="flex items-center justify-center gap-2 border border-[#E2E8F0] rounded-xl py-2.5 text-[13px] font-bold text-[#334155] hover:brightness-95 cursor-pointer transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20">
        <svg class="w-4 h-4" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.43 3.58v2.98h3.93c2.3-2.12 3.62-5.24 3.62-8.8z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.92l-3.93-2.98c-1.09.73-2.48 1.16-4 1.16-3.08 0-5.69-2.08-6.62-4.87H1.32v3.07C3.29 21.3 7.31 24 12 24z"/>
            <path fill="#FBBC05" d="M5.38 14.39c-.24-.73-.38-1.5-.38-2.39s.14-1.66.38-2.39V6.54H1.32C.48 8.2 0 10.05 0 12s.48 3.8 1.32 5.46l4.06-3.07z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.48-3.48C17.95 1.19 15.24 0 12 0 7.31 0 3.29 2.7 1.32 6.54l4.06 3.07C6.31 6.82 8.92 4.75 12 4.75z"/>
        </svg>
        Google
    </a>
    <a href="{{ route('social.redirect', 'facebook') }}"
        class="flex items-center justify-center gap-2 border border-[#E2E8F0] rounded-xl py-2.5 text-[13px] font-bold text-[#334155] hover:brightness-95 cursor-pointer transition-all duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20">
        <svg class="w-4 h-4" fill="#1877F2" viewBox="0 0 24 24">
            <path d="M24 12.07C24 5.68 18.63.4 12 .4S0 5.68 0 12.07c0 5.77 4.39 10.56 10.13 11.44v-8.09H7.08v-3.35h3.05V9.41c0-3 1.79-4.66 4.53-4.66 1.31 0 2.68.24 2.68.24v2.94h-1.51c-1.49 0-1.95.92-1.95 1.87v2.24h3.32l-.53 3.35h-2.79v8.09C19.61 22.63 24 17.84 24 12.07z"/>
        </svg>
        Facebook
    </a>
</div>
