<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeBlockUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function makeTemplateOfType(User $user, string $type): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => $type,
        'slug' => $type.'-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Block type CRUD
// ──────────────────────────────────────────────────────────────────────────────

test('store accepts the block type and persists default span metadata', function () {
    $user = makeBlockUser();
    $this->actingAs($user);

    $response = $this->postJson('/templates', [
        'name' => 'Follower Counter Block',
        'html' => '<div class="counter">[[[followers_total]]]</div>',
        'type' => 'block',
        'is_public' => true,
        'metadata' => ['block' => ['default_span' => ['w' => 4, 'h' => 2]]],
    ]);

    $response->assertOk();

    $template = OverlayTemplate::where('owner_id', $user->id)->where('type', 'block')->first();
    expect($template)->not->toBeNull()
        ->and($template->metadata['block']['default_span'])->toBe(['w' => 4, 'h' => 2]);
});

test('store rejects unknown template types', function () {
    $user = makeBlockUser();
    $this->actingAs($user);

    $this->postJson('/templates', [
        'name' => 'Nope',
        'html' => '<div></div>',
        'type' => 'banana',
    ])->assertUnprocessable();
});

test('store strips unknown metadata keys', function () {
    $user = makeBlockUser();
    $this->actingAs($user);

    $this->postJson('/templates', [
        'name' => 'Sneaky Block',
        'html' => '<div></div>',
        'type' => 'block',
        'metadata' => [
            'block' => ['default_span' => ['w' => 2, 'h' => 2]],
            'sneaky' => ['payload' => 'nope'],
        ],
    ])->assertOk();

    $template = OverlayTemplate::where('owner_id', $user->id)->where('type', 'block')->first();
    expect(array_keys($template->metadata))->toBe(['block']);
});

test('store rejects an out of range default span', function () {
    $user = makeBlockUser();
    $this->actingAs($user);

    $this->postJson('/templates', [
        'name' => 'Too Wide',
        'html' => '<div></div>',
        'type' => 'block',
        'metadata' => ['block' => ['default_span' => ['w' => 99, 'h' => 2]]],
    ])->assertUnprocessable();
});

test('update accepts block metadata and leaves it untouched when not sent', function () {
    $user = makeBlockUser();
    $this->actingAs($user);
    $block = makeTemplateOfType($user, 'block');

    $this->putJson("/templates/{$block->id}", [
        'metadata' => ['block' => ['default_span' => ['w' => 6, 'h' => 3]]],
    ])->assertSuccessful();

    expect($block->fresh()->metadata['block']['default_span'])->toBe(['w' => 6, 'h' => 3]);

    // A save without metadata (the normal code-editor form) must not wipe it.
    $this->putJson("/templates/{$block->id}", [
        'name' => 'Renamed Block',
    ])->assertSuccessful();

    expect($block->fresh()->metadata['block']['default_span'])->toBe(['w' => 6, 'h' => 3]);
});

test('the block scope returns only blocks', function () {
    $user = makeBlockUser();
    makeTemplateOfType($user, 'static');
    makeTemplateOfType($user, 'alert');
    $block = makeTemplateOfType($user, 'block');

    $blocks = OverlayTemplate::block()->where('owner_id', $user->id)->get();

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->id)->toBe($block->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Blocks stay out of the static/alert machinery
// ──────────────────────────────────────────────────────────────────────────────

test('alert targeting rejects block templates as targets', function () {
    $user = makeBlockUser();
    $this->actingAs($user);
    $alert = makeTemplateOfType($user, 'alert');
    $block = makeTemplateOfType($user, 'block');

    $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [$block->id],
    ])->assertStatus(422);
});

test('the static overlay picker on the alert edit page excludes blocks', function () {
    $user = makeBlockUser();
    $this->actingAs($user);
    $alert = makeTemplateOfType($user, 'alert');
    $static = makeTemplateOfType($user, 'static');
    makeTemplateOfType($user, 'block');

    $this->get("/templates/{$alert->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('templates/edit')
            ->has('staticOverlays', 1)
            ->where('staticOverlays.0.id', $static->id)
    );
});

test('a block renders through the overlay pipeline for its owner', function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
    });

    $user = makeBlockUser();
    $user->update(['access_token' => 'fake-twitch-token']);
    $block = makeTemplateOfType($user, 'block');
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'block-render-test',
        'is_active' => true,
    ]);

    $this->postJson('/api/overlay/render', [
        'slug' => $block->slug,
        'token' => $plain,
    ])->assertOk()->assertJsonPath('template.html', $block->html);
});
