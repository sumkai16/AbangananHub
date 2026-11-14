<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `two-factor`, on the admin group after `admin`. It looks only at the signed-in user, never at how they
 * signed in, so password, Google, Facebook and webview logins are all challenged the same way.
 *
 * - Admin with two-factor on and no `auth.two_factor_passed` flag for this session: sent to the challenge.
 * - Admin without two-factor while `security.admin_two_factor` is `required`: sent to the setup page.
 * - Everyone else passes. While two-factor is not yet set up, the setup, logout and step-up routes stay open so the admin can finish setup.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Admin')) {
            return $next($request);
        }

        if ($user->hasTwoFactor()) {
            if ((int) $request->session()->get('auth.two_factor_passed') === (int) $user->user_id) {
                return $next($request);
            }

            // No exemptions here: until the challenge is passed, even the setup page (recovery codes, turn off) is closed.
            return $this->block($request, 'two-factor.challenge', null);
        }

        if (config('security.admin_two_factor') !== 'required' || $this->isExempt($request)) {
            return $next($request);
        }

        return $this->block($request, 'admin.security.two-factor', 'Set up two-factor sign-in to use the admin area.');
    }

    private function isExempt(Request $request): bool
    {
        return $request->routeIs('admin.security.two-factor*', 'logout', 'admin.stepUp.*');
    }

    private function block(Request $request, string $route, ?string $notice): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Two-factor required.'], 403);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $redirect = redirect()->route($route);

        return $notice ? $redirect->with('warning', $notice) : $redirect;
    }
}
