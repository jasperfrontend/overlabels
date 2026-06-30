<?php

use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * @return array{0: User, 1: array<int, OverlayTemplate>}
 */
function makeUserWithAlertTemplates(int $count = 1): array
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

function makeDonationMapping(User $user, OverlayTemplate $template, ?string $type, ?int $value, string $service = 'kofi'): ExternalEventTemplateMapping
{
    return ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => $service,
        'event_type' => 'donation',
        'condition_type' => $type,
        'condition_value' => $value,
        'overlay_template_id' => $template->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);
}

test('exactly match beats at_least and base', function () {
    [$user, $t] = makeUserWithAlertTemplates(3);
    makeDonationMapping($user, $t[0], null, null);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);
    $exact = makeDonationMapping($user, $t[2], ExternalEventTemplateMapping::CONDITION_EXACTLY, 10);

    $winner = ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '10.00');

    expect($winner?->id)->toBe($exact->id);
});

test('highest matching at_least threshold wins', function () {
    [$user, $t] = makeUserWithAlertTemplates(3);
    makeDonationMapping($user, $t[0], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 1);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);
    $big = makeDonationMapping($user, $t[2], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 50);

    $winner = ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '100.00');

    expect($winner?->id)->toBe($big->id);
});

test('at_least falls through to a lower tier below the top threshold', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $small = makeDonationMapping($user, $t[0], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 1);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 50);

    $winner = ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '20.00');

    expect($winner?->id)->toBe($small->id);
});

test('decimal amount below a whole-unit threshold does not match it', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $base = makeDonationMapping($user, $t[0], null, null);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);

    // 4.99 is below the "at least 5" tier - falls to base.
    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '4.99')?->id)->toBe($base->id);
});

test('decimal amount at or above a whole-unit threshold matches it', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    makeDonationMapping($user, $t[0], null, null);
    $tier = makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);

    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '5.00')?->id)->toBe($tier->id);
});

test('base row is the fallback when no condition matches', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $base = makeDonationMapping($user, $t[0], null, null);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 50);

    $winner = ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '5.00');

    expect($winner?->id)->toBe($base->id);
});

test('no match and no base row resolves to null', function () {
    [$user, $t] = makeUserWithAlertTemplates(1);
    makeDonationMapping($user, $t[0], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 50);

    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '5.00'))->toBeNull();
});

test('null or blank amount is treated as zero', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $base = makeDonationMapping($user, $t[0], null, null);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 1);

    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', null)?->id)->toBe($base->id);
    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '')?->id)->toBe($base->id);
});

test('equal at_least thresholds break the tie on lowest template id', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $first = makeDonationMapping($user, $t[0], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);
    makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_AT_LEAST, 5);

    $winner = ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '20.00');

    expect($winner?->id)->toBe($first->id);
});

test('disabled variants are ignored by the resolver', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    $base = makeDonationMapping($user, $t[0], null, null);
    $disabled = makeDonationMapping($user, $t[1], ExternalEventTemplateMapping::CONDITION_EXACTLY, 10);
    $disabled->update(['enabled' => false]);

    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'donation', '10.00')?->id)->toBe($base->id);
});

test('a non-amount external event type ignores conditions and returns its base', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    // Ko-fi 'subscription' is not in AMOUNT_EVENT_TYPES.
    $base = ExternalEventTemplateMapping::create([
        'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'subscription',
        'overlay_template_id' => $t[0]->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    expect(ExternalEventTemplateMapping::supportsCondition('kofi', 'subscription'))->toBeFalse();
    expect(ExternalEventTemplateMapping::resolveForEvent($user->id, 'kofi', 'subscription', '99.00')?->id)->toBe($base->id);
});

test('updateTriggers lets two templates own kofi donation with different conditions', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    [$small, $big] = $t;
    $this->actingAs($user);

    $this->put("/templates/{$small->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'donation', 'condition_type' => null, 'condition_value' => null, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertRedirect();

    $this->put("/templates/{$big->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'donation', 'condition_type' => 'at_least', 'condition_value' => 50, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('external_event_template_mappings', [
        'service' => 'kofi', 'event_type' => 'donation', 'overlay_template_id' => $small->id, 'condition_type' => null,
    ]);
    $this->assertDatabaseHas('external_event_template_mappings', [
        'service' => 'kofi', 'event_type' => 'donation', 'overlay_template_id' => $big->id, 'condition_type' => 'at_least', 'condition_value' => 50,
    ]);
});

test('updateTriggers strips a condition smuggled onto a non-amount external event', function () {
    [$user, $t] = makeUserWithAlertTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'subscription', 'condition_type' => 'at_least', 'condition_value' => 50, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('external_event_template_mappings', [
        'service' => 'kofi', 'event_type' => 'subscription', 'overlay_template_id' => $t[0]->id, 'condition_type' => null, 'condition_value' => null,
    ]);
});

test('updateTriggers reassigning a non-amount external event moves ownership', function () {
    [$user, $t] = makeUserWithAlertTemplates(2);
    [$a, $b] = $t;

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'subscription',
        'overlay_template_id' => $b->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    $this->actingAs($user);
    $this->put("/templates/{$a->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'subscription', 'condition_type' => null, 'condition_value' => null, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('external_event_template_mappings', [
        'service' => 'kofi', 'event_type' => 'subscription', 'overlay_template_id' => $a->id,
    ]);
    $this->assertDatabaseMissing('external_event_template_mappings', [
        'service' => 'kofi', 'event_type' => 'subscription', 'overlay_template_id' => $b->id,
    ]);
});

test('updateTriggers rejects an invalid external condition_type', function () {
    [$user, $t] = makeUserWithAlertTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'donation', 'condition_type' => 'more_than', 'condition_value' => 5, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertSessionHasErrors('external.0.condition_type');
});

test('updateTriggers requires a condition_value when an external condition_type is set', function () {
    [$user, $t] = makeUserWithAlertTemplates(1);
    $this->actingAs($user);

    $this->put("/templates/{$t[0]->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'donation', 'condition_type' => 'at_least', 'condition_value' => null, 'duration_ms' => 5000, 'enabled' => true],
        ],
    ])->assertSessionHasErrors('external.0.condition_value');
});
