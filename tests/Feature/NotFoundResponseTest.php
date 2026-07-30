<?php

/**
 * A missing page must say so in the status line, not only in the body.
 *
 * Both fallbacks used to answer 200 with the HTML 404 page. The page read
 * "404 - Not Found" while the status line read success, and status is what
 * machines branch on: a crawler could not tell a live URL from a dead one,
 * and an agent following the "append .md to any help URL" convention that
 * llms.txt documents got 200 and an app shell for every wrong guess.
 */
it('returns a real 404 status for unknown web routes', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    expect($response->headers->get('content-type'))->toContain('text/html');
});

it('serves the 404 view body, not a blank response', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('404', escape: false);
});

it('returns JSON rather than the HTML page for unknown API routes', function () {
    // The web fallback would answer text/html here, so a client checking
    // response.ok saw 200 and then failed parsing `<!doctype html>`.
    $response = $this->getJson('/api/this-endpoint-does-not-exist');

    $response->assertNotFound()->assertJson(['message' => 'Not Found.']);
    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('keeps the API fallback scoped to the api prefix', function () {
    // Nested and top-level API paths both belong to the API fallback. Collected
    // into one array so a failure names the offending path instead of just the
    // index Pest would otherwise report from inside a loop.
    $types = collect(['/api/nope', '/api/overlay/nope', '/api/internal/nope'])
        ->mapWithKeys(fn ($path) => [
            $path => str_contains((string) $this->getJson($path)->headers->get('content-type'), 'application/json'),
        ]);

    expect($types->all())->toBe([
        '/api/nope' => true,
        '/api/overlay/nope' => true,
        '/api/internal/nope' => true,
    ]);

    // ...while a web path that merely starts with the letters "api" does not.
    expect($this->get('/apixnope')->headers->get('content-type'))
        ->toContain('text/html');
});

/**
 * An overlay URL carries its access token in the URL *fragment*, which browsers
 * never send. At request time the server genuinely cannot know whether the slug
 * is valid, so it must return the authenticate shell and let the client resolve
 * it. A 404 here would be a lie, and this test exists to stop a future tidy-up
 * from "fixing" it into one.
 */
it('does not 404 an overlay slug the server cannot yet verify', function () {
    $this->get('/overlay/some-slug-the-server-cannot-check')->assertOk();
});
