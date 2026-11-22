<?php

/**
 * Caddy's access log printed every request header in plain text: the bot's
 * X-Internal-Secret, the listeners' X-Listener-Secret, the front desk's
 * X-Front-Desk-Secret and Twitch's signatures. Caddy redacts Cookie and
 * Authorization on its own, never our headers. The fix drops request headers
 * from the log as a whole, so a secret header added later cannot leak either.
 * Validated against the prod binary (Caddy 2.11.9) with `frankenphp adapt`.
 */
test('the access log never records request headers', function () {
    $caddyfile = file_get_contents(base_path('docker/frankenphp.Caddyfile'));

    expect($caddyfile)
        ->toContain('format filter {')
        ->toMatch('/^\s*request>headers delete\s*$/m')
        ->not->toMatch('/^\s*format console\s*$/m');
});
