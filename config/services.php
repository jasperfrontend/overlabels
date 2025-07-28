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
        'cache_ttl' => (int) env('TWITCH_CACHE_TTL', 10)
    ],

];