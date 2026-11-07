<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Redacted messages: chatters may hide their message on the chat overlay with
 * `!hide <text>`, drawn in the Redacted font, if the streamer turns it on at
 * /settings/chat. Off by default. It reaches the overlay as
 * `chat_redact_command`, null whenever the feature is off. The overlay half
 * is chatRedact.test.ts and useTwitchChat.test.ts.
 */
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

// Own helpers, not another test file's: Pest functions are global, so
// borrowing them works only when both files happen to load in the same run.
function installProductForChatRedact(User $user, string $slug, string $instance): void
{
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find($slug)), $user, $instance);
}

function saveChatSettings(User $user, array $input): TestResponse
{
    return test()->actingAs($user)
        ->from('/settings/chat')
        ->patch('/settings/chat', ['hide_commands' => false, 'hidden_logins' => '', ...$input]);
}

function renderChatRedactOverlay(User $user): TestResponse
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'chat-redact-test',
        'is_active' => true,
    ]);

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[foreach:chat as msg]]]<p>[[[msg.html]]]</p>[[[endforeach]]]</div>',
        'slug' => 'redact-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    return test()->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain]);
}

it('is off by default, with hide as the word', function () {
    expect($this->user->chatRedact())->toBe(['enabled' => false, 'command' => 'hide'])
        ->and($this->user->chatRedactCommand())->toBeNull();
});

it('shows on the chat settings page', function () {
    $this->actingAs($this->user)
        ->get('/settings/chat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Chat')
            ->where('chatRedact', ['enabled' => false, 'command' => 'hide']));
});

it('saves the switch and the word, forgiving a typed !, capitals and spaces', function () {
    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => ' !Shh '])
        ->assertRedirect('/settings/chat')
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->chatRedact())->toBe(['enabled' => true, 'command' => 'shh']);
});

it('is left alone by a save that does not mention it, like the chat designer does', function () {
    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => 'shh']);

    test()->actingAs($this->user)
        ->patchJson('/settings/chat', ['hide_commands' => true, 'hidden_logins' => 'somebot'])
        ->assertOk()
        ->assertJsonPath('chat_filters.hide_commands', true);

    expect($this->user->fresh()->chatRedact())->toBe(['enabled' => true, 'command' => 'shh']);
});

it('refuses anything that could not be a command, and keeps the old word', function (mixed $command) {
    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => 'shh']);

    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => $command])->assertSessionHasErrors('redact_command');

    expect($this->user->fresh()->chatRedact()['command'])->toBe('shh');
})->with([
    'empty' => [''],
    'just the bang' => ['!'],
    'two words' => ['hide it'],
    'a hyphen' => ['hide-it'],
    'too long' => [str_repeat('a', 26)],
]);

it('refuses the Plinko word when Plinko is installed', function () {
    installProductForChatRedact($this->user, 'twitch-chat-plinko', 'twitch_chat_plinko');

    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => 'drop'])->assertSessionHasErrors('redact_command');

    expect($this->user->fresh()->chatRedact()['command'])->toBe('hide');
});

it('allows the Plinko default word when Plinko is not installed', function () {
    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => 'drop'])->assertSessionHasNoErrors();

    expect($this->user->fresh()->chatRedact()['command'])->toBe('drop');
});

it('ships no word to the overlay while it is off', function () {
    saveChatSettings($this->user, ['redact_enabled' => false, 'redact_command' => 'shh']);

    renderChatRedactOverlay($this->user->fresh())->assertOk()->assertJsonPath('chat_redact_command', null);
});

it('ships the word to the overlay once it is on', function () {
    saveChatSettings($this->user, ['redact_enabled' => true, 'redact_command' => 'shh']);

    renderChatRedactOverlay($this->user->fresh())->assertOk()->assertJsonPath('chat_redact_command', 'shh');
});

it('falls back to hide when the stored word no longer passes the pattern', function () {
    $this->user->setPreference('chat_redact.command', 'not a command');
    $this->user->save();

    expect($this->user->fresh()->chatRedact()['command'])->toBe('hide');
});

it('stays out of incidental User serialisation', function () {
    expect($this->user->toArray())->not->toHaveKey('chat_redact');
});

it('tells the chat overlay product page whether it is on, and no other product', function () {
    installProductForChatRedact($this->user, 'twitch-chat-overlay', 'twitch_chat');
    installProductForChatRedact($this->user, 'twitch-chat-plinko', 'twitch_chat_plinko');

    $this->actingAs($this->user)
        ->get('/products/twitch-chat-overlay/install')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('chat_redact', ['enabled' => false, 'command' => 'hide']));

    $this->actingAs($this->user)
        ->get('/products/twitch-chat-plinko/install')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('chat_redact', null));
});
