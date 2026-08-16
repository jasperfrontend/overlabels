<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/*
 * `alerts_need_emotes` decides whether an overlay downloads the 7TV/BTTV/FFZ
 * emote library, which is ~77 kB and was previously fetched by every overlay on
 * every load.
 *
 * The tempting gate - "is this overlay targeted by an alert?" - is WRONG, and
 * that is what these tests exist to lock down. An alert with no targeting fires
 * on EVERY static overlay by design, so almost every overlay can host alerts.
 * The real question is whether any alert that can fire here actually renders an
 * emote-parsed field (`event.message.text` or `event.user_input`).
 *
 * Getting this backwards is silent in both directions: too eager wastes the
 * download forever, too strict renders emote codes as literal text in someone's
 * sub alert on stream.
 */

function emoteGateUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);
}

function emoteGateToken(User $user): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'emote-gate-test',
        'is_active' => true,
    ]);

    return $plain;
}

function emoteGateTemplate(User $user, string $type, string $html = '<div>x</div>'): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => $type,
        'html' => $html,
        'slug' => $type.'-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
}

function renderOverlay(string $slug, string $token)
{
    return test()->postJson('/api/overlay/render', ['slug' => $slug, 'token' => $token]);
}

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

it('does not ask for emotes when the user has no alerts at all', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', false);
});

it('does not ask for emotes when an alert renders no emote-parsed field', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');
    emoteGateTemplate($user, 'alert', '<div>[[[event.user_name]]] followed</div>');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', false);
});

it('asks for emotes when an UNTARGETED alert renders a message field', function () {
    // The case the naive gate would miss. No targeting means it fires here.
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');
    emoteGateTemplate($user, 'alert', '<div>[[[event.message.text]]]</div>');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', true);
});

it('asks for emotes for a channel-points alert using user_input', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');
    emoteGateTemplate($user, 'alert', '<div>[[[event.user_input]]]</div>');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', true);
});

it('ignores an emote-using alert that is targeted at a DIFFERENT overlay', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');
    $other = emoteGateTemplate($user, 'static');

    $alert = emoteGateTemplate($user, 'alert', '<div>[[[event.message.text]]]</div>');
    $alert->targetStaticOverlays()->attach($other->id);

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', false);
});

it('asks for emotes when the alert targets THIS overlay', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');

    $alert = emoteGateTemplate($user, 'alert', '<div>[[[event.message.text]]]</div>');
    $alert->targetStaticOverlays()->attach($static->id);

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', true);
});

it('ignores another user\'s emote-using alert', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');

    $stranger = emoteGateUser();
    emoteGateTemplate($stranger, 'alert', '<div>[[[event.message.text]]]</div>');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', false);
});

it('needs only one emote-using alert among many that do not', function () {
    $user = emoteGateUser();
    $static = emoteGateTemplate($user, 'static');

    emoteGateTemplate($user, 'alert', '<div>[[[event.user_name]]] followed</div>');
    emoteGateTemplate($user, 'alert', '<div>[[[event.user_name]]] raided</div>');
    emoteGateTemplate($user, 'alert', '<div>[[[event.message.text]]]</div>');

    renderOverlay($static->slug, emoteGateToken($user))
        ->assertOk()
        ->assertJsonPath('alerts_need_emotes', true);
});
