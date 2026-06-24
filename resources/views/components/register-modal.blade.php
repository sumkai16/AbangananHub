@props(['show' => false])

<x-modal name="register-modal" :show="$show" maxWidth="md">
    <div class="relative p-6 sm:p-8">
        {{-- Close Button --}}
        <button x-on:click="$dispatch('close')" class="absolute top-4 right-4 text-gray-400 hover:text-[#1A1A2E] transition-colors focus:outline-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Branding --}}
        <div class="mb-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-[#286CD2] flex items-center justify-center shadow-md shadow-blue-500/10">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="text-[#222222] font-bold text-base tracking-tight">Abanganan<span class="text-[#61B2F0]">Hub</span></span>
            </div>
            <h2 class="text-xl font-black text-[#222222] tracking-tight">Create Your <span class="bg-gradient-to-r from-[#286CD2] to-[#61B2F0] bg-clip-text text-transparent">Account</span></h2>
            <p class="text-[#9B9F98] text-xs font-medium mt-0.5">Join AbangananHub and find your perfect place to stay.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="from_modal" value="1">

            {{-- First Name + Last Name Grid Layout --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="reg_first_name" class="block text-xs font-bold text-[#222222] mb-0.5">First Name</label>
                    <input id="reg_first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" placeholder="First name"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    @error('first_name')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="reg_last_name" class="block text-xs font-bold text-[#222222] mb-0.5">Last Name</label>
                    <input id="reg_last_name" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" placeholder="Last name"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    @error('last_name')
                        <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Email Address --}}
            <div>
                <label for="reg_email" class="block text-xs font-bold text-[#222222] mb-0.5">Email Address</label>
                <input id="reg_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Enter your email address"
                    class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                @error('email')
                    <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            {{-- Contact Number --}}
            <div>
                <label for="reg_contact_number" class="block text-xs font-bold text-[#222222] mb-0.5">Contact Number</label>
                <input id="reg_contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}" required autocomplete="tel" placeholder="Enter your contact number"
                    class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                @error('contact_number')
                    <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password Input Module --}}
            <div x-data="{ showPassword: false }">
                <label for="reg_password" class="block text-xs font-bold text-[#222222] mb-0.5">Password</label>
                <div class="relative">
                    <input id="reg_password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password" placeholder="Create a password"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    <button type="button" x-on:click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#222222] transition-colors focus:outline-none">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" style="display: none;" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm Password Input Module --}}
            <div x-data="{ showPassword: false }">
                <label for="reg_password_confirmation" class="block text-xs font-bold text-[#222222] mb-0.5">Confirm Password</label>
                <div class="relative">
                    <input id="reg_password_confirmation" :type="showPassword ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your password"
                        class="w-full px-3 py-2 bg-slate-50/50 border border-slate-300 rounded-lg text-xs text-[#222222] placeholder-[#9B9F98] transition-all focus:bg-white focus:outline-none focus:border-[#61B2F0] focus:ring-4 focus:ring-[#61B2F0]/10" />
                    <button type="button" x-on:click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9B9F98] hover:text-[#222222] transition-colors focus:outline-none">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" style="display: none;" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="mt-0.5 text-[11px] text-[#BD5434] font-semibold">{{ $message }}</p>
                @enderror
            </div>

            {{-- Action Submit Button --}}
            <div class="pt-2">
                <button type="submit" class="w-full py-2 bg-[#286CD2] hover:opacity-95 text-white font-bold text-xs rounded-lg shadow-md transition-all duration-300 transform active:scale-[0.995]">
                    Create Account
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-[#9B9F98] font-medium mt-4">
            Already have an account?
            <button x-on:click="$dispatch('close'); setTimeout(() => $dispatch('open-modal', 'login-modal'), 300)" class="text-[#286CD2] font-bold hover:underline ml-1 focus:outline-none">Login</button>
        </p>
    </div>
</x-modal>
