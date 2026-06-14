<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLandlord
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Having the Landlord role is not enough on its own —
        // verification status also matters. An approved landlord
        // has the role; a rejected one might too (depending on your
        // flow). Check the role here; verification status gets
        // checked at the listing-creation level, not middleware.
        if (! $request->user()->hasRole('Landlord')) {
            abort(403, 'Landlord access required.');
        }

        return $next($request);
    }
}