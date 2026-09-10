<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The per-viewer !stack cooldown is the streamer's own knob. It may go down to
 * one second (the bot's channel-wide window is 1s in production, so anything
 * lower would be unreachable anyway) and starts at five for a channel that
 * never touches it.
 */
beforeEach(function () {
    $this->user = User::factory()->create(['twitch_data' => ['login' => 'towerstreamer']]);
    $this->actingAs($this->user);
});

function towerSettings(User $user): array
{
    return ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'tower')
        ->firstOrFail()
        ->settings ?? [];
}

test('a one-second cooldown is accepted and stored', function () {
    $this->post('/settings/integrations/tower', [
        'tower_lifetime' => 'per_stream',
        'cooldown_seconds' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(towerSettings($this->user)['cooldown_seconds'])->toBe(1);
});

test('zero is refused, so the backstop can never be switched off from settings', function () {
    $this->post('/settings/integrations/tower', [
        'tower_lifetime' => 'per_stream',
        'cooldown_seconds' => 0,
    ])->assertSessionHasErrors('cooldown_seconds');

    expect(ExternalIntegration::where('user_id', $this->user->id)->where('service', 'tower')->exists())->toBeFalse();
});

test('omitting the cooldown stores five seconds', function () {
    $this->post('/settings/integrations/tower', ['tower_lifetime' => 'per_stream'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(towerSettings($this->user)['cooldown_seconds'])->toBe(5);
});

test('the settings page reports five for a channel that has never saved one', function () {
    $this->get('/settings/integrations/tower')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('integration.cooldown_seconds', 5));
});
