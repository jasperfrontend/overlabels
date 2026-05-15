<?php

use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function lwEndpointFixture(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'slug' => 'test-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $template];
}

function lwEndpointCounter(User $user, OverlayTemplate $template, string $key = 'wins'): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => $key,
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);
}

function lwEndpointList(User $user, string $slug = 'log'): OptionSet
{
    return OptionSet::create([
        'user_id' => $user->id,
        'slug' => $slug,
        'items' => [],
        'min_items' => 0,
        'user_editable' => true,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Create (POST /templates/{id}/controls)
// ──────────────────────────────────────────────────────────────────────────────

it('creates a list_writer control with valid source + target', function () {
    [$user, $template] = lwEndpointFixture();
    $source = lwEndpointCounter($user, $template, 'wins');
    $list = lwEndpointList($user, 'wins_log');

    $response = $this->actingAs($user)->postJson("/templates/{$template->id}/controls", [
        'key' => 'wins_logger',
        'label' => 'Log wins',
        'type' => 'list_writer',
        'config' => [
            'source_control_id' => $source->id,
            'target_list_id' => $list->id,
        ],
    ]);

    $response->assertStatus(201);
    $control = OverlayControl::where('key', 'wins_logger')
        ->where('overlay_template_id', $template->id)
        ->first();

    expect($control)->not->toBeNull()
        ->and($control->type)->toBe('list_writer')
        ->and($control->value)->toBeNull()
        ->and($control->config['source_control_id'])->toBe($source->id)
        ->and($control->config['target_list_id'])->toBe($list->id);
});

it('rejects list_writer creation when source_control_id is missing', function () {
    [$user, $template] = lwEndpointFixture();
    $list = lwEndpointList($user);

    $this->actingAs($user)->postJson("/templates/{$template->id}/controls", [
        'key' => 'broken',
        'type' => 'list_writer',
        'config' => ['target_list_id' => $list->id],
    ])->assertStatus(422);
});

it('rejects list_writer creation when source belongs to a different user', function () {
    [$owner, $template] = lwEndpointFixture();
    [$other, $otherTemplate] = lwEndpointFixture();
    $foreignSource = lwEndpointCounter($other, $otherTemplate, 'foreign');
    $list = lwEndpointList($owner);

    $this->actingAs($owner)->postJson("/templates/{$template->id}/controls", [
        'key' => 'cross_user',
        'type' => 'list_writer',
        'config' => [
            'source_control_id' => $foreignSource->id,
            'target_list_id' => $list->id,
        ],
    ])->assertStatus(422);
});

it('rejects list_writer creation when target list belongs to a different user', function () {
    [$owner, $template] = lwEndpointFixture();
    [$other] = lwEndpointFixture();
    $source = lwEndpointCounter($owner, $template, 'wins');
    $foreignList = lwEndpointList($other, 'their_list');

    $this->actingAs($owner)->postJson("/templates/{$template->id}/controls", [
        'key' => 'cross_user',
        'type' => 'list_writer',
        'config' => [
            'source_control_id' => $source->id,
            'target_list_id' => $foreignList->id,
        ],
    ])->assertStatus(422);
});

it('accepts a user-scoped service control as the source', function () {
    [$user, $template] = lwEndpointFixture();
    $serviceControl = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'latest_donor_name',
        'type' => 'text',
        'value' => '',
        'sort_order' => 0,
        'source' => 'kofi',
        'source_managed' => true,
    ]);
    $list = lwEndpointList($user, 'donors');

    $this->actingAs($user)->postJson("/templates/{$template->id}/controls", [
        'key' => 'donor_logger',
        'type' => 'list_writer',
        'config' => [
            'source_control_id' => $serviceControl->id,
            'target_list_id' => $list->id,
        ],
    ])->assertStatus(201);

    expect(OverlayControl::where('key', 'donor_logger')->first()?->config['source_control_id'])
        ->toBe($serviceControl->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Update (PATCH /templates/{id}/controls/{control})
// ──────────────────────────────────────────────────────────────────────────────

it('updates source + target on an existing list_writer', function () {
    [$user, $template] = lwEndpointFixture();
    $oldSource = lwEndpointCounter($user, $template, 'old');
    $newSource = lwEndpointCounter($user, $template, 'new');
    $oldList = lwEndpointList($user, 'old_list');
    $newList = lwEndpointList($user, 'new_list');

    $writer = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'mover',
        'type' => 'list_writer',
        'value' => null,
        'config' => ['source_control_id' => $oldSource->id, 'target_list_id' => $oldList->id],
        'sort_order' => 0,
    ]);

    $this->actingAs($user)->putJson("/templates/{$template->id}/controls/{$writer->id}", [
        'config' => ['source_control_id' => $newSource->id, 'target_list_id' => $newList->id],
    ])->assertStatus(200);

    $writer->refresh();
    expect($writer->config['source_control_id'])->toBe($newSource->id)
        ->and($writer->config['target_list_id'])->toBe($newList->id);
});

it('rejects update that points the list_writer at another users source', function () {
    [$owner, $template] = lwEndpointFixture();
    [$other, $otherTemplate] = lwEndpointFixture();
    $mySource = lwEndpointCounter($owner, $template, 'mine');
    $foreignSource = lwEndpointCounter($other, $otherTemplate, 'foreign');
    $myList = lwEndpointList($owner);

    $writer = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $owner->id,
        'key' => 'mover',
        'type' => 'list_writer',
        'value' => null,
        'config' => ['source_control_id' => $mySource->id, 'target_list_id' => $myList->id],
        'sort_order' => 0,
    ]);

    $this->actingAs($owner)->putJson("/templates/{$template->id}/controls/{$writer->id}", [
        'config' => ['source_control_id' => $foreignSource->id, 'target_list_id' => $myList->id],
    ])->assertStatus(422);

    $writer->refresh();
    expect($writer->config['source_control_id'])->toBe($mySource->id);
});

it('permits label-only update without re-validating source/target', function () {
    [$user, $template] = lwEndpointFixture();
    $source = lwEndpointCounter($user, $template, 'wins');
    $list = lwEndpointList($user);

    $writer = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'renamed',
        'type' => 'list_writer',
        'value' => null,
        'config' => ['source_control_id' => $source->id, 'target_list_id' => $list->id],
        'sort_order' => 0,
    ]);

    $this->actingAs($user)->putJson("/templates/{$template->id}/controls/{$writer->id}", [
        'label' => 'A nice new label',
    ])->assertStatus(200);

    expect($writer->fresh()->label)->toBe('A nice new label');
});
