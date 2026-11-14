<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AuthEventRecorder;
use App\Services\Security\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The second step of an admin sign-in: a 6-digit authenticator code, or a recovery code. EnsureTwoFactor sends
 * every admin who has two-factor on and has not passed it this session here, whatever way they signed in.
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return redirect($user->homeRoute());
        }

        if ($this->passed($request)) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactor $twoFactor, AuthEventRecorder $events): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return redirect($user->homeRoute());
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        if (! $twoFactor->verify($user, $data['code'])) {
            // The attempt is recorded, never the code that was typed.
            $events->record('two_factor_failed', $user);

            throw ValidationException::withMessages([
                'code' => "That code didn't work. Check the 6-digit code in your app, or use a recovery code.",
            ]);
        }

        $request->session()->put('auth.two_factor_passed', $user->user_id);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function passed(Request $request): bool
    {
        return (int) $request->session()->get('auth.two_factor_passed') === (int) $request->user()->user_id;
    }
}
