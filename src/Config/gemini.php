<?php

declare(strict_types=1);

return [
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
    'embed_model' => env('GEMINI_EMBED_MODEL', 'models/text-embedding-004'),
    'llm_model' => env('GEMINI_LLM_MODEL', 'models/gemini-2.0-flash-exp'),
    'timeout' => env('GEMINI_TIMEOUT', 30),
    'max_retries' => env('GEMINI_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Configure middleware for bot routes. Set to null or empty array to disable.
    | Examples:
    | - ['api', 'bot.rate-limit'] (default, no auth)
    | - ['api', 'auth:sanctum', 'bot.rate-limit'] (require Sanctum auth)
    | - ['api', 'auth:api', 'bot.rate-limit'] (require Passport auth)
    |
    */
    'route_middleware' => [
        'api',
        'bot.rate-limit',
        // Add authentication middleware here if needed:
        // env('GEMINI_AUTH_MIDDLEWARE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Require Authentication
    |--------------------------------------------------------------------------
    |
    | If set to true, all bot routes will require authentication.
    | The auth guard specified in 'auth_guard' will be used.
    |
    */
    'require_auth' => env('GEMINI_REQUIRE_AUTH', false),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use when require_auth is true.
    | Common options: 'sanctum', 'api', 'web'
    |
    */
    'auth_guard' => env('GEMINI_AUTH_GUARD', 'sanctum'),
];

