<?php

use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * A List with an expiry date ships a countdown timer the renderer ticks four
 * times a second, and every tick rewrites the overlay's data. It used to go
 * to every overlay the account had, whether the overlay showed it or not, so
 * one expiring List kept every overlay re-rendering forever. It now ships
 * only to an overlay that reads it.
 */

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function countdownUser(): User
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);

    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'raffle',
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'max_items' => null,
        'user_editable' => true,
        'expires_at' => now()->addHour(),
    ]);

    return $user;
}

function countdownToken(User $user): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'countdown-test',
        'is_active' => true,
    ]);

    return $plain;
}

function countdownOverlay(User $user, string $html): OverlayTemplate
{
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'head' => '',
        'html' => $html,
        'css' => '',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
    $template->update(['template_tags' => $template->extractTemplateTags($user->foreachCaps())]);

    return $template;
}

test('an overlay that shows a List countdown gets its timer', function () {
    $user = countdownUser();
    $overlay = countdownOverlay($user, '<div>[[[c:list:raffle:countdown|duration:mm:ss]]]</div>');

    $timers = $this->postJson('/api/overlay/render', ['slug' => $overlay->slug, 'token' => countdownToken($user)])
        ->assertOk()
        ->json('timer_states');

    expect($timers)->toHaveKey('list:raffle:countdown')
        ->and($timers['list:raffle:countdown']['mode'])->toBe('countto');
});

test('an overlay that never mentions the countdown gets no timer for it', function () {
    $user = countdownUser();
    $overlay = countdownOverlay($user, '<div>[[[c:list:raffle:count]]] in the raffle</div>');

    $response = $this->postJson('/api/overlay/render', ['slug' => $overlay->slug, 'token' => countdownToken($user)])
        ->assertOk();

    expect((array) $response->json('timer_states'))->not->toHaveKey('list:raffle:countdown')
        // The List itself still rides along as it always did; only the ticking timer is gone.
        ->and($response->json('data')['c:list:raffle:expires_at'])->not->toBe('');
});
