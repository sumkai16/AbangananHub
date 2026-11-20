<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `not.admin`. On the routes that start an inquiry (a chat, a reservation request): an administrator may look at
 * a listing as a tenant would, but never opens a deal on it. The Send Inquiry button is hidden for them too; this is
 * the server side of that, so a hand-made request is refused the same way.
 */
class EnsureNotAdmin
{
    public const MESSAGE = 'Administrators cannot send inquiries. Use a tenant account to inquire about a listing.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('Admin')) {
            abort(403, self::MESSAGE);
        }

        return $next($request);
    }
}
