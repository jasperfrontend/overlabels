<?php

use App\Models\BotBuiltin;
use App\Models\User;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The registry's `enabled` and `permission_level` columns were seeded at signup
 * and published to the bot from the start, but nothing in the app ever wrote
 * them. This page does. A command a product or the platform owns is refused by
 * the controller, not merely left out of the page.
 */
beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);

    $this->user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'builtinstreamer'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
    $this->actingAs($this->user);
});

function builtin(User $user, string $command): BotBuiltin
{
    return BotBuiltin::where('user_id', $user->id)->where('command', $command)->firstOrFail();
}

function mapEntries(string $login = 'builtinstreamer'): array
{
    return test()->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json("channels.{$login}") ?? [];
}

test('the page lists every registered builtin with who owns it', function () {
    $response = $this->get('/settings/bot/builtins')->assertOk();

    $response->assertInertia(fn ($page) => $page->component('settings/bot/builtins/Index'));

    $builtins = collect($response->viewData('page')['props']['builtins']);

    expect($builtins)->toHaveCount(count(BotBuiltin::DEFAULTS))
        ->and($builtins->firstWhere('command', 'followage')['owner'])->toBe('user')
        ->and($builtins->firstWhere('command', 'followage')['editable'])->toBeTrue()
        ->and($builtins->firstWhere('command', 'stack')['owner'])->toBe('product')
        ->and($builtins->firstWhere('command', 'stack')['service'])->toBe('tower')
        ->and($builtins->firstWhere('command', 'stack')['editable'])->toBeFalse()
        ->and($builtins->firstWhere('command', 'enablecontrols')['owner'])->toBe('overlabels')
        ->and($builtins->firstWhere('command', 'enablecontrols')['editable'])->toBeFalse();
});

test('a streamer can switch their own builtin off and move its tier', function () {
    $row = builtin($this->user, 'followage');

    $this->patch("/settings/bot/builtins/{$row->id}", [
        'enabled' => false,
        'permission_level' => 'moderator',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($row->fresh()->enabled)->toBeFalse()
        ->and($row->fresh()->permission_level)->toBe('moderator');
});

// The point of the toggle: the map is what the bot reads, and it drops any
// command missing from it.
test('a builtin switched off disappears from the command map', function () {
    expect(collect(mapEntries())->pluck('command'))->toContain('followage');

    $row = builtin($this->user, 'followage');
    $this->patch("/settings/bot/builtins/{$row->id}", ['enabled' => false, 'permission_level' => 'everyone'])
        ->assertRedirect();

    expect(collect(mapEntries())->pluck('command'))->not->toContain('followage');
});

test('a product-owned builtin refuses the write and keeps its row', function () {
    $row = builtin($this->user, 'stack');

    $this->patch("/settings/bot/builtins/{$row->id}", [
        'enabled' => false,
        'permission_level' => 'broadcaster',
    ])->assertForbidden();

    expect($row->fresh()->enabled)->toBeTrue()
        ->and($row->fresh()->permission_level)->toBe('everyone');
});

test('the controls escape hatch refuses the write, tier included', function () {
    $row = builtin($this->user, 'enablecontrols');

    $this->patch("/settings/bot/builtins/{$row->id}", [
        'enabled' => false,
        'permission_level' => 'everyone',
    ])->assertForbidden();

    expect($row->fresh()->enabled)->toBeTrue()
        ->and($row->fresh()->permission_level)->toBe('broadcaster');
});

test('another channel\'s builtin is not found', function () {
    $other = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'someoneelse'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);

    $row = builtin($other, 'followage');

    $this->patch("/settings/bot/builtins/{$row->id}", ['enabled' => false, 'permission_level' => 'everyone'])
        ->assertNotFound();

    expect($row->fresh()->enabled)->toBeTrue();
});

test('an unknown tier is refused', function () {
    $row = builtin($this->user, 'followage');

    $this->patch("/settings/bot/builtins/{$row->id}", ['enabled' => true, 'permission_level' => 'partner'])
        ->assertSessionHasErrors('permission_level');

    expect($row->fresh()->permission_level)->toBe('everyone');
});

// Drift guard: ownership is declared in DEFAULTS and nowhere else, so a builtin
// added without it would silently become the streamer's to switch off - which
// for a product's verb is the bug this page exists to avoid.
test('every declared builtin names a known owner, and a product names a real service', function () {
    $owners = [BotBuiltin::OWNER_USER, BotBuiltin::OWNER_PRODUCT, BotBuiltin::OWNER_PLATFORM];
    $services = ExternalServiceRegistry::services();

    foreach (BotBuiltin::DEFAULTS as $def) {
        expect($def)->toHaveKey('owner')
            ->and($def['owner'])->toBeIn($owners);

        if ($def['owner'] === BotBuiltin::OWNER_PRODUCT) {
            expect($def['service'] ?? null)->toBeIn($services);
        } else {
            expect($def)->not->toHaveKey('service');
        }
    }
});
