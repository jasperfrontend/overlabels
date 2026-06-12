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
        // Fall back to parsing CLOUDINARY_URL (cloudinary://key:secret@cloud_name)
        // so prod only needs CLOUDINARY_URL in Kamal env; local can keep the
        // explicit CLOUDINARY_CLOUD_NAME if it's already set.
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME')
            ?: (env('CLOUDINARY_URL') ? parse_url((string) env('CLOUDINARY_URL'), PHP_URL_HOST) : null),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'url' => env('CLOUDINARY_URL'),
    ],
    'twitch' => [
        // Default to '' so secret-less environments (CI, fresh installs) don't
        // fatal when services type these into non-nullable string properties.
        'client_id' => env('TWITCH_CLIENT_ID', ''),
        'client_secret' => env('TWITCH_CLIENT_SECRET', ''),
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

    'fourthwall' => [
        'client_id' => env('FW_CLIENT_ID'),
        'client_secret' => env('FW_CLIENT_SECRET'),
        'auth_url' => env('FW_AUTH_URL'),
        'redirect_url' => env('FW_REDIRECT_URL'),
        'api_base' => env('FW_API_BASE', 'https://api.fourthwall.com'),
        // App-level HMAC: Fourthwall signs every inbound webhook with this secret
        // using the X-Fourthwall-Hmac-Apps-Sha256 header. Per-webhook secrets from
        // the registerWebhook response are not used (Fourthwall doesn't return them).
        'hmac' => env('FW_HMAC'),
    ],

    'twitchbot' => [
        'client_id' => env('TWITCHBOT_CLIENT_ID'),
        'client_secret' => env('TWITCHBOT_CLIENT_SECRET'),
        'redirect' => env('TWITCHBOT_REDIRECT_URI', env('APP_URL').'/auth/twitchbot/callback'),
        'listener_secret' => env('TWITCHBOT_LISTENER_SECRET'),
    ],

    'expression_engine' => [
        // Localhost-only HTTP sidecar that evaluates Expression Controls
        // server-side using the same JS engine the overlay runs. See
        // expression-engine.mjs at the repo root.
        'url' => env('EXPRESSION_ENGINE_URL', 'http://127.0.0.1:3010'),
        'secret' => env('EXPRESSION_ENGINE_SECRET'),
        'timeout_ms' => (int) env('EXPRESSION_ENGINE_TIMEOUT_MS', 2000),
    ],

    'deploy' => [
        'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET'),
    ],

    'freesound' => [
        // Static API key for read-only endpoints (search, sound info). Issued
        // alongside CLIENT_ID/CLIENT_SECRET when you create an app at
        // https://freesound.org/apiv2/apply/. We avoid OAuth because we only
        // hit endpoints that accept the static token, and per-user OAuth is
        // unnecessary at our scale (one app key, all users share rate limit).
        'api_key' => env('FREESOUND_API_KEY'),
        // OAuth credentials are reserved for a possible future expansion
        // (downloading originals would require OAuth) - unused right now.
        'client_id' => env('FREESOUND_CLIENT_ID'),
        'client_secret' => env('FREESOUND_CLIENT_SECRET'),
    ],

    'integration_suggestions' => [
        'webhook_url' => env('INTEGRATION_SUGGESTION_WEBHOOK_URL'),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        // Kaylin - "the voice of Overlabels". Single voice for all TTS.
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        // Flash 2.5 is the lowest-latency model and good enough quality for
        // ~3s alert lines. Bump to eleven_multilingual_v2 if quality bites.
        'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_flash_v2_5'),
    ],

    // Sqids encoding for the public map slug. Encodes a Twitch ID into an
    // opaque short string so /map/{slug} URLs and the public map.{slug}
    // broadcast channel never expose the numeric Twitch ID. Pure CPU,
    // no DB lookup. The alphabet is the only "secret" - it must stay
    // stable across deploys or every previously shared map URL breaks.
    'map_slug' => [
        'alphabet' => env('MAP_SLUG_ALPHABET', 'k4n2pSvBDfWHtgJULRamE6GYqcQA7ujM3i85zVbsX9eyKwoxN1ZhCdrTPlF0OI'),
        'min_length' => (int) env('MAP_SLUG_MIN_LENGTH', 8),
    ],

];
