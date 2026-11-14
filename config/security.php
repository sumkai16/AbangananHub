<?php

return [
    /*
     * Content-Security-Policy (App\Support\ContentSecurityPolicy, sent by App\Http\Middleware\SecurityHeaders).
     *
     *   off          no CSP header
     *   report-only  the browser logs what the policy would block (POST /csp-report -> laravel.log) and blocks nothing
     *   enforce      the browser blocks it
     *
     * Roll out in this order: report-only, click through the main pages, read the "CSP violation" lines in the log,
     * add the missing origin to ContentSecurityPolicy, then switch to enforce. See context/ARCHITECTURE.md.
     */
    'csp_mode' => env('CSP_MODE', 'report-only'),

    /*
     * Strict-Transport-Security, sent only on https requests (never on plain http, so local dev is untouched).
     * Six months, no includeSubDomains, no preload: easy to back out. Needs TRUSTED_PROXIES when behind a proxy so
     * the app can tell the request was https.
     */
    'hsts_max_age' => env('HSTS_MAX_AGE', 15552000),

    /* The websocket the browser opens for live chat (Laravel Reverb), the same values Echo is built with. */
    'ws' => [
        'host' => env('VITE_REVERB_HOST', env('REVERB_HOST')),
        'port' => env('VITE_REVERB_PORT', env('REVERB_PORT')),
        'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')),
    ],

    /* Account & security events (auth_events). See plans/security-audit-hardening.md. */
    'auth_events_retention_days'     => 90,
    'auth_events_ip_cap_per_minute'  => 30,
    'device_cookie'                  => 'ah_device',
];
