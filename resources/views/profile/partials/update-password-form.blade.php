<div class="rounded-2xl border border-[#E2E8F0]/80 bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-7 border-b border-[#E2E8F0] pb-6">
        <h2 class="text-[15px] font-semibold text-[#1F2937]">Password</h2>
        <p class="mt-1 text-sm text-[#64748B]">Use a long, unique password you don't reuse elsewhere.</p>
    </div>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="flex flex-col gap-5">
            {{-- Current password --}}
            <div>
                <label for="update_password_current_password"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">
                    Current password
                </label>
                <input id="update_password_current_password" name="current_password" type="password"
                    autocomplete="current-password" class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                           focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                @if($errors->updatePassword->get('current_password'))
                    <span class="mt-1.5 block text-xs font-medium text-red-500">
                        {{ $errors->updatePassword->get('current_password')[0] }}
                    </span>
                @endif
            </div>

            {{-- New password --}}
            <div>
                <label for="update_password_password"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">
                    New password
                </label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password" class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                           focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                @if($errors->updatePassword->get('password'))
                    <span class="mt-1.5 block text-xs font-medium text-red-500">
                        {{ $errors->updatePassword->get('password')[0] }}
                    </span>
                @endif
            </div>

            {{-- Confirm password --}}
            <div>
                <label for="update_password_password_confirmation"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">
                    Confirm password
                </label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                    autocomplete="new-password" class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                           focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                @if($errors->updatePassword->get('password_confirmation'))
                    <span class="mt-1.5 block text-xs font-medium text-red-500">
                        {{ $errors->updatePassword->get('password_confirmation')[0] }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-7 flex items-center gap-4 border-t border-[#E2E8F0] pt-6">
            <button type="submit"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#2AA7A1] px-5 text-[13px] font-semibold text-white transition hover:brightness-95 active:scale-[0.98]">
                Update password
            </button>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Updated
                </p>
            @endif
        </div>
    </form>
</div>