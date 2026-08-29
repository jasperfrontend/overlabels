<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('dismissing a nudge remembers the key against the user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('nudges.dismiss', ['key' => 'lists-new-style']))
        ->assertRedirect();

    expect($user->fresh()->preference('dismissed_nudges'))->toBe(['lists-new-style']);
});

test('dismissing the same nudge twice is a no-op', function () {
    // The client fires the request without checking first, so a double click
    // or a second tab must not grow the list.
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('nudges.dismiss', ['key' => 'lists-new-style']));
    $this->post(route('nudges.dismiss', ['key' => 'lists-new-style']));

    expect($user->fresh()->preference('dismissed_nudges'))->toBe(['lists-new-style']);
});

test('dismissing a nudge leaves other preferences alone', function () {
    $user = User::factory()->create();
    $user->setPreference('locale', 'nl-NL')->save();
    $this->actingAs($user);

    $this->post(route('nudges.dismiss', ['key' => 'lists-new-style']));

    $user->refresh();
    expect($user->locale)->toBe('nl-NL');
    expect($user->preference('dismissed_nudges'))->toBe(['lists-new-style']);
});

test('the dismissed list is capped, keeping the newest keys', function () {
    $user = User::factory()->create();
    $existing = collect(range(1, User::MAX_DISMISSED_NUDGES))
        ->map(fn (int $n) => "nudge-$n")
        ->all();
    $user->setPreference('dismissed_nudges', $existing)->save();
    $this->actingAs($user);

    $this->post(route('nudges.dismiss', ['key' => 'brand-new']));

    $dismissed = $user->fresh()->preference('dismissed_nudges');
    expect($dismissed)->toHaveCount(User::MAX_DISMISSED_NUDGES)
        ->and($dismissed)->toContain('brand-new')
        ->and($dismissed)->not->toContain('nudge-1');
});

test('a key outside the allowed shape does not resolve to the route', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Uppercase, underscores and path separators are all rejected by the route
    // constraint, so nothing arbitrary can be written into preferences. The
    // status is 405 rather than 404 because the app's fallback handler is
    // GET-only - what matters is that no route accepted the write.
    $this->post('/nudges/Lists_New_Style/dismiss')->assertStatus(405);
    $this->post('/nudges/a%2Fb/dismiss')->assertStatus(405);

    expect($user->fresh()->preference('dismissed_nudges'))->toBe([]);
});

test('a guest cannot dismiss a nudge', function () {
    $this->post(route('nudges.dismiss', ['key' => 'lists-new-style']))
        ->assertRedirect();

    expect(auth()->check())->toBeFalse();
});

test('dismissed nudges are shared with every Inertia page', function () {
    // The NudgeBar can sit on any page, so it reads the shared prop rather than
    // one passed per page. If this stops being shared, every dismissed nudge
    // silently comes back.
    $user = User::factory()->create();
    $user->setPreference('dismissed_nudges', ['lists-new-style'])->save();

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertInertia(fn ($page) => $page->where('dismissedNudges', ['lists-new-style']));
});
