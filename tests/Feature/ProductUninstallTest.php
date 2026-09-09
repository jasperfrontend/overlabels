<?php

use App\Models\BotAlias;
use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Uninstall undoes an install from the ledger the install wrote into
 * primitive_map: overlay, list, appender, aliases, commands, and the
 * integration only if the install created it. Rows the streamer already
 * removed are skipped; an overlay in a Kit refuses the whole thing.
 */
function uninstallUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'uninstaller'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function installProduct(User $user, string $slug): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install($catalog->sync($catalog->find($slug)), $user, $slug);
}

it('removes everything Follower Bowling created, and the instance', function () {
    $user = uninstallUser();
    $instance = installProduct($user, 'follower_bowling');
    $map = $instance->primitive_map;

    app(RecipeInstaller::class)->uninstall($instance);

    expect(OverlayTemplate::find($map['overlays']['lane']))->toBeNull()
        ->and(OptionSet::find($map['lists']['lane']))->toBeNull()
        ->and(ListAppender::find($map['list_appenders']['bowl']))->toBeNull()
        ->and(BotAlias::find($map['bot_aliases']['fbfirst']))->toBeNull()
        ->and(BotAlias::find($map['bot_aliases']['fbdraw']))->toBeNull()
        ->and(RecipeInstance::find($instance->id))->toBeNull();
});

it('disconnects the checkin integration it created, controls included', function () {
    $user = uninstallUser();
    $instance = installProduct($user, 'chat_checkin');

    expect($instance->primitive_map['integrations']['checkin']['created'])->toBeTrue();

    app(RecipeInstaller::class)->uninstall($instance);

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->exists())->toBeFalse()
        ->and(OverlayControl::where('user_id', $user->id)->where('source', 'checkin')->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse();
});

it('leaves an integration the streamer connected before the install', function () {
    $user = uninstallUser();
    ExternalIntegration::create(['user_id' => $user->id, 'service' => 'checkin', 'enabled' => true, 'settings' => ['pin_lifetime' => 'persistent']]);
    $instance = installProduct($user, 'chat_checkin');

    expect($instance->primitive_map['integrations']['checkin']['created'])->toBeFalse()
        ->and(app(RecipeInstaller::class)->removals($instance))->not->toContain('The checkin integration connection and its controls');

    app(RecipeInstaller::class)->uninstall($instance);

    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->first();
    expect($integration)->not->toBeNull()
        ->and($integration->settings['pin_lifetime'])->toBe('persistent')
        ->and(OverlayControl::where('user_id', $user->id)->where('source', 'checkin')->exists())->toBeTrue();
});

it('names what it will remove, and skips what the streamer already deleted', function () {
    $user = uninstallUser();
    $instance = installProduct($user, 'follower_bowling');

    $before = app(RecipeInstaller::class)->removals($instance);
    expect($before)->toContain('The overlay Follower bowling lane', 'The list Bowling lane and everything in it', 'The !bowl chat command', 'The !fbfirst alias', 'The !fbdraw alias');

    BotAlias::find($instance->primitive_map['bot_aliases']['fbdraw'])->delete();
    OverlayTemplate::find($instance->primitive_map['overlays']['lane'])->delete();

    $after = app(RecipeInstaller::class)->removals($instance->fresh());
    expect($after)->not->toContain('The !fbdraw alias')
        ->and($after)->not->toContain('The overlay Follower bowling lane')
        ->and($after)->toContain('The !fbfirst alias');

    app(RecipeInstaller::class)->uninstall($instance->fresh());
    expect(RecipeInstance::find($instance->id))->toBeNull();
});

it('refuses when the overlay is in a kit, and removes nothing', function () {
    $user = uninstallUser();
    $instance = installProduct($user, 'follower_bowling');
    $kit = Kit::create(['owner_id' => $user->id, 'title' => 'My bowling kit', 'is_public' => false]);
    $kit->templates()->attach($instance->primitive_map['overlays']['lane']);

    expect(fn () => app(RecipeInstaller::class)->uninstall($instance))
        ->toThrow(RuntimeException::class, 'My bowling kit');

    expect(RecipeInstance::find($instance->id))->not->toBeNull()
        ->and(OptionSet::find($instance->primitive_map['lists']['lane']))->not->toBeNull()
        ->and(BotAlias::find($instance->primitive_map['bot_aliases']['fbfirst']))->not->toBeNull();
});

it('installs again cleanly after an uninstall', function () {
    $user = uninstallUser();
    $first = installProduct($user, 'follower_bowling');
    app(RecipeInstaller::class)->uninstall($first);

    $second = installProduct($user, 'follower_bowling');

    expect($second->id)->not->toBe($first->id)
        ->and(OptionSet::where('user_id', $user->id)->where('slug', 'lane')->count())->toBe(1)
        ->and(BotAlias::where('user_id', $user->id)->where('command', 'fbfirst')->count())->toBe(1);
});

it('uninstalls from the product page and shows the page uninstalled', function () {
    $user = uninstallUser();
    installProduct($user, 'follower_bowling');

    $this->actingAs($user)
        ->get('/products/follower_bowling')
        ->assertInertia(fn (Assert $page) => $page->has('installed.removes', 5));

    $this->actingAs($user)
        ->post('/products/follower_bowling/uninstall')
        ->assertRedirect('/products/follower_bowling');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get('/products/follower_bowling')
        ->assertInertia(fn (Assert $page) => $page->where('installed', null));
});

it('surfaces the kit refusal on the product page', function () {
    $user = uninstallUser();
    $instance = installProduct($user, 'follower_bowling');
    $kit = Kit::create(['owner_id' => $user->id, 'title' => 'Keep', 'is_public' => false]);
    $kit->templates()->attach($instance->primitive_map['overlays']['lane']);

    $this->actingAs($user)
        ->post('/products/follower_bowling/uninstall')
        ->assertRedirect('/products/follower_bowling')
        ->assertSessionHasErrors('uninstall');

    expect(RecipeInstance::find($instance->id))->not->toBeNull();
});

it('is a no-op when nothing is installed, and needs a login', function () {
    $this->post('/products/follower_bowling/uninstall')->assertRedirect();

    $user = uninstallUser();
    $this->actingAs($user)
        ->post('/products/follower_bowling/uninstall')
        ->assertRedirect('/products/follower_bowling')
        ->assertSessionHasNoErrors();
});
