<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Services\Tower\TowerPhysics;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A chat game's public demo (ProductDemo): its real overlay, built from its
 * recipe with sample values, framed by the homepage and the product page.
 */
it('builds the Chat Tower demo from its recipe, with install values and no account', function () {
    $json = $this->getJson('/api/overlay/demo/chat-tower')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->json();

    expect($json['template']['html'])->toContain('tower')
        ->and(array_column($json['expression_controls'], 'key'))->toContain('banner', 'danger', 'sway')
        ->and($json['data']['c:tower:tower_room'])->toBe((string) TowerPhysics::FALL_LINE)
        ->and($json['data']['c:list:tower_record:count'])->toBe('0')
        ->and($json['data']['tower.count'])->toBe('0')
        // No account: the renderer opens no socket and listens to nothing.
        ->and($json['data'])->not->toHaveKey('user_twitch_id');
});

it('gives Follower Bowling sample followers for its pins and an empty lane', function () {
    $json = $this->getJson('/api/overlay/demo/follower-bowling')->assertOk()->json();

    expect($json['data']['channel_followers.0.user_name'])->toBe('PixelMoth')
        ->and($json['data']['c:list:lane:count'])->toBe('0')
        ->and(array_column($json['expression_controls'], 'key'))->toContain('bowl_seed', 'bowl_knocked');
});

it('writes nothing while building a demo', function () {
    $this->getJson('/api/overlay/demo/chat-tower')->assertOk();
    $this->getJson('/api/overlay/demo/follower-bowling')->assertOk();

    expect(OverlayTemplate::count())->toBe(0)
        ->and(OverlayControl::count())->toBe(0);
});

it('has no demo for a product without designer scenes, or one that does not exist', function (string $slug) {
    $this->getJson("/api/overlay/demo/{$slug}")->assertNotFound();
    $this->get("/products/{$slug}/demo-frame")->assertNotFound();
})->with(['chat-plinko', 'no-such-product']);

it('serves the demo frame page, noindex', function () {
    $this->get('/products/chat-tower/demo-frame')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('"chat-tower"', false);
});

it('waits for a click on the homepage and starts by itself on the product page', function () {
    $home = $this->get('/')->assertOk()->getContent();
    expect(substr_count($home, 'data-overlay-demo-autostart="0"'))->toBe(2)
        ->and($home)->toContain('Play the live demo');

    $this->get('/products/chat-tower')->assertOk()->assertSee('data-overlay-demo-autostart="1"', false);
    $this->get('/products/follower-bowling')->assertOk()->assertSee('data-overlay-demo-autostart="1"', false);
});
