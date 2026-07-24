<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function builderUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function builderBlock(User $owner, array $attributes = []): OverlayTemplate
{
    return OverlayTemplate::factory()->block()->create(array_merge([
        'owner_id' => $owner->id,
        'fork_of_id' => null,
        'slug' => 'block-'.fake()->unique()->lexify('????????'),
        'is_public' => true,
        'metadata' => ['block' => ['default_span' => ['w' => 3, 'h' => 2]]],
    ], $attributes));
}

function builderMetadataPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'version' => 1,
        'grid' => ['cols' => 12, 'rows' => 8, 'gap' => 8],
        'canvas' => ['width' => 1920, 'height' => 1080],
        'placements' => [
            [
                'instance_id' => 'a3f2',
                'block_template_id' => 1,
                'block_slug' => 'some-block',
                'block_name' => 'Some Block',
                'x' => 1,
                'y' => 1,
                'w' => 4,
                'h' => 2,
                'snapshot' => ['head' => '', 'html' => '<div class="c">[[[followers_total]]]</div>', 'css' => '.c { color: red; }'],
            ],
        ],
    ], $overrides);
}

function postComposedOverlay(User $user, array $builderOverrides = [])
{
    return test()->actingAs($user)->postJson('/templates', [
        'name' => 'Composed Overlay',
        'html' => '<div id="builder-root"><div id="blk-a3f2"><div class="c">[[[followers_total]]]</div></div></div>',
        'css' => '#builder-root { display: grid; }',
        'type' => 'static',
        'is_public' => false,
        'metadata' => ['builder' => builderMetadataPayload($builderOverrides)],
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Builder metadata persistence + validation
// ──────────────────────────────────────────────────────────────────────────────

test('a composed overlay persists its builder metadata and round-trips', function () {
    $user = builderUser();
    postComposedOverlay($user)->assertOk();

    $template = OverlayTemplate::where('owner_id', $user->id)->first();
    expect($template->type)->toBe('static')
        ->and($template->isBuilderComposed())->toBeTrue()
        ->and($template->metadata['builder']['grid'])->toBe(['cols' => 12, 'rows' => 8, 'gap' => 8])
        ->and($template->metadata['builder']['placements'][0]['instance_id'])->toBe('a3f2')
        ->and($template->metadata['builder']['placements'][0]['snapshot']['html'])
        ->toBe('<div class="c">[[[followers_total]]]</div>');
});

test('builder metadata rejects more than 40 placements', function () {
    $user = builderUser();
    $placement = builderMetadataPayload()['placements'][0];
    $placements = [];
    for ($i = 0; $i < 41; $i++) {
        $placements[] = array_merge($placement, ['instance_id' => 'p'.$i]);
    }

    postComposedOverlay($user, ['placements' => $placements])->assertUnprocessable();
});

test('builder metadata rejects an oversized snapshot', function () {
    $user = builderUser();

    postComposedOverlay($user, [
        'placements' => [['snapshot' => ['css' => str_repeat('x', 70000)]]],
    ])->assertUnprocessable();
});

test('builder metadata rejects an out of range grid', function () {
    $user = builderUser();

    postComposedOverlay($user, ['grid' => ['cols' => 99]])->assertUnprocessable();
});

test('script tags in placement snapshots are stripped on save', function () {
    $user = builderUser();

    postComposedOverlay($user, [
        'placements' => [['snapshot' => ['html' => '<div>ok</div><script>alert(1)</script>']]],
    ])->assertOk();

    $template = OverlayTemplate::where('owner_id', $user->id)->first();
    expect($template->metadata['builder']['placements'][0]['snapshot']['html'])
        ->not->toContain('<script')
        ->toContain('<div>ok</div>');
});

// ──────────────────────────────────────────────────────────────────────────────
// Eject: metadata.builder = null converts to a hand-edited overlay
// ──────────────────────────────────────────────────────────────────────────────

test('ejecting removes builder metadata but keeps the compiled code', function () {
    $user = builderUser();
    postComposedOverlay($user)->assertOk();
    $template = OverlayTemplate::where('owner_id', $user->id)->first();
    $htmlBefore = $template->html;

    $this->actingAs($user)->putJson("/templates/{$template->id}", [
        'metadata' => ['builder' => null],
    ])->assertSuccessful();

    $template->refresh();
    expect($template->isBuilderComposed())->toBeFalse()
        ->and($template->html)->toBe($htmlBefore);
});

// ──────────────────────────────────────────────────────────────────────────────
// Block snapshot endpoint
// ──────────────────────────────────────────────────────────────────────────────

test('blockSnapshot returns code, span, and non-service controls', function () {
    $owner = builderUser();
    $viewer = builderUser();
    $block = builderBlock($owner, ['css' => '.x { color: red; }']);

    OverlayControl::create([
        'overlay_template_id' => $block->id,
        'user_id' => $owner->id,
        'key' => 'kill_count',
        'label' => 'Kill Count',
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);
    OverlayControl::create([
        'overlay_template_id' => $block->id,
        'user_id' => $owner->id,
        'key' => 'donations_received',
        'type' => 'number',
        'value' => '0',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($viewer)
        ->getJson("/templates/blocks/{$block->id}/snapshot")
        ->assertOk()
        ->assertJsonPath('slug', $block->slug)
        ->assertJsonPath('css', '.x { color: red; }')
        ->assertJsonPath('default_span', ['w' => 3, 'h' => 2])
        ->assertJsonCount(1, 'controls')
        ->assertJsonPath('controls.0.key', 'kill_count');
});

test('blockSnapshot rejects non-blocks and private blocks of other users', function () {
    $owner = builderUser();
    $viewer = builderUser();

    $static = OverlayTemplate::factory()->create([
        'owner_id' => $owner->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
    ]);
    $privateBlock = builderBlock($owner, ['is_public' => false]);

    $this->actingAs($viewer)->getJson("/templates/blocks/{$static->id}/snapshot")->assertNotFound();
    $this->actingAs($viewer)->getJson("/templates/blocks/{$privateBlock->id}/snapshot")->assertNotFound();
    $this->actingAs($owner)->getJson("/templates/blocks/{$privateBlock->id}/snapshot")->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// Builder page + edit-mode props
// ──────────────────────────────────────────────────────────────────────────────

test('the builder page loads with the visible block library', function () {
    $user = builderUser();
    $other = builderUser();
    builderBlock($other, ['name' => 'Public Block']);
    builderBlock($other, ['name' => 'Hidden Block', 'is_public' => false]);
    builderBlock($user, ['name' => 'My Private Block', 'is_public' => false]);

    $this->actingAs($user)->get('/builder')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('builder/create')
            ->has('sampleData')
            ->has('blocks', 2)
    );
});

test('the edit page ships builder props only for composed overlays', function () {
    $user = builderUser();
    postComposedOverlay($user)->assertOk();
    $composed = OverlayTemplate::where('owner_id', $user->id)->first();

    $plain = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'plain-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);

    $this->actingAs($user)->get("/templates/{$composed->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page->has('sampleData')->has('builderBlocks')
    );
    $this->actingAs($user)->get("/templates/{$plain->id}/edit")->assertInertia(
        fn (AssertableInertia $page) => $page->where('sampleData', null)->where('builderBlocks', null)
    );
});

// ──────────────────────────────────────────────────────────────────────────────
// Controls carryover via the existing import endpoint
// ──────────────────────────────────────────────────────────────────────────────

test('importing block controls into a composed overlay skips existing keys', function () {
    $user = builderUser();
    postComposedOverlay($user)->assertOk();
    $composed = OverlayTemplate::where('owner_id', $user->id)->first();

    OverlayControl::create([
        'overlay_template_id' => $composed->id,
        'user_id' => $user->id,
        'key' => 'goal_target',
        'type' => 'number',
        'value' => '500',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)->postJson("/templates/{$composed->id}/controls/import", [
        'controls' => [
            ['action' => 'create', 'key' => 'goal_target', 'type' => 'number', 'value' => '9999'],
            ['action' => 'create', 'key' => 'kill_count', 'type' => 'counter', 'value' => '0'],
        ],
    ])->assertOk()->assertJsonPath('count', 1);

    expect($composed->controls()->where('key', 'goal_target')->first()->value)->toBe('500')
        ->and($composed->controls()->where('key', 'kill_count')->exists())->toBeTrue();
});
