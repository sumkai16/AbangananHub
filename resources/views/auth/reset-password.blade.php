<x-guest-layout>
    {{-- Right panel top action --}}
    <x-slot name="rightTopAction">
        <a href="{{ route('login') }}" class="text-xs text-white/90 font-semibold hover:text-white transition-colors">
            Back to login
        </a>
    </x-slot>

    {{-- Right panel headline & feature area --}}
    <x-slot name="rightContent">
        <div class="max-w-xl mx-auto">
            <h1 class="text-3xl font-black text-white leading-tight tracking-tight mb-3 drop-shadow-sm">
                Choose a new<br>
                <span class="italic text-[#C9A84C]">password.</span>
            </h1>
            <p class="text-white/90 font-medium text-xs leading-relaxed mb-6 drop-shadow">
                Almost done. Set a new password to secure your AbangananHub account.
            </p>
        </div>
    </x-slot>

    {{-- Left panel: form --}}
    <div class="w-full min-h-screen bg-[#F7F8FC] flex flex-col items-center px-4 py-10 lg:py-14">
        <div class="w-full max-w-sm sm:max-w-md bg-white rounded-2xl border border-[#E2E4EC]/80 p-6 lg:p-8 shadow-xl shadow-[#060D26]/[0.04] transition-all duration-300">

            {{-- Branding --}}
            <div class="mb-5">
                <a href="/" class="flex items-center gap-2 mb-4 group inline-flex">
                    <div class="w-8 h-8 rounded-lg bg-[#060D26] flex items-center justify-center shadow-md shadow-[#060D26]/10 transition-transform duration-300 group-hover:scale-105">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span class="text-[#060D26] font-bold text-base tracking-tight">Abanganan<span class="text-[#060D26]">Hub</span></span>
                </a>
                <h2 class="text-lg sm:text-xl font-normal text-[#060D26] tracking-tight">Reset your password</h2>
                <p class="text-[#5B6A8E] text-xs font-medium mt-0.5">Choose a strong password you haven't used before.</p>
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

            <form method="POST" action="{{ route('password.store') }}" class="space-y-3.5">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                {{-- Email Address --}}
                <div>
                    <label for="email" class="block text-xs font-bold text-[#060D26] mb-1">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" placeholder="Enter your email"
                        class="w-full px-4 py-2.5 bg-white border border-[#E2E4EC] rounded-xl text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#C9A84C] focus:ring-2 focus:ring-[#C9A84C]/20 focus:outline-none transition-all" />
                </div>

                {{-- New Password --}}
                <div>
                    <label for="password" class="block text-xs font-bold text-[#060D26] mb-1">New Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Create a new password"
                            class="w-full px-4 py-2.5 bg-white border border-[#E2E4EC] rounded-xl text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#C9A84C] focus:ring-2 focus:ring-[#C9A84C]/20 focus:outline-none transition-all" />
                        <button type="button" onclick="togglePassword('password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5B6A8E] hover:text-[#060D26] transition-colors focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-[#060D26] mb-1">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your new password"
                        class="w-full px-4 py-2.5 bg-white border border-[#E2E4EC] rounded-xl text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#C9A84C] focus:ring-2 focus:ring-[#C9A84C]/20 focus:outline-none transition-all" />
                </div>

                {{-- Action Submission Button --}}
                <div class="pt-1">
                    <button type="submit" class="w-full bg-[#060D26] text-[#F7F4ED] font-bold py-3 rounded-xl hover:brightness-105 active:scale-[0.99] transition-all shadow-md shadow-[#060D26]/20 text-[15px]">
                        Reset Password
                    </button>
                </div>
            </form>

            <p class="text-center text-[10px] font-bold text-[#5B6A8E]/70 mt-5 tracking-wider uppercase">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
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
