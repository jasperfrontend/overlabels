<?php

use App\Models\AdminAuditLog;
use App\Models\Kit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function kitUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ], $attrs));
}

function kitAdmin(): User
{
    return kitUser(['role' => 'admin']);
}

function starterKit(bool $isStarter = false, ?User $owner = null): Kit
{
    return Kit::factory()->create([
        'owner_id' => ($owner ?? kitUser())->id,
        'forked_from_id' => null,
        'is_starter_kit' => $isStarter,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Access
// ──────────────────────────────────────────────────────────────────────────────

test('non-admins get a 404 on the kits page', function () {
    $this->actingAs(kitUser())
        ->get('/admin/kits')
        ->assertNotFound();
});

test('non-admins cannot set the starter kit', function () {
    $kit = starterKit();

    $this->actingAs(kitUser())
        ->post("/admin/kits/{$kit->id}/set-starter")
        ->assertNotFound();

    expect($kit->fresh()->is_starter_kit)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// Exactly one starter
// ──────────────────────────────────────────────────────────────────────────────

test('promoting a kit demotes the previous starter', function () {
    $old = starterKit(true);
    $new = starterKit();

    $this->actingAs(kitAdmin())
        ->post("/admin/kits/{$new->id}/set-starter")
        ->assertRedirect();

    expect($old->fresh()->is_starter_kit)->toBeFalse()
        ->and($new->fresh()->is_starter_kit)->toBeTrue();
});

test('promoting collapses several flagged kits down to one', function () {
    // Only reachable by editing the database directly, which is exactly the
    // state the admin screen has to be able to recover from.
    $a = starterKit(true);
    $b = starterKit(true);
    $c = starterKit(true);

    expect(Kit::where('is_starter_kit', true)->count())->toBe(3);

    $this->actingAs(kitAdmin())
        ->post("/admin/kits/{$b->id}/set-starter")
        ->assertRedirect();

    expect(Kit::where('is_starter_kit', true)->pluck('id')->all())->toBe([$b->id])
        ->and($a->fresh()->is_starter_kit)->toBeFalse()
        ->and($c->fresh()->is_starter_kit)->toBeFalse();
});

test('re-promoting the only starter leaves it as the only starter', function () {
    // The recovery button posts the same route for a kit that is already
    // flagged, so this must not clear the flag it just re-set.
    $kit = starterKit(true);

    $this->actingAs(kitAdmin())
        ->post("/admin/kits/{$kit->id}/set-starter")
        ->assertRedirect();

    expect($kit->fresh()->is_starter_kit)->toBeTrue()
        ->and(Kit::where('is_starter_kit', true)->count())->toBe(1);
});

test('a kit owned by the ghost user can still be demoted', function () {
    // Deleting a user can reassign their kits to the ghost user. Ownership must
    // not affect whether the starter flag can be moved off the kit.
    $ghost = kitUser(['twitch_id' => 'GHOST_USER_'.fake()->unique()->randomNumber(6), 'is_system_user' => true]);
    $ghostKit = starterKit(true, $ghost);
    $mine = starterKit();

    $this->actingAs(kitAdmin())
        ->post("/admin/kits/{$mine->id}/set-starter")
        ->assertRedirect();

    expect($ghostKit->fresh()->is_starter_kit)->toBeFalse()
        ->and($mine->fresh()->is_starter_kit)->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// The page carries what the UI needs to detect the broken state
// ──────────────────────────────────────────────────────────────────────────────

test('the kits page exposes is_starter_kit for every kit', function () {
    // The recovery action is rendered off a count of flagged kits, so the flag
    // has to survive the controller's explicit column select.
    starterKit(true);
    starterKit(true);
    starterKit();

    $this->actingAs(kitAdmin())
        ->get('/admin/kits')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/kits/index')
                ->has('kits', 3)
                ->where('kits.0.is_starter_kit', true)
        );
});

// ──────────────────────────────────────────────────────────────────────────────
// Audit
// ──────────────────────────────────────────────────────────────────────────────

test('the audit entry records every kit the change cleared', function () {
    $a = starterKit(true);
    $b = starterKit(true);
    $new = starterKit();

    $this->actingAs(kitAdmin())
        ->post("/admin/kits/{$new->id}/set-starter")
        ->assertRedirect();

    $log = AdminAuditLog::where('action', 'kit.starter_kit_changed')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['new_kit_id'])->toBe($new->id)
        ->and(collect($log->metadata['cleared_kits'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});
