<div class="bg-white border border-gray-100 rounded-2xl p-7 shadow-sm">
    <h2 class="text-[16px] font-extrabold text-[#1A1A2E] tracking-tight">Update Password</h2>
    <p class="text-[13px] text-gray-400 font-medium mt-1 mb-6 leading-relaxed">
        Use a long, random password you don't use anywhere else.
    </p>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-4">
            <label for="update_password_current_password"
                class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                Current Password
            </label>
            <input id="update_password_current_password" name="current_password" type="password"
                autocomplete="current-password"
                class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10">
            @if($errors->updatePassword->get('current_password'))
                <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">
                    {{ $errors->updatePassword->get('current_password')[0] }}
                </span>
            @endif
        </div>

        <div class="mb-4">
            <label for="update_password_password"
                class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                New Password
            </label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10">
            @if($errors->updatePassword->get('password'))
                <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">
                    {{ $errors->updatePassword->get('password')[0] }}
                </span>
            @endif
        </div>

        <div class="mb-6">
            <label for="update_password_password_confirmation"
                class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                Confirm Password
            </label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                autocomplete="new-password"
                class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/10">
            @if($errors->updatePassword->get('password_confirmation'))
                <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">
                    {{ $errors->updatePassword->get('password_confirmation')[0] }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                class="h-10 px-6 bg-[#286CD2] hover:bg-[#1e5ab8] text-white text-[13.5px] font-bold rounded-xl transition-colors shadow-sm">
                Update password
            </button>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-[12.5px] font-semibold text-emerald-600 flex items-center gap-1.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Password updated.
                </p>
            @endif
        </div>
    </form>
</div>