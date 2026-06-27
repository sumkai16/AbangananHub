<x-guest-layout>
    {{-- Right panel top action --}}
    <x-slot name="rightTopAction">
        {{-- Empty on login page — nothing needed top-right --}}
    </x-slot>

    {{-- Right panel headline & feature area --}}
    <x-slot name="rightContent">
        <div class="bg-slate-950/25 backdrop-blur-lg border border-white/15 rounded-2xl p-6 shadow-2xl max-w-xl mx-auto transform transition-all duration-300 hover:border-white/25">
            <h1 class="text-3xl font-black text-white leading-tight tracking-tight mb-3 drop-shadow-sm">
                Your Trusted Platform<br>
                for Verified Rental <span class="bg-gradient-to-r from-[#9cd4ff] via-[#61B2F0] to-[#286CD2] bg-clip-text text-transparent font-black">Properties.</span>
            </h1>
            <p class="text-white/90 font-medium text-xs leading-relaxed mb-6 drop-shadow">
                We engine-verify listings to match you with premier, safe, and highly affordable accommodations with absolute certainty.
            </p>

            <div class="space-y-3">
                {{-- Feature 1 --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">Verified Landlords</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Strict multi-point identity verification enforced for occupant safety.</p>
                    </div>
                </div>

                {{-- Feature 2 --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">Quality Listings</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Every singular unit is curated and thoroughly evaluated before going live.</p>
                    </div>
                </div>

                {{-- Feature 3 --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">Secure & Transparent</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Absolute legal accountability with direct automated lease processing.</p>
                    </div>
                </div>

                {{-- Feature 4 (SDG 16 integrated clean into list container) --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-[#286CD2] flex flex-col items-center justify-center shrink-0 shadow-md shadow-blue-600/20 select-none">
                        <span class="text-white text-[8px] font-black tracking-tighter leading-none">SDG</span>
                        <span class="text-white text-xs font-black leading-none mt-0.5">16</span>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">Supporting SDG 16</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">We promote transparency, accountability, and trust in rental transactions to build a safer and more trustworthy community.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    {{-- Left panel: Unified layout and no-scrollbar viewport constraints --}}
    <div class="w-full min-h-screen lg:h-screen bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-100 flex flex-col justify-center items-center px-4 py-6 lg:py-2 overflow-y-auto lg:overflow-hidden">
        <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200/80 p-6 sm:p-8 shadow-xl shadow-blue-900/[0.04] transition-all duration-300">

            {{-- Branding --}}
            <div class="mb-5">
                <a href="/" class="flex items-center gap-2 mb-4 group inline-flex">
                    <div class="w-8 h-8 rounded-lg bg-[#286CD2] flex items-center justify-center shadow-md shadow-blue-500/10 transition-transform duration-300 group-hover:scale-105">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-[#1A1A2E] font-bold text-base tracking-tight">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
                </a>
                <h2 class="text-xl font-black text-[#1A1A2E] tracking-tight">Welcome Back!</h2>
                <p class="text-[#9B9F98] text-xs font-medium mt-0.5">Login to continue to AbangananHub</p>
            </div>

            {{-- Session alerts --}}
            @if (session('status'))
                <div class="mb-4 text-xs text-green-600 bg-green-50 rounded-lg px-4 py-2.5 border border-green-100 font-semibold">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 flex items-start gap-3 bg-[#BD5434]/10 border border-[#BD5434]/30 rounded-lg px-4 py-2.5">
                    <svg class="w-4 h-4 text-[#BD5434] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p class="text-xs text-[#BD5434] font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-3.5">
                @csrf

                {{-- Email Address --}}
                <div>
                    <label for="email" class="block text-xs font-bold text-[#1A1A2E] mb-1">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Enter your email"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#1A1A2E] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                </div>

                {{-- Password Input --}}
                <div>
                    <label for="password" class="block text-xs font-bold text-[#1A1A2E] mb-1">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"
                            class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#1A1A2E] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                        <button type="button" onclick="togglePassword('password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#1A1A2E] transition-colors focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Remember Me & Forgot Password Layout --}}
                <div class="flex items-center justify-between pt-0.5">
                    <label class="flex items-center gap-2 text-xs text-[#5E6968] font-semibold cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-[#61B2F0] focus:ring-[#61B2F0] transition-colors w-3.5 h-3.5" />
                        Remember me
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs text-[#286CD2] hover:text-[#61B2F0] font-bold transition-colors hover:underline">
                            Forgot Password?
                        </a>
                    @endif
                </div>

                {{-- Action Submission Button --}}
                <div class="pt-1">
                    <button type="submit" class="w-full py-2 bg-[#286CD2] hover:opacity-95 text-white font-bold text-xs rounded-lg shadow-md shadow-blue-600/10 transition-all duration-300 transform active:scale-[0.995]">
                        Login
                    </button>
                </div>
            </form>

            <p class="text-center text-xs text-[#9B9F98] font-medium mt-5">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-[#286CD2] font-bold hover:underline ml-1">Register here</a>
            </p>

            <p class="text-center text-[10px] font-bold text-[#9B9F98]/70 mt-5 tracking-wider uppercase">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
        </div>
    </div>
</x-guest-layout>

<script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.innerHTML = isPassword
            ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`
            : `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`;
    }
</script>