<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TenantInvites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * A tenant accepting a landlord's invite: they choose a password and the walk-in placeholder becomes their own
 * account (active, email verified by the link itself, no longer a walk-in). Uses the 'invites' password broker,
 * so the link lasts a week instead of the reset link's hour.
 */
class TenantInviteController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.accept-invite', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $accepted = null;

        $status = Password::broker(TenantInvites::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$accepted) {
                // Only a walk-in placeholder can be claimed this way; a real account resets its password elsewhere.
                if (! $user->is_walk_in) {
                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'is_walk_in' => false,
                    'account_status' => 'active',
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                $accepted = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $accepted) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => $status === Password::PASSWORD_RESET
                    ? 'This invite has already been used. Log in instead.'
                    : 'This invite link has expired or does not match this email. Ask your landlord to send a new one.']);
        }

        Auth::login($accepted);
        $request->session()->regenerate();

        return redirect()->to($accepted->homeRoute())->with('success', 'Welcome to AbangananHub. Your account is ready.');
    }
}
