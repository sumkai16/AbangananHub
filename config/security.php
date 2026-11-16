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
    // Most rows a single CSV export of an activity log will contain.
    'export_row_cap'                 => 50000,

    /* Phase 2-3 of plans/security-audit-hardening.md. */
    // required: every Admin must set up and pass two-factor. off: the middleware is a no-op (tests, emergencies).
    'admin_two_factor' => env('ADMIN_TWO_FACTOR', 'required'),
    // Re-enter the password before the risky admin actions, fresh for this many seconds.
    'step_up_enabled' => env('STEP_UP_ENABLED', true),
    'step_up_seconds' => 900,

    // Key for the audit log's hash chain (AuditChain). Separate from APP_KEY so rotating APP_KEY never breaks the chain.
    'audit_chain_key' => env('AUDIT_CHAIN_KEY', env('APP_KEY')),

    /*
     * Tenant payment holds and duplicate-account alerts. See plans/tenant-payment-holds.md.
     * A new account alerts the admins when it matches a tenant on hold by any single signal in `alone`, or by every
     * signal of one group in `together`. IP alone and name alone never alert: shared Wi-Fi and common names.
     */
    'hold_match' => [
        'alone'                    => ['phone', 'device'],
        'together'                 => [['name', 'ip']],
        'dismissed_retention_days' => 90,
        'min_reason_length'        => 10,
    ],
];
