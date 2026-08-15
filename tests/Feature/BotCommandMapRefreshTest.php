<?php

use App\Events\BotChannelsChanged;
use App\Models\BotAlias;
use App\Models\BotBuiltin;
use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\RecipeChatTrigger;
use App\Models\User;
use App\Services\Bot\BotChatAdminService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function mapUser(string $login = 'streamer'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// The bug this fixes: the bot polls its command map every 60 seconds and
// silently ignores any command it has not heard of, so a brand new !wins did
// nothing and said nothing about why. Every model the map is built from now
// pushes a refresh the moment it changes.
// ──────────────────────────────────────────────────────────────────────────────

it('tells the bot to refresh when a command-map model is saved', function (string $kind) {
    // Build the user BEFORE faking. Creating a user seeds the whole default
    // BotBuiltin set, which legitimately announces - if that ran inside the
    // fake, every dataset below would pass whether or not its own model is
    // observed at all. Forgetting the scoped announcer then makes this read as
    // a fresh request, so the assertion can only be satisfied by the save.
    $user = mapUser();
    app()->forgetScopedInstances();

    Event::fake([BotChannelsChanged::class]);

    match ($kind) {
        'reply' => BotCommand::create([
            'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
            'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
            'hidden' => false,
        ]),
        'alias' => BotAlias::create([
            'user_id' => $user->id, 'command' => 'w', 'target_template' => 'increment wins {1}',
            'permission_level' => 'moderator', 'cooldown_seconds' => 0, 'enabled' => true,
        ]),
        // Builtins are seeded with the user, so the realistic change here is a
        // permission edit rather than an insert.
        'builtin' => BotBuiltin::where('user_id', $user->id)
            ->where('command', 'control')
            ->firstOrFail()
            ->update(['permission_level' => 'moderator']),
        'list appender' => ListAppender::factory()->create(['user_id' => $user->id]),
        'list meta command' => ListMetaCommand::create([
            'user_id' => $user->id, 'command' => 'list', 'enabled' => true,
        ]),
    };

    Event::assertDispatched(
        BotChannelsChanged::class,
        fn (BotChannelsChanged $e) => $e->login === 'streamer',
    );
})->with([
    'reply',
    'alias',
    'builtin',
    'list appender',
    'list meta command',
    // RecipeChatTrigger is the sixth command-map model and is deliberately not
    // exercised here: it needs a whole Recipe + RecipeInstance fixture chain
    // (no factories exist, and recipes are parked) to satisfy a NOT NULL FK,
    // for no extra coverage. It runs the identical observer, and the
    // registration test below proves it is wired to saved and deleted.
]);

it('tells the bot to refresh when a command is renamed, disabled or deleted', function (string $action) {
    $user = mapUser();
    $command = BotCommand::create([
        'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
        'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
        'hidden' => false,
    ]);

    // The edit is a separate request from the create in real use, and the
    // announcer deliberately only speaks once per request. Forget the scoped
    // instance so this reads as the next request rather than the same one.
    app()->forgetScopedInstances();

    // Faked only now, so the setup above doesn't count toward the assertion.
    Event::fake([BotChannelsChanged::class]);

    match ($action) {
        // A rename leaves the old name live in the bot's map until it refreshes.
        'rename' => $command->update(['command' => 'victories']),
        // A disabled command drops out of the map, so it must push too.
        'disable' => $command->update(['enabled' => false]),
        'delete' => $command->delete(),
    };

    Event::assertDispatched(BotChannelsChanged::class);
})->with(['rename', 'disable', 'delete']);

it('pushes a refresh when a command is added from chat with !ol cmd add', function () {
    $user = mapUser();
    app()->forgetScopedInstances();

    Event::fake([BotChannelsChanged::class]);

    // The exact flow that surfaced this: author a command in chat, then try it
    // a second later.
    $reply = app(BotChatAdminService::class)->dispatch($user, [
        'subject' => 'cmd', 'action' => 'add',
        'name' => 'wins', 'payload' => 'won [[[counter:wins]]] times',
    ]);

    expect($reply)->toStartWith('added !wins');
    Event::assertDispatched(BotChannelsChanged::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Coalescing. BotChannelsChanged is ShouldBroadcastNow and broadcasts are the
// metered resource, so one user action must not put a burst on the wire.
// ──────────────────────────────────────────────────────────────────────────────

it('announces a login once per request no matter how many rows move', function () {
    $user = mapUser();
    app()->forgetScopedInstances();

    Event::fake([BotChannelsChanged::class]);

    // BotChannelsChanged is ShouldBroadcastNow and broadcasts are the metered
    // resource, so a bulk edit must not put a burst on the wire. Ten rows, one
    // announcement - the bot re-reads the whole map either way.
    foreach (range(1, 10) as $i) {
        BotCommand::create([
            'user_id' => $user->id, 'command' => "cmd$i", 'permission_level' => 'everyone',
            'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
            'hidden' => false,
        ]);
    }

    Event::assertDispatchedTimes(BotChannelsChanged::class, 1);
});

it('coalesces the whole default command set seeded on signup into one announcement', function () {
    Event::fake([BotChannelsChanged::class]);

    // Signing up seeds seventeen-odd BotBuiltin rows in a loop. Before the
    // announcer coalesced, that was one synchronous broadcast per row.
    $user = mapUser();

    expect(BotBuiltin::where('user_id', $user->id)->count())->toBeGreaterThan(5);
    Event::assertDispatchedTimes(BotChannelsChanged::class, 1);
});

it('stays quiet for a user who has not turned the bot on', function () {
    Event::fake([BotChannelsChanged::class]);

    // The command map only lists users with bot_enabled, so a signup with the
    // bot off changes nothing the bot can see. Broadcasts are metered, and
    // this is the difference between one per registration and none.
    $user = User::factory()->create([
        'bot_enabled' => false,
        'twitch_data' => ['login' => 'lurker'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);

    BotCommand::create([
        'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
        'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
        'hidden' => false,
    ]);

    Event::assertNotDispatched(BotChannelsChanged::class);
});

it('still announces each distinct streamer separately', function () {
    $first = mapUser('alpha');
    $second = mapUser('beta');
    app()->forgetScopedInstances();

    Event::fake([BotChannelsChanged::class]);

    foreach ([$first, $second] as $user) {
        BotCommand::create([
            'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
            'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
            'hidden' => false,
        ]);
    }

    Event::assertDispatchedTimes(BotChannelsChanged::class, 2);
});

it('says nothing for a user the bot could never reach', function () {
    // No twitch_data means no login, and the map is keyed by login.
    $user = User::factory()->create(['bot_enabled' => true, 'twitch_data' => null]);
    app()->forgetScopedInstances();

    Event::fake([BotChannelsChanged::class]);

    BotCommand::create([
        'user_id' => $user->id, 'command' => 'wins', 'permission_level' => 'everyone',
        'cooldown_seconds' => 0, 'reply' => 'hi', 'enabled' => true,
        'hidden' => false,
    ]);

    Event::assertNotDispatched(BotChannelsChanged::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// The list of observed models has to stay in step with the endpoint the bot
// actually reads, or a command type silently goes back to being 60s stale.
// ──────────────────────────────────────────────────────────────────────────────

it('observes every model the command map endpoint is built from', function () {
    // Derived from the controller's own imports rather than a second hardcoded
    // list, so adding a seventh command type to the endpoint fails here until
    // AppServiceProvider observes it too. A hand-copied list would just drift.
    $source = file_get_contents(app_path('Http/Controllers/Api/Internal/BotCommandMapController.php'));

    preg_match_all('/^use App\\\\Models\\\\(\w+);/m', $source, $matches);

    // User is how the endpoint finds opted-in channels, not a command source.
    $modelsFeedingTheMap = array_values(array_diff($matches[1], ['User']));

    expect($modelsFeedingTheMap)->toHaveCount(6);

    $dispatcher = Model::getEventDispatcher();

    foreach ($modelsFeedingTheMap as $model) {
        $class = "App\\Models\\$model";

        foreach (['saved', 'deleted'] as $hook) {
            expect($dispatcher->hasListeners("eloquent.$hook: $class"))->toBeTrue(
                "$model feeds the bot command map but nothing observes its $hook event, "
                .'so changes to it stay invisible to the bot for up to a minute'
            );
        }
    }
});
