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

    // ── YouTube Data API v3 ────────────────────────────────────────────────────
    // Churches can also supply per-connection keys via ChannelConnection.settings.api_key
    // to use their own quota. The platform key is the default.
    'youtube' => [
        'api_key'  => env('YOUTUBE_API_KEY', ''),
        'base_url' => 'https://www.googleapis.com/youtube/v3',
    ],

    // ── Instagram (Instagram API with Instagram Login) ─────────────────────────
    // Only the access token is required: a long-lived (60-day) Instagram User
    // token generated in the Meta App Dashboard. The app refreshes it on a
    // schedule and keeps the refreshed copy encrypted in private storage, so
    // this value only needs replacing if the token is ever allowed to lapse.
    // No account ID is needed — /me resolves it from the token.
    'instagram' => [
        'access_token'  => env('INSTAGRAM_ACCESS_TOKEN', ''),
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v26.0'),
        'post_limit'    => (int) env('INSTAGRAM_POST_LIMIT', 12),
        'cache_minutes' => (int) env('INSTAGRAM_CACHE_MINUTES', 60),
        'timeout'       => (int) env('INSTAGRAM_TIMEOUT_SECONDS', 8),
    ],

];
