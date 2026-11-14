<?php

use App\Http\Middleware\DropArrayFilters;
use App\Http\Middleware\EmbeddedPage;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureDeviceCookie;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureLandlord;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\RequireRecentPassword;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Every response, including the API and file downloads.
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            DropArrayFilters::class,
            EnsureAccountActive::class,
            EnsureDeviceCookie::class,
        ]);
        $middleware->alias([
            'tenant'   => EnsureTenant::class,
            'landlord' => EnsureLandlord::class,
            'admin'    => EnsureAdmin::class,
            'two-factor'      => EnsureTwoFactor::class,
            'recent.password' => RequireRecentPassword::class,
            // Landlord pages that can open inside the page panel (an iframe) without the sidebar and top bar.
            'embeddable' => EmbeddedPage::class,
            // Aliased for the API, where it must run *after* auth:sanctum has
            // resolved the guard. Appending it to the api group instead would
            // run it before, find no authenticated user, and pass everything
            // through. The web stack appends it (above) because the session
            // guard is already resolved by then.
            'active'   => EnsureAccountActive::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
            // The browser posts CSP reports without a CSRF token.
            'csp-report',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Never flash these into the session when validation fails.
        $exceptions->dontFlash([
            'id_number',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_number',
        ]);

        // Record who got turned away from a page they should not see. Laravel never *reports* 4xx HTTP exceptions
        // (HttpException and AuthorizationException are in its built-in "don't report" list), so a report()
        // callback would never fire. A render callback does run, and returning null leaves the 403 response and
        // the normal logging untouched. Policies and authorize() arrive here already converted to a 403.
        // Only /admin/* denials and signed-in users are recorded; anonymous 403s on public pages are noise.
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 403 && ($request->is('admin/*') || $request->user())) {
                app(\App\Services\Security\AuthEventRecorder::class)->record(
                    'access_denied',
                    $request->user(),
                    metadata: ['path' => $request->path(), 'method' => $request->method()],
                );
            }

            return null;
        });
    })->create();

// Behind a tunnel or a reverse proxy the browser speaks https to the proxy and the proxy speaks http to us, so
// without this every generated link and asset URL comes out as http and the browser blocks them (an unstyled
// page). Off unless TRUSTED_PROXIES is set: '*' for a throwaway tunnel on a dev machine, or the proxy's
// address(es), comma separated, on a server. Never '*' on a server reachable directly.
// Read in booted(), not in withMiddleware(): that callback runs before .env is loaded, so env() was always null.
$app->booted(function () {
    if ($trusted = env('TRUSTED_PROXIES')) {
        TrustProxies::at($trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
    }
});

return $app;
