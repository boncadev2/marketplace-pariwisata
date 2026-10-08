<?php

return [
    'payment_gateway' => ['driver' => env('PAYMENT_GATEWAY', 'sandbox')],
    'midtrans' => ['server_key' => env('MIDTRANS_SERVER_KEY'), 'production_enabled' => (bool) env('MIDTRANS_PRODUCTION_ENABLED', false), 'production_server_key' => env('MIDTRANS_PRODUCTION_SERVER_KEY'), 'refunds_enabled' => (bool) env('MIDTRANS_REFUNDS_ENABLED', false), 'webhook_url' => env('MIDTRANS_WEBHOOK_URL')],
    'commerce' => ['production_enabled' => (bool) env('COMMERCE_PRODUCTION_ENABLED', false)],
    'shipping' => ['driver' => env('SHIPPING_DRIVER', 'manual'), 'enabled' => (bool) env('SHIPPING_PROVIDER_ENABLED', false), 'api_key' => env('BITESHIP_API_KEY'), 'couriers' => env('SHIPPING_COURIERS', 'jne,sicepat,anteraja')],
    'refund' => ['driver' => env('REFUND_DRIVER', 'sandbox')],
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:8080'),
    'transaction_notices' => ['enabled' => env('TRANSACTION_NOTICES_ENABLED', false)],
    'sandbox_payment' => [
        'webhook_secret' => env('SANDBOX_PAYMENT_WEBHOOK_SECRET'),
    ],
    'sandbox_refund' => [
        'enabled' => env('SANDBOX_REFUND_ENABLED', false),
    ],
    'payment_reconciliation' => [
        'requests_per_minute' => env('PAYMENT_RECONCILIATION_REQUESTS_PER_MINUTE', 30),
    ],
    'whatsapp' => [
        'driver' => env('WHATSAPP_GATEWAY_DRIVER', 'mock'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],
    'payout' => [
        'driver' => env('PAYOUT_DRIVER', 'unconfigured'),
        'sandbox_enabled' => (bool) env('PAYOUT_SANDBOX_ENABLED', true),
        'iris' => [
            'api_key' => env('IRIS_API_KEY'),
            'merchant_key' => env('IRIS_MERCHANT_KEY'),
            'base_url' => env('IRIS_BASE_URL', 'https://app.sandbox.midtrans.com/iris/api/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
