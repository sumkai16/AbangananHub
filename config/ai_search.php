<?php

/**
 * AI search (plans/ai-search.md). The driver reads the sentence a tenant types
 * and returns structured filters; everything else (querying, relaxing, the
 * hit/miss chips) is plain PHP over our own data.
 */
return [

    // openrouter | gemini | anthropic | null. 'null' (or a missing key) means the keyword
    // fallback answers every search and the UI shows the "smart search is busy" notice.
    'driver' => env('AI_SEARCH_DRIVER', 'null'),

    // Second AI call (order the results + write the summary). Off by default: PHP already orders by fit
    // and writes the summary from real data, and free tiers allow so few requests that one call per
    // search (instead of two) doubles how many searches fit in a day.
    'rank_with_ai' => (bool) env('AI_SEARCH_RANK', false),

    // Seconds per AI call. The page already shows instant keyword results while the AI reads, so a
    // slow free model (10-15 s) is tolerable; use 8 or less with a fast paid model. One retry on 429/5xx, then the keyword fallback.
    // Default 20, not more: PHP's max_execution_time is 30 s, so a longer wait (plus a retry) turns a provider outage into a fatal 500.
    'timeout' => (int) env('AI_SEARCH_TIMEOUT', 20),

    // Below this many exact matches, filters are relaxed step by step.
    'min_results' => 3,
    'max_candidates' => 20,
    'max_query_length' => 300,

    // AI calls allowed to run at the same moment, across all visitors. Each one holds a PHP worker while
    // the model answers, so this keeps workers free for normal pages. Over it -> keyword results.
    'max_concurrent' => (int) env('AI_SEARCH_MAX_CONCURRENT', 5),

    // Per user id (or IP for guests), per hour. Over it -> keyword fallback.
    'rate_per_hour' => 20,

    // Searches per day across everyone, so the provider's quota is never blown. Resets at midnight
    // Asia/Manila. Over it -> keyword fallback. A search is 2 AI calls and Gemini's FREE tier allows
    // only ~20 calls per model per day (Oct 2026), so set AI_SEARCH_DAILY_CAP=8 in .env for free
    // testing. The default stays 600 so a server that never set it is not capped at a handful a day.
    'daily_cap' => (int) env('AI_SEARCH_DAILY_CAP', 600),

    // Features of plans/ai-search-maps-and-haiku.md. Each one is on in local development and off elsewhere until it
    // has been checked by hand: set AI_SEARCH_FEATURE_<NAME>=true|false to override. A failure of any of them falls
    // back to the plain search, never to an error page.
    'features' => [
        'sort' => (bool) env('AI_SEARCH_FEATURE_SORT', env('APP_ENV') === 'local'),
        'geo_lookup' => (bool) env('AI_SEARCH_FEATURE_GEO_LOOKUP', env('APP_ENV') === 'local'),
        'area' => (bool) env('AI_SEARCH_FEATURE_AREA', env('APP_ENV') === 'local'),
        'refine' => (bool) env('AI_SEARCH_FEATURE_REFINE', env('APP_ENV') === 'local'),
        'routing' => (bool) env('AI_SEARCH_FEATURE_ROUTING', env('APP_ENV') === 'local'),
        'planner' => (bool) env('AI_SEARCH_FEATURE_PLANNER', env('APP_ENV') === 'local'),
        'reasons' => (bool) env('AI_SEARCH_FEATURE_REASONS', env('APP_ENV') === 'local'),
        'vision_search' => (bool) env('AI_SEARCH_FEATURE_VISION_SEARCH', env('APP_ENV') === 'local'),
        'photo_check' => (bool) env('AI_SEARCH_FEATURE_PHOTO_CHECK', env('APP_ENV') === 'local'),
    ],

    // Travel-time estimates, shared by PHP and the map (resources/js/maps/property-map.js uses the same numbers).
    // Straight-line distance x detour = road distance; speed in km/h. Walking and the jeepney are estimates until
    // OpenRouteService answers (ORS_API_KEY), and are always shown as "about".
    'travel' => [
        'detour' => 1.3,
        'walk_kmh' => 4.5,
        'drive_kmh' => 22.0,
        'jeep_kmh' => 14.0,
        'jeep_wait_min' => 6,
    ],

    // Planner (tool use): at most this many tool rounds and this many seconds for the whole plan.
    'planner' => ['max_rounds' => 3, 'budget_seconds' => 12],

    'cache' => [
        'interpret_ttl' => 86400, // same sentence -> same filters
        'rank_ttl' => 600,        // same filters + same candidates -> same summary
    ],

    // "Near X" widens 2 km -> 4 km; a barangay widens to its city, a city to these neighbours.
    'default_radius_km' => 2,
    'neighbors' => [
        'Cebu City' => ['Mandaue City', 'Talisay City', 'Minglanilla', 'Consolacion'],
        'Mandaue City' => ['Cebu City', 'Lapu-Lapu City', 'Consolacion', 'Cordova'],
        'Lapu-Lapu City' => ['Mandaue City', 'Cordova'],
        'Talisay City' => ['Cebu City', 'Minglanilla'],
        'Minglanilla' => ['Talisay City', 'Cebu City'],
        'Consolacion' => ['Mandaue City', 'Liloan', 'Cebu City'],
        'Cordova' => ['Lapu-Lapu City', 'Mandaue City'],
    ],
];
