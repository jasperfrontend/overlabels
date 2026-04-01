<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeExpressionTestFixture(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'slug' => 'test-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $template];
}

test('creating expression control with valid expression succeeds', function () {
    [$user, $template] = makeExpressionTestFixture();

    // Create the dependency
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '5',
        'sort_order' => 0,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'double_deaths',
        'label' => 'Double Deaths',
        'type' => 'expression',
        'config' => [
            'expression' => 'c.deaths * 2',
        ],
    ]);

    $response->assertStatus(201);
    $control = OverlayControl::where('key', 'double_deaths')
        ->where('overlay_template_id', $template->id)
        ->first();

    expect($control)->not->toBeNull();
    expect($control->type)->toBe('expression');
    expect($control->config['expression'])->toBe('c.deaths * 2');
    expect($control->config['dependencies'])->toBe(['deaths']);
});

test('creating expression control referencing service-managed control extracts namespaced deps', function () {
    [$user, $template] = makeExpressionTestFixture();

    // Create user-scoped Ko-fi control
    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'kofis_received',
        'type' => 'counter',
        'value' => '10',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    // Create user-scoped StreamLabs control
    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'donations_received',
        'type' => 'counter',
        'value' => '5',
        'source' => 'streamlabs',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'total_donations',
        'label' => 'Total Donations',
        'type' => 'expression',
        'config' => [
            'expression' => 'c.kofi.kofis_received + c.streamlabs.donations_received',
        ],
    ]);

    $response->assertStatus(201);
    $control = OverlayControl::where('key', 'total_donations')
        ->where('overlay_template_id', $template->id)
        ->first();

    expect($control->config['dependencies'])->toContain('kofi:kofis_received');
    expect($control->config['dependencies'])->toContain('streamlabs:donations_received');
});

test('creating expression control with missing dependency returns 422', function () {
    [$user, $template] = makeExpressionTestFixture();

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'broken',
        'label' => 'Broken',
        'type' => 'expression',
        'config' => [
            'expression' => 'c.nonexistent + 1',
        ],
    ]);

    $response->assertStatus(422);
});

test('creating expression control with no references returns 422', function () {
    [$user, $template] = makeExpressionTestFixture();

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'static_val',
        'label' => 'Static',
        'type' => 'expression',
        'config' => [
            'expression' => '42 + 1',
        ],
    ]);

    $response->assertStatus(422);
});

test('creating expression control exceeding 500 chars returns 422', function () {
    [$user, $template] = makeExpressionTestFixture();

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'long_expr',
        'type' => 'expression',
        'config' => [
            'expression' => str_repeat('c.x + ', 100),
        ],
    ]);

    $response->assertStatus(422);
});

test('expression control cycle detection - self-reference is rejected', function () {
    [$user, $template] = makeExpressionTestFixture();

    // Create a dependency
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'base',
        'type' => 'text',
        'value' => '1',
        'sort_order' => 0,
    ]);

    $this->actingAs($user);

    // First create expr_a that depends on base
    $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'expr_a',
        'type' => 'expression',
        'config' => ['expression' => 'c.base + 1'],
    ])->assertStatus(201);

    // Now try to create expr_b that depends on expr_a
    $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'expr_b',
        'type' => 'expression',
        'config' => ['expression' => 'c.expr_a + 1'],
    ])->assertStatus(201);

    // Now try to update expr_a to depend on expr_b - should detect cycle
    $exprA = OverlayControl::where('key', 'expr_a')
        ->where('overlay_template_id', $template->id)
        ->first();

    $response = $this->putJson("/templates/{$template->id}/controls/{$exprA->id}", [
        'config' => ['expression' => 'c.expr_b + 1'],
    ]);

    $response->assertStatus(422);
});

test('setValue returns 403 for expression control', function () {
    [$user, $template] = makeExpressionTestFixture();

    $base = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'base',
        'type' => 'text',
        'value' => '1',
        'sort_order' => 0,
    ]);

    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'expr_test',
        'type' => 'expression',
        'value' => null,
        'config' => [
            'expression' => 'c.base + 1',
            'dependencies' => ['base'],
        ],
        'sort_order' => 1,
    ]);

    $this->actingAs($user);

    $this->postJson("/templates/{$template->id}/controls/{$control->id}/value", [
        'value' => 'manual',
    ])->assertStatus(403);
});

test('updating expression control config validates dependencies', function () {
    [$user, $template] = makeExpressionTestFixture();

    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'base',
        'type' => 'text',
        'value' => '1',
        'sort_order' => 0,
    ]);

    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'expr_test',
        'type' => 'expression',
        'value' => null,
        'config' => [
            'expression' => 'c.base + 1',
            'dependencies' => ['base'],
        ],
        'sort_order' => 1,
    ]);

    $this->actingAs($user);

    // Update to reference a non-existent control
    $response = $this->putJson("/templates/{$template->id}/controls/{$control->id}", [
        'config' => ['expression' => 'c.nonexistent + 1'],
    ]);

    $response->assertStatus(422);
});

test('reserved key names are rejected', function () {
    [$user, $template] = makeExpressionTestFixture();

    $this->actingAs($user);

    foreach (['kofi', 'streamlabs', 'twitch', 'gpslogger'] as $reserved) {
        $response = $this->postJson("/templates/{$template->id}/controls", [
            'key' => $reserved,
            'type' => 'text',
            'value' => 'test',
        ]);

        $response->assertStatus(422);
    }
});

test('extractExpressionDependencies parses simple references', function () {
    $deps = OverlayControl::extractExpressionDependencies('c.deaths + 1');
    expect($deps)->toBe(['deaths']);
});

test('extractExpressionDependencies parses namespaced references', function () {
    $deps = OverlayControl::extractExpressionDependencies('c.kofi.kofis_received + c.streamlabs.total_received');
    expect($deps)->toContain('kofi:kofis_received');
    expect($deps)->toContain('streamlabs:total_received');
    expect($deps)->toHaveCount(2);
});

test('extractExpressionDependencies deduplicates references', function () {
    $deps = OverlayControl::extractExpressionDependencies('c.deaths + c.deaths');
    expect($deps)->toBe(['deaths']);
});

test('extractExpressionDependencies handles mixed references', function () {
    $deps = OverlayControl::extractExpressionDependencies('c.deaths > 5 ? c.kofi.total_received : c.goal');
    expect($deps)->toContain('deaths');
    expect($deps)->toContain('kofi:total_received');
    expect($deps)->toContain('goal');
    expect($deps)->toHaveCount(3);
});

test('extractExpressionDependencies resolves _at references to base controls', function () {
    $deps = OverlayControl::extractExpressionDependencies(
        'c.streamlabs.latest_donor_name_at > c.kofi.latest_donor_name_at ? c.streamlabs.latest_donor_name : c.kofi.latest_donor_name'
    );
    expect($deps)->toContain('streamlabs:latest_donor_name');
    expect($deps)->toContain('kofi:latest_donor_name');
    expect($deps)->toHaveCount(2);
});

test('extractExpressionDependencies resolves template-scoped _at references', function () {
    $deps = OverlayControl::extractExpressionDependencies('c.deaths_at > 1000 ? c.deaths : 0');
    expect($deps)->toContain('deaths');
    expect($deps)->toHaveCount(1);
});
