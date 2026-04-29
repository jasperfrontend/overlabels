<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;

uses(DatabaseTransactions::class);

function makeAlertTemplate(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $alert];
}

test('updateTriggers creates twitch event mappings bound to this template', function () {
    [$user, $alert] = makeAlertTemplate();
    $this->actingAs($user);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 5000, 'enabled' => true],
            ['event_type' => 'channel.cheer', 'duration_ms' => 10000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'enabled' => true,
    ]);
    $this->assertDatabaseHas('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'template_id' => $alert->id,
        'duration_ms' => 10000,
    ]);
});

test('updateTriggers deletes rows that this template previously owned but were not resubmitted', function () {
    [$user, $alert] = makeAlertTemplate();

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'template_id' => $alert->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $this->actingAs($user);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 7000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'duration_ms' => 7000,
    ]);
    $this->assertDatabaseMissing('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'template_id' => $alert->id,
    ]);
});

test('updateTriggers does not touch rows owned by other templates', function () {
    [$user, $alertA] = makeAlertTemplate();
    $alertB = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-b-'.fake()->unique()->lexify('????????'),
    ]);

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'template_id' => $alertB->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $this->actingAs($user);

    $this->put("/templates/{$alertA->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'event_type' => 'channel.cheer',
        'template_id' => $alertB->id,
    ]);
});

test('updateTriggers reassigning a twitch event from another template overrides ownership', function () {
    [$user, $alertA] = makeAlertTemplate();
    $alertB = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-b-'.fake()->unique()->lexify('????????'),
    ]);

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alertB->id,
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $this->actingAs($user);

    $this->put("/templates/{$alertA->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 5000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alertA->id,
    ]);
    $this->assertDatabaseMissing('event_template_mappings', [
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alertB->id,
    ]);
});

test('updateTriggers writes external service mappings', function () {
    [$user, $alert] = makeAlertTemplate();
    ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'tok'])),
    ]);

    $this->actingAs($user);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [],
        'external' => [
            ['service' => 'kofi', 'event_type' => 'donation', 'duration_ms' => 6000, 'enabled' => true],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('external_event_template_mappings', [
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'duration_ms' => 6000,
    ]);
});

test('updateTriggers rejects non-owners with 403', function () {
    [$user, $alert] = makeAlertTemplate();
    $other = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $this->actingAs($other);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [],
        'external' => [],
    ])->assertStatus(403);
});

test('updateTriggers rejects non-alert templates with 422', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $static = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
    ]);
    $this->actingAs($user);

    $this->put("/templates/{$static->id}/triggers", [
        'twitch' => [],
        'external' => [],
    ])->assertStatus(422);
});

test('updateTriggers accepts duration_ms up to 999000 and rejects above', function () {
    [$user, $alert] = makeAlertTemplate();
    $this->actingAs($user);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 999000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertRedirect();

    $this->assertDatabaseHas('event_template_mappings', [
        'event_type' => 'channel.follow',
        'duration_ms' => 999000,
    ]);

    $this->put("/templates/{$alert->id}/triggers", [
        'twitch' => [
            ['event_type' => 'channel.follow', 'duration_ms' => 1000000, 'enabled' => true],
        ],
        'external' => [],
    ])->assertSessionHasErrors();
});
