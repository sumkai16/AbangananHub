<div class="abg-card abg-card-danger" style="border-left: 4px solid #EF4444; background: #FFFDFD; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.04);">
    <div style="display: flex; gap: 18px; align-items: flex-start; margin-bottom: 24px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: #FEF2F2; display: flex; align-items: center; justify-content: center; color: #EF4444; flex-shrink: 0; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.12);">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div>
            <h2 class="abg-card-title" style="color: #1A1A2E; margin-bottom: 4px; font-size: 18px; font-weight: 800;">
                {{ __('Delete Account') }}
            </h2>
            <p class="abg-card-sub" style="color: #5A6475; font-size: 13.5px; line-height: 1.5; margin-bottom: 0;">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
            </p>
        </div>
    </div>

    <div style="margin-bottom: 24px; padding: 14px 16px; background: #FEF2F2; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.15); display: flex; gap: 10px; align-items: center;">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#EF4444" stroke-width="2.5" style="flex-shrink: 0;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span style="font-size: 13px; font-weight: 700; color: #991B1B;">
            {{ __('Warning: This action is permanent and cannot be undone.') }}
        </span>
    </div>

    <button
        type="button"
        class="abg-btn-danger"
        style="text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; font-size: 13.5px; padding: 0 26px; height: 44px; border-radius: 12px;"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        {{ __('Delete Account') }}
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="abg-modal-form" style="background: #fff; border-radius: 20px; overflow: hidden;">
            @csrf
            @method('delete')

            <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #FEF2F2; display: flex; align-items: center; justify-content: center; color: #EF4444; flex-shrink: 0;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h2 class="abg-modal-title" style="margin-bottom: 6px;">
                        {{ __('Are you sure you want to delete your account?') }}
                    </h2>
                    <p class="abg-modal-text" style="margin-bottom: 0;">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>
                </div>
            </div>

            <div class="abg-input-group" style="margin-top: 24px;">
                <label for="password" class="abg-label">{{ __('Account Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="abg-input"
                    placeholder="{{ __('Enter your password to confirm') }}"
                    style="width: 100%;"
                    required
                />
                @if($errors->userDeletion->get('password'))
                    <span class="abg-error" style="margin-top: 8px;">{{ $errors->userDeletion->get('password')[0] }}</span>
                @endif
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 28px;">
                <button type="button" class="abg-btn-secondary" style="height: 42px; border-radius: 10px; font-size: 13.5px;" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </button>

                <button type="submit" class="abg-btn-danger" style="height: 42px; border-radius: 10px; font-size: 13.5px; text-transform: uppercase; letter-spacing: 0.3px;">
                    {{ __('Delete Account') }}
                </button>
            </div>
        </form>
    </x-modal>
</div>
