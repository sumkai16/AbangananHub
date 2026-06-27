<div class="rounded-2xl border border-red-100 bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-5 border-b border-red-50 pb-5">
        <h2 class="text-[15px] font-semibold text-slate-900">Danger zone</h2>
        <p class="mt-1 text-sm text-slate-500">Permanently delete your account and all associated data.</p>
    </div>

    <div class="flex items-start justify-between gap-6">
        <p class="max-w-sm text-sm text-slate-500">
            Once your account is deleted, all of your data will be permanently removed. This action cannot be undone.
        </p>
        <button type="button"
            class="inline-flex shrink-0 h-9 items-center rounded-lg border border-red-200 bg-white px-4 text-[13px] font-semibold text-red-600 transition hover:bg-red-50 active:scale-[0.98]"
            x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            Delete account
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-base font-semibold text-slate-900">Delete your account?</h2>
            <p class="mt-1.5 text-sm text-slate-500">Enter your password to confirm. This cannot be undone.</p>

            <div class="mt-5">
                <label for="password"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-slate-400">
                    Password
                </label>
                <input id="password" name="password" type="password" placeholder="Your current password" required
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 text-sm text-slate-800 outline-none transition
                           focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100 placeholder:text-slate-300">
                @if($errors->userDeletion->get('password'))
                    <span class="mt-1.5 block text-xs font-medium text-red-500">
                        {{ $errors->userDeletion->get('password')[0] }}
                    </span>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button type="button"
                    class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50"
                    x-on:click="$dispatch('close')">
                    Cancel
                </button>
                <button type="submit"
                    class="h-9 rounded-lg bg-red-500 px-4 text-[13px] font-semibold text-white transition hover:bg-red-600 active:scale-[0.98]">
                    Delete account
                </button>
            </div>
        </form>
    </x-modal>
</div>