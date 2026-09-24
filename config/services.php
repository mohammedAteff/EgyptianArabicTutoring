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

    'geoip' => [
        'database_path' => env('GEOIP_DATABASE_PATH', storage_path('geoip/GeoLite2-Country.mmdb')),
        'download_url' => env('GEOIP_DOWNLOAD_URL'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        // Country headers are ignored until the deployment explicitly lists
        // its reverse-proxy/CDN CIDRs. This prevents direct clients from
        // spoofing CF-IPCountry or X-Country-Code.
        'trusted_proxies' => env('GEOIP_TRUSTED_PROXIES', ''),
    ],

    'student_auth' => [
        'hmac_key' => env('STUDENT_AUTH_HMAC_KEY'),
    ],

];
