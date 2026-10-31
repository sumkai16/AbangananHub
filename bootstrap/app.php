<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
   ->withMiddleware(function (Middleware $middleware) {
      $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountActive::class,
        ]);
        $middleware->alias([
            'tenant'   => \App\Http\Middleware\EnsureTenant::class,
            'landlord' => \App\Http\Middleware\EnsureLandlord::class,
            'admin'    => \App\Http\Middleware\EnsureAdmin::class,
            // Aliased for the API, where it must run *after* auth:sanctum has
            // resolved the guard. Appending it to the api group instead would
            // run it before, find no authenticated user, and pass everything
            // through. The web stack appends it (above) because the session
            // guard is already resolved by then.
            'active'   => \App\Http\Middleware\EnsureAccountActive::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);

        // Behind a tunnel or a reverse proxy the browser speaks https to the proxy and the proxy speaks http to
        // us, so without this every generated link and asset URL comes out as http and the browser blocks them
        // (an unstyled page). Off unless TRUSTED_PROXIES is set: '*' for a throwaway tunnel on a dev machine,
        // or the proxy's address(es), comma separated, on a server. Never '*' on a server reachable directly.
        if ($trusted = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();