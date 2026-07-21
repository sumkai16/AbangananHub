<div class="rounded-2xl border border-[#EF4444]/20 bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-5 border-b border-[#EF4444]/20 pb-5 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#EF4444]/[0.07] flex items-center justify-center shrink-0">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </div>
        <div>
            <h2 class="text-[15px] font-semibold text-[#1F2937]">Danger zone</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">Permanently delete your account and all associated data.</p>
        </div>
    </div>

    <div class="flex items-start justify-between gap-6 flex-wrap">
        <p class="max-w-sm text-sm text-[#64748B]">
            Once your account is deleted, all of your data will be permanently removed. This action cannot be undone.
        </p>
        <button type="button"
            class="inline-flex shrink-0 h-9 items-center rounded-lg border border-[#EF4444]/25 bg-white px-4 text-[13px] font-semibold text-[#DC2626] transition hover:bg-[#EF4444]/[0.07] active:scale-[0.98]"
            x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            Delete account
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-base font-semibold text-[#1F2937]">Delete your account?</h2>
            <p class="mt-1.5 text-sm text-[#64748B]">Enter your password to confirm. This cannot be undone.</p>

            <div class="mt-5">
                <label for="password"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">
                    Password
                </label>
                <input id="password" name="password" type="password" placeholder="Your current password" required
                    class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                           focus:border-[#EF4444] focus:bg-white focus:ring-2 focus:ring-[#EF4444]/20 placeholder:text-[#64748B]/50">
                @if($errors->userDeletion->get('password'))
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">
                        {{ $errors->userDeletion->get('password')[0] }}
                    </span>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button type="button"
                    class="h-9 rounded-lg border border-[#E2E8F0] bg-white px-4 text-[13px] font-semibold text-[#64748B] transition hover:bg-[#EEF8F8]"
                    x-on:click="$dispatch('close')">
                    Cancel
                </button>
                <button type="submit"
                    class="h-9 rounded-lg bg-[#EF4444] px-4 text-[13px] font-semibold text-white transition hover:bg-[#EF4444] active:scale-[0.98]">
                    Delete account
                </button>
            </div>
        </form>
    </x-modal>
</div>