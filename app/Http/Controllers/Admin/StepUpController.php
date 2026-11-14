<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireRecentPassword;
use App\Services\Security\AuthEventRecorder;
use App\Services\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Re-confirm the admin's password (or authenticator code) before risky actions. See RequireRecentPassword. */
class StepUpController extends Controller
{
    public function status(Request $request)
    {
        return response()->json(['fresh' => RequireRecentPassword::isFresh($request)]);
    }

    public function confirm(Request $request, AuthEventRecorder $events)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $password = (string) ($data['password'] ?? '');
        $code = trim((string) ($data['code'] ?? ''));

        if ($password === '' && $code === '') {
            throw ValidationException::withMessages(['password' => 'Enter your password.']);
        }

        $ok = $password !== ''
            ? Hash::check($password, (string) $user->password)
            : ($user->hasTwoFactor() && app(TwoFactor::class)->verify($user, $code));

        if (! $ok) {
            // Never store what was typed.
            $events->record('password_confirm_failed', $user, metadata: [
                'method' => $password !== '' ? 'password' : 'code',
            ]);

            $message = $password !== '' ? 'That password is not right.' : 'That code is not right.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['password' => $message]);
        }

        $request->session()->put('auth.step_up_at', now()->timestamp);

        return $request->expectsJson()
            ? response()->json(['fresh' => true])
            : back()->with('success', 'Password confirmed. You can repeat the action now.');
    }
}
