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

    'line' => [
        'channel_access_token'  => env('LINE_CHANNEL_ACCESS_TOKEN', ''),
        'channel_secret'        => env('LINE_CHANNEL_SECRET', ''),
        'login_channel_id'      => env('LINE_LOGIN_CHANNEL_ID', ''),
        'login_channel_secret'  => env('LINE_LOGIN_CHANNEL_SECRET', ''),
        'callback_url'          => env('LINE_CALLBACK_URL', ''),
        'redirect_base_url'     => env('LINE_REDIRECT_BASE_URL', ''),
    ],
    
    'ai_server' => [
        'url' => env('AI_SERVER_URL', 'http://192.168.1.45:8000'),
    ],

];
