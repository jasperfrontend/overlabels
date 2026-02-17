<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set trusted proxy IP addresses. Railway and other hosting providers
    | use proxies, so we need to trust them to properly detect HTTPS.
    |
    */
    'proxies' => '*', // Trust all proxies (Railway uses dynamic IPs)

    /*
    |--------------------------------------------------------------------------
    | Trusted Headers
    |--------------------------------------------------------------------------
    |
    | These are the headers that proxies use to convey the original request
    | information. Railway will send these headers.
    |
    */
    'headers' => [
        'FORWARDED' => 'FORWARDED',
        'X_FORWARDED_FOR' => 'X_FORWARDED_FOR',
        'X_FORWARDED_HOST' => 'X_FORWARDED_HOST',
        'X_FORWARDED_PORT' => 'X_FORWARDED_PORT',
        'X_FORWARDED_PROTO' => 'X_FORWARDED_PROTO',
        'X_FORWARDED_AWS_ELB' => 'X_FORWARDED_AWS_ELB',
    ],
];
