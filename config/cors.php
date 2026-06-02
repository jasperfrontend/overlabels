<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published explicitly (rather than relying on the framework default) so the
    | open-origin policy below is an intentional decision, not an inherited one.
    |
    | `api/*` is the only path that needs CORS: the public List read endpoint
    | (GET /api/lists/{slug}) is fetched cross-origin by external consumers - a
    | custom wheel page, a browser source on another host - because overlay
    | templates run no JavaScript and cannot consume list data themselves.
    |
    | allowed_origins is '*' on purpose: the OverlayAccessToken in the request
    | is the real gate, and a read token is treated like a shareable overlay
    | URL. supports_credentials stays false (we authenticate by token query
    | param, never by cookie), which is required for the '*' origin to be legal.
    | If list sharing ever needs finer control, swap '*' for an explicit origin
    | allowlist here - no code change needed elsewhere.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
