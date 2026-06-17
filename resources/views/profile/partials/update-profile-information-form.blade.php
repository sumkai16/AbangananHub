<div class="abg-card">
    <header>
        <h2 class="abg-card-title">
            {{ __('Profile Information') }}
        </h2>

        <p class="abg-card-sub">
            {{ __("Update your account's profile details, contact number, and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6">
        @csrf
        @method('patch')

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
            <div>
                <label for="first_name" class="abg-label">{{ __('First Name') }}</label>
                <input id="first_name" name="first_name" type="text" class="abg-input" value="{{ old('first_name', $user->first_name) }}" required autofocus autocomplete="given-name">
                @error('first_name')
                    <span class="abg-error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="last_name" class="abg-label">{{ __('Last Name') }}</label>
                <input id="last_name" name="last_name" type="text" class="abg-input" value="{{ old('last_name', $user->last_name) }}" required autocomplete="family-name">
                @error('last_name')
                    <span class="abg-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="abg-input-group">
            <label for="email" class="abg-label">{{ __('Email Address') }}</label>
            <input id="email" name="email" type="email" class="abg-input" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')
                <span class="abg-error">{{ $message }}</span>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div style="margin-top: 12px; padding: 12px; background: #FFFBEB; border-radius: 8px; border: 1px solid #FDE68A;">
                    <p class="text-sm" style="color: #92400E; font-size: 13.5px; font-weight: 500;">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" style="background:none; border:none; text-decoration:underline; color:#B45309; font-weight:700; cursor:pointer;">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p style="margin-top: 8px; font-size: 13px; font-weight: 600; color: #059669;">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="abg-input-group" style="margin-bottom: 24px;">
            <label for="contact_number" class="abg-label">{{ __('Contact Number') }}</label>
            <input id="contact_number" name="contact_number" type="text" class="abg-input" value="{{ old('contact_number', $user->contact_number) }}" placeholder="e.g. +639123456789" autocomplete="tel">
            @error('contact_number')
                <span class="abg-error">{{ $message }}</span>
            @enderror
        </div>

        <div style="display: flex; align-items: center; gap: 16px;">
            <button type="submit" class="abg-btn-primary">{{ __('Save') }}</button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    style="font-size: 13.5px; font-weight: 600; color: #059669;"
                >
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="display:inline-block; vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Saved successfully.') }}
                </p>
            @endif
        </div>
    </form>
</div>
