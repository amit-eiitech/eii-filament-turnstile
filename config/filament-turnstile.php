<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare credentials
    |--------------------------------------------------------------------------
    */
    'site_key' => env('TURNSTILE_SITE_KEY'),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Server-side validation
    |--------------------------------------------------------------------------
    */
    'allowed_hostnames' => array_values(array_filter(array_map(
        static fn(string $hostname): string => strtolower(trim($hostname)),
        explode(',', (string) env('TURNSTILE_ALLOWED_HOSTNAMES', '')),
    ))),

    'action' => 'default',

    'actions' => [
        'login' => 'admin-login',
        'register' => 'admin-register',
        'request_password_reset' => 'admin-password-reset-request',
    ],

    // Only enable this for local/testing environments if your test response
    // does not contain an action. Keep false in production.
    'allow_missing_action' => (bool) env('TURNSTILE_ALLOW_MISSING_ACTION', false),

    'connect_timeout' => 3,
    'timeout' => 8,

    /*
    |--------------------------------------------------------------------------
    | Widget appearance
    |--------------------------------------------------------------------------
    */
    'theme' => 'auto', // auto, light, dark
    'size' => 'normal', // normal, compact
    'appearance' => 'always', // always, execute, interaction-only
    'language' => env('TURNSTILE_LANGUAGE'), // e.g., 'en', 'es', 'fr', etc.
];
