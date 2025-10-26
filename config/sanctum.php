<?php

return [
    // Minutes until tokens expire. Null means never expires.
    'expiration' => env('SANCTUM_EXPIRATION', 60),

    // Stateful domains for SPA auth; not used for this API-only project.
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),

    // Sanctum middleware (defaults are fine for API token usage).
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
