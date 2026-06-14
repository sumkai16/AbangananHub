<x-guest-layout>
    {{-- Right panel top action --}}
    <x-slot name="rightTopAction">
        <p class="text-white/80 text-sm">
            Already have an account?
            <a href="{{ route('login') }}" class="text-[#61B2F0] font-semibold hover:underline">Login</a>
        </p>
    </x-slot>

    {{-- Right panel headline --}}
    <x-slot name="rightContent">
        <h1 class="text-3xl font-bold text-white leading-tight mb-4">
            A safer way to find<br>
            <span class="text-[#61B2F0]">rental accommodations.</span>
        </h1>
        <p class="text-white/80 text-sm mb-8">
            AbangananHub connects tenants with verified landlords and quality properties. Transparent, secure, and
            trusted.
        </p>
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-[#61B2F0] flex items-center justify-center shrink-0">
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
                <div class="w-9 h-9 rounded-full bg-[#BD5434] flex items-center justify-center shrink-0">
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
                <div class="w-9 h-9 rounded-full bg-[#5E6968] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-medium text-sm">Direct Communication</p>
                    <p class="text-white/70 text-xs">Chat directly with landlords in real-time.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-[#5E6968] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-medium text-sm">Secure Reservations</p>
                    <p class="text-white/70 text-xs">Reserve your next home safely on our platform.</p>
                </div>
            </div>
        </div>
    </x-slot>

    {{-- Left panel: form --}}
    <div class="flex flex-col justify-center px-8 py-10 max-w-md mx-auto w-full flex-1">

        {{-- Branding --}}
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
            <h2 class="text-2xl font-bold text-[#2A2523]">Create Your <span class="text-[#61B2F0]">Account</span></h2>
            <p class="text-[#9B9F98] text-sm mt-1">Join AbangananHub and find your perfect place to stay.</p>
        </div>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- First Name + Last Name --}}
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-[#2A2523] mb-1">First Name</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                        autofocus autocomplete="given-name" placeholder="Enter your first name"
                        class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                    @error('first_name')
                        <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-[#2A2523] mb-1">Last Name</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                        autocomplete="family-name" placeholder="Enter your last name"
                        class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                    @error('last_name')
                        <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Email --}}
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-[#2A2523] mb-1">Email Address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    placeholder="Enter your email address"
                    class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                @error('email')
                    <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                @enderror
            </div>

            {{-- Contact Number --}}
            <div class="mb-4">
                <label for="contact_number" class="block text-sm font-medium text-[#2A2523] mb-1">Contact Number</label>
                <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}"
                    required autocomplete="tel" placeholder="Enter your contact number"
                    class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                @error('contact_number')
                    <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-[#2A2523] mb-1">Password</label>
                <div class="relative">
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        placeholder="Create a password"
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

            {{-- Confirm Password --}}
            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-[#2A2523] mb-1">Confirm
                    Password</label>
                <div class="relative">
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password" placeholder="Confirm your password"
                        class="w-full px-3 py-2 border border-[#9B9F98] rounded-lg text-sm text-[#2A2523] placeholder-[#9B9F98] focus:outline-none focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]" />
                    <button type="button" onclick="togglePassword('password_confirmation', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#2A2523]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                @enderror
            </div>

            {{-- Security notice --}}
            <div class="flex items-start gap-3 bg-[#D7E8F3] rounded-lg p-3 mb-4">
                <div class="w-7 h-7 rounded-full bg-[#61B2F0] flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[#2A2523] text-xs font-semibold">Your security is important to us.</p>
                    <p class="text-[#5E6968] text-xs">We protect your data and ensure a safe experience.</p>
                </div>
            </div>

            {{-- Submit --}}
            <button type="submit"
                class="w-full py-2.5 bg-[#286CD2] hover:bg-[#3a79d8] text-white font-semibold text-sm rounded-lg transition-colors">
                Create Account
            </button>
        </form>

        {{-- Landlord callout --}}
        <div class="mt-6 flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#D7E8F3] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#61B2F0]" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <p class="text-[#2A2523] text-xs font-semibold">Planning to list a property?</p>
                    <p class="text-[#9B9F98] text-xs">You can apply as a landlord after creating your account.</p>
                </div>
            </div>
            <a href="#"
                class="text-xs text-[#BD5434] border border-[#BD5434] rounded-lg px-3 py-1.5 hover:bg-[#BD5434] hover:text-white transition-colors shrink-0 ml-3">
                Apply as Landlord Later
            </a>
        </div>

        <p class="text-center text-xs text-[#9B9F98] mt-6">© {{ date('Y') }} AbangananHub. All rights reserved.</p>
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