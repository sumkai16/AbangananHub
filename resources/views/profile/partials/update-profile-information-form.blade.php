<div class="rounded-2xl ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)] bg-white p-6 sm:p-8">

    {{-- Section header --}}
    <div class="mb-7 border-b border-[#E2E4EC] pb-6 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#ECEEF6] flex items-center justify-center shrink-0">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[15px] font-normal text-[#060D26]">Personal information</h2>
            <p class="mt-0.5 text-sm text-[#5B6A8E]">Update your name, email, and contact details.</p>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            {{-- First name --}}
            <div>
                <label for="first_name"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    First name
                </label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}"
                    required autofocus autocomplete="given-name"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26] outline-none transition
                           focus:border-[#C9A84C] focus:bg-white focus:ring-2 focus:ring-[#C9A84C]/15 placeholder:text-[#5B6A8E]/50">
                @error('first_name')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror
            </div>

            {{-- Last name --}}
            <div>
                <label for="last_name"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    Last name
                </label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}"
                    required autocomplete="family-name"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26] outline-none transition
                           focus:border-[#C9A84C] focus:bg-white focus:ring-2 focus:ring-[#C9A84C]/15 placeholder:text-[#5B6A8E]/50">
                @error('last_name')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror
            </div>

            {{-- Email --}}
            <div class="sm:col-span-2">
                <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    Email address
                </label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                    autocomplete="username"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26] outline-none transition
                           focus:border-[#C9A84C] focus:bg-white focus:ring-2 focus:ring-[#C9A84C]/15 placeholder:text-[#5B6A8E]/50">
                @error('email')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                    <div class="mt-2.5 flex items-start gap-2 rounded-lg border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] px-3.5 py-2.5">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="mt-0.5 shrink-0 text-[#B45309]">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-xs text-[#B45309]">
                            Email not verified. &nbsp;
                            <button form="send-verification" class="font-semibold underline underline-offset-2">
                                Resend link
                            </button>
                        </p>
                    </div>
                @endif
            </div>

            {{-- Contact number --}}
            <div class="sm:col-span-2">
                <label for="contact_number"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    Contact number
                </label>
                <input id="contact_number" name="contact_number" type="text"
                    value="{{ old('contact_number', $user->contact_number) }}" placeholder="+63 912 345 6789"
                    autocomplete="tel"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26] outline-none transition
                           focus:border-[#C9A84C] focus:bg-white focus:ring-2 focus:ring-[#C9A84C]/15 placeholder:text-[#5B6A8E]/50">
                @error('contact_number')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror
            </div>

            {{-- Bio --}}
            <div class="sm:col-span-2">
                <label for="bio" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    Bio <span class="normal-case font-normal text-[#5B6A8E]/70">(Optional)</span>
                </label>
                <textarea id="bio" name="bio" rows="3" maxlength="1000" placeholder="Tell others a bit about yourself…"
                    class="w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 py-2.5 text-sm text-[#060D26] outline-none transition resize-none
                           focus:border-[#C9A84C] focus:bg-white focus:ring-2 focus:ring-[#C9A84C]/15 placeholder:text-[#5B6A8E]/50">{{ old('bio', $user->bio) }}</textarea>
                @error('bio')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror
            </div>

            {{-- Profile visibility --}}
            <div class="sm:col-span-2">
                <label for="profile_visibility"
                    class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                    Profile visibility
                </label>
                @php
                    $visibility = old('profile_visibility', $user->profile_visibility);
                    $visibilityOptions = [
                        'private' => 'Private — only you can see your profile',
                        'landlords_only' => "Landlords only — visible to landlords you've reserved with",
                        'public' => 'Public — visible to everyone',
                    ];
                @endphp
                <x-styled-select name="profile_visibility" :options="$visibilityOptions" :selected="$visibility"
                    class="h-10 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26]" />
                @error('profile_visibility')
                    <span class="mt-1.5 block text-xs font-medium text-[#DC2626]">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-7 flex items-center gap-4 border-t border-[#E2E4EC] pt-6">
            <button type="submit"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#060D26] px-5 text-[13px] font-semibold text-white transition hover:brightness-95 active:scale-[0.98]">
                Save changes
            </button>
        </div>
    </form>
</div>