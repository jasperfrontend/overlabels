<?php

use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\OptionSet;
use App\Models\Picker;
use App\Models\Recipe;
use App\Models\RecipeChatTrigger;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function seedCoinFlip(): Recipe
{
    $manifest = json_decode(
        file_get_contents(base_path('resources/recipes/coin_flip/manifest.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    return Recipe::create([
        'slug' => $manifest['slug'],
        'version' => $manifest['version'],
        'name' => $manifest['name'],
        'description' => $manifest['description'],
        'author_name' => $manifest['author']['name'],
        'changelog' => $manifest['changelog'] ?? null,
        'min_overlabels_version' => $manifest['min_overlabels_version'] ?? 1,
        'requires_integrations' => $manifest['requires_integrations'] ?? [],
        'max_instances_per_user' => $manifest['max_instances_per_user'] ?? null,
        'manifest' => $manifest,
        'is_first_party' => true,
    ]);
}

function botUser(string $login = 'streamer_t'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function fireTriggerRequest(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/recipe-triggers/fire',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

function triggerPayload(array $overrides = []): array
{
    return array_merge([
        'channel_login' => 'streamer_t',
        'command' => 'flip',
        'chatter_id' => '12345',
        'chatter_login' => 'cool_chatter',
        'chatter_display_name' => 'CoolChatter',
        'badges' => [],
        'args' => '',
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// RecipeInstaller materialises chat triggers
// ──────────────────────────────────────────────────────────────────────────────

it('materialises chat_command triggers as recipe_chat_triggers rows', function () {
    $recipe = seedCoinFlip();
    $user = botUser();

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($instance->chatTriggers)->toHaveCount(1);
    $trigger = $instance->chatTriggers->first();
    expect($trigger->command)->toBe('flip')
        ->and($trigger->permission_level)->toBe('everyone')
        ->and($trigger->cooldown_seconds)->toBe(10)
        ->and($trigger->picker_id)->toBe($instance->pickers->first()->id);
});

it('refuses to install when a chat trigger collides with an existing BotExpression', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'flip',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'Hi',
        'enabled' => true,
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'main'))
        ->toThrow(RuntimeException::class, 'collides with an existing Bot Expression');
});

it('refuses to install when a chat trigger collides with an existing builtin BotCommand', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'flip',
        'permission_level' => 'everyone',
        'enabled' => true,
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'main'))
        ->toThrow(RuntimeException::class, 'collides with an existing built-in bot command');
});

it('cascade-deletes recipe_chat_triggers when the instance is removed', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $instanceId = $instance->id;

    expect(RecipeChatTrigger::where('recipe_instance_id', $instanceId)->count())->toBe(1);

    $instance->delete();

    expect(RecipeChatTrigger::where('recipe_instance_id', $instanceId)->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Internal API: /recipe-triggers/fire
// ──────────────────────────────────────────────────────────────────────────────

it('fires a recipe chat trigger via the bot internal API', function () {
    $recipe = seedCoinFlip();
    $user = botUser('streamer_t');
    app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $resp = fireTriggerRequest(triggerPayload());

    $resp->assertOk()
        ->assertJson(['fired' => true])
        ->assertJsonPath('result', fn ($v) => in_array($v, ['Heads', 'Tails'], true));

    $trigger = RecipeChatTrigger::where('user_id', $user->id)->where('command', 'flip')->first();
    expect($trigger->last_fired_at)->not->toBeNull();
});

it('returns trigger_not_found for an unknown command', function () {
    botUser('streamer_t');

    fireTriggerRequest(triggerPayload(['command' => 'doesnotexist']))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'trigger_not_found']);
});

it('returns channel_not_found when no opted-in user owns the login', function () {
    fireTriggerRequest(triggerPayload(['channel_login' => 'ghost_channel']))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'channel_not_found']);
});

it('gates by permission_level', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $instance->chatTriggers->first()->update(['permission_level' => 'moderator']);

    fireTriggerRequest(triggerPayload(['badges' => []]))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'gate']);

    fireTriggerRequest(triggerPayload(['badges' => ['moderator']]))
        ->assertOk()
        ->assertJson(['fired' => true]);
});

it('enforces cooldown for non-broadcaster chatters', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $instance->chatTriggers->first()->update([
        'cooldown_seconds' => 60,
        'last_fired_at' => Carbon::now(),
    ]);

    fireTriggerRequest(triggerPayload())
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'gate']);
});

it('broadcaster bypasses cooldown', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $instance->chatTriggers->first()->update([
        'cooldown_seconds' => 60,
        'last_fired_at' => Carbon::now(),
    ]);

    fireTriggerRequest(triggerPayload(['badges' => ['broadcaster']]))
        ->assertOk()
        ->assertJson(['fired' => true]);
});

it('rejects requests without the X-Internal-Secret header', function () {
    test()->postJson('/api/internal/bot/recipe-triggers/fire', triggerPayload())
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// BotCommandController surfaces recipe triggers
// ──────────────────────────────────────────────────────────────────────────────

it('exposes recipe triggers in the bot commandMap with type=recipe_trigger', function () {
    $recipe = seedCoinFlip();
    $user = botUser('streamer_map');
    app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $resp = test()->get(
        '/api/internal/bot/commands',
        ['X-Internal-Secret' => 'test-bot-secret']
    );

    $resp->assertOk();
    $channels = $resp->json('channels');
    expect($channels)->toHaveKey('streamer_map');

    $entries = collect($channels['streamer_map']);
    $flip = $entries->firstWhere('command', 'flip');
    expect($flip)->not->toBeNull()
        ->and($flip['type'])->toBe('recipe_trigger')
        ->and($flip['permission_level'])->toBe('everyone');
});

it('lets a builtin win over a recipe trigger with the same name', function () {
    $recipe = seedCoinFlip();
    $user = botUser('streamer_collide');

    // Install first, then sneak in a colliding builtin (skipping the
    // installer's collision check that would normally prevent this).
    app(RecipeInstaller::class)->install($recipe, $user, 'main');
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'flip',
        'permission_level' => 'broadcaster',
        'enabled' => true,
    ]);

    $resp = test()->get(
        '/api/internal/bot/commands',
        ['X-Internal-Secret' => 'test-bot-secret']
    );

    $entries = collect($resp->json('channels.streamer_collide'));
    $flip = $entries->firstWhere('command', 'flip');
    expect($flip['type'])->toBe('builtin');
    // Recipe trigger should NOT appear as a second entry.
    expect($entries->where('command', 'flip'))->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Web endpoint: dashboard fire-button
// ──────────────────────────────────────────────────────────────────────────────

it('fires a dashboard_button trigger via the web endpoint', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $resp = test()->actingAs($user)->postJson(
        "/recipes/instances/{$instance->id}/fire-button",
        ['picker_ref' => 'flipper']
    );

    $resp->assertOk()
        ->assertJson(['fired' => true])
        ->assertJsonPath('result', fn ($v) => in_array($v, ['Heads', 'Tails'], true));
});

it('refuses to fire a button on someone elses instance with 404', function () {
    $recipe = seedCoinFlip();
    $owner = botUser('owner_x');
    $other = User::factory()->create();
    $instance = app(RecipeInstaller::class)->install($recipe, $owner, 'main');

    test()->actingAs($other)->postJson(
        "/recipes/instances/{$instance->id}/fire-button",
        ['picker_ref' => 'flipper']
    )->assertStatus(404);
});

it('refuses to fire a picker that has no dashboard_button trigger declared', function () {
    $recipe = seedCoinFlip();
    $user = botUser();
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    test()->actingAs($user)->postJson(
        "/recipes/instances/{$instance->id}/fire-button",
        ['picker_ref' => 'nonexistent']
    )->assertStatus(404);
});
