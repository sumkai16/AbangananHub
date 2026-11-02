<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side safety nets sent on every response (pages, the API and file downloads):
 *  - nosniff: the browser must trust the Content-Type we send instead of guessing it from the bytes, so a file
 *    cannot be treated as a page or a script;
 *  - X-Frame-Options: no other site can put ours in a frame to trick a click (clickjacking);
 *  - Referrer-Policy: a link to another site does not hand over the full page address;
 *  - HSTS: once the site is on HTTPS in production, browsers refuse to fall back to plain HTTP.
 * A response that already set one of these keeps its own value.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
