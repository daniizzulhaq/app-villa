<?php

return [

    'hostex' => [
        'access_token' => env('HOSTEX_ACCESS_TOKEN'),
        'sync_enabled' => (bool) env('HOSTEX_SYNC_ENABLED', false),
        'auto_income_enabled' => (bool) env('HOSTEX_AUTO_INCOME_ENABLED', false),
        'income_date_basis' => env('HOSTEX_INCOME_DATE_BASIS', 'check_in'),
    ],

    'beds24' => [
        'invite_code' => env('BEDS24_INVITE_CODE'),
        'token' => env('BEDS24_TOKEN'),
        'refresh_token' => env('BEDS24_REFRESH_TOKEN'),
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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
