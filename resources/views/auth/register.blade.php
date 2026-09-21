<x-guest-layout>
    <x-slot name="rightContent">
        @include('auth.partials.brand-panel')
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('home') }}"
            class="group inline-flex items-center gap-2 h-10 pl-3 pr-4 rounded-full border border-[#E2E4EC] text-[14px] font-semibold text-[#5B6A8E] transition-colors duration-200 hover:border-[#060D26]/40 hover:text-[#060D26] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
            <svg class="w-4 h-4 transition-transform duration-200 group-hover:-translate-x-0.5 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Back
        </a>
    </x-slot>
    <div class="flex-1 flex flex-col justify-center w-full max-w-lg mx-auto px-6 sm:px-8 py-10">

        {{-- Branding --}}
        <a href="/" class="group inline-flex items-center gap-2 self-start">
            <div class="w-9 h-9 rounded-lg bg-[#060D26] flex items-center justify-center transition-transform duration-300 group-hover:scale-105 motion-reduce:transition-none">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
            </div>
            <span class="text-[#060D26] font-bold text-[17px] tracking-tight">AbangananHub</span>
        </a>

        <h2 class="mt-8 font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[28px] sm:text-[32px] font-extrabold text-[#060D26] tracking-tight leading-tight">Create your account</h2>
        <p class="mt-1.5 text-[15px] text-[#5B6A8E]">Join AbangananHub and find your place to stay.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-7 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">First name</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                        autofocus autocomplete="given-name" placeholder="First name"
                        class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                    @error('first_name')
                        <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">Last name</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                        autocomplete="family-name" placeholder="Last name"
                        class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                    @error('last_name')
                        <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="email" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    placeholder="you@example.com"
                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                @error('email')
                    <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="contact_number" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">Contact number</label>
                <input id="contact_number" type="tel" name="contact_number" value="{{ old('contact_number') }}"
                    required autocomplete="tel" inputmode="tel" placeholder="09XX XXX XXXX"
                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                @error('contact_number')
                    <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                            placeholder="Create a password"
                            class="w-full pl-4 pr-12 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                        <button type="button" onclick="togglePassword('password', this)" aria-label="Show password"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-lg text-[#5B6A8E] hover:text-[#060D26] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block font-['Plus_Jakarta_Sans',_Inter,_sans-serif] text-[13px] font-bold text-[#060D26] mb-1.5">Confirm password</label>
                    <div class="relative">
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                            autocomplete="new-password" placeholder="Repeat password"
                            class="w-full pl-4 pr-12 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors" />
                        <button type="button" onclick="togglePassword('password_confirmation', this)" aria-label="Show password"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-lg text-[#5B6A8E] hover:text-[#060D26] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p class="mt-1 text-[12.5px] text-[#EF4444] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <p class="text-[13px] leading-snug text-[#5B6A8E]">
                By creating an account you agree to our
                <a href="{{ route('terms') }}" class="font-semibold text-[#060D26] hover:text-[#B35A3D] hover:underline" target="_blank" rel="noopener">Terms</a>
                and
                <a href="{{ route('privacy') }}" class="font-semibold text-[#060D26] hover:text-[#B35A3D] hover:underline" target="_blank" rel="noopener">Privacy Policy</a>.
            </p>

            <button type="submit"
                class="w-full font-['Plus_Jakarta_Sans',_Inter,_sans-serif] bg-[#FF8A66] text-[#060D26] font-bold py-3.5 rounded-xl hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                Create account
            </button>
        </form>

        <x-social-login-buttons />

        <p class="mt-6 text-center text-[14px] text-[#5B6A8E]">
            Already have an account?
            <a href="{{ route('login') }}" class="ml-1 font-bold text-[#060D26] hover:text-[#B35A3D] hover:underline transition-colors">Log in</a>
        </p>

        <p class="mt-3 text-center text-[13px] text-[#5B6A8E]">
            Listing a property? Create an account first, then apply as a landlord.
        </p>
    </div>
</x-guest-layout>

<script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.innerHTML = isPassword
            ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`
            : `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`;
    }
</script>
