<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives each browser a random id (cookie, 1 year) so a sign-in from a browser we have not seen can be told apart
 * from a routine one. It identifies a browser, not a person, and is read only by AuthEventRecorder.
 */
class EnsureDeviceCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = config('security.device_cookie', 'ah_device');

        if (! $request->cookies->has($name)) {
            $id = (string) Str::uuid();
            // Make it visible to this same request, so the first sign-in is recorded with it.
            $request->cookies->set($name, $id);
            Cookie::queue(Cookie::make($name, $id, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $next($request);
    }
}
