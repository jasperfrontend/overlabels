<?php

use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function readApiToken(User $user): string
{
    $plain = str_repeat('a', 64);
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'read-api-test',
        'is_active' => true,
    ]);

    return $plain;
}

function readApiList(User $user, string $slug, array $values): OptionSet
{
    $built = ListItems::freshFromValues($values, 1);

    return OptionSet::factory()->create([
        'user_id' => $user->id,
        'slug' => $slug,
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
    ]);
}

it('returns the list as full item objects for a valid token', function () {
    $user = User::factory()->create();
    $token = readApiToken($user);
    readApiList($user, 'wheel', ['Pizza', 'Tacos']);

    $resp = $this->getJson("/api/lists/wheel?token={$token}")->assertOk();

    $resp->assertJsonPath('slug', 'wheel')
        ->assertJsonPath('count', 2)
        ->assertJsonPath('items.0.id', 1)
        ->assertJsonPath('items.0.value', 'Pizza')
        ->assertJsonPath('items.1.id', 2)
        ->assertJsonPath('items.1.value', 'Tacos');

    // Every item carries the full object shape.
    foreach ($resp->json('items') as $item) {
        expect($item)->toHaveKeys(['id', 'value', 'added_at', 'label', 'weight', 'color']);
    }
});

it('includes list metadata', function () {
    $user = User::factory()->create();
    $token = readApiToken($user);
    $list = readApiList($user, 'pool', ['a']);
    $list->update(['label' => 'My Pool', 'entry_ttl_seconds' => 300]);

    $this->getJson("/api/lists/pool?token={$token}")
        ->assertOk()
        ->assertJsonPath('label', 'My Pool')
        ->assertJsonPath('entry_ttl_seconds', 300)
        ->assertJsonPath('disabled_at', null);
});

it('refuses a missing or malformed token with 401', function () {
    $user = User::factory()->create();
    readApiToken($user);
    readApiList($user, 'wheel', ['a']);

    $this->getJson('/api/lists/wheel')->assertStatus(401);
    $this->getJson('/api/lists/wheel?token=tooshort')->assertStatus(401);
});

it('refuses an unknown token with 401', function () {
    $user = User::factory()->create();
    readApiToken($user);
    readApiList($user, 'wheel', ['a']);

    $this->getJson('/api/lists/wheel?token='.str_repeat('b', 64))->assertStatus(401);
});

it('scopes lookups to the token owner - cannot read another user\'s list', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    readApiList($owner, 'secret_donors', ['Alice']);
    $intruderToken = readApiToken($intruder);

    // The intruder's token is valid, but the slug belongs to someone else.
    $this->getJson("/api/lists/secret_donors?token={$intruderToken}")
        ->assertStatus(404);
});

it('returns 404 for a slug the owner does not have', function () {
    $user = User::factory()->create();
    $token = readApiToken($user);

    $this->getJson("/api/lists/nope?token={$token}")->assertStatus(404);
});

it('sends an open CORS header so a cross-origin browser fetch can read it', function () {
    $user = User::factory()->create();
    $token = readApiToken($user);
    readApiList($user, 'wheel', ['a']);

    $this->getJson("/api/lists/wheel?token={$token}", ['Origin' => 'https://my-wheel.example'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});
