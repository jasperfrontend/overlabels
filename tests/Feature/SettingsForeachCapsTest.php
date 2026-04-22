<?php

use App\Models\User;

test('PATCH /settings/foreach-caps saves all four caps to preferences', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 15,
        'goals' => 5,
        'followers' => 8,
        'followed' => 4,
    ]);

    $response->assertRedirect();
    $user->refresh();

    expect($user->foreachCaps())->toBe([
        'subscribers' => 15,
        'goals' => 5,
        'followers' => 8,
        'followed' => 4,
    ]);
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

test('PATCH /settings/foreach-caps requires all four keys', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch(route('settings.foreach-caps'), [
        'subscribers' => 10,
    ]);

    $response->assertSessionHasErrors(['goals', 'followers', 'followed']);
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
