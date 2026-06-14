<x-guest-layout>
    {{-- Right panel top action --}}
    <x-slot name="rightTopAction">
        {{-- Empty on login page — nothing needed top-right --}}
    </x-slot>

    {{-- Right panel headline --}}
    <x-slot name="rightContent">
        <h1 class="text-3xl font-bold text-white leading-tight mb-4">
            Your trusted platform<br>for verified rental<br>
            <span class="text-[#61B2F0]">properties.</span>
        </h1>
        <p class="text-white/80 text-sm mb-8">
            We help you find safe, affordable, and verified accommodations with peace of mind.
        </p>
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-medium text-sm">Verified Landlords</p>
                    <p class="text-white/70 text-xs">All landlords are verified for your safety.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-medium text-sm">Quality Listings</p>
                    <p class="text-white/70 text-xs">Every property is reviewed before it goes live.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-medium text-sm">Secure & Transparent</p>
                    <p class="text-white/70 text-xs">We promote transparency, accountability, and trust.</p>
                </div>
            </div>
        </div>
    </x-slot>

    {{-- Left panel: form --}}
    <div class="flex flex-col justify-center px-8 py-10 max-w-md mx-auto w-full" style="min-height: 100vh;">
        {{--Branding --}}
        <div class="mb-8">
            <a href="/" class="flex items-center gap-2 mb-6">
                <div class="w-9 h-9 rounded-lg bg-[#61B2F0] flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="text-[#2A2523] font-bold text-lg">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
            </a>
            <h2 class="text-2xl font-bold text-[#2A2523]">Welcome Back!</h2>
            <p class="text-[#9B9F98] text-sm mt-1">Login to continue to AbangananHub</p>
        </div>

        {{-- Session errors --}}
        @if (session('status'))
            <div class="mb-4 text-sm text-green-600 bg-green-50 rounded-lg px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Email --}}
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-[#2A2523] mb-1">Email Address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    placeholder="Enter your email"
                    class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                @error('email')
                    <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="mb-2">
                <label for="password" class="block text-sm font-medium text-[#2A2523] mb-1">Password</label>
                <div class="relative">
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        placeholder="Enter your password"
                        class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                    <button type="button" onclick="togglePassword('password', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#2A2523]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                @enderror
            </div>

            {{-- Forgot password + Remember me --}}
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center gap-2 text-sm text-[#5E6968]">
                    <input type="checkbox" name="remember"
                        class="rounded border-[#9B9F98] text-[#61B2F0] focus:ring-[#61B2F0]" />
                    Remember me
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-[#61B2F0] hover:underline">
                        Forgot Password?
                    </a>
                @endif
            </div>

            {{-- Submit --}}
            <button type="submit"
                class="w-full py-2.5 bg-[#286CD2] hover:bg-[#3a79d8] text-white font-semibold text-sm rounded-lg transition-colors">
                Login
            </button>
        </form>

        <p class="text-center text-sm text-[#9B9F98] mt-6">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-[#DF4D1B] font-semibold hover:underline">Register here</a>
        </p>

        <p class="text-center text-xs text-[#9B9F98] mt-8">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
    </div>
</x-guest-layout>

<script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' :