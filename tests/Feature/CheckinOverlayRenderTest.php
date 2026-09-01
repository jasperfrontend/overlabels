<?php

use App\Models\Checkin;
use App\Models\ExternalIntegration;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\StreamSession;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);

    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function connectCheckinIntegration(User $user, string $pinLifetime): ExternalIntegration
{
    return ExternalIntegration::create([
        'user_id' => $user->id,
        'service' => 'checkin',
        'enabled' => true,
        'settings' => ['pin_lifetime' => $pinLifetime],
    ]);
}

function seedPin(User $user, string $chatterId, string $login, string $place, ?Carbon\Carbon $at = null): Checkin
{
    return Checkin::create([
        'user_id' => $user->id,
        'chatter_twitch_id' => $chatterId,
        'chatter_login' => $login,
        'chatter_display_name' => strtoupper($login),
        'place_label' => $place,
        'country_code' => 'NL',
        'lat' => 51.9225,
        'lng' => 4.47917,
        'checked_in_at' => $at ?? now(),
    ]);
}

function renderCheckinOverlay(User $user, string $html): TestResponse
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'checkin-render-test',
        'is_active' => true,
    ]);

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => $html,
        'slug' => 'checkin-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    // The controller extracts tags at store/import/update; a factory create
    // bypasses that, so run the real extraction the render path relies on.
    $template->template_tags = $template->extractTemplateTags();
    $template->save();

    return test()->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain]);
}

const CHECKIN_FOREACH_HTML = '<div>[[[foreach:checkins as pin]]][[[pin.name]]] [[[pin.place]]][[[endforeach]]]</div>';

test('a template that loops checkins gets the window and the cap', function () {
    connectCheckinIntegration($this->user, 'persistent');
    seedPin($this->user, '1', 'viewer_one', 'Rotterdam, NL', now()->subMinutes(2));
    seedPin($this->user, '2', 'viewer_two', 'Paris, FR', now()->subMinute());

    $response = renderCheckinOverlay($this->user, CHECKIN_FOREACH_HTML)->assertOk();

    $data = $response->json('data');
    expect($data['checkins.count'])->toBe('2')
        // Index 0 = newest.
        ->and($data['checkins.0.login'])->toBe('viewer_two')
        ->and($data['checkins.0.place'])->toBe('Paris, FR')
        ->and($data['checkins.1.login'])->toBe('viewer_one')
        ->and($response->json('checkins_window'))->toBe(50);
});

test('a template without a checkins loop ships no checkin data', function () {
    connectCheckinIntegration($this->user, 'persistent');
    seedPin($this->user, '1', 'viewer_one', 'Rotterdam, NL');

    $response = renderCheckinOverlay($this->user, '<div>[[[channel_name]]]</div>')->assertOk();

    expect($response->json('data'))->not->toHaveKey('checkins.count');
});

test('per_stream mode with no open session is an empty window', function () {
    connectCheckinIntegration($this->user, 'per_stream');
    seedPin($this->user, '1', 'viewer_one', 'Rotterdam, NL');

    $response = renderCheckinOverlay($this->user, CHECKIN_FOREACH_HTML)->assertOk();

    $data = $response->json('data');
    expect($data['checkins.count'])->toBe('0')
        ->and($data)->not->toHaveKey('checkins.0.login');
});

test('per_stream mode only shows pins made since the stream started', function () {
    connectCheckinIntegration($this->user, 'per_stream');
    seedPin($this->user, '1', 'old_viewer', 'Rotterdam, NL', now()->subHours(5));
    StreamSession::create(['user_id' => $this->user->id, 'started_at' => now()->subHour(), 'ended_at' => null]);
    seedPin($this->user, '2', 'live_viewer', 'Paris, FR', now()->subMinutes(10));

    $data = renderCheckinOverlay($this->user, CHECKIN_FOREACH_HTML)->assertOk()->json('data');

    expect($data['checkins.count'])->toBe('1')
        ->and($data['checkins.0.login'])->toBe('live_viewer')
        ->and($data)->not->toHaveKey('checkins.1.login');
});

test('the foreach cap slices the pins but never the count', function () {
    connectCheckinIntegration($this->user, 'persistent');
    $this->user->setPreference('foreach_caps.checkins', 2)->save();
    seedPin($this->user, '1', 'viewer_one', 'Rotterdam, NL', now()->subMinutes(3));
    seedPin($this->user, '2', 'viewer_two', 'Paris, FR', now()->subMinutes(2));
    seedPin($this->user, '3', 'viewer_three', 'Tokyo, JP', now()->subMinute());

    $response = renderCheckinOverlay($this->user, CHECKIN_FOREACH_HTML)->assertOk();

    $data = $response->json('data');
    expect($data['checkins.count'])->toBe('3')
        ->and($data['checkins.0.login'])->toBe('viewer_three')
        ->and($data['checkins.1.login'])->toBe('viewer_two')
        ->and($data)->not->toHaveKey('checkins.2.login')
        ->and($response->json('checkins_window'))->toBe(2);
});

test('no integration renders an empty window rather than erroring', function () {
    seedPin($this->user, '1', 'viewer_one', 'Rotterdam, NL');

    $data = renderCheckinOverlay($this->user, CHECKIN_FOREACH_HTML)->assertOk()->json('data');

    // Default lifetime is per_stream and there is no open session.
    expect($data['checkins.count'])->toBe('0');
});
