@props(['show' => false])

<x-modal name="login-modal" :show="$show" maxWidth="md">
    <div class="relative p-8">
        {{-- Close Button --}}
        <button x-on:click="$dispatch('close')" class="absolute top-4 right-4 text-gray-400 hover:text-[#1A1A2E] transition-colors focus:outline-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Branding --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-9 h-9 rounded-lg bg-[#FF8A65] flex items-center justify-center shadow-md shadow-blue-500/10">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="text-[#1A1A2E] font-bold text-lg tracking-tight">Abanganan<span class="text-[#FF8A65]">Hub</span></span>
            </div>
            <h2 class="text-2xl font-black text-[#1A1A2E] tracking-tight">Welcome Back!</h2>
            <p class="text-[#9B9F98] text-sm font-medium mt-0.5">Login to continue to AbangananHub</p>
        </div>

        {{-- Session errors --}}
        @if (session('status'))
            <div class="mb-4 text-sm text-green-600 bg-green-50 rounded-lg px-4 py-3 border border-green-100 font-medium">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any() && !request()->routeIs('register'))
            <div class="mb-4 flex items-start gap-3 bg-[#DC2626]/10 border border-[#DC2626]/30 rounded-lg px-4 py-3">
                <svg class="w-4 h-4 text-[#DC2626] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <p class="text-sm text-[#DC2626] font-semibold">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="from_modal" value="1">

            {{-- Email --}}
            <div>
                <label for="login_email" class="block text-sm font-bold text-[#1A1A2E] mb-1">Email Address</label>
                <input id="login_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Enter your email"
                    class="w-full px-3 py-2.5 bg-slate-50/50 border border-slate-300 rounded-lg text-sm text-[#1A1A2E] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#FF8A65] focus:ring-4 focus:ring-[#FF8A65]/10" />
            </div>

            {{-- Password --}}
            <div x-data="{ showPassword: false }">
                <label for="login_password" class="block text-sm font-bold text-[#1A1A2E] mb-1">Password</label>
                <div class="relative">
                    <input id="login_password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Enter your password"
                        class="w-full px-3 py-2.5 bg-slate-50/50 border border-slate-300 rounded-lg text-sm text-[#1A1A2E] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#FF8A65] focus:ring-4 focus:ring-[#FF8A65]/10" />
                    <button type="button" x-on:click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#1A1A2E] transition-colors focus:outline-none">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" style="display: none;" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Forgot password + Remember me --}}
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 text-sm text-[#5E6968] font-semibold cursor-pointer select-none">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-[#FF8A65] focus:ring-[#FF8A65] transition-colors w-4 h-4" />
                    Remember me
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-[#FF8A65] hover:text-[#FF8A65] font-bold transition-colors hover:underline">
                        Forgot Password?
                    </a>
                @endif
            </div>

            {{-- Submit Button --}}
            <div class="pt-2">
                <button type="submit" class="w-full py-2.5 bg-[#FF8A65] hover:opacity-95 text-white font-bold text-sm rounded-lg shadow-md shadow-blue-600/10 transition-all duration-300 transform active:scale-[0.995]">
                    Login
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-[#9B9F98] font-medium mt-6">
            Don't have an account?
            <button x-on:click="$dispatch('close'); setTimeout(() => $dispatch('open-modal', 'register-modal'), 300)" class="text-[#FF8A65] font-bold hover:underline ml-1 focus:outline-none">Register here</button>
        </p>
    </div>
</x-modal>
