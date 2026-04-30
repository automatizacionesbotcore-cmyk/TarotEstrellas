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
        'key' => env('RESEND_KEY', env('RESEND_API_KEY')),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'daily' => [
        'api_key' => env('DAILY_API_KEY'),
        'domain' => env('DAILY_DOMAIN'),
        'base_url' => env('DAILY_BASE_URL', 'https://api.daily.co/v1'),
        'webhook_secret' => env('DAILY_WEBHOOK_SECRET'),
        'default_room_expiry_minutes' => (int) env('DAILY_ROOM_EXPIRY_MINUTES', 90),
        'enable_transcription' => filter_var(env('DAILY_ENABLE_TRANSCRIPTION', true), FILTER_VALIDATE_BOOLEAN),
        'recording_retention_days' => (int) env('DAILY_RECORDING_RETENTION_DAYS', 90),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        'meta_app_secret' => env('META_WHATSAPP_APP_SECRET'),
        'meta_verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
        'ycloud_webhook_secret' => env('YCLOUD_WEBHOOK_SECRET'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'summary_model' => env('ANTHROPIC_SUMMARY_MODEL', 'claude-3-5-haiku-latest'),
        'agente_model' => env('ANTHROPIC_AGENT_MODEL', 'claude-3-5-sonnet-latest'),
        'agente_max_sesiones' => (int) env('ANTHROPIC_AGENT_MAX_SESIONES', 20),
        'agente_max_tokens' => (int) env('ANTHROPIC_AGENT_MAX_TOKENS', 1024),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
