<?php

use App\Events\AlertTriggered;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function makeUserWithAlertTemplate(?string $ttsExpression = null): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'tts_expression' => $ttsExpression,
    ]);

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    return [$user, $alert];
}

function makeKofiDonationEvent(User $user, array $tags): ExternalEvent
{
    return ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'msg-'.fake()->uuid(),
        'raw_payload' => $tags,
        'normalized_payload' => $tags,
    ]);
}

test('AlertTriggered carries rendered tts_text when tts_expression is set', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate(
        '[[[event.from_name]]] just tipped [[[event.amount|currency:USD]]]!'
    );

    $event = makeKofiDonationEvent($user, [
        'event.from_name' => 'Frank',
        'event.amount' => '5.00',
    ]);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return is_string($e->ttsText) && str_contains($e->ttsText, 'Frank') && str_contains($e->ttsText, '5.00');
    });
});

test('AlertTriggered ships null tts_text when tts_expression is null', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate(null);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Gina']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsText === null;
    });
});

test('AlertTriggered ships null tts_text when expression renders to empty string', function () {
    Event::fake([AlertTriggered::class]);

    // Only references a tag that's not in the payload - resolves to empty
    [$user] = makeUserWithAlertTemplate('[[[event.nonexistent_tag]]]');

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Henry']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsText === null;
    });
});

test('boolean tts control set to off suppresses tts_text', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    // User-scoped boolean control with the reserved gate key
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'tts',
        'label' => 'TTS',
        'type' => 'boolean',
        'value' => '0',
        'sort_order' => 0,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Iris']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsText === null;
    });
});

test('template-attached tts control set to off also suppresses tts_text', function () {
    Event::fake([AlertTriggered::class]);

    [$user, $alert] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    // Template-scoped boolean control: lives on the user's alert template
    // (overlay_template_id set, not null). The renderer should still pick it
    // up because the streamer's mental model is "one switch", not "one switch
    // per overlay".
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => $alert->id,
        'key' => 'tts',
        'label' => 'TTS',
        'type' => 'boolean',
        'value' => '0',
        'sort_order' => 0,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Kai']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsText === null;
    });
});

test('boolean tts control set to on permits tts_text', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'tts',
        'label' => 'TTS',
        'type' => 'boolean',
        'value' => '1',
        'sort_order' => 0,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Jules']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsText === 'Hello Jules';
    });
});

test('AlertTriggered broadcastWith payload exposes tts_text', function () {
    $event = new AlertTriggered(
        html: '',
        css: '',
        data: [],
        duration: 5000,
        broadcasterId: '12345',
        targetOverlaySlugs: null,
        alertTemplateSlug: 'some-slug',
        ttsText: 'Hello world',
    );

    $payload = $event->broadcastWith();

    expect($payload['alert']['tts_text'])->toBe('Hello world');
});

test('AlertTriggered ships tts_delay_ms from the alert template', function () {
    Event::fake([AlertTriggered::class]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'tts_expression' => 'Hello [[[event.from_name]]]',
        'tts_delay_ms' => 2500,
    ]);

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Lex']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsDelayMs === 2500;
    });
});

test('AlertTriggered defaults tts_delay_ms to 0 when not set', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate('Hi [[[event.from_name]]]');

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Mira']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->ttsDelayMs === 0;
    });
});

test('AlertTriggered broadcastWith payload exposes tts_delay_ms', function () {
    $event = new AlertTriggered(
        html: '',
        css: '',
        data: [],
        duration: 5000,
        broadcasterId: '12345',
        targetOverlaySlugs: null,
        alertTemplateSlug: 'some-slug',
        ttsText: 'Hi',
        ttsDelayMs: 1500,
    );

    $payload = $event->broadcastWith();

    expect($payload['alert']['tts_delay_ms'])->toBe(1500);
});

test('AlertTriggered clamps negative tts_delay_ms to zero', function () {
    $event = new AlertTriggered(
        html: '',
        css: '',
        data: [],
        duration: 5000,
        broadcasterId: '12345',
        targetOverlaySlugs: null,
        alertTemplateSlug: 'some-slug',
        ttsText: 'Hi',
        ttsDelayMs: -500,
    );

    expect($event->ttsDelayMs)->toBe(0);
});
