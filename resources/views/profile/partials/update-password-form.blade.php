<div class="abg-card">
    <header>
        <h2 class="abg-card-title">
            {{ __('Update Password') }}
        </h2>

        <p class="abg-card-sub">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6">
        @csrf
        @method('put')

        <div class="abg-input-group">
            <label for="update_password_current_password" class="abg-label">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="abg-input" autocomplete="current-password">
            @if($errors->updatePassword->get('current_password'))
                <span class="abg-error">{{ $errors->updatePassword->get('current_password')[0] }}</span>
            @endif
        </div>

        <div class="abg-input-group">
            <label for="update_password_password" class="abg-label">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" class="abg-input" autocomplete="new-password">
            @if($errors->updatePassword->get('password'))
                <span class="abg-error">{{ $errors->updatePassword->get('password')[0] }}</span>
            @endif
        </div>

        <div class="abg-input-group" style="margin-bottom: 24px;">
            <label for="update_password_password_confirmation" class="abg-label">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="abg-input" autocomplete="new-password">
            @if($errors->updatePassword->get('password_confirmation'))
                <span class="abg-error">{{ $errors->updatePassword->get('password_confirmation')[0] }}</span>
            @endif
        </div>

        <div style="display: flex; align-items: center; gap: 16px;">
            <button type="submit" class="abg-btn-primary">{{ __('Save') }}</button>

            @if (session('status') === 'password-updated')
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
