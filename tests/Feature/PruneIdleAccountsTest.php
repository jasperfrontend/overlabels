<?php

use App\Jobs\OnboardNewUser;
use App\Models\EventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Support\WelcomeFunnel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

// Erasure revokes the Twitch grant and talks to the images bucket; unfaked,
// those are real network calls.
beforeEach(function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);
    Storage::fake('images');

    $author = User::factory()->create();
    $this->starter = Kit::factory()->create(['owner_id' => $author->id, 'is_starter_kit' => true]);

    foreach (['Follow alert', 'Raid alert', 'Stream overlay'] as $name) {
        $template = OverlayTemplate::factory()->create([
            'owner_id' => $author->id,
            'name' => $name,
            'type' => $name === 'Stream overlay' ? 'static' : 'alert',
        ]);
        OverlayControl::factory()->create([
            'overlay_template_id' => $template->id,
            'user_id' => $author->id,
            'source' => null,
        ]);
        $this->starter->templates()->attach($template->id);
    }
});

/**
 * A signup that copied the starter Kit from the kit picker and assigned its
 * alerts to Twitch events: its overlays, their controls and the mappings.
 * Onboarding no longer copies anything itself (OL-2611-009), but the starter
 * copy must still count as nothing, so the prune rules are pinned against it.
 */
function signedUp(int $hoursAgo = 49, array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['created_at' => now()->subHours($hoursAgo)], $attributes));

    (new OnboardNewUser($user))->handle();

    $copy = test()->starter->fork($user);
    foreach ($copy->templates()->where('type', 'alert')->get() as $i => $alert) {
        EventTemplateMapping::create([
            'user_id' => $user->id,
            'event_type' => ['channel.follow', 'channel.raid'][$i],
            'template_id' => $alert->id,
            'enabled' => true,
            'duration_ms' => 5000,
        ]);
    }

    return $user;
}

function prune(): void
{
    test()->artisan('users:prune-idle')->assertSuccessful();
}

it('deletes an account that only holds the starter kit after 48 hours', function () {
    $user = signedUp();

    expect(Kit::where('owner_id', $user->id)->count())->toBe(1)
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(3)
        ->and(OverlayControl::where('user_id', $user->id)->count())->toBe(3)
        ->and(EventTemplateMapping::where('user_id', $user->id)->count())->toBeGreaterThan(0);

    prune();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    expect(Kit::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse();
});

it('still deletes an account whose only activity was editing the starter kit', function () {
    $user = signedUp();

    OverlayTemplate::where('owner_id', $user->id)->first()->update(['html' => '<div>mine now</div>']);
    OverlayControl::where('user_id', $user->id)->first()->update(['value' => 'changed']);

    prune();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('leaves an account alone inside its first 48 hours', function () {
    $user = signedUp(hoursAgo: 47);

    prune();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('keeps an account with an active overlay token', function () {
    $user = signedUp();
    OverlayAccessToken::factory()->create(['user_id' => $user->id, 'token_prefix' => 'abcd1234', 'is_active' => true, 'expires_at' => null]);

    prune();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('does not count a revoked or expired token as active', function () {
    $revoked = signedUp();
    OverlayAccessToken::factory()->create(['user_id' => $revoked->id, 'token_prefix' => 'abcd1234', 'is_active' => false, 'expires_at' => null]);

    $expired = signedUp();
    OverlayAccessToken::factory()->create(['user_id' => $expired->id, 'token_prefix' => 'abcd1234', 'is_active' => true, 'expires_at' => now()->subHour()]);

    prune();

    $this->assertDatabaseMissing('users', ['id' => $revoked->id]);
    $this->assertDatabaseMissing('users', ['id' => $expired->id]);
});

it('keeps an account that installed a product, even if the install was removed', function () {
    $user = signedUp();
    $user->setPreference(WelcomeFunnel::PREFERENCE.'.installed_at', now()->timestamp);
    $user->save();

    prune();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('keeps an account that made anything of its own', function (Closure $make) {
    $user = signedUp();
    $make($user);

    prune();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
})->with([
    'an overlay' => [fn (User $user) => OverlayTemplate::factory()->create(['owner_id' => $user->id])],
    'a kit' => [fn (User $user) => Kit::factory()->create(['owner_id' => $user->id])],
    'a user-scoped control' => [fn (User $user) => OverlayControl::factory()->create(['user_id' => $user->id, 'overlay_template_id' => null])],
    'an integration' => [fn (User $user) => ExternalIntegration::factory()->create(['user_id' => $user->id])],
    'the bot switched on' => [fn (User $user) => $user->update(['bot_enabled' => true])],
]);

it('never deletes admins, the system user or banned accounts', function () {
    $admin = signedUp(attributes: ['role' => 'admin']);
    $system = signedUp(attributes: ['is_system_user' => true]);
    $banned = signedUp();
    $banned->ban(['comment' => 'test']);

    prune();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
    $this->assertDatabaseHas('users', ['id' => $system->id]);
    $this->assertDatabaseHas('users', ['id' => $banned->id]);
});

it('deletes nothing on a dry run', function () {
    $user = signedUp();

    $this->artisan('users:prune-idle --dry-run')
        ->expectsOutputToContain('idle accounts: 1')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});
