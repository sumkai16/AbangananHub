<div class="bg-white border border-red-100 rounded-2xl p-7 shadow-sm" style="border-left: 3px solid #EF4444;">
    <div class="flex gap-4 items-start mb-5">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 shrink-0">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[16px] font-extrabold text-[#1A1A2E] tracking-tight">Delete Account</h2>
            <p class="text-[13px] text-gray-400 font-medium mt-1 leading-relaxed">
                Once your account is deleted, all data is permanently removed. Download anything you need before
                proceeding.
            </p>
        </div>
    </div>

    <div class="flex items-center gap-2.5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl mb-6">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#EF4444" stroke-width="2.5"
            class="shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-[12.5px] font-bold text-red-700">This action is permanent and cannot be undone.</span>
    </div>

    <button type="button"
        class="h-10 px-6 bg-red-500 hover:bg-red-600 text-white text-[13px] font-extrabold uppercase tracking-wide rounded-xl transition-colors shadow-sm"
        x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        Delete Account
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-7">
            @csrf
            @method('delete')

            <div class="flex gap-4 items-start mb-5">
                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 shrink-0">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-[16px] font-extrabold text-[#1A1A2E] tracking-tight">Are you sure?</h2>
                    <p class="text-[13px] text-gray-400 font-medium mt-1 leading-relaxed">
                        Enter your password to permanently delete your account. This cannot be undone.
                    </p>
                </div>
            </div>

            <div class="mb-6">
                <label for="password"
                    class="block text-[11.5px] font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                    Account Password
                </label>
                <input id="password" name="password" type="password" placeholder="Enter your password to confirm"
                    required
                    class="w-full h-11 px-4 bg-gray-50 border border-gray-200 rounded-xl text-[13.5px] font-medium text-[#1A1A2E] outline-none transition focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-400/10 placeholder:text-gray-300">
                @if($errors->userDeletion->get('password'))
                    <span class="block mt-1.5 text-[11.5px] font-semibold text-red-500">
                        {{ $errors->userDeletion->get('password')[0] }}
                    </span>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <button type="button"
                    class="h-10 px-5 bg-white border border-gray-200 text-[13.5px] font-semibold text-gray-600 rounded-xl hover:bg-gray-50 transition-colors"
                    x-on:click="$dispatch('close')">
                    Cancel
                </button>
                <button type="submit"
                    class="h-10 px-5 bg-red-500 hover:bg-red-600 text-white text-[13px] font-extrabold uppercase tracking-wide rounded-xl transition-colors">
                    Delete Account
                </button>
            </div>
        </form>
    </x-modal>
</div>