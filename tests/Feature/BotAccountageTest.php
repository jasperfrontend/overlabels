<?php

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

function postAccountage(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/accountage',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

it('reports the chatter own account age via id lookup', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
        'api.twitch.tv/helix/users*' => Http::response([
            'data' => [[
                'id' => '11111',
                'login' => 'viewer',
                'display_name' => 'Viewer',
                'created_at' => now()->subYears(7)->subMonths(2)->toIso8601String(),
            ]],
        ]),
    ]);

    $reply = postAccountage([
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'chatter_display_name' => 'Viewer',
    ])->assertOk()->json('reply');

    expect($reply)
        ->toContain('your account was created')
        ->toContain('7 years')
        ->toContain('2 months')
        ->toContain('ago');
});

it('reports a target user account age via login lookup', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
        'api.twitch.tv/helix/users*' => Http::response([
            'data' => [[
                'id' => '77777',
                'login' => 'targetuser',
                'display_name' => 'TargetUser',
                'created_at' => now()->subMonths(4)->toIso8601String(),
            ]],
        ]),
    ]);

    $reply = postAccountage([
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'chatter_display_name' => 'Viewer',
        'target_login' => 'targetuser',
    ])->assertOk()->json('reply');

    expect($reply)
        ->toContain("@TargetUser's account was created")
        ->toContain('4 months')
        ->toContain('ago');
});

it('returns "no twitch user" when target_login does not resolve', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
        'api.twitch.tv/helix/users*' => Http::response(['data' => []]),
    ]);

    $reply = postAccountage([
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
        'target_login' => 'nobody_here',
    ])->assertOk()->json('reply');

    expect($reply)->toBe('no twitch user named @nobody_here');
});

it('rejects requests without the internal secret', function () {
    $this->postJson('/api/internal/bot/accountage', [
        'chatter_id' => '11111',
        'chatter_login' => 'viewer',
    ])->assertStatus(403);
});
