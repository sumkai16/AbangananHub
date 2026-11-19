@extends('layouts.signup')

@section('content')
    @php
        // Open on the step that holds a server-side error (a taken email caught on submit, a weak password).
        $startStep = $errors->hasAny(['email', 'password', 'password_confirmation']) ? 2 : 1;
        $label = 'block font-jakarta text-[13px] font-bold text-[#060D26] mb-1.5';
        $input = 'w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[16px] sm:text-[15px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors';
        $err = 'mt-1 text-[12.5px] text-[#EF4444] font-semibold';
        $eye = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>';
        $eyeBtn = 'absolute right-1.5 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center rounded-lg text-[#5B6A8E] hover:text-[#060D26] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40';
    @endphp

    <div class="w-full max-w-lg mx-auto px-6 sm:px-8 pt-6 pb-12"
        x-data="registerForm({ emailUrl: @js(route('register.emailAvailable')), steps: 2, step: {{ $startStep }} })">

        <h2 class="mt-6 font-jakarta text-[28px] sm:text-[32px] font-extrabold text-[#060D26] tracking-tight leading-tight">Sign up as a landlord</h2>
        <p class="mt-1.5 text-[15px] text-[#5B6A8E]">Four short steps, so tenants can trust who they rent from.</p>

        <x-landlord-signup-steps />

        <form method="POST" action="{{ route('register') }}" class="mt-7" novalidate x-on:submit="onSubmit($event)">
            @csrf
            <input type="hidden" name="as_landlord" value="1">

            {{-- ── Step 1: About you ── --}}
            <div data-step="1" x-show="step === 1" class="space-y-4">
                <div>
                    <h3 class="font-jakarta text-[18px] font-extrabold text-[#060D26]">About you</h3>
                    <p class="mt-0.5 text-[13.5px] text-[#5B6A8E]">Use your real name. It has to match the ID you verify with.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="{{ $label }}">First name</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="255"
                            autocomplete="given-name" placeholder="First name" class="{{ $input }}" />
                        @error('first_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="last_name" class="{{ $label }}">Last name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="255"
                            autocomplete="family-name" placeholder="Last name" class="{{ $input }}" />
                        @error('last_name')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="contact_number" class="{{ $label }}">Contact number</label>
                    <input id="contact_number" type="tel" name="contact_number" value="{{ old('contact_number') }}" required
                        autocomplete="tel" inputmode="tel" placeholder="09XX XXX XXXX"
                        pattern="[0-9+\(\)\s\-]{7,20}" data-error-tag="Invalid number" title="Enter a valid phone number, for example 0917 123 4567"
                        class="{{ $input }}" />
                    @error('contact_number')<p class="{{ $err }}">{{ $message }}</p>@enderror
                </div>

                <button type="submit"
                    class="w-full font-jakarta bg-[#FF8A66] text-[#060D26] font-bold py-3.5 rounded-xl hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                    Next
                </button>
            </div>

            {{-- ── Step 2: Account ── --}}
            <div data-step="2" x-show="step === 2" x-cloak class="space-y-4">
                <div>
                    <h3 class="font-jakarta text-[18px] font-extrabold text-[#060D26]">Set up your account</h3>
                    <p class="mt-0.5 text-[13.5px] text-[#5B6A8E]">This is how you will log in. Next you verify your ID and face.</p>
                </div>

                <div>
                    <label for="email" class="{{ $label }}">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username"
                        placeholder="you@example.com" class="{{ $input }}"
                        x-on:input="onEmailInput()" x-on:blur="onEmailBlur()" />
                    @error('email')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[12.5px] text-[#5B6A8E]" x-show="emailState === 'checking'" x-cloak aria-live="polite">Checking this email…</p>
                    <p class="mt-1 text-[12.5px] font-semibold text-[#15803D]" x-show="emailState === 'ok'" x-cloak aria-live="polite">This email is available.</p>
                    <p class="mt-1 text-[12.5px] font-semibold text-[#EF4444]" x-show="emailState === 'taken'" x-cloak aria-live="polite">
                        This email is already registered.
                        <a href="{{ route('login') }}" class="underline text-[#060D26] hover:text-[#B35A3D]">Log in instead</a>
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="{{ $label }}">Password</label>
                        <div class="relative">
                            <input id="password" type="password" name="password" minlength="8" data-strength required autocomplete="new-password"
                                placeholder="Create a password" class="{{ $input }} !pl-4 !pr-12" />
                            <button type="button" onclick="togglePassword('password', this)" aria-label="Show password" class="{{ $eyeBtn }}">{!! $eye !!}</button>
                        </div>
                        @error('password')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirm password</label>
                        <div class="relative">
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                placeholder="Repeat password" class="{{ $input }} !pl-4 !pr-12" />
                            <button type="button" onclick="togglePassword('password_confirmation', this)" aria-label="Show password" class="{{ $eyeBtn }}">{!! $eye !!}</button>
                        </div>
                        @error('password_confirmation')<p class="{{ $err }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                <p class="text-[13px] leading-snug text-[#5B6A8E]">
                    By creating an account you agree to our
                    <a href="{{ route('terms') }}" class="font-semibold text-[#060D26] hover:text-[#B35A3D] hover:underline" target="_blank" rel="noopener">Terms</a>
                    and
                    <a href="{{ route('privacy') }}" class="font-semibold text-[#060D26] hover:text-[#B35A3D] hover:underline" target="_blank" rel="noopener">Privacy Policy</a>.
                </p>

                <div class="flex gap-3">
                    <button type="button" x-on:click="back()"
                        class="shrink-0 px-5 font-jakarta border border-[#E2E4EC] bg-white text-[#060D26] font-bold py-3.5 rounded-xl hover:bg-[#F7F8FC] transition-colors text-[15px] cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                        Back
                    </button>
                    <button type="submit"
                        class="flex-1 font-jakarta bg-[#FF8A66] text-[#060D26] font-bold py-3.5 rounded-xl hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                        Continue to ID verification
                    </button>
                </div>
            </div>
        </form>

        {{-- Google and Facebook skip the form: the provider supplies name and email, and the ID step follows. --}}
        <div x-show="step === 1">
            <x-social-login-buttons intent="landlord" />
        </div>

        <p class="mt-6 text-center text-[14px] text-[#5B6A8E]">
            Already have an account?
            <a href="{{ route('login') }}" class="ml-1 font-bold text-[#060D26] hover:text-[#B35A3D] hover:underline transition-colors">Log in</a>
        </p>
        <p class="mt-3 text-center text-[13px] text-[#5B6A8E]">
            Looking for a place to rent instead?
            <a href="{{ route('register') }}" class="font-bold text-[#060D26] hover:text-[#B35A3D] hover:underline">Sign up as a tenant</a>
        </p>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/register-form.js') }}"></script>
<script>
    function togglePassword(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.innerHTML = isPassword
            ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>`
            : `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`;
    }
</script>
@endpush
