<div class="rounded-2xl border border-slate-200/80 bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-7 border-b border-slate-100 pb-6">
        <h2 class="text-[15px] font-semibold text-slate-900">Personal information</h2>
        <p class="mt-1 text-sm text-slate-500">Update your name, email, and contact details.</p>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            {{-- First name --}}
            <div>
                <label for="first_name"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-slate-400">
                    First name
                </label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}"
                    required autofocus autocomplete="given-name"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 text-sm text-slate-800 outline-none transition
                           focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/15 placeholder:text-slate-300">
                @error('first_name')
                    <span class="mt-1.5 block text-xs font-medium text-red-500">{{ $message }}</span>
                @enderror
            </div>

            {{-- Last name --}}
            <div>
                <label for="last_name"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-slate-400">
                    Last name
                </label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}"
                    required autocomplete="family-name"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 text-sm text-slate-800 outline-none transition
                           focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/15 placeholder:text-slate-300">
                @error('last_name')
                    <span class="mt-1.5 block text-xs font-medium text-red-500">{{ $message }}</span>
                @enderror
            </div>

            {{-- Email --}}
            <div class="sm:col-span-2">
                <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-slate-400">
                    Email address
                </label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                    autocomplete="username"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 text-sm text-slate-800 outline-none transition
                           focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/15 placeholder:text-slate-300">
                @error('email')
                    <span class="mt-1.5 block text-xs font-medium text-red-500">{{ $message }}</span>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                    <div class="mt-2.5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="mt-0.5 shrink-0 text-amber-500">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-xs text-amber-700">
                            Email not verified. &nbsp;
                            <button form="send-verification" class="font-semibold underline underline-offset-2">
                                Resend link
                            </button>
                        </p>
                    </div>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-xs font-medium text-emerald-600">Verification link sent to your inbox.</p>
                    @endif
                @endif
            </div>

            {{-- Contact number --}}
            <div class="sm:col-span-2">
                <label for="contact_number"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-slate-400">
                    Contact number
                </label>
                <input id="contact_number" name="contact_number" type="text"
                    value="{{ old('contact_number', $user->contact_number) }}" placeholder="+63 912 345 6789"
                    autocomplete="tel"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 text-sm text-slate-800 outline-none transition
                           focus:border-[#286CD2] focus:bg-white focus:ring-2 focus:ring-[#286CD2]/15 placeholder:text-slate-300">
                @error('contact_number')
                    <span class="mt-1.5 block text-xs font-medium text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-7 flex items-center gap-4 border-t border-slate-100 pt-6">
            <button type="submit"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#286CD2] px-5 text-[13px] font-semibold text-white transition hover:bg-[#1e55a8] active:scale-[0.98]">
                Save changes
            </button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Saved
                </p>
            @endif
        </div>
    </form>
</div>