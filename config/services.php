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
        'forward_proxy'         => env('FORWARD_PROXY', ''),
    ],
    
    'ai_server' => [
        // ว่าง = ไม่มี Python AI node (ใช้ face_api เท่านั้น; face_api ก็ว่าง = ไม่มีบริการใบหน้า)
        'url'                       => env('AI_SERVER_URL', ''),
        'urls'                      => env('AI_SERVERS') ? array_values(array_filter(array_map('trim', explode(',', (string) env('AI_SERVERS'))))) : null,
        'key'                       => env('AI_SERVER_KEY', env('AI_SERVICE_API_KEY', 'uni-activity-ai-secret-key-2026')),
        'timeout'                   => (int) env('AI_SERVER_TIMEOUT', 6),
        'retry'                     => (int) env('AI_SERVER_RETRIES', 2),
        'circuit_breaker_threshold' => (int) env('AI_CIRCUIT_BREAKER_THRESHOLD', 3),
        'circuit_breaker_cooldown'  => (int) env('AI_CIRCUIT_BREAKER_COOLDOWN', 30),
    ],

    /*
    | face_api — บริการสกัดใบหน้าแบบ native (ncnn) ที่รันบนเครื่อง S2
    | POST /extract: รับรูปเต็ม -> SCRFD detect -> ArcFace align -> 512D embedding
    | POST /verify:  เทียบ selfie กับ embedding ที่ลงทะเบียนไว้ + liveness
    | FACE_API_URLS = หลายโหนดคั่นด้วย comma (web จะ failover ตามลำดับ), FACE_API_URL = โหนดเดียว
    | ปล่อยว่างทั้งคู่ = ไม่ใช้ (กลับไปใช้ ai_server cluster เดิม)
    */
    'face_api' => [
        'url'     => env('FACE_API_URL', ''),
        'urls'    => array_values(array_filter(array_map('trim', explode(',', (string) env('FACE_API_URLS', ''))))),
        'key'     => env('FACE_API_KEY') ?: env('AI_SERVER_KEY', env('AI_SERVICE_API_KEY', '')),
        'timeout' => (int) env('FACE_API_TIMEOUT', 15),
    ],

    // ── (8) SSO OAuth2/OIDC — เปิดใช้ด้วย .env: SSO_ENABLED=true + SSO_ISSUER + SSO_CLIENT_ID/SECRET/REDIRECT_URI ──
    'sso' => [
        'enabled'       => env('SSO_ENABLED', false),
        'client_id'     => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'redirect_uri'  => env('SSO_REDIRECT_URI'),
        'issuer'        => env('SSO_ISSUER'),
        'discovery_url' => env('SSO_DISCOVERY_URL'),
        'scopes'        => env('SSO_SCOPES', 'openid email profile'),
    ],

    // ── (3) PromptPay: หมายเลขสำรองเมื่อกิจกรรมไม่ได้ระบุเบอร์รับเงิน ──
    'promptpay' => [
        'fallback_id' => env('PROMPTPAY_FALLBACK_ID', ''),
    ],
];
