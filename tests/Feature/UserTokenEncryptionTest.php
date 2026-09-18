<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

// users.access_token and users.refresh_token were the last credentials on the
// platform held in the clear, which meant every nightly database dump carried a
// live Twitch grant for every account. bot_tokens has been encrypted since
// April; this brings user tokens to the same footing.
//
// The equivalent assertion for the bot lives in BotInternalApiTest.

it('writes a twitch access token to the database encrypted', function () {
    $user = User::factory()->create(['access_token' => 'plaintext-access-token']);

    $stored = DB::table('users')->where('id', $user->id)->value('access_token');

    expect($stored)->not->toBe('plaintext-access-token')
        ->and($stored)->not->toContain('plaintext-access-token')
        ->and(Crypt::decryptString($stored))->toBe('plaintext-access-token');
});

it('writes a twitch refresh token to the database encrypted', function () {
    $user = User::factory()->create(['refresh_token' => 'plaintext-refresh-token']);

    $stored = DB::table('users')->where('id', $user->id)->value('refresh_token');

    expect($stored)->not->toBe('plaintext-refresh-token')
        ->and(Crypt::decryptString($stored))->toBe('plaintext-refresh-token');
});

it('reads both tokens back through the model unchanged', function () {
    $user = User::factory()->create([
        'access_token' => 'access-abc',
        'refresh_token' => 'refresh-xyz',
    ]);

    $fresh = User::find($user->id);

    expect($fresh->access_token)->toBe('access-abc')
        ->and($fresh->refresh_token)->toBe('refresh-xyz');
});

// The reason for a custom cast rather than Laravel's `encrypted`. During a
// rolling deploy an old container can still write plaintext; that row must
// degrade to "no token", which the app already handles by re-authorizing, and
// must not throw on every read for that user forever.
it('reads an undecryptable legacy value as null instead of throwing', function () {
    $user = User::factory()->create();

    DB::table('users')->where('id', $user->id)->update([
        'access_token' => 'oauth-token-from-before-the-migration',
        'refresh_token' => 'refresh-from-before-the-migration',
    ]);

    $fresh = User::find($user->id);

    expect($fresh->access_token)->toBeNull()
        ->and($fresh->refresh_token)->toBeNull();
});

it('stores null rather than an encrypted empty string when the token is cleared', function () {
    $user = User::factory()->create(['access_token' => 'something']);

    $user->update(['access_token' => null]);

    expect(DB::table('users')->where('id', $user->id)->value('access_token'))->toBeNull();
});

// The columns were varchar(255) on production. An encrypted token is roughly
// 200-260 characters and Postgres rejects an over-length varchar rather than
// truncating, so the migration widens them before it encrypts anything. If this
// ever regresses, logins break for everyone at once.
it('keeps the token columns wide enough to hold ciphertext', function () {
    $lengths = DB::table('information_schema.columns')
        ->where('table_name', 'users')
        ->whereIn('column_name', ['access_token', 'refresh_token'])
        ->pluck('character_maximum_length', 'column_name');

    expect($lengths['access_token'])->toBeNull()
        ->and($lengths['refresh_token'])->toBeNull();
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Postgres-specific column introspection');
