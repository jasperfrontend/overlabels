<?php

use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * A manifest can install Bot Aliases and custom Bot Commands. Both go
 * through the validators the settings forms use, before anything is
 * created, so a reply the form would refuse refuses the install. No shipped
 * product installs a custom command yet; the manifest here is built in the
 * test, validated against the real schema by the installer.
 */
function chatUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'chattester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

/**
 * @param  array<string, mixed>  $installs
 */
function chatRecipe(array $installs): Recipe
{
    $manifest = [
        'recipe_format_version' => 1,
        'slug' => 'chat_test',
        'name' => 'Chat test',
        'version' => 1,
        'description' => 'A manifest that installs chat things only.',
        'author' => ['name' => 'Overlabels'],
        'requires_bot' => true,
        'installs' => $installs,
    ];

    return app(RecipeCatalog::class)->sync($manifest);
}

it('installs the bowling aliases pointing at the list commands, for moderators', function () {
    $user = chatUser();
    $catalog = app(RecipeCatalog::class);

    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('follower_bowling')), $user, 'follower_bowling');

    $first = BotAlias::find($instance->primitive_map['bot_aliases']['fbfirst']);
    $draw = BotAlias::find($instance->primitive_map['bot_aliases']['fbdraw']);

    expect($first)->not->toBeNull()
        ->and($first->target_template)->toBe('list lane pop first')
        ->and($first->targetCommand())->toBe('list')
        ->and($first->permission_level)->toBe('moderator')
        ->and($first->enabled)->toBeTrue()
        ->and($first->hidden)->toBeFalse()
        ->and($draw?->target_template)->toBe('list lane draw');
});

it('refuses the bowling install when the account already has an alias called fbfirst', function () {
    $user = chatUser();
    BotAlias::create(['user_id' => $user->id, 'command' => 'fbfirst', 'target_template' => 'ping', 'permission_level' => 'everyone']);
    $catalog = app(RecipeCatalog::class);

    expect(fn () => app(RecipeInstaller::class)->install($catalog->sync($catalog->find('follower_bowling')), $user, 'follower_bowling'))
        ->toThrow(RuntimeException::class, '!fbfirst');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0);
});

it('installs a custom bot command and provisions the counter its reply names', function () {
    $user = chatUser();
    $recipe = chatRecipe([
        'bot_commands' => [
            ['command' => '!strikes', 'reply' => 'Strikes so far: [[[counter:strikes]]]', 'permissions' => 'everyone', 'cooldown_seconds' => 5],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'chat_test');

    $command = BotCommand::find($instance->primitive_map['bot_commands']['strikes']);
    expect($command)->not->toBeNull()
        ->and($command->reply)->toBe('Strikes so far: [[[counter:strikes]]]')
        ->and($command->permission_level)->toBe('everyone')
        ->and($command->cooldown_seconds)->toBe(5)
        ->and($command->enabled)->toBeTrue();

    expect(OverlayControl::where('user_id', $user->id)->where('key', 'strikes')->exists())->toBeTrue();
});

it('refuses a reply the settings form would refuse, before creating anything', function () {
    $user = chatUser();
    $recipe = chatRecipe([
        'lists' => [['ref' => 'q', 'slug' => 'chat_test_queue']],
        'bot_commands' => [
            ['command' => '!oops', 'reply' => '/timeout {1} 600'],
        ],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'chat_test'))
        ->toThrow(RuntimeException::class, 'Command !oops');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0);
});

it('refuses an alias that would chain onto another alias', function () {
    $user = chatUser();
    BotAlias::create(['user_id' => $user->id, 'command' => 'hop', 'target_template' => 'ping', 'permission_level' => 'everyone']);
    $recipe = chatRecipe([
        'bot_aliases' => [['command' => '!skip', 'target' => '!hop']],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'chat_test'))
        ->toThrow(RuntimeException::class, 'Alias !skip');
});

it('refuses a command that collides with a builtin name', function () {
    $user = chatUser();
    $recipe = chatRecipe([
        'bot_commands' => [['command' => '!ping', 'reply' => 'pong again']],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'chat_test'))
        ->toThrow(RuntimeException::class, '!ping');
});

it('counts aliases and commands in the product command wire', function () {
    $user = chatUser(['bot_enabled' => true]);
    $recipe = chatRecipe([
        'bot_aliases' => [['command' => '!short', 'target' => '!ping']],
        'bot_commands' => [['command' => '!hello', 'reply' => 'hi']],
    ]);
    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'chat_test');

    expect(WiringFacts::productSubject($instance)['states']['product.command'])->toBe(WiringCatalog::SATISFIED);

    BotAlias::find($instance->primitive_map['bot_aliases']['short'])->update(['enabled' => false]);
    expect(WiringFacts::productSubject($instance->fresh())['states']['product.command'])->toBe(WiringCatalog::MISSING);

    BotAlias::find($instance->primitive_map['bot_aliases']['short'])->update(['enabled' => true]);
    BotCommand::find($instance->primitive_map['bot_commands']['hello'])->delete();
    expect(WiringFacts::productSubject($instance->fresh())['states']['product.command'])->toBe(WiringCatalog::MISSING);
});
