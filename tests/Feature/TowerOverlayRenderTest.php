<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\TowerBlock;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);

    ExternalIntegration::create([
        'user_id' => $this->user->id,
        'service' => 'tower',
        'enabled' => true,
        'settings' => ['tower_lifetime' => 'per_stream'],
    ]);

    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function seedTowerBlocks(User $user, int $height): void
{
    for ($i = 1; $i <= $height; $i++) {
        TowerBlock::create([
            'user_id' => $user->id,
            'position' => $i,
            'chatter_twitch_id' => (string) $i,
            'chatter_login' => "builder_{$i}",
            'chatter_display_name' => "Builder{$i}",
            'color' => '#1E90FF',
            'offset' => 0.25,
            'x' => round(0.25 * $i, 3),
            'placed_at' => now()->subMinutes($height - $i),
        ]);
    }
}

function renderTowerOverlay(User $user, string $html): TestResponse
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'tower-render-test',
        'is_active' => true,
    ]);

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'html' => $html,
        'slug' => 'tower-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    // The controller extracts tags at store/import/update; a factory create
    // bypasses that, so run the real extraction the render path relies on.
    $template->template_tags = $template->extractTemplateTags();
    $template->save();

    return test()->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain]);
}

const TOWER_FOREACH_HTML = '<div>[[[foreach:tower as block]]][[[block.name]]] [[[block.x]]][[[endforeach]]]</div>';

test('a template that loops the tower gets the blocks bottom to top, the height and the window', function () {
    seedTowerBlocks($this->user, 3);

    $response = renderTowerOverlay($this->user, TOWER_FOREACH_HTML)->assertOk();

    $data = $response->json('data');
    expect($data['tower.count'])->toBe('3')
        ->and($data['tower.0.position'])->toBe('1')
        ->and($data['tower.0.login'])->toBe('builder_1')
        ->and($data['tower.0.color'])->toBe('#1E90FF')
        ->and($data['tower.2.position'])->toBe('3')
        ->and($data['tower.2.x'])->toBe('0.75')
        ->and($response->json('tower_window'))->toBe(TowerBlock::WINDOW);
});

test('a template without a tower loop ships no tower data', function () {
    seedTowerBlocks($this->user, 2);

    $response = renderTowerOverlay($this->user, '<div>[[[channel_name]]]</div>')->assertOk();

    expect($response->json('data'))->not->toHaveKey('tower.count');
});

test('the window keeps the top of a tall tower and never the count', function () {
    seedTowerBlocks($this->user, TowerBlock::WINDOW + 5);

    $data = renderTowerOverlay($this->user, TOWER_FOREACH_HTML)->assertOk()->json('data');

    $last = TowerBlock::WINDOW - 1;

    expect($data['tower.count'])->toBe((string) (TowerBlock::WINDOW + 5))
        // The lowest five blocks fell out of the window; the camera follows the top.
        ->and($data['tower.0.position'])->toBe('6')
        ->and($data["tower.{$last}.position"])->toBe((string) (TowerBlock::WINDOW + 5))
        ->and($data)->not->toHaveKey('tower.'.TowerBlock::WINDOW.'.position');
});
