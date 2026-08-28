<?php

use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\Update;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// A `nullable` rule means the key may be ABSENT from the request, not merely
// null. Laravel's validated() only returns keys that were actually present, so
// reading $validated['optional'] unconditionally throws "Undefined array key"
// and the request 500s. These endpoints all had that shape: the Vue forms
// initialise every field and always send it, so the crash was reachable only by
// a direct API call, which is exactly the sort of thing that stays broken for a
// year because no browser ever does it.

function optUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ], $attrs));
}

// ──────────────────────────────────────────────────────────────────────────────
// Kits - `description`
// ──────────────────────────────────────────────────────────────────────────────

test('a kit can be created without sending a description at all', function () {
    $user = optUser();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static',
    ]);

    $this->actingAs($user)
        ->post(route('kits.store'), [
            'title' => 'No description key',
            'is_public' => false,
            'template_ids' => [$template->id],
        ])
        ->assertRedirect();

    $kit = Kit::where('title', 'No description key')->first();

    expect($kit)->not->toBeNull()
        ->and($kit->description)->toBeNull();
});

test('a kit can be updated without sending a description at all', function () {
    $user = optUser();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static',
    ]);
    $kit = Kit::factory()->create([
        'owner_id' => $user->id, 'description' => 'was set before',
    ]);
    $kit->templates()->attach($template->id);

    $this->actingAs($user)
        ->put(route('kits.update', $kit), [
            'title' => 'Renamed',
            'is_public' => false,
            'template_ids' => [$template->id],
        ])
        ->assertRedirect();

    // PUT is a full replace here - every other field is required, so an omitted
    // description means "no description", not "leave it alone".
    expect($kit->fresh()->title)->toBe('Renamed')
        ->and($kit->fresh()->description)->toBeNull();
});

test('a description is still stored when one is sent', function () {
    $user = optUser();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static',
    ]);

    $this->actingAs($user)
        ->post(route('kits.store'), [
            'title' => 'Has one',
            'description' => 'a real description',
            'is_public' => false,
            'template_ids' => [$template->id],
        ])
        ->assertRedirect();

    expect(Kit::where('title', 'Has one')->first()->description)
        ->toBe('a real description');
});

// ──────────────────────────────────────────────────────────────────────────────
// Admin updates - `slug`
// ──────────────────────────────────────────────────────────────────────────────

test('an update can be created without sending a slug at all', function () {
    $this->actingAs(optUser(['role' => 'admin']))
        ->post(route('admin.updates.store'), [
            'title' => 'Shipped The Thing',
            'excerpt' => 'A short line.',
            'body' => 'Body copy.',
        ])
        ->assertRedirect();

    // An omitted slug falls back to deriving one from the title, which is what
    // the `?:` was there to do all along.
    expect(Update::where('title', 'Shipped The Thing')->first()->slug)
        ->toBe('shipped-the-thing');
});

test('an update can be edited without sending a slug at all', function () {
    $update = Update::create([
        'title' => 'Original', 'slug' => 'original', 'body' => 'Body copy.',
        'published_at' => now(),
    ]);

    $this->actingAs(optUser(['role' => 'admin']))
        ->put(route('admin.updates.update', $update), [
            'title' => 'Retitled',
            'excerpt' => 'A short line.',
            'body' => 'New body.',
        ])
        ->assertRedirect();

    // An omitted slug keeps the existing one: updates are linkable and indexed,
    // so a rename must not silently move the URL.
    expect($update->fresh()->slug)->toBe('original')
        ->and($update->fresh()->title)->toBe('Retitled');
});

test('an explicit slug still wins when one is sent', function () {
    $this->actingAs(optUser(['role' => 'admin']))
        ->post(route('admin.updates.store'), [
            'title' => 'Ignore This Title',
            'slug' => 'chosen-by-hand',
            'excerpt' => 'A short line.',
            'body' => 'Body copy.',
        ])
        ->assertRedirect();

    expect(Update::where('title', 'Ignore This Title')->first()->slug)
        ->toBe('chosen-by-hand');
});
