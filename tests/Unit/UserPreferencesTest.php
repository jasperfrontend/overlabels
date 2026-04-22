<?php

use App\Models\User;

test('preference falls back to defaults when key is unset', function () {
    $user = new User;
    $user->preferences = [];

    expect($user->preference('locale'))->toBe('en-US');
    expect($user->preference('foreach_caps.subscribers'))->toBe(10);
    expect($user->preference('foreach_caps.goals'))->toBe(3);
});

test('preference returns stored value when present', function () {
    $user = new User;
    $user->preferences = ['locale' => 'nl-NL', 'foreach_caps' => ['subscribers' => 25]];

    expect($user->preference('locale'))->toBe('nl-NL');
    expect($user->preference('foreach_caps.subscribers'))->toBe(25);
    // Unset sub-keys still fall back to defaults
    expect($user->preference('foreach_caps.goals'))->toBe(3);
});

test('setPreference writes nested keys without clobbering siblings', function () {
    $user = new User;
    $user->preferences = ['locale' => 'nl-NL'];

    $user->setPreference('foreach_caps.subscribers', 15);

    expect($user->preferences)->toBe([
        'locale' => 'nl-NL',
        'foreach_caps' => ['subscribers' => 15],
    ]);
});

test('locale accessor reads from preferences', function () {
    $user = new User;
    $user->preferences = ['locale' => 'de-DE'];

    expect($user->locale)->toBe('de-DE');
});

test('locale accessor falls back when preferences missing', function () {
    $user = new User;
    $user->preferences = null;

    expect($user->locale)->toBe('en-US');
});

test('foreachCaps clamps values below 1 to 1', function () {
    $user = new User;
    $user->preferences = ['foreach_caps' => ['subscribers' => 0, 'goals' => -5]];

    $caps = $user->foreachCaps();

    expect($caps['subscribers'])->toBe(1);
    expect($caps['goals'])->toBe(1);
});

test('foreachCaps clamps values above FOREACH_CAP_MAX', function () {
    $user = new User;
    $user->preferences = ['foreach_caps' => ['subscribers' => 9999]];

    expect($user->foreachCaps()['subscribers'])->toBe(User::FOREACH_CAP_MAX);
});

test('foreachCaps merges stored values over defaults', function () {
    $user = new User;
    $user->preferences = ['foreach_caps' => ['subscribers' => 20]];

    $caps = $user->foreachCaps();

    // Stored wins where present
    expect($caps['subscribers'])->toBe(20);
    // Defaults fill in the rest
    expect($caps['goals'])->toBe(3);
    expect($caps['followers'])->toBe(5);
    expect($caps['followed'])->toBe(5);
});

test('foreachCaps ignores unknown keys in preferences', function () {
    $user = new User;
    $user->preferences = ['foreach_caps' => ['subscribers' => 20, 'nonsense' => 999]];

    expect($user->foreachCaps())->not->toHaveKey('nonsense');
});
