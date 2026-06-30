<?php

use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Spin up a user plus N distinct alert templates for variant ladders.
 *
 * @return array{0: User, 1: array<int, OverlayTemplate>}
 */
function makeUserWithTemplates(int $count = 1): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $templates = [];
    for ($i = 0; $i < $count; $i++) {
        $templates[] = OverlayTemplate::factory()->create([
            'owner_id' => $user->id,
            'fork_of_id' => null,
            'type' => 'alert',
            'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        ]);
    }

    return [$user, $templates];
}

function makeCheerMapping(User $user, OverlayTemplate $template, ?string $type, ?int $value): EventTemplateMapping
{
    return EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'condition_type' => $type,
        'condition_value' => $value,
        'template_id' => $template->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);
}

test('exactly match beats at_least and base', function () {
    [$user, $t] = makeUserWithTemplates(3);
    $base = makeCheerMapping($user, $t[0], null, null);
    $atLeast = makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 100);
    $exact = makeCheerMapping($user, $t[2], EventTemplateMapping::CONDITION_EXACTLY, 200);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 200]);

    expect($winner?->id)->toBe($exact->id);
});

test('highest matching at_least threshold wins', function () {
    [$user, $t] = makeUserWithTemplates(3);
    makeCheerMapping($user, $t[0], EventTemplateMapping::CONDITION_AT_LEAST, 1);
    makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 100);
    $big = makeCheerMapping($user, $t[2], EventTemplateMapping::CONDITION_AT_LEAST, 1000);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 5000]);

    expect($winner?->id)->toBe($big->id);
});

test('at_least falls through to a lower tier when amount is below the top threshold', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $small = makeCheerMapping($user, $t[0], EventTemplateMapping::CONDITION_AT_LEAST, 1);
    makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 1000);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 500]);

    expect($winner?->id)->toBe($small->id);
});

test('base row is the fallback when no condition matches', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $base = makeCheerMapping($user, $t[0], null, null);
    makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 1000);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 5]);

    expect($winner?->id)->toBe($base->id);
});

test('no match and no base row resolves to null', function () {
    [$user, $t] = makeUserWithTemplates(1);
    makeCheerMapping($user, $t[0], EventTemplateMapping::CONDITION_AT_LEAST, 1000);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 5]);

    expect($winner)->toBeNull();
});

test('missing amount field is treated as zero', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $base = makeCheerMapping($user, $t[0], null, null);
    makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 1);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', []);

    expect($winner?->id)->toBe($base->id);
});

test('equal at_least thresholds break the tie on lowest template_id', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $first = makeCheerMapping($user, $t[0], EventTemplateMapping::CONDITION_AT_LEAST, 100);
    makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_AT_LEAST, 100);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 500]);

    expect($winner?->id)->toBe($first->id);
});

test('disabled variants are ignored by the resolver', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $base = makeCheerMapping($user, $t[0], null, null);
    $disabled = makeCheerMapping($user, $t[1], EventTemplateMapping::CONDITION_EXACTLY, 200);
    $disabled->update(['enabled' => false]);

    $winner = EventTemplateMapping::resolveForEvent($user->id, 'channel.cheer', ['bits' => 200]);

    expect($winner?->id)->toBe($base->id);
});

test('gift count uses the total field for its condition', function () {
    [$user, $t] = makeUserWithTemplates(2);
    $base = EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.subscription.gift',
        'template_id' => $t[0]->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);
    $big = EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.subscription.gift',
        'condition_type' => EventTemplateMapping::CONDITION_AT_LEAST, 'condition_value' => 10,
        'template_id' => $t[1]->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    expect(EventTemplateMapping::resolveForEvent($user->id, 'channel.subscription.gift', ['total' => 25])?->id)->toBe($big->id);
    expect(EventTemplateMapping::resolveForEvent($user->id, 'channel.subscription.gift', ['total' => 3])?->id)->toBe($base->id);
});

test('updateTriggers lets two templates own channel.cheer with different conditions', function () {
    [$user, $t] = makeUserWithTemplates(2);
    [$small, $big] = $t;
    $this->actingAs($user);

    $this->put("/templates/{$small->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.cheer', 'condition_type' => null, 'condition_value' => null, 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->put("/templates/{$big->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.cheer', 'condition_type' => 'at_least', 'condition_value' => 1000, 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    // The small/base row survives - assigning cheer to a second template did
    // not override it, because amount-bearing events form a variant set.
    $this->assertDatabaseHas('event_template_mappings', [
        'event_type' => 'channel.cheer', 'template_id' => $small->id, 'condition_type' => null,
    ]);
    $this->assertDatabaseHas('event_template_mappings', [
        'event_type' => 'channel.cheer', 'template_id' => $big->id, 'condition_type' => 'at_least', 'condition_value' => 1000,
    ]);
});

test('updateTriggers strips a condition smuggled onto a non-amount event', function () {
    [$user, $t] = makeUserWithTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'condition_type' => 'at_least', 'condition_value' => 50, 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'event_type' => 'channel.follow', 'template_id' => $t[0]->id, 'condition_type' => null, 'condition_value' => null,
    ]);
});

test('updateTriggers rejects an invalid condition_type', function () {
    [$user, $t] = makeUserWithTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.cheer', 'condition_type' => 'more_than', 'condition_value' => 50, 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertSessionHasErrors('twitch.0.condition_type');
});

test('updateTriggers requires a condition_value when a condition_type is set', function () {
    [$user, $t] = makeUserWithTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.cheer', 'condition_type' => 'at_least', 'condition_value' => null, 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertSessionHasErrors('twitch.0.condition_value');
});
