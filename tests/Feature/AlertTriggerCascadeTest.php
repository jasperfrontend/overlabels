<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Deleting an alert takes its triggers with it. They used to stay behind with
 * no alert (nullOnDelete): invisible on the Triggers page, skipped by alert
 * resolution, and never usable again.
 */

test('deleting an alert deletes its Twitch and external triggers, and only those', function () {
    $user = User::factory()->create();
    $gone = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'alert']);
    $kept = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'alert']);

    EventTemplateMapping::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $gone->id, 'enabled' => true, 'duration_ms' => 5000]);
    ExternalEventTemplateMapping::create(['user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'donation', 'overlay_template_id' => $gone->id, 'enabled' => true, 'duration_ms' => 5000]);
    EventTemplateMapping::create(['user_id' => $user->id, 'event_type' => 'channel.raid', 'template_id' => $kept->id, 'enabled' => true, 'duration_ms' => 5000]);
    ExternalEventTemplateMapping::create(['user_id' => $user->id, 'service' => 'throne', 'event_type' => 'donation', 'overlay_template_id' => $kept->id, 'enabled' => true, 'duration_ms' => 5000]);

    $this->actingAs($user)->delete("/templates/{$gone->id}");

    expect(OverlayTemplate::find($gone->id))->toBeNull()
        ->and(EventTemplateMapping::where('user_id', $user->id)->pluck('event_type')->all())->toBe(['channel.raid'])
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->pluck('service')->all())->toBe(['throne'])
        ->and(EventTemplateMapping::whereNull('template_id')->count())->toBe(0)
        ->and(ExternalEventTemplateMapping::whereNull('overlay_template_id')->count())->toBe(0);
});
