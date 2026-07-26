<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || $user->account_status !== 'suspended') {
            return $next($request);
        }

        // Revoke the token that made this request so the suspension takes effect
        // on the device immediately, rather than only when it next logs in.
        // Guarded on the concrete class: a session-authenticated Sanctum request
        // (stateful domain — which in dev includes 127.0.0.1) yields a
        // TransientToken instead, and that has nothing to revoke.
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        // A token request has no session to invalidate and nowhere to redirect to.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This account has been suspended.',
            ], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('suspended', true);
    }
}
