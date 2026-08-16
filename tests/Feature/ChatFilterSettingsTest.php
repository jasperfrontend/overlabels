<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
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

function saveChatFilters(User $user, array $payload): TestResponse
{
    return test()->actingAs($user)->patch('/settings/chat', $payload);
}

/**
 * Render a static overlay for this user and return the payload response. The
 * filters have to reach the client because the overlay reads chat directly
 * from Twitch - the server never sees a message, so it cannot filter one.
 */
function renderChatOverlay(User $user): TestResponse
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'chat-filter-test',
        'is_active' => true,
    ]);

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[foreach:chat as msg]]][[[msg.text]]][[[endforeach]]]</div>',
        'slug' => 'chat-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    return test()->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Access
// ──────────────────────────────────────────────────────────────────────────────

it('requires authentication', function () {
    $this->get('/settings/chat')->assertRedirect();
    $this->patch('/settings/chat', ['hide_commands' => true])->assertRedirect();
});

it('renders the settings page with the current filters', function () {
    $this->user->setPreference('chat_filters.hide_commands', true);
    $this->user->setPreference('chat_filters.hidden_logins', ['spambot']);
    $this->user->save();

    $this->actingAs($this->user)
        ->get('/settings/chat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Chat')
            ->where('chatFilters.hide_commands', true)
            ->where('chatFilters.hidden_logins', ['spambot'])
        );
});

// ──────────────────────────────────────────────────────────────────────────────
// Defaults
// ──────────────────────────────────────────────────────────────────────────────

it('shows everything by default', function () {
    // Deciding for the streamer which of their chatters is worth rendering is
    // not this app's call - both filters start off.
    expect($this->user->chatFilters())->toBe([
        'hide_commands' => false,
        'hidden_logins' => [],
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Saving
// ──────────────────────────────────────────────────────────────────────────────

it('saves the command toggle', function () {
    saveChatFilters($this->user, ['hide_commands' => true, 'hidden_logins' => ''])
        ->assertRedirect();

    expect($this->user->fresh()->chatFilters()['hide_commands'])->toBeTrue();
});

it('parses one login per line', function () {
    saveChatFilters($this->user, [
        'hide_commands' => false,
        'hidden_logins' => "spambot\nanotherbot",
    ]);

    expect($this->user->fresh()->chatFilters()['hidden_logins'])->toBe(['spambot', 'anotherbot']);
});

it('accepts a messy pasted list without erroring', function () {
    // A textarea invites mess. Rejecting it with a validation error would be
    // hostile when the fix is obvious.
    saveChatFilters($this->user, [
        'hide_commands' => false,
        'hidden_logins' => "  SpamBot  \n\n@AnotherBot\r\nspambot\n,,\n",
    ]);

    expect($this->user->fresh()->chatFilters()['hidden_logins'])
        ->toBe(['spambot', 'anotherbot']);
});

it('drops entries that cannot be a Twitch login', function () {
    saveChatFilters($this->user, [
        'hide_commands' => false,
        'hidden_logins' => "good_name\nhas spaces\nway_too_long_to_be_a_twitch_login_at_all\nbad!char\nok2",
    ]);

    expect($this->user->fresh()->chatFilters()['hidden_logins'])->toBe(['good_name', 'ok2']);
});

it('caps the list', function () {
    $logins = collect(range(1, User::MAX_HIDDEN_LOGINS + 50))
        ->map(fn (int $n) => "user$n")
        ->implode("\n");

    saveChatFilters($this->user, ['hide_commands' => false, 'hidden_logins' => $logins]);

    expect($this->user->fresh()->chatFilters()['hidden_logins'])
        ->toHaveCount(User::MAX_HIDDEN_LOGINS);
});

it('clears the list when the textarea is emptied', function () {
    $this->user->setPreference('chat_filters.hidden_logins', ['spambot']);
    $this->user->save();

    saveChatFilters($this->user, ['hide_commands' => false, 'hidden_logins' => '']);

    expect($this->user->fresh()->chatFilters()['hidden_logins'])->toBe([]);
});

it('rejects a missing toggle', function () {
    saveChatFilters($this->user, ['hidden_logins' => 'spambot'])
        ->assertSessionHasErrors('hide_commands');
});

it('leaves other preferences alone', function () {
    // chat_filters is one key in a shared jsonb column. Writing it must not
    // stomp the neighbours.
    $this->user->setPreference('locale', 'nl-NL');
    $this->user->save();

    saveChatFilters($this->user, ['hide_commands' => true, 'hidden_logins' => 'spambot']);

    expect($this->user->fresh()->locale)->toBe('nl-NL');
});

it('does not leak the hidden list through incidental serialisation', function () {
    // Deliberately NOT in $appends, unlike locale and foreach_caps. A list of
    // logins the streamer has hidden is theirs, and appending it would put it
    // into every serialisation of a User anywhere in the app.
    $this->user->setPreference('chat_filters.hidden_logins', ['spambot']);
    $this->user->save();

    expect($this->user->fresh()->toArray())->not->toHaveKey('chat_filters');
});

// ──────────────────────────────────────────────────────────────────────────────
// Reaching the overlay
// ──────────────────────────────────────────────────────────────────────────────

it('ships the filters in the overlay render payload', function () {
    saveChatFilters($this->user, ['hide_commands' => true, 'hidden_logins' => "spambot\nanotherbot"]);

    renderChatOverlay($this->user->fresh())
        ->assertOk()
        ->assertJsonPath('chat_filters.hide_commands', true)
        ->assertJsonPath('chat_filters.hidden_logins', ['spambot', 'anotherbot']);
});

it('ships the permissive defaults when nothing was ever saved', function () {
    renderChatOverlay($this->user)
        ->assertOk()
        ->assertJsonPath('chat_filters.hide_commands', false)
        ->assertJsonPath('chat_filters.hidden_logins', []);
});

it('scopes filters to the user who saved them', function () {
    $other = User::factory()->create();

    saveChatFilters($this->user, ['hide_commands' => true, 'hidden_logins' => 'spambot']);

    expect($other->fresh()->chatFilters())->toBe([
        'hide_commands' => false,
        'hidden_logins' => [],
    ]);
});
