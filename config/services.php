<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        // Seconds a webhook's signed timestamp may differ from now before it is refused (replay guard).
        'webhook_tolerance' => (int) env('PAYMONGO_WEBHOOK_TOLERANCE', 300),
        // Paying landlords out (PayMongo Send Money). Automatic payouts stay switched off, and money stays owed,
        // until all three are set: the wallet account the money leaves from, and PayMongo's code for GCash as a
        // receiving institution (`php artisan payouts:find-gcash-bic` prints what PayMongo lists).
        'wallet_account_number' => env('PAYMONGO_WALLET_ACCOUNT_NUMBER'),
        'wallet_account_name' => env('PAYMONGO_WALLET_ACCOUNT_NAME'),
        'gcash_bic' => env('PAYMONGO_GCASH_BIC'),
        // PayMongo test keys start with sk_test_. Drives the "Test mode" notice
        // on checkout so visitors know no real money is charged.
        'test_mode' => str_starts_with((string) env('PAYMONGO_SECRET_KEY', ''), 'sk_test_'),
        // Refund endpoints. QR Ph payments are refunded through their own host (PayMongo's QR Ph refund guide);
        // everything else, GCash included, through the main API. Configurable because PayMongo's docs are not
        // explicit about which host QR Ph needs.
        'refunds_url' => env('PAYMONGO_REFUNDS_URL', 'https://api.paymongo.com/v1/refunds'),
        'qrph_refunds_url' => env('PAYMONGO_QRPH_REFUNDS_URL', 'https://refunds-api.paymongo.com/v1/refunds'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    // AI search (config/ai_search.php picks the driver). Gemini's free tier is for
    // testing; model IDs change, so read the current one from AI Studio.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        // minimal | low | medium | high. Empty = the model's default. Lower = faster answers.
        'thinking' => env('GEMINI_THINKING', 'minimal'),
    ],

    // OpenRouter: free models end in ":free" (about 50 requests/day, 1,000 with $10 of credit ever bought).
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_SEARCH_MODEL', 'claude-haiku-5-5'),
    ],

];
