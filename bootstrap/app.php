<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureLandlord;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ForceJsonResponse;
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
            EnsureAccountActive::class,
        ]);
        $middleware->alias([
            'tenant'   => EnsureTenant::class,
            'landlord' => EnsureLandlord::class,
            'admin'    => EnsureAdmin::class,
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
    })->create();
