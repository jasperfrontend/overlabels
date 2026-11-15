<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\ProductInstall;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use App\Support\ProductDesigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/*
 * Every product install is kept after the uninstall: installed, first shown
 * by an overlay link, uninstalled. The welcome funnel only knows an
 * account's first install; this is the history the admin funnel page shows
 * below it, with broken installs worked out on the visit.
 */

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function historyUser(array $attributes = []): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'history'.fake()->unique()->randomNumber(5)],
        'access_token' => 'fake-twitch-token',
        ...$attributes,
    ]);
}

function historyInstall(User $user, string $slug): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install($catalog->sync($catalog->find($slug)), $user, RecipeInstance::instanceSlugFrom($slug));
}

function historyToken(User $user, ?array $metadata = null): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'OBS - test',
        'is_active' => true,
        'metadata' => $metadata,
    ]);

    return $plain;
}

function historyOverlay(RecipeInstance $instance): OverlayTemplate
{
    return OverlayTemplate::whereIn('id', array_values($instance->primitive_map['overlays']))->where('type', 'static')->firstOrFail();
}

function historyRender(OverlayTemplate $overlay, string $token): void
{
    test()->postJson('/api/overlay/render', ['slug' => $overlay->slug, 'token' => $token])->assertOk();
}

it('opens a row on install and closes it on uninstall, keeping the row', function () {
    $this->freezeTime();
    $user = historyUser();
    $instance = historyInstall($user, 'chat-tower');

    $row = ProductInstall::where('user_id', $user->id)->sole();
    expect($row->product_slug)->toBe('chat-tower')
        ->and($row->recipe_instance_id)->toBe($instance->id)
        ->and($row->static_overlay_ids)->toBe([historyOverlay($instance)->id])
        ->and($row->installed_at->timestamp)->toBe(now()->timestamp)
        ->and($row->uninstalled_at)->toBeNull();

    $this->travel(11)->minutes();
    app(RecipeInstaller::class)->uninstall($instance);

    $row->refresh();
    expect($row->recipe_instance_id)->toBeNull()
        ->and($row->uninstalled_at->timestamp)->toBe(now()->timestamp);
});

it('keeps one row per install, so a second product the next day shows up too', function () {
    $user = historyUser();
    app(RecipeInstaller::class)->uninstall(historyInstall($user, 'follower-bowling'));
    $this->travel(1)->day();
    historyInstall($user, 'twitch-chat-plinko');

    expect(ProductInstall::where('user_id', $user->id)->orderBy('id')->pluck('product_slug')->all())
        ->toBe(['follower-bowling', 'twitch-chat-plinko']);
});

it('stamps the first load through a real overlay link, and only the first', function () {
    $this->freezeTime();
    $user = historyUser();
    $overlay = historyOverlay(historyInstall($user, 'chat-tower'));
    $token = historyToken($user);

    $this->travel(3)->minutes();
    historyRender($overlay, $token);
    $first = now()->timestamp;

    $this->travel(5)->minutes();
    historyRender($overlay, $token);

    expect(ProductInstall::where('user_id', $user->id)->sole()->first_loaded_at->timestamp)->toBe($first);
});

it('does not count the designer preview link as a load', function () {
    $user = historyUser();
    $overlay = historyOverlay(historyInstall($user, 'chat-tower'));

    historyRender($overlay, historyToken($user, ['purpose' => ProductDesigner::TOKEN_PURPOSE]));

    expect(ProductInstall::where('user_id', $user->id)->sole()->first_loaded_at)->toBeNull();
});

it('counts any static overlay load for an alerts-only product', function () {
    $user = historyUser();
    historyInstall($user, 'ko-fi-alerts');
    $row = ProductInstall::where('user_id', $user->id)->sole();
    expect($row->static_overlay_ids)->toBe([]);

    $host = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'head' => '',
        'html' => '<div></div>',
        'css' => '',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
    historyRender($host, historyToken($user));

    expect($row->fresh()->first_loaded_at)->not->toBeNull();
});

it('shows each install with the state it is in now', function () {
    $admin = historyUser(['role' => 'admin', 'created_at' => now()->subYear()]);

    $streaming = historyUser();
    $overlay = historyOverlay(historyInstall($streaming, 'chat-tower'));
    historyRender($overlay, historyToken($streaming));

    $waiting = historyUser();
    historyInstall($waiting, 'chat-tower');
    historyToken($waiting);

    $deletedOverlay = historyUser();
    historyOverlay(historyInstall($deletedOverlay, 'chat-tower'))->delete();
    historyToken($deletedOverlay);

    $noLinks = historyUser();
    historyInstall($noLinks, 'chat-tower');

    $gone = historyUser();
    $goneOverlay = historyOverlay($goneInstance = historyInstall($gone, 'chat-tower'));
    historyRender($goneOverlay, historyToken($gone));
    app(RecipeInstaller::class)->uninstall($goneInstance);

    $this->actingAs($admin)->get('/admin/funnel?days=30')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($streaming, $waiting, $deletedOverlay, $noLinks, $gone) {
            $props = $page->toArray()['props'];
            $installs = collect($props['installs'])->keyBy('user_id');
            $rows = collect($props['rows'])->keyBy('id');

            expect($installs[$streaming->id]['state'])->toBe('on_stream')
                ->and($installs[$waiting->id]['state'])->toBe('waiting')
                ->and($installs[$deletedOverlay->id]['state'])->toBe('broken')
                ->and($installs[$deletedOverlay->id]['overlay_deleted'])->toBeTrue()
                ->and($installs[$noLinks->id]['state'])->toBe('broken')
                ->and($installs[$noLinks->id]['no_links'])->toBeTrue()
                ->and($installs[$gone->id]['state'])->toBe('uninstalled')
                ->and($installs[$gone->id]['first_loaded_at'])->not->toBeNull()
                ->and($props['install_totals']['installed'])->toBe(count($props['installs']))
                ->and($rows[$streaming->id]['on_stream_at'])->not->toBeNull()
                ->and($rows[$gone->id]['all_removed'])->toBeTrue()
                ->and($rows[$waiting->id]['all_removed'])->toBeFalse()
                ->and($props['products']['chat-tower'])->toBe('Chat Tower');
        });
});
