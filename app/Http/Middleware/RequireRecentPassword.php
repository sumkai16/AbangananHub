<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up gate (alias `recent.password`): a risky admin action needs the password (or an authenticator
 * code) to have been re-entered in the last `security.step_up_seconds`. The server is the real gate;
 * the global modal in admin/_step-up-modal.blade.php only makes the retry smooth.
 */
class RequireRecentPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.step_up_enabled') || $request->isMethodSafe() || self::isFresh($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'needs_password' => true,
                'message' => 'Confirm your password to continue.',
            ], 423);
        }

        // No `error` flash on purpose: the flash modal would stack a second dialog on top of the step-up modal.
        return back()->with('step_up_required', true);
    }

    public static function isFresh(Request $request): bool
    {
        $at = (int) $request->session()->get('auth.step_up_at', 0);

        return $at > now()->timestamp - (int) config('security.step_up_seconds', 900);
    }
}
