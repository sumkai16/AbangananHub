<div class="rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-7 border-b border-[#E2E8F0] pb-6 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[15px] font-semibold text-[#1F2937]">Password</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">Use a long, unique password you don't reuse elsewhere.</p>
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" x-data="{ pw: '', confirm: '' }">
        @csrf
        @method('put')

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_200px] gap-8">
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
                        <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">
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
                    <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                        x-model="pw"
                        class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                               focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                    @if($errors->updatePassword->get('password'))
                        <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">
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
                        autocomplete="new-password" x-model="confirm"
                        class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                               focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                    @if($errors->updatePassword->get('password_confirmation'))
                        <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">
                            {{ $errors->updatePassword->get('password_confirmation')[0] }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Live requirements checklist --}}
            <div class="bg-[#F7FCFC] rounded-2xl p-4 h-fit">
                <p class="text-[11px] font-bold uppercase tracking-widest text-[#64748B] mb-3">Requirements</p>
                <ul class="flex flex-col gap-2.5">
                    <li class="flex items-center gap-2 text-[12.5px]" :class="pw.length >= 8 ? 'text-[#1F2937] font-semibold' : 'text-[#64748B]'">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center shrink-0 transition-colors"
                            :class="pw.length >= 8 ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]'">
                            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        At least 8 characters
                    </li>
                    <li class="flex items-center gap-2 text-[12.5px]" :class="/[0-9]/.test(pw) && /[a-zA-Z]/.test(pw) ? 'text-[#1F2937] font-semibold' : 'text-[#64748B]'">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center shrink-0 transition-colors"
                            :class="/[0-9]/.test(pw) && /[a-zA-Z]/.test(pw) ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]'">
                            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        Letters &amp; numbers
                    </li>
                    <li class="flex items-center gap-2 text-[12.5px]" :class="confirm.length > 0 && confirm === pw ? 'text-[#1F2937] font-semibold' : 'text-[#64748B]'">
                        <span class="w-4 h-4 rounded-full flex items-center justify-center shrink-0 transition-colors"
                            :class="confirm.length > 0 && confirm === pw ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]'">
                            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        Passwords match
                    </li>
                </ul>
                <p class="text-[11px] text-[#64748B] leading-relaxed mt-4 pt-4 border-t border-[#E2E8F0]">
                    Avoid reusing passwords from other sites you use.
                </p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-7 flex items-center gap-4 border-t border-[#E2E8F0] pt-6">
            <button type="submit"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#2AA7A1] px-5 text-[13px] font-semibold text-white transition hover:brightness-95 active:scale-[0.98]">
                Update password
            </button>
        </div>
    </form>
</div>