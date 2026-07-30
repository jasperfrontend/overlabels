<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A ban has to reach the overlay, not just the login.
 *
 * `CheckBanned` inspects the requester. An overlay render arrives from OBS with
 * an access token and no session, so `$request->user()` is null and a user ban
 * never fired: the banned streamer kept a working overlay on screen while shut
 * out of the site. Revoking the tokens closes it at the only identity that
 * request actually carries.
 */
function bannableOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);
}

function tokenFor(User $user): string
{
    $plain = bin2hex(random_bytes(32));

    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'test-token',
        'is_active' => true,
    ]);

    return $plain;
}

beforeEach(function () {
    $this->mock(TwitchTokenService::class, fn ($m) => $m->shouldReceive('ensureValidToken')->andReturnTrue());
    $this->mock(TwitchApiService::class, fn ($m) => $m->shouldReceive('getExtendedUserData')->andReturn([]));
});

it('kills a live overlay render the moment the owner is banned', function () {
    $owner = bannableOwner();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $owner->id,
        'is_public' => true,
        'fork_of_id' => null,
    ]);
    $plain = tokenFor($owner);

    $payload = ['slug' => $template->slug, 'token' => $plain];

    // Working before the ban, so a failure after it means the ban, not setup.
    $this->postJson('/api/overlay/render', $payload)->assertOk();

    $owner->ban();

    $this->postJson('/api/overlay/render', $payload)->assertUnauthorized();
});

it('deactivates every active token the banned user held', function () {
    $owner = bannableOwner();
    tokenFor($owner);
    tokenFor($owner);

    expect(OverlayAccessToken::where('user_id', $owner->id)->where('is_active', true)->count())->toBe(2);

    $owner->ban();

    expect(OverlayAccessToken::where('user_id', $owner->id)->where('is_active', true)->count())->toBe(0);
});

it('leaves other users tokens alone', function () {
    $banned = bannableOwner();
    $bystander = bannableOwner();
    tokenFor($banned);
    tokenFor($bystander);

    $banned->ban();

    expect(OverlayAccessToken::where('user_id', $bystander->id)->where('is_active', true)->count())->toBe(1);
});

it('destroys the banned user sessions on every ban path, including the CLI', function () {
    $owner = bannableOwner();

    DB::table('sessions')->insert([
        'id' => 'test-session-id',
        'user_id' => $owner->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    // Calling ->ban() directly is what routes/console.php does; it deletes no
    // sessions of its own, so this proves the listener covers that path.
    $owner->ban();

    expect(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0);
});

/**
 * Public templates outlive their author on purpose: one may have shipped as
 * part of a kit, and it is a thing people (and models) can still learn from.
 * This test exists so a later "clean up banned users' content" pass has to
 * argue with a stated decision rather than silently reverse it.
 */
it('keeps a banned users public template reachable to everyone else', function () {
    $owner = bannableOwner();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $owner->id,
        'is_public' => true,
        'fork_of_id' => null,
    ]);

    $owner->ban();

    $this->get("/overlay/{$template->slug}/public")->assertOk();
});
