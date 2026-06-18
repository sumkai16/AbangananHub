<div class="bg-white border border-gray-100 rounded-2xl p-7 shadow-sm">
    <h2 class="text-[16px] font-extrabold text-[#1A1A2E] tracking-tight">Profile Information</h2>
    <p class="text-[13px] text-gray-400 font-medium mt-1 mb-6 leading-relaxed">
        Update your account's profile details, contact number, and email address.
    </p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="first_name"
                    class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                    First Name
                </label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}"
                    required autofocus autocomplete="given-name"
                    class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10 placeholder:text-gray-300">
                @error('first_name')
                    <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="last_name"
                    class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                    Last Name
                </label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}"
                    required autocomplete="family-name"
                    class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10 placeholder:text-gray-300">
                @error('last_name')
                    <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="email" class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                Email Address
            </label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                autocomplete="username"
                class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10 placeholder:text-gray-300">
            @error('email')
                <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">{{ $message }}</span>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                <div class="mt-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl">
                    <p class="text-[12.5px] font-medium text-amber-800">
                        Your email address is unverified.
                        <button form="send-verification"
                            class="underline font-bold text-amber-700 bg-transparent border-none cursor-pointer">
                            Resend verification email.
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-[12px] font-semibold text-emerald-600">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="mb-6">
            <label for="contact_number"
                class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                Contact Number
            </label>
            <input id="contact_number" name="contact_number" type="text"
                value="{{ old('contact_number', $user->contact_number) }}" placeholder="+63 912 345 6789"
                autocomplete="tel"
                class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10 placeholder:text-gray-300">
            @error('contact_number')
                <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                class="h-10 px-6 bg-[#286CD2] hover:bg-[#1e5ab8] text-white text-[13.5px] font-bold rounded-xl transition-colors shadow-sm">
                Save changes
            </button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-[12.5px] font-semibold text-emerald-600 flex items-center gap-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Saved successfully.
                </p>
            @endif
        </div>
    </form>
</div>