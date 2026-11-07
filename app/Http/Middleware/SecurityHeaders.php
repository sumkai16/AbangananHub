<?php

namespace App\Http\Middleware;

use App\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening on every response (pages, the API and file downloads):
 *  - nosniff: the browser must trust the Content-Type we send instead of guessing it from the bytes, so a file
 *    cannot be treated as a page or a script;
 *  - X-Frame-Options: no other site can put ours in a frame to trick a click (clickjacking);
 *  - Referrer-Policy: a link to another site does not hand over the full page address;
 *  - Content-Security-Policy (config security.csp_mode: off / report-only / enforce);
 *  - Permissions-Policy: camera and location stay on this site;
 *  - HSTS (config security.hsts_max_age): on https only, so local dev is untouched.
 * A response that already set one of the first three keeps its own value.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        foreach ($defaults as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $mode = config('security.csp_mode');

        if ($mode === 'enforce') {
            $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::build());
        } elseif ($mode === 'report-only') {
            $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::build());
        }

        $response->headers->set('Permissions-Policy', ContentSecurityPolicy::permissions());

        if ($request->isSecure() && (int) config('security.hsts_max_age') > 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.(int) config('security.hsts_max_age'));
        }

        return $response;
    }
}
