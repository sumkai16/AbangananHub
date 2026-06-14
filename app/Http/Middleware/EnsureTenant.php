<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Auth check first — middleware can run before auth middleware
        // if routes are misconfigured, so we guard against that.
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // hasRole() is the helper you wrote on the User model.
        // It queries user_roles for a matching role string.
        if (! $request->user()->hasRole('Tenant')) {
            abort(403, 'Tenant access required.');
        }

        return $next($request);
    }
}