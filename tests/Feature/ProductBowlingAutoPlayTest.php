<?php

use App\Events\ListUpdated;
use App\Jobs\AutoPlayNext;
use App\Models\BotChatOutbox;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Lists\ListAppendService;
use App\Services\Recipes\AutoPlayService;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

/**
 * Follower Bowling runs itself. While `gobowl` is on and someone is in
 * line, the product pops the front of the lane List every fifteen seconds
 * and says nothing in chat; the mod aliases stay as a way to skip ahead.
 * The loop is declared in the manifest (`auto_play`) and driven by
 * AutoPlayService from two hooks: a list append and a control write.
 */
function autoPlayUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'autobowl'.fake()->unique()->randomNumber(5)],
    ]);
}

function autoPlayInstall(User $user): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install(
        $catalog->sync($catalog->find('follower-bowling')),
        $user,
        RecipeInstance::instanceSlugFrom('follower-bowling'),
    );
}

function laneOf(RecipeInstance $instance): OptionSet
{
    return OptionSet::findOrFail($instance->primitive_map['lists']['lane']);
}

function gobowlOf(RecipeInstance $instance): OverlayControl
{
    return OverlayControl::where('overlay_template_id', $instance->primitive_map['overlays']['lane'])
        ->where('key', 'gobowl')
        ->firstOrFail();
}

function bowl(User $user, RecipeInstance $instance, string $login): array
{
    $appender = ListAppender::findOrFail($instance->primitive_map['list_appenders']['bowl']);

    return app(ListAppendService::class)->fire($appender, $user, [
        'channel_login' => $user->twitch_data['login'],
        'command' => 'bowl',
        'chatter_id' => (string) crc32($login),
        'chatter_login' => $login,
        'chatter_display_name' => ucfirst($login),
        'args' => '',
    ]);
}

function fillLane(OptionSet $lane, array $names): OptionSet
{
    $built = ListItems::freshFromValues($names, 1);
    $lane->update(['items' => $built['items'], 'next_item_id' => $built['next_id']]);

    return $lane->fresh();
}

beforeEach(function () {
    Cache::flush();
});

it('declares the loop in the manifest', function () {
    $manifest = json_decode(file_get_contents(base_path('resources/recipes/follower-bowling/manifest.json')), true);

    expect($manifest['auto_play'])->toBe(['list' => 'lane', 'switch' => 'gobowl', 'seconds' => 15]);
});

it('refuses a manifest whose loop names a list the product does not install', function () {
    $validator = new RecipeManifestValidator;
    $manifest = app(RecipeCatalog::class)->find('follower-bowling');

    expect($validator->validate($manifest)['valid'])->toBeTrue();

    $manifest['auto_play']['list'] = 'queue';
    $result = $validator->validate($manifest);

    expect($result['valid'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('pointer'))->toContain('/auto_play/list');
});

it('arms the first pop at once when a chatter joins an idle lane that is switched on', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');
    $lane = laneOf($instance);

    expect(bowl($user, $instance, 'ana')['fired'])->toBeTrue();

    Bus::assertDispatched(AutoPlayNext::class, fn (AutoPlayNext $job) => $job->listId === $lane->id
        && (int) now()->diffInSeconds($job->delay, false) <= 0);
});

it('arms nothing while the lane is switched off', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);

    expect(bowl($user, $instance, 'ana')['fired'])->toBeTrue();

    Bus::assertNotDispatched(AutoPlayNext::class);
});

it('starts the loop from the switch when people are already in line', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    fillLane(laneOf($instance), ['ana', 'bo']);

    gobowlOf($instance)->writeValue('1');

    Bus::assertDispatched(AutoPlayNext::class, 1);
});

it('does not start the loop from the switch when the line is empty', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);

    gobowlOf($instance)->writeValue('1');

    Bus::assertNotDispatched(AutoPlayNext::class);
});

it('arms one pending pop however many chatters join', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');

    bowl($user, $instance, 'ana');
    bowl($user, $instance, 'bo');
    bowl($user, $instance, 'cy');

    Bus::assertDispatched(AutoPlayNext::class, 1);
});

it('waits out a throw still playing before joining the queue arms the next pop', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');
    // A mod popped five seconds ago: the ball is mid-lane.
    laneOf($instance)->update(['last_removed' => 'zed', 'last_removed_at' => now()->subSeconds(5)]);

    bowl($user, $instance, 'ana');

    Bus::assertDispatched(AutoPlayNext::class, function (AutoPlayNext $job) {
        $delay = (int) now()->diffInSeconds($job->delay, false);

        return $delay >= 9 && $delay <= 10;
    });
});

it('pops the front of the line, broadcasts it, says nothing in chat and arms the next pop', function () {
    Bus::fake([AutoPlayNext::class]);
    Event::fake([ListUpdated::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');
    $lane = fillLane(laneOf($instance), ['ana', 'bo']);

    app(AutoPlayService::class)->run($lane);

    $lane->refresh();
    expect(ListItems::values($lane->items))->toBe(['bo'])
        ->and($lane->last_removed)->toBe('ana')
        ->and($lane->last_removed_at)->not->toBeNull()
        ->and(BotChatOutbox::where('user_id', $user->id)->count())->toBe(0);
    Event::assertDispatched(ListUpdated::class);
    Bus::assertDispatched(AutoPlayNext::class, function (AutoPlayNext $job) use ($lane) {
        $delay = (int) now()->diffInSeconds($job->delay, false);

        return $job->listId === $lane->id && $delay >= 14 && $delay <= 15;
    });
});

it('ends the loop when the last bowler has gone', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');
    $lane = fillLane(laneOf($instance), ['ana']);

    app(AutoPlayService::class)->run($lane);

    expect(ListItems::values($lane->fresh()->items))->toBe([]);
    Bus::assertNotDispatched(AutoPlayNext::class);
});

it('pops nothing once the lane has been switched off', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    $lane = fillLane(laneOf($instance), ['ana', 'bo']);

    app(AutoPlayService::class)->run($lane);

    expect(ListItems::values($lane->fresh()->items))->toBe(['ana', 'bo'])
        ->and($lane->fresh()->last_removed)->toBeNull();
    Bus::assertNotDispatched(AutoPlayNext::class);
});

it('holds a pop that lands while a mod-started throw is still playing', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    gobowlOf($instance)->writeValue('1');
    $lane = fillLane(laneOf($instance), ['ana', 'bo']);
    $lane->update(['last_removed' => 'zed', 'last_removed_at' => now()->subSeconds(3)]);

    app(AutoPlayService::class)->run($lane->fresh());

    expect(ListItems::values($lane->fresh()->items))->toBe(['ana', 'bo']);
    Bus::assertDispatched(AutoPlayNext::class, function (AutoPlayNext $job) {
        $delay = (int) now()->diffInSeconds($job->delay, false);

        return $delay >= 11 && $delay <= 12;
    });
});

it('leaves a list that belongs to no product alone', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'raffle',
        'items' => [],
        'next_item_id' => 1,
    ]);
    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'enter',
        'value_template' => '[[[bot:from_user]]]',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'dedup_policy' => ListAppender::DEDUP_NONE,
        'enabled' => true,
    ]);

    app(ListAppendService::class)->fire($appender, $user, [
        'channel_login' => $user->twitch_data['login'],
        'command' => 'enter',
        'chatter_id' => '1',
        'chatter_login' => 'ana',
        'chatter_display_name' => 'Ana',
        'args' => '',
    ]);
    app(AutoPlayService::class)->run($list->fresh());

    expect(app(AutoPlayService::class)->loopFor($list->fresh()))->toBeNull();
    Bus::assertNotDispatched(AutoPlayNext::class);
});

it('ignores a boolean control that is not the switch', function () {
    Bus::fake([AutoPlayNext::class]);
    $user = autoPlayUser();
    $instance = autoPlayInstall($user);
    fillLane(laneOf($instance), ['ana']);
    $other = OverlayControl::createForTemplate(
        OverlayTemplate::findOrFail($instance->primitive_map['overlays']['lane']),
        $user,
        ['key' => 'lights', 'label' => 'Lights', 'type' => 'boolean', 'value' => '0'],
    );

    $other->writeValue('1');

    Bus::assertNotDispatched(AutoPlayNext::class);
});
