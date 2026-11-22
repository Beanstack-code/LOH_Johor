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

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],

    'paycools' => [
        'app_id'      => env('PAYCOOLS_APP_ID'),
        'private_key' => env('PAYCOOLS_PRIVATE_KEY'),
        'public_key'  => env('PAYCOOLS_PUBLIC_KEY'),
        'base_url'    => env('PAYCOOLS_BASE_URL'),
        'return_url'  => env('PAYCOOLS_RETURN_URL'),
        'notify_url'  => env('PAYCOOLS_NOTIFY_URL'),
        'country_code'         => env('PAYCOOLS_COUNTRY_CODE', 'MY'),
        'currency'             => env('PAYCOOLS_CURRENCY', 'MYR'),
        'settlement_currency'  => env('PAYCOOLS_SETTLEMENT_CURRENCY', 'MYR'),
    ],

];
