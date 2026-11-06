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
 * The word Twitch Chat Plinko listens for is the streamer's own setting,
 * because `!drop` is already a well-known command from another chat game.
 * It lives on /settings/integrations/plinko, which only exists once the
 * product is installed, and reaches the overlay in the render payload:
 * top-level for the board's matching, and as the [[[plinko_command]]] tag
 * for the overlay's copy.
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

function installPlinkoFor(User $user): void
{
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('twitch-chat-plinko')), $user, 'twitch_chat_plinko');
}

function savePlinkoCommand(User $user, mixed $command): TestResponse
{
    return test()->actingAs($user)
        ->from('/settings/integrations/plinko')
        ->post('/settings/integrations/plinko', ['plinko_command' => $command]);
}

/** @return list<array<string, mixed>> */
function integrationServices(User $user): array
{
    $services = [];
    test()->actingAs($user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$services) {
            $services = $page->toArray()['props']['services'];
        });

    return $services;
}

function renderPlinkoOverlay(User $user): TestResponse
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'plinko-command-test',
        'is_active' => true,
    ]);

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => '<div>[[[plinko_board]]]<h1>Type ![[[plinko_command]]] in chat</h1></div>',
        'slug' => 'plinko-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    return test()->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain]);
}

it('has no settings page and no overview row until Plinko is installed', function () {
    $this->actingAs($this->user)->get('/settings/integrations/plinko')->assertNotFound();
    savePlinkoCommand($this->user, 'bonk')->assertNotFound();

    expect(collect(integrationServices($this->user))->pluck('key'))->not->toContain('plinko')
        ->and($this->user->fresh()->plinkoCommand())->toBe('drop');
});

it('opens the settings page once installed, defaulting to drop', function () {
    installPlinkoFor($this->user);

    $this->actingAs($this->user)
        ->get('/settings/integrations/plinko')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/integrations/plinko')->where('plinkoCommand', 'drop'));
});

it('lists Plinko on the integrations overview once installed, saying what it listens for', function () {
    installPlinkoFor($this->user);
    savePlinkoCommand($this->user, 'bonk');

    $row = collect(integrationServices($this->user))->firstWhere('key', 'plinko');

    expect($row)->not->toBeNull()
        ->and($row['url_slug'])->toBe('plinko')
        ->and($row['name'])->toBe('Plinko')
        ->and($row['product'])->toBe('twitch-chat-plinko')
        ->and($row['connected'])->toBeTrue()
        ->and($row['status'])->toBe('Listening for !bonk');
});

it('saves a new word, forgiving a typed !, capitals and spaces', function () {
    installPlinkoFor($this->user);

    savePlinkoCommand($this->user, ' !Bonk ')
        ->assertRedirect('/settings/integrations/plinko')
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->plinkoCommand())->toBe('bonk');
});

it('refuses anything that could not be a command, and keeps the old word', function (mixed $command) {
    installPlinkoFor($this->user);
    savePlinkoCommand($this->user, 'plink');

    savePlinkoCommand($this->user, $command)->assertSessionHasErrors('plinko_command');

    expect($this->user->fresh()->plinkoCommand())->toBe('plink');
})->with([
    'empty' => [''],
    'just the bang' => ['!'],
    'two words' => ['drop it'],
    'a hyphen' => ['drop-it'],
    'too long' => [str_repeat('a', 26)],
]);

it('never touches the chat display filters', function () {
    installPlinkoFor($this->user);
    $this->user->setPreference('chat_filters.hide_commands', true);
    $this->user->save();

    savePlinkoCommand($this->user, 'bonk');

    expect($this->user->fresh()->chatFilters()['hide_commands'])->toBeTrue();
});

it('falls back to drop when the stored word no longer passes the pattern', function () {
    $this->user->setPreference('plinko.command', 'not a command');
    $this->user->save();

    expect($this->user->fresh()->plinkoCommand())->toBe('drop');
});

it('ships the word to the overlay, top-level and as the tag', function () {
    installPlinkoFor($this->user);
    savePlinkoCommand($this->user, 'bonk');

    renderPlinkoOverlay($this->user->fresh())
        ->assertOk()
        ->assertJsonPath('plinko_command', 'bonk')
        ->assertJsonPath('data.plinko_command', 'bonk');
});

it('ships drop to an overlay of an account that never chose', function () {
    renderPlinkoOverlay($this->user)
        ->assertOk()
        ->assertJsonPath('plinko_command', 'drop')
        ->assertJsonPath('data.plinko_command', 'drop');
});
