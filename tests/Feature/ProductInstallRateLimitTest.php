<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;

uses(DatabaseTransactions::class);

// products.install used to share kit-fork's bucket (3/min, 10/hour), sized for
// kit-fork's per-request amplification. Installing a product has none - it's
// one recipe install, already idempotent-guarded - so it got its own bucket,
// sized off the products page instead: see AppServiceProvider::boot().

beforeEach(function () {
    RateLimiter::clear('product-install');
    RateLimiter::clear('kit-fork');
    RateLimiter::clear('template-write');
});

function ratelimitUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

test('installing products is limited to 20 per minute', function () {
    $this->actingAs(ratelimitUser());

    // The first call installs ko-fi-alerts; the rest redirect straight back
    // (already installed) but still spend the bucket - the limiter runs
    // before the controller's idempotency check does.
    foreach (range(1, 20) as $i) {
        $this->post('/products/ko-fi-alerts/install')->assertRedirect();
    }

    $this->post('/products/ko-fi-alerts/install')->assertStatus(429);
});

test('installing products is limited to 60 per hour even spread across minutes', function () {
    $this->actingAs(ratelimitUser());

    // Stay under the per-minute cap (20) but cross three separate minute
    // windows, so it's the hourly bucket - not the per-minute one - that
    // trips on the 61st request.
    foreach (range(1, 3) as $minute) {
        $this->travel(61)->seconds();
        foreach (range(1, 20) as $i) {
            $this->post('/products/ko-fi-alerts/install')->assertRedirect();
        }
    }

    $this->post('/products/ko-fi-alerts/install')->assertStatus(429);
});

test('installing products has its own bucket, separate from kit-fork and template-write', function () {
    $this->actingAs(ratelimitUser());

    foreach (range(1, 20) as $i) {
        $this->post('/products/ko-fi-alerts/install')->assertRedirect();
    }
    $this->post('/products/ko-fi-alerts/install')->assertStatus(429);

    // Exhausting product-install must not lock someone out of ordinary
    // authoring - the two routes used to share kit-fork's bucket.
    $this->post(route('templates.store'), ['name' => 'Test overlay', 'html' => '<div></div>', 'type' => 'static'])
        ->assertRedirect();
});
