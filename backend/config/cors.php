<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'webhooks/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Local dev
        'http://localhost',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://127.0.0.1:5173',
        'http://localhost/tarotEstrella/tarotestrellas/frontend/dist',
        // Production
        'https://tarotestrellas.cl',
        'https://www.tarotestrellas.cl',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
