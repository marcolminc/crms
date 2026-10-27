<?php

return [

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

    'azampay' => [
        'app_name' => env('AZAMPAY_APP_NAME'),
        'client_id' => env('AZAMPAY_CLIENT_ID'),
        'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
        'webhook_secret' => env('AZAMPAY_WEBHOOK_SECRET'),
        // Sandbox by default. Production: https://checkout.azampay.co.tz and
        // https://authenticator.azampay.co.tz/AppRegistration/GenerateToken.
        'base_url' => env('AZAMPAY_BASE_URL', 'https://sandbox.azampay.co.tz'),
        'auth_url' => env('AZAMPAY_AUTH_URL', 'https://authenticator-sandbox.azampay.co.tz/AppRegistration/GenerateToken'),
        'currency' => env('AZAMPAY_CURRENCY', 'TZS'),
        // Issued by AzamPay when the hosted (card) checkout partner is registered.
        'vendor_id' => env('AZAMPAY_VENDOR_ID'),
        'vendor_name' => env('AZAMPAY_VENDOR_NAME'),
        // Merchant wallet money is paid back out of (refunds, deposit releases).
        'payout_account_number' => env('AZAMPAY_PAYOUT_ACCOUNT_NUMBER'),
        'payout_account_name' => env('AZAMPAY_PAYOUT_ACCOUNT_NAME'),
    ],

];
