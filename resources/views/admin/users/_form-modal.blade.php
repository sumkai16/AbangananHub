{{--
    Create/edit user as a modal. Needs `userFormOpen` in an ancestor Alpine scope; the page opens it on load
    when a validation redirect carries old('_form') (see admin/users/index and show).
--}}
@php($user = $user ?? null)
<template x-teleport="body">
    <div x-show="userFormOpen" x-cloak x-on:keydown.escape.window="userFormOpen = false"
        class="fixed inset-0 z-[200] overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div @click="userFormOpen = false" class="fixed inset-0 bg-black/40"></div>
            <div x-show="userFormOpen" x-transition role="dialog" aria-modal="true" aria-labelledby="user-form-title"
                class="relative w-full max-w-xl bg-white rounded-2xl shadow-xl">
                <div class="px-5 sm:px-7 py-5 border-b border-[#E2E4EC] flex items-start justify-between gap-4">
                    <div>
                        <h2 id="user-form-title" class="text-[18px] font-normal text-[#060D26] tracking-tight">{{ $user ? 'Edit User' : 'Create User' }}</h2>
                        <p class="text-[13px] text-[#94A3B8] mt-0.5">{{ $user ? $user->email : 'Add a new account to the system.' }}</p>
                    </div>
                    <button type="button" @click="userFormOpen = false" aria-label="Close"
                        class="h-9 w-9 -mr-2 -mt-1 flex items-center justify-center rounded-lg text-[#5B6A8E] hover:text-[#060D26] cursor-pointer">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                @include('admin.users._form', ['user' => $user, 'modal' => true])
            </div>
        </div>
    </div>
</template>
