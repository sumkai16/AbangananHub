<x-guest-layout>
    {{-- Right panel top action intentionally left empty to keep footer link consistent --}}

    {{-- Right panel headline & feature area (Identical copy/style from image_1eb42f.jpg) --}}
    <x-slot name="rightContent">
        <div class="bg-slate-950/25 backdrop-blur-md border border-white/15 rounded-2xl p-6 shadow-2xl max-w-xl mx-auto transform transition-all duration-300 hover:border-white/25">
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
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">VERIFIED LANDLORDS</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Strict multi-point identity verification enforced for occupant safety.</p>
                    </div>
                </div>

                {{-- Feature 2 --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">QUALITY LISTINGS</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Every singular unit is curated and thoroughly evaluated before going live.</p>
                    </div>
                </div>

                {{-- Feature 3 --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">SECURE & TRANSPARENT</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">Absolute legal accountability with direct automated lease processing.</p>
                    </div>
                </div>

                {{-- Feature 4 (SDG Content matched cleanly inside the card layout) --}}
                <div class="flex items-center gap-3 bg-slate-950/45 backdrop-blur-sm p-3 rounded-xl border border-white/5 shadow-md">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex flex-col items-center justify-center shrink-0 shadow-md shadow-blue-600/20 select-none">
                        <span class="text-white text-[8px] font-black tracking-tighter leading-none">SDG</span>
                        <span class="text-white text-xs font-black leading-none mt-0.5">16</span>
                    </div>
                    <div>
                        <p class="text-white font-black text-xs tracking-wide uppercase">SUPPORTING SDG 16</p>
                        <p class="text-white/80 text-[11px] font-semibold mt-0.5">We promote transparency, accountability, and trust in rental transactions to build a safer and more trustworthy community.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    {{-- Left panel: Optimized layout to maintain no-scrollbar execution --}}
    <div class="w-full min-h-screen lg:h-screen bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-100 flex flex-col justify-center items-center px-4 py-4 lg:py-2 overflow-y-auto lg:overflow-hidden">
        <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200/80 p-5 sm:px-6 sm:py-5 shadow-xl shadow-blue-900/[0.04] transition-all duration-300">

            {{-- Branding --}}
            <div class="mb-4">
                <a href="/" class="flex items-center gap-2 mb-3 group inline-flex">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shadow-md shadow-blue-500/10 transition-transform duration-300 group-hover:scale-105">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-[#2A2523] font-bold text-base tracking-tight">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
                </a>
                <h2 class="text-xl font-black text-[#2A2523] tracking-tight">Create Your <span class="bg-gradient-to-r from-[#286CD2] to-[#61B2F0] bg-clip-text text-transparent">Account</span></h2>
                <p class="text-[#9B9F98] text-xs font-medium mt-0.5">Join AbangananHub and find your perfect place to stay.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-3">
                @csrf

                {{-- First Name + Last Name Grid Layout --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-[#2A2523] mb-0.5">First Name</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                            autofocus autocomplete="given-name" placeholder="First name"
                            class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                        @error('first_name')
                            <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-[#2A2523] mb-0.5">Last Name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                            autocomplete="family-name" placeholder="Last name"
                            class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                        @error('last_name')
                            <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Email Address --}}
                <div>
                    <label for="email" class="block text-xs font-bold text-[#2A2523] mb-0.5">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                        placeholder="Enter your email address"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    @error('email')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Contact Number --}}
                <div>
                    <label for="contact_number" class="block text-xs font-bold text-[#2A2523] mb-0.5">Contact Number</label>
                    <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}"
                        required autocomplete="tel" placeholder="Enter your contact number"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    @error('contact_number')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password Input Module --}}
                <div>
                    <label for="password" class="block text-xs font-bold text-[#2A2523] mb-0.5">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                            placeholder="Create a password"
                            class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                        <button type="button" onclick="togglePassword('password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#2A2523] transition-colors focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Confirm Password Input Module --}}
                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-[#2A2523] mb-0.5">Confirm Password</label>
                    <div class="relative">
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                            autocomplete="new-password" placeholder="Confirm your password"
                            class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#2A2523] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                        <button type="button" onclick="togglePassword('password_confirmation', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#2A2523] transition-colors focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Security Banner --}}
                <div class="flex items-start gap-2.5 bg-blue-50/60 border border-blue-100/80 rounded-xl p-2.5 shadow-sm">
                    <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-[#61B2F0] to-[#286CD2] flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[#2A2523] text-[11px] font-bold uppercase tracking-wide">Your security is our priority</p>
                        <p class="text-[#5E6968] text-[11px] font-semibold leading-tight text-[#5E6968]/90">We protect your data and identity seamlessly.</p>
                    </div>
                </div>

                {{-- Action Submit Button --}}
                <div class="pt-0.5">
                    <button type="submit"
                        class="w-full py-2 bg-gradient-to-r from-[#286CD2] via-[#3B82F6] to-[#61B2F0] hover:opacity-95 text-white font-bold text-xs rounded-lg shadow-md transition-all duration-300 transform active:scale-[0.995]">
                        Create Account
                    </button>
                </div>
            </form>

            {{-- Landlord CTA Callout Node --}}
            <div class="mt-3.5 flex items-center justify-between gap-2 bg-slate-50 border border-slate-200/60 rounded-xl p-2.5 shadow-inner">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center shrink-0 border border-blue-100">
                        <svg class="w-3.5 h-3.5 text-[#286CD2]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[#2A2523] text-[11px] font-bold uppercase tracking-wide">Listing a property?</p>
                        <p class="text-[#9B9F98] text-[10px] font-semibold">Apply as a landlord later.</p>
                    </div>
                </div>
                <a href="#" class="text-[10px] text-[#DF4D1B] font-black border border-[#DF4D1B]/30 bg-white rounded-md px-2.5 py-1 hover:bg-[#DF4D1B] hover:text-white transition-all duration-200 shrink-0">
                    Apply Later
                </a>
            </div>

            <p class="text-center text-sm text-[#9B9F98] font-medium mt-4">
                Already have an account?
                <a href="{{ route('login') }}" class="text-[#286CD2] font-bold hover:underline ml-1">Login</a>
            </p>

            <p class="text-center text-[10px] font-bold text-[#9B9F98]/70 mt-4 tracking-wider uppercase">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
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