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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();