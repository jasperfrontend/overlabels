<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// The logout target is '/', a plain Blade view rather than an Inertia page. A
// 302 to it gets followed by the XHR itself, handing Inertia a full HTML
// document it cannot parse - which then hit the httpException backstop in
// app.ts and hard-navigated the browser to the visit's own URL, GET /logout.
// There is no GET /logout, so the user landed on the fallback 404, logged out
// but stranded. Inertia::location() is what keeps that from happening.
test('logout answers an Inertia request with a full-page location visit', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->withHeader('X-Inertia', 'true')->post('/logout');

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toBe(url('/'));
    $this->assertGuest();
});

test('logout redirects a non-Inertia request to the homepage', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->post('/logout');

    $response->assertRedirect(url('/'));
    $this->assertGuest();
});

// GET /logout is the 404 the user actually saw. Asserting it stays absent is
// not the point - the point is that nothing should ever be sent there.
test('logout is POST only', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/logout')->assertNotFound();
});
