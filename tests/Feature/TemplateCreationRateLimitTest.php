<?php

use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;

uses(DatabaseTransactions::class);

// Every route here writes an overlay_templates row and burns a slug. Until
// Aug 2026 none of them were limited at all: the only guard was "are you logged
// in", and every other limiter in AppServiceProvider faces the overlay-serving
// or bot-ingress side. These tests exist so that cannot silently come back.

beforeEach(function () {
    RateLimiter::clear('template-write');
    RateLimiter::clear('kit-fork');
});

function rlUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ], $attrs));
}

function templatePayload(): array
{
    return ['name' => 'Test overlay', 'html' => '<div></div>', 'type' => 'static'];
}

// ──────────────────────────────────────────────────────────────────────────────
// templates.store
// ──────────────────────────────────────────────────────────────────────────────

test('creating templates is rate limited', function () {
    $this->actingAs(rlUser());

    // The limiter allows 30/minute per user; the 31st is the one that must fail.
    foreach (range(1, 30) as $i) {
        $this->post(route('templates.store'), templatePayload())->assertRedirect();
    }

    $this->post(route('templates.store'), templatePayload())->assertStatus(429);

    expect(OverlayTemplate::count())->toBe(30);
});

test('the template limit is per user, so one account cannot starve another', function () {
    $this->actingAs($first = rlUser());
    foreach (range(1, 30) as $i) {
        $this->post(route('templates.store'), templatePayload())->assertRedirect();
    }
    $this->post(route('templates.store'), templatePayload())->assertStatus(429);

    // A different account is unaffected by the first one hitting its ceiling.
    $this->actingAs(rlUser());
    $this->post(route('templates.store'), templatePayload())->assertRedirect();

    expect(OverlayTemplate::where('owner_id', $first->id)->count())->toBe(30)
        ->and(OverlayTemplate::count())->toBe(31);
});

// ──────────────────────────────────────────────────────────────────────────────
// templates.fork
// ──────────────────────────────────────────────────────────────────────────────

test('copying a template shares the same budget as creating one', function () {
    $this->actingAs(rlUser());
    $source = OverlayTemplate::factory()->create([
        'owner_id' => rlUser()->id, 'fork_of_id' => null, 'is_public' => true, 'type' => 'static',
    ]);

    // Spend the whole minute budget on stores, then a copy must still be refused:
    // both routes draw on one bucket, so they cannot be used to double the rate.
    foreach (range(1, 30) as $i) {
        $this->post(route('templates.store'), templatePayload())->assertRedirect();
    }

    $this->post(route('templates.fork', $source))->assertStatus(429);
});

// ──────────────────────────────────────────────────────────────────────────────
// kits.fork - the amplified path
// ──────────────────────────────────────────────────────────────────────────────

test('forking a kit is limited far more tightly, because one request writes many rows', function () {
    $owner = rlUser();
    $kit = Kit::factory()->create(['owner_id' => $owner->id, 'is_public' => true]);
    $kit->templates()->attach(
        OverlayTemplate::factory()->count(3)->create([
            'owner_id' => $owner->id, 'fork_of_id' => null, 'type' => 'static',
        ])->pluck('id')
    );

    $this->actingAs(rlUser());

    // 3/minute, so the fourth fails. Each success writes 3 template rows.
    foreach (range(1, 3) as $i) {
        $this->post(route('kits.fork', $kit))->assertRedirect();
    }

    $this->post(route('kits.fork', $kit))->assertStatus(429);
});

test('kit forking has its own bucket and does not consume the template budget', function () {
    $owner = rlUser();
    $kit = Kit::factory()->create(['owner_id' => $owner->id, 'is_public' => true]);
    $kit->templates()->attach(
        OverlayTemplate::factory()->create([
            'owner_id' => $owner->id, 'fork_of_id' => null, 'type' => 'static',
        ])->id
    );

    $this->actingAs(rlUser());

    foreach (range(1, 3) as $i) {
        $this->post(route('kits.fork', $kit))->assertRedirect();
    }
    $this->post(route('kits.fork', $kit))->assertStatus(429);

    // Exhausting kit-fork must not lock someone out of ordinary authoring.
    $this->post(route('templates.store'), templatePayload())->assertRedirect();
});

// ──────────────────────────────────────────────────────────────────────────────
// The amplifier itself
// ──────────────────────────────────────────────────────────────────────────────

test('a kit cannot hold more templates than the fork limit assumes', function () {
    $user = rlUser();
    $ids = OverlayTemplate::factory()->count(Kit::MAX_TEMPLATES + 1)->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static',
    ])->pluck('id')->all();

    // Without this ceiling, kits.fork has an unbounded per-request cost and no
    // per-request throttle can cap it.
    $this->actingAs($user)
        ->post(route('kits.store'), [
            'title' => 'Oversized', 'description' => '', 'is_public' => false, 'template_ids' => $ids,
        ])
        ->assertSessionHasErrors('template_ids');

    expect(Kit::where('title', 'Oversized')->exists())->toBeFalse();
});

test('a kit at exactly the ceiling is still allowed', function () {
    $user = rlUser();
    $ids = OverlayTemplate::factory()->count(Kit::MAX_TEMPLATES)->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static',
    ])->pluck('id')->all();

    $this->actingAs($user)
        ->post(route('kits.store'), [
            'title' => 'At the line', 'description' => '', 'is_public' => false, 'template_ids' => $ids,
        ])
        ->assertSessionHasNoErrors();

    expect(Kit::where('title', 'At the line')->first()->templates()->count())
        ->toBe(Kit::MAX_TEMPLATES);
});
