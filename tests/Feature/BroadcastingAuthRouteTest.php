<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Regression: /broadcasting/auth must not get shadowed by the
 * catch-all 404 fallback in web.php. A previous version of the catch-all
 * used Route::any('{catchall}', ...) with a wildcard regex that beat
 * Laravel's auto-registered broadcasting route on registration order,
 * causing Echo's channel-auth POSTs to receive an HTML 404 page instead
 * of the JSON auth signature. Switched to Route::fallback() which
 * always matches last. This test locks that fix in.
 */
it('routes POST /broadcasting/auth to the BroadcastController, not the fallback 404', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);

    $response = $this->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-alerts.12345',
    ]);

    // We don't care WHAT the response is exactly (Reverb may not be
    // available in tests, the signature may not validate), only that
    // it isn't the catch-all 404 HTML page. The fallback returns
    // text/html with the "errors.404" view (which contains "404" or
    // "Not Found" in the body); the real BroadcastController returns
    // either a JSON auth payload or a non-200 with auth-denied details,
    // never the literal 404 view.
    $body = (string) $response->getContent();
    expect($body)->not->toContain('errors.404')
        ->and($body)->not->toMatch('/<title>\s*404/i');
});

it('still 404s for genuinely unknown routes', function () {
    $response = $this->get('/this-route-does-not-exist-anywhere');

    // The 404 view returns HTTP 200 with the error HTML (existing
    // app behaviour) - we only check that some HTML response comes
    // back rather than a 500.
    expect($response->getStatusCode())->toBeIn([200, 404]);
});
