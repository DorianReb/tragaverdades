<?php

return [
    'sabert' => [
        'url' => env('SABERT_URL', 'http://127.0.0.1:8001'),
        'endpoint' => env('SABERT_ENDPOINT', '/analizar'),
        'text_field' => env('SABERT_TEXT_FIELD', 'texto'),
        'max_characters' => (int) env('SABERT_MAX_CHARACTERS', 100000),
        'connect_timeout' => env('SABERT_CONNECT_TIMEOUT', 3),
        'timeout' => env('SABERT_TIMEOUT', 120),
        'extraction_endpoint' => env('SABERT_EXTRACTION_ENDPOINT', '/extraer-url'),
        'extraction_timeout' => env('SABERT_EXTRACTION_TIMEOUT', 30),
    ],

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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
