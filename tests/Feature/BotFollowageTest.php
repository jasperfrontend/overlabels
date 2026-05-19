<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'services.twitchbot.listener_secret' => 'test-bot-secret',
        'services.twitch.client_id' => 'test-client-id',
        'services.twitch.client_secret' => 'test-client-secret',
    ]);
    Cache::flush();
});

function followageOwner(string $login = 'streamer_b', string $twitchId = '99999999'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => $twitchId,
        'access_token' => 'valid-access-token',
        'refresh_token' => 'valid-refresh-token',
        'token_expires_at' => now()->addHour(),
    ]);
}

function postFollowage(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/followage',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

it('returns null reply when channel owner is not opted in', function () {
    Http::fake([
        'id.twitch.tv/oauth2/validate' => Http::response(['client_id' => 'test-client-id'], 200),
    ]);

    postFollowage([
        'channel_login' => 'ghost_channel',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
    ])->assertOk()->assertJson(['reply' => null]);
});

it('bounces the broadcaster querying their own follow date', function () {
    $owner = followageOwner('streamer_b', '99999999');
    Http::fake([
        'id.twitch.tv/oauth2/validate' => Http::response(['client_id' => 'test-client-id'], 200),
    ]);

    $reply = postFollowage([
        'channel_login' => 'streamer_b',
        'chatter_id' => $owner->twitch_id,
        'chatter_login' => 'streamer_b',
        'chatter_display_name' => 'Streamer_B',
    ])->assertOk()->json('reply');

    expect($reply)->toContain('own')->toContain('this channel');
});

it('reports follow age when the chatter follows the channel', function () {
    followageOwner('streamer_b', '99999999');

    Http::fake([
        'id.twitch.tv/oauth2/validate' => Http::response(['client_id' => 'test-client-id'], 200),
        'api.twitch.tv/helix/channels/followers*' => Http::response([
            'total' => 1,
            'data' => [[
                'user_id' => '11111',
                'user_login' => 'viewer',
                'user_name' => 'Viewer',
                'followed_at' => now()->subYears(2)->subMonths(3)->toIso8601String(),
            ]],
        ]),
    ]);

    $reply = postFollowage([
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'chatter_display_name' => 'Viewer',
    ])->assertOk()->json('reply');

    expect($reply)
        ->toContain('you have been following for')
        ->toContain('2 years')
        ->toContain('3 months');
});

it('reports not-following when Helix returns an empty data array', function () {
    followageOwner('streamer_b', '99999999');

    Http::fake([
        'id.twitch.tv/oauth2/validate' => Http::response(['client_id' => 'test-client-id'], 200),
        'api.twitch.tv/helix/channels/followers*' => Http::response(['total' => 0, 'data' => []]),
    ]);

    $reply = postFollowage([
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'chatter_display_name' => 'Viewer',
    ])->assertOk()->json('reply');

    expect($reply)->toBe("you don't follow this channel yet");
});

it('resolves target_login via Helix users when provided', function () {
    followageOwner('streamer_b', '99999999');

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
        'id.twitch.tv/oauth2/validate' => Http::response(['client_id' => 'test-client-id'], 200),
        'api.twitch.tv/helix/users*' => Http::response([
            'data' => [['id' => '77777', 'login' => 'targetuser', 'display_name' => 'TargetUser']],
        ]),
        'api.twitch.tv/helix/channels/followers*' => Http::response([
            'total' => 1,
            'data' => [[
                'user_id' => '77777',
                'user_login' => 'targetuser',
                'user_name' => 'TargetUser',
                'followed_at' => now()->subDays(5)->toIso8601String(),
            ]],
        ]),
    ]);

    $reply = postFollowage([
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'chatter_display_name' => 'Viewer',
        'target_login' => 'targetuser',
    ])->assertOk()->json('reply');

    expect($reply)
        ->toContain('@TargetUser has been following for')
        ->toContain('5 days');
});

it('returns "no twitch user" when target_login does not resolve', function () {
    followageOwner('streamer_b', '99999999');

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
        'api.twitch.tv/helix/users*' => Http::response(['data' => []]),
    ]);

    $reply = postFollowage([
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'target_login' => 'nobody_here',
    ])->assertOk()->json('reply');

    expect($reply)->toBe('no twitch user named @nobody_here');
});

it('rejects requests without the internal secret', function () {
    $this->postJson('/api/internal/bot/followage', [
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
    ])->assertStatus(403);
});
