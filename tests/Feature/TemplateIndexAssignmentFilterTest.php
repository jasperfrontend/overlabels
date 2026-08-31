<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * /templates?type=alert&assignment=assigned|unassigned filters alerts on
 * whether THE VIEWER has an event mapping (Twitch or external) pointing at
 * them. Mappings are per-user rows, so another user's assignment must never
 * count, and the param is ignored entirely when the type filter is not
 * "alert" - a static overlay is neither assigned nor unassigned.
 */
function assignmentUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function assignmentAlert(User $owner): OverlayTemplate
{
    return OverlayTemplate::factory()->alert()->create([
        'owner_id' => $owner->id,
        'is_public' => false,
        'slug' => 'asgn-'.fake()->unique()->lexify('????????'),
    ]);
}

function templateIdsFrom($response): array
{
    $props = $response->viewData('page')['props'];

    return collect($props['templates']['data'])->pluck('id')->all();
}

test('assigned shows only alerts the viewer has mapped, twitch or external', function () {
    $user = assignmentUser();
    $twitchMapped = assignmentAlert($user);
    $externalMapped = assignmentAlert($user);
    $unmapped = assignmentAlert($user);

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $twitchMapped->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $externalMapped->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $ids = templateIdsFrom(
        $this->actingAs($user)->get('/templates?filter=mine&type=alert&assignment=assigned')
    );

    expect($ids)->toContain($twitchMapped->id)
        ->toContain($externalMapped->id)
        ->not->toContain($unmapped->id);
});

test('unassigned shows only alerts with no mapping for the viewer', function () {
    $user = assignmentUser();
    $mapped = assignmentAlert($user);
    $unmapped = assignmentAlert($user);

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.raid',
        'template_id' => $mapped->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $ids = templateIdsFrom(
        $this->actingAs($user)->get('/templates?filter=mine&type=alert&assignment=unassigned')
    );

    expect($ids)->toContain($unmapped->id)
        ->not->toContain($mapped->id);
});

test('another user\'s mapping does not make an alert assigned for the viewer', function () {
    $user = assignmentUser();
    $other = assignmentUser();
    $alert = assignmentAlert($user);

    EventTemplateMapping::create([
        'user_id' => $other->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $assigned = templateIdsFrom(
        $this->actingAs($user)->get('/templates?filter=mine&type=alert&assignment=assigned')
    );
    $unassigned = templateIdsFrom(
        $this->actingAs($user)->get('/templates?filter=mine&type=alert&assignment=unassigned')
    );

    expect($assigned)->not->toContain($alert->id)
        ->and($unassigned)->toContain($alert->id);
});

test('assignment is ignored when the type filter is not alert', function () {
    $user = assignmentUser();
    $static = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'is_public' => false,
        'type' => 'static',
        'slug' => 'asgn-'.fake()->unique()->lexify('????????'),
    ]);

    $ids = templateIdsFrom(
        $this->actingAs($user)->get('/templates?filter=mine&assignment=assigned')
    );

    expect($ids)->toContain($static->id);
});
