<?php

use App\Models\User;
use App\Models\UserFreesoundSound;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.freesound.api_key' => 'test-api-key']);
});

function fakeSoundPayload(int $id, string $license = 'Creative Commons 0'): array
{
    return [
        'id' => $id,
        'name' => "Test sound $id",
        'username' => 'someone',
        'license' => $license,
        'duration' => 1.5,
        'url' => "https://freesound.org/people/someone/sounds/$id/",
        'previews' => [
            'preview-hq-mp3' => "https://cdn.freesound.org/previews/$id/sound-hq.mp3",
            'preview-lq-mp3' => "https://cdn.freesound.org/previews/$id/sound-lq.mp3",
        ],
    ];
}

test('search proxies Freesound with the commercial-safe license filter', function () {
    Http::fake(function () {
        return Http::response([
            'count' => 1,
            'results' => [fakeSoundPayload(101)],
        ], 200);
    });

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/freesound/search?q=coin');

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('results.0.id', 101)
        ->assertJsonPath('results.0.preview_url', 'https://cdn.freesound.org/previews/101/sound-hq.mp3');

    Http::assertSent(function ($request) {
        $url = urldecode((string) $request->url());

        return str_contains($url, 'freesound.org/apiv2/search/text/')
            && str_contains($url, 'Creative Commons 0')
            && str_contains($url, 'Attribution')
            && $request->hasHeader('Authorization', 'Token test-api-key');
    });
});

test('search requires authentication', function () {
    $response = $this->getJson('/freesound/search?q=coin');

    $response->assertStatus(401);
});

test('save adds a sound to the user library', function () {
    Http::fake(fn () => Http::response(fakeSoundPayload(202), 200));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/freesound/library', ['freesound_id' => 202]);

    $response->assertOk();
    $this->assertDatabaseHas('user_freesound_sounds', [
        'user_id' => $user->id,
        'freesound_id' => 202,
        'license' => 'Creative Commons 0',
        'preview_url' => 'https://cdn.freesound.org/previews/202/sound-hq.mp3',
    ]);
});

test('save is idempotent on the same freesound_id', function () {
    Http::fake(fn () => Http::response(fakeSoundPayload(303), 200));

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/freesound/library', ['freesound_id' => 303])->assertOk();
    $this->postJson('/freesound/library', ['freesound_id' => 303])->assertOk();

    expect(UserFreesoundSound::where('user_id', $user->id)->count())->toBe(1);
});

test('save rejects non-commercial-safe licenses', function () {
    Http::fake(fn () => Http::response(
        fakeSoundPayload(404, 'Attribution NonCommercial'),
        200
    ));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/freesound/library', ['freesound_id' => 404]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Sound has a non-commercial license and cannot be saved. Licence: Attribution NonCommercial']);
    $this->assertDatabaseMissing('user_freesound_sounds', ['freesound_id' => 404]);
});

test('save accepts CC0 URL-format licenses (real API shape)', function () {
    Http::fake(fn () => Http::response(
        fakeSoundPayload(700, 'http://creativecommons.org/publicdomain/zero/1.0/'),
        200
    ));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/freesound/library', ['freesound_id' => 700]);

    $response->assertOk();
    $this->assertDatabaseHas('user_freesound_sounds', [
        'user_id' => $user->id,
        'freesound_id' => 700,
    ]);
});

test('save accepts CC-BY URL-format licenses', function () {
    Http::fake(fn () => Http::response(
        fakeSoundPayload(701, 'https://creativecommons.org/licenses/by/4.0/'),
        200
    ));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/freesound/library', ['freesound_id' => 701]);

    $response->assertOk();
    $this->assertDatabaseHas('user_freesound_sounds', [
        'user_id' => $user->id,
        'freesound_id' => 701,
    ]);
});

test('save rejects URL-format NC licenses', function () {
    Http::fake(fn () => Http::response(
        fakeSoundPayload(702, 'http://creativecommons.org/licenses/by-nc/4.0/'),
        200
    ));

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/freesound/library', ['freesound_id' => 702]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('user_freesound_sounds', ['freesound_id' => 702]);
});

test('save rejects beyond the 10-sound cap', function () {
    Http::fake(fn () => Http::response(fakeSoundPayload(999), 200));

    $user = User::factory()->create();
    $this->actingAs($user);

    for ($i = 1; $i <= 10; $i++) {
        UserFreesoundSound::create([
            'user_id' => $user->id,
            'freesound_id' => $i,
            'name' => "Sound $i",
            'author' => 'tester',
            'license' => 'Creative Commons 0',
            'preview_url' => "https://example.com/$i.mp3",
        ]);
    }

    $response = $this->postJson('/freesound/library', ['freesound_id' => 999]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Your sound library is full (10 sounds). Remove one before adding another.']);
});

test('destroy removes a sound owned by the user', function () {
    $user = User::factory()->create();
    $sound = UserFreesoundSound::create([
        'user_id' => $user->id,
        'freesound_id' => 555,
        'name' => 'Removable',
        'author' => 'tester',
        'license' => 'Creative Commons 0',
        'preview_url' => 'https://example.com/555.mp3',
    ]);
    $this->actingAs($user);

    $this->deleteJson("/freesound/library/{$sound->id}")->assertOk();

    $this->assertDatabaseMissing('user_freesound_sounds', ['id' => $sound->id]);
});

test('destroy returns 404 when sound belongs to another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sound = UserFreesoundSound::create([
        'user_id' => $owner->id,
        'freesound_id' => 666,
        'name' => 'Not yours',
        'author' => 'tester',
        'license' => 'Creative Commons 0',
        'preview_url' => 'https://example.com/666.mp3',
    ]);
    $this->actingAs($intruder);

    $this->deleteJson("/freesound/library/{$sound->id}")->assertStatus(404);

    $this->assertDatabaseHas('user_freesound_sounds', ['id' => $sound->id]);
});

test('search returns 502 when API key is missing', function () {
    config(['services.freesound.api_key' => null]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/freesound/search?q=coin');

    $response->assertStatus(502);
});
