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

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'url' => env('CLOUDINARY_URL'),
    ],
    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'redirect' => env('TWITCH_REDIRECT_URI'),
        'cache_ttl' => (int) env('TWITCH_CACHE_TTL', 10),
    ],

    'streamlabs' => [
        'client_id' => env('STREAMLABS_CLIENT_ID'),
        'client_secret' => env('STREAMLABS_CLIENT_SECRET'),
        'listener_secret' => env('STREAMLABS_LISTENER_SECRET'),
    ],

    'streamelements' => [
        'listener_secret' => env('STREAMELEMENTS_LISTENER_SECRET'),
    ],

    'twitchbot' => [
        'client_id' => env('TWITCHBOT_CLIENT_ID'),
        'client_secret' => env('TWITCHBOT_CLIENT_SECRET'),
        'redirect' => env('TWITCHBOT_REDIRECT_URI', env('APP_URL').'/auth/twitchbot/callback'),
        'listener_secret' => env('TWITCHBOT_LISTENER_SECRET'),
    ],

    'railway' => [
        'webhook_secret' => env('RAILWAY_WEBHOOK_SECRET'),
    ],

    'integration_suggestions' => [
        'webhook_url' => env('INTEGRATION_SUGGESTION_WEBHOOK_URL'),
    ],

];
