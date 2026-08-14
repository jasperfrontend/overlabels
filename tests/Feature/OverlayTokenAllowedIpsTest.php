<?php

use App\Models\OverlayAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The allowed-IP restriction on an overlay token is an EXACT address match:
 * `isValid()` does `in_array($clientIp, $this->allowed_ips)`. Range notation
 * never worked, and for four months the README advertised "a specific IP or
 * CIDR range" anyway.
 *
 * The saving grace is that `allowed_ips.*` validates with `ip`, so a range is
 * refused at creation rather than stored as a rule that silently matches
 * nothing. The help page at /help/tokens now promises exactly that, so these
 * tests pin the promise: loosening the rule to `ip_or_cidr` without teaching
 * `isValid()` to understand a range would make the docs lie and lock users out
 * of their own overlays.
 */
it('refuses a CIDR range in the allowed IP list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/tokens', [
            'name' => 'OBS',
            'allowed_ips' => ['203.0.113.0/24'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('allowed_ips.0');

    expect($user->overlayAccessTokens()->count())->toBe(0);
});

it('accepts exact addresses, v4 and v6 alike', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/tokens', [
            'name' => 'OBS',
            'allowed_ips' => ['203.0.113.7', '2001:db8::1'],
        ])
        ->assertOk();

    expect($user->overlayAccessTokens()->sole()->allowed_ips)
        ->toBe(['203.0.113.7', '2001:db8::1']);
});

it('matches only the exact address it was given', function () {
    $user = User::factory()->create();
    $generated = OverlayAccessToken::generateToken();

    $user->overlayAccessTokens()->create([
        'name' => 'Pinned',
        'token_hash' => $generated['hash'],
        'token_prefix' => $generated['prefix'],
        'allowed_ips' => ['203.0.113.7'],
    ]);

    // The neighbouring address is inside the /24 a user might have expected to
    // work, and is refused - which is the behaviour the help page describes.
    expect(OverlayAccessToken::findByToken($generated['plain'], '203.0.113.7'))->not->toBeNull()
        ->and(OverlayAccessToken::findByToken($generated['plain'], '203.0.113.8'))->toBeNull();
});

it('skips the restriction entirely when there is no client IP to check', function () {
    // `isValid()` guards the allowlist with `&& $clientIp`, so a caller that
    // passes nothing bypasses it. Worth pinning: it is the reason the help page
    // calls this a fixed-IP feature rather than a security boundary.
    $user = User::factory()->create();
    $generated = OverlayAccessToken::generateToken();

    $user->overlayAccessTokens()->create([
        'name' => 'Pinned',
        'token_hash' => $generated['hash'],
        'token_prefix' => $generated['prefix'],
        'allowed_ips' => ['203.0.113.7'],
    ]);

    expect(OverlayAccessToken::findByToken($generated['plain']))->not->toBeNull();
});
