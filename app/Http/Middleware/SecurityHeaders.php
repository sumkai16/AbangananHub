<?php

namespace App\Http\Middleware;

use App\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening on every web response: Content-Security-Policy (config security.csp_mode), HSTS on https,
 * a Permissions-Policy that keeps camera and location to this site, and a Referrer-Policy.
 *
 * X-Frame-Options and X-Content-Type-Options are set by nginx (plans/hostinger-vps-deployment.md), not here, so
 * they are not sent twice. frame-ancestors in the CSP covers framing for browsers that read it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $mode = config('security.csp_mode');

        if ($mode === 'enforce') {
            $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::build());
        } elseif ($mode === 'report-only') {
            $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::build());
        }

        $response->headers->set('Permissions-Policy', ContentSecurityPolicy::permissions());
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure() && (int) config('security.hsts_max_age') > 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.(int) config('security.hsts_max_age'));
        }

        return $response;
    }
}
