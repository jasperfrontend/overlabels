<?php

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The Ko-fi settings page shows the verification token field empty once a
 * token is stored, with a "(token saved - enter new to replace)" placeholder.
 * Re-saving the page to change which events alert must therefore not demand
 * the token again, and must not blank the stored one.
 */
function kofiUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

function storedKofiToken(User $user): ?string
{
    return ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'kofi')
        ->first()
        ?->getCredentialsDecrypted()['verification_token'] ?? null;
}

test('the first connect requires a verification token', function () {
    $user = kofiUser();

    $this->post('/settings/integrations/kofi', ['verification_token' => ''])
        ->assertSessionHasErrors('verification_token');

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->exists())->toBeFalse();
});

test('re-saving with an empty token field keeps the stored token', function () {
    $user = kofiUser();

    $this->post('/settings/integrations/kofi', ['verification_token' => 'first-token'])->assertSessionHasNoErrors();

    $this->post('/settings/integrations/kofi', [
        'verification_token' => '',
        'enabled_events' => ['donation'],
    ])->assertSessionHasNoErrors();

    expect(storedKofiToken($user))->toBe('first-token');

    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->first();
    expect($integration->settings['enabled_events'])->toBe(['donation']);
});

test('re-saving with a new token replaces the stored one', function () {
    $user = kofiUser();

    $this->post('/settings/integrations/kofi', ['verification_token' => 'first-token'])->assertSessionHasNoErrors();
    $this->post('/settings/integrations/kofi', ['verification_token' => 'second-token'])->assertSessionHasNoErrors();

    expect(storedKofiToken($user))->toBe('second-token');
});
