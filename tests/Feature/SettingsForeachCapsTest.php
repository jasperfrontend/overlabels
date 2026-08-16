<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('PATCH /settings/foreach-caps saves every cap to preferences', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 15,
        'goals' => 5,
        'followers' => 8,
        'followed' => 4,
        'chat' => 12,
    ]);

    $response->assertRedirect();
    $user->refresh();

    expect($user->foreachCaps())->toBe([
        'subscribers' => 15,
        'goals' => 5,
        'followers' => 8,
        'followed' => 4,
        'chat' => 12,
    ]);
});

test('every declared cap is settable through the endpoint', function () {
    // Structural, so a cap added to PREFERENCE_DEFAULTS but never wired into
    // the route or the settings UI fails here rather than silently being
    // unreachable. The route builds its rules from the same constant, so the
    // failure this catches is a cap the FRONTEND never sends.
    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = collect(array_keys(User::PREFERENCE_DEFAULTS['foreach_caps']))
        ->mapWithKeys(fn (string $key) => [$key => 7])
        ->all();

    $this->patch(route('settings.foreach-caps'), $payload)->assertRedirect();

    expect($user->fresh()->foreachCaps())
        ->each->toBe(7);
});

test('PATCH /settings/foreach-caps rejects values above FOREACH_CAP_MAX', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 100,
        'goals' => 3,
        'followers' => 5,
        'followed' => 5,
    ]);

    $response->assertSessionHasErrors(['subscribers']);
});

test('PATCH /settings/foreach-caps rejects values below 1', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 0,
        'goals' => 3,
        'followers' => 5,
        'followed' => 5,
    ]);

    $response->assertSessionHasErrors(['subscribers']);
});

test('PATCH /settings/foreach-caps requires every key', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 10,
    ]);

    $response->assertSessionHasErrors(['goals', 'followers', 'followed', 'chat']);
});

test('PATCH /settings/foreach-caps preserves locale on save', function () {
    $user = User::factory()->create();
    $user->setPreference('locale', 'nl-NL')->save();
    $this->actingAs($user);

    $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 12,
        'goals' => 3,
        'followers' => 5,
        'followed' => 5,
        'chat' => 50,
    ]);

    $user->refresh();
    expect($user->locale)->toBe('nl-NL');
    expect($user->foreachCaps()['subscribers'])->toBe(12);
});

test('PATCH /settings/locale writes to preferences instead of a column', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->patch(route('settings.locale'), ['locale' => 'de-DE']);

    $user->refresh();
    expect($user->locale)->toBe('de-DE');
    expect($user->preferences)->toHaveKey('locale', 'de-DE');
});
