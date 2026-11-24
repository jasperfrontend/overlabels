<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * /admin/users shows each account's Twitch follower total, refreshed daily by
 * `twitch:refresh-follower-counts`, so the channels that could drop a big
 * raid's follow flood on the platform are visible before it happens (#343).
 */
function followerAccount(string $twitchId, array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => $twitchId,
        'access_token' => 'token-'.$twitchId,
        'token_expires_at' => now()->addHour(),
    ], $attrs));
}

test('the refresh stores each account\'s follower total', function () {
    Http::fake([
        'api.twitch.tv/helix/channels/followers*' => fn ($request) => Http::response([
            'total' => $request['broadcaster_id'] === '111' ? 55000 : 12,
            'data' => [],
        ]),
    ]);
    $big = followerAccount('111');
    $small = followerAccount('222');

    $this->artisan('twitch:refresh-follower-counts')->assertSuccessful();

    expect($big->fresh()->twitch_followers_total)->toBe(55000)
        ->and($small->fresh()->twitch_followers_total)->toBe(12)
        ->and($big->fresh()->twitch_followers_checked_at)->not->toBeNull();
});

test('an account Twitch will not answer for keeps its last known total', function () {
    Http::fake(['api.twitch.tv/helix/channels/followers*' => Http::response(['message' => 'Unauthorized'], 401)]);
    $user = followerAccount('333', ['twitch_followers_total' => 900, 'twitch_followers_checked_at' => now()->subDay()]);

    $this->artisan('twitch:refresh-follower-counts')->assertSuccessful();

    expect($user->fresh()->twitch_followers_total)->toBe(900);
});

test('the system user is never looked up', function () {
    Http::fake(['api.twitch.tv/helix/channels/followers*' => Http::response(['total' => 5, 'data' => []])]);
    followerAccount('GHOST_USER', ['is_system_user' => true]);

    $this->artisan('twitch:refresh-follower-counts')->assertSuccessful();

    Http::assertNothingSent();
});

test('the admin users list shows totals and can put the biggest channels first', function () {
    $admin = followerAccount('900', ['role' => 'admin']);
    followerAccount('901', ['twitch_followers_total' => 120]);
    followerAccount('902', ['twitch_followers_total' => 48000]);
    followerAccount('903');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['sort' => 'followers']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'followers')
            ->where('users.data.0.twitch_followers_total', 48000)
            ->where('users.data.1.twitch_followers_total', 120)
        );
});
