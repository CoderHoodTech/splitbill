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

    /*
    |--------------------------------------------------------------------------
    | OCR microservice (Phase 5)
    |--------------------------------------------------------------------------
    |
    | Local Python FastAPI service that runs Tesseract (ind) on receipt images.
    | Laravel calls it via HTTP only — see ocr-service/README.md for the contract.
    | Binds to 127.0.0.1 only; never public.
    |
    */
    'ocr' => [
        'base_url' => env('OCR_BASE_URL', 'http://127.0.0.1:8001'),
        'timeout' => env('OCR_TIMEOUT', 20),
    ],

];
