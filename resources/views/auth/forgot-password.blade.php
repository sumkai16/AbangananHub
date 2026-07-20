<x-guest-layout>
    {{-- Right panel top action --}}
    <x-slot name="rightTopAction">
        <a href="{{ route('login') }}" class="text-xs text-white/90 font-semibold hover:text-white transition-colors">
            Back to login
        </a>
    </x-slot>

    {{-- Right panel headline & feature area --}}
    <x-slot name="rightContent">
        <div class="bg-slate-950/25 backdrop-blur-lg border border-white/15 rounded-2xl p-6 shadow-2xl max-w-xl mx-auto transform transition-all duration-300 hover:border-white/25">
            <h1 class="text-3xl font-black text-white leading-tight tracking-tight mb-3 drop-shadow-sm">
                Forgot your password?<br>
                <span class="bg-gradient-to-r from-[#69D2C6] via-[#2AA7A1] to-[#156F8C] bg-clip-text text-transparent font-black">No problem.</span>
            </h1>
            <p class="text-white/90 font-medium text-xs leading-relaxed mb-6 drop-shadow">
                Enter the email address linked to your account and we'll send you a secure link to reset your password.
            </p>
        </div>
    </x-slot>

    {{-- Left panel: form --}}
    <div class="w-full min-h-screen lg:h-screen bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-100 flex flex-col justify-center items-center px-4 py-6 lg:py-2 overflow-y-auto lg:overflow-hidden">
        <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0]/80 p-6 sm:p-8 shadow-xl shadow-[#1F2937]/[0.04] transition-all duration-300">

            {{-- Branding --}}
            <div class="mb-5">
                <a href="/" class="flex items-center gap-2 mb-4 group inline-flex">
                    <div class="w-8 h-8 rounded-lg bg-[#2AA7A1] flex items-center justify-center shadow-md shadow-[#2AA7A1]/10 transition-transform duration-300 group-hover:scale-105">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-[#156F8C] font-bold text-base tracking-tight">Abanganan<span class="text-[#156F8C]">Hub</span></span>
                </a>
                <h2 class="text-xl font-black text-[#156F8C] tracking-tight">Reset your password</h2>
                <p class="text-[#64748B] text-xs font-medium mt-0.5">We'll email you a link to choose a new one.</p>
            </div>

            {{-- Session alerts --}}
            @if ($errors->any())
                <div class="mb-4 flex items-start gap-3 bg-[#EF4444]/10 border border-[#EF4444]/30 rounded-lg px-4 py-2.5">
                    <svg class="w-4 h-4 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p class="text-xs text-[#EF4444] font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-3.5">
                @csrf

                {{-- Email Address --}}
                <div>
                    <label for="email" class="block text-xs font-bold text-[#156F8C] mb-1">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Enter your email"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#156F8C] placeholder-[#64748B] transition-all focus:bg-white focus:outline-none focus:border-[#2AA7A1] focus:ring-4 focus:ring-[#2AA7A1]/10" />
                </div>

                {{-- Action Submission Button --}}
                <div class="pt-1">
                    <button type="submit" class="w-full py-2 bg-[#2AA7A1] hover:opacity-95 text-white font-bold text-xs rounded-lg shadow-md shadow-[#2AA7A1]/10 transition-all duration-300 transform active:scale-[0.995]">
                        Email Password Reset Link
                    </button>
                </div>
            </form>

            <p class="text-center text-xs text-[#64748B] font-medium mt-5">
                Remembered your password?
                <a href="{{ route('login') }}" class="text-[#156F8C] font-bold hover:underline ml-1">Login here</a>
            </p>

            <p class="text-center text-[10px] font-bold text-[#64748B]/70 mt-5 tracking-wider uppercase">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
        </div>
    </div>
</x-guest-layout>
