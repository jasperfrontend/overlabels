<?php

use App\Events\AlertTriggered;
use App\Events\TtsAudioReady;
use App\Jobs\SynthesizeAlertTts;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Expressions\AlertExpressionRenderer;
use App\Services\HtmlSanitizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

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

test('AlertTriggered broadcastWith payload exposes alert_id and omits tts_text', function () {
    $event = new AlertTriggered(
        alertId: 'alert-abc-123',
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

    // alert_id ships so the overlay can correlate the matching TtsAudioReady.
    expect($payload['alert']['alert_id'])->toBe('alert-abc-123');
    // tts_text is server-only now: synthesis happens in SynthesizeAlertTts
    // and the overlay receives audio_url via TtsAudioReady, not raw text.
    expect($payload['alert'])->not->toHaveKey('tts_text');
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
        alertId: (string) Str::uuid(),
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

test('AlertTriggered ships alert_sound_url from the alert template', function () {
    Event::fake([AlertTriggered::class]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'alert_sound_url' => 'https://example.com/sounds/coin.mp3',
    ]);

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Noa']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->alertSoundUrl === 'https://example.com/sounds/coin.mp3';
    });
});

test('AlertTriggered ships null alert_sound_url when not set', function () {
    Event::fake([AlertTriggered::class]);

    [$user] = makeUserWithAlertTemplate();

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Owen']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $e) {
        return $e->alertSoundUrl === null;
    });
});

test('AlertTriggered broadcastWith payload exposes alert_sound_url', function () {
    $event = new AlertTriggered(
        alertId: (string) Str::uuid(),
        html: '',
        css: '',
        data: [],
        duration: 5000,
        broadcasterId: '12345',
        targetOverlaySlugs: null,
        alertTemplateSlug: 'some-slug',
        ttsText: null,
        ttsDelayMs: 0,
        alertSoundUrl: 'https://example.com/sounds/ding.mp3',
    );

    $payload = $event->broadcastWith();

    expect($payload['alert']['alert_sound_url'])->toBe('https://example.com/sounds/ding.mp3');
});

test('HtmlSanitizationService strips audio video and source tags from template HTML', function () {
    $dirty = '<div>Hi <audio src="https://example.com/sound.mp3" autoplay></audio></div>'
        .'<video src="https://example.com/clip.mp4" autoplay></video>'
        .'<source src="https://example.com/alt.mp3" type="audio/mpeg" />';

    $clean = HtmlSanitizationService::sanitize($dirty);

    expect($clean)->not->toContain('<audio')
        ->and($clean)->not->toContain('<video')
        ->and($clean)->not->toContain('<source')
        ->and($clean)->toContain('<div>Hi </div>');
});

test('AlertTriggered clamps negative tts_delay_ms to zero', function () {
    $event = new AlertTriggered(
        alertId: (string) Str::uuid(),
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

test('SynthesizeAlertTts is dispatched with the alert_id when tts_text is non-null', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);

    [$user] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Pat']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Bus::assertDispatched(SynthesizeAlertTts::class, function (SynthesizeAlertTts $job) use ($user) {
        return $job->broadcasterId === (string) $user->twitch_id
            && $job->text === 'Hello Pat'
            && $job->alertId !== '';
    });
});

test('SynthesizeAlertTts is NOT dispatched when tts_text resolves to null', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);

    [$user] = makeUserWithAlertTemplate(null);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Quinn']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Bus::assertNotDispatched(SynthesizeAlertTts::class);
});

test('SynthesizeAlertTts is NOT dispatched when the tts gate control is off', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);

    [$user] = makeUserWithAlertTemplate('Hi [[[event.from_name]]]');

    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'tts',
        'label' => 'TTS',
        'type' => 'boolean',
        'value' => '0',
        'sort_order' => 0,
    ]);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Riley']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Bus::assertNotDispatched(SynthesizeAlertTts::class);
});

test('SynthesizeAlertTts is dispatched with the alert target slugs when set', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);

    [$user, $alert] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    // Target a single static overlay so the synthesis job must carry its slug.
    $static = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
    ]);
    $alert->targetStaticOverlays()->attach($static->id);

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Sam']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Bus::assertDispatched(SynthesizeAlertTts::class, function (SynthesizeAlertTts $job) use ($static) {
        return $job->targetSlugs === [$static->slug];
    });
});

test('SynthesizeAlertTts is dispatched with null target slugs when none set', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);

    [$user] = makeUserWithAlertTemplate('Hello [[[event.from_name]]]');

    $event = makeKofiDonationEvent($user, ['event.from_name' => 'Tess']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Bus::assertDispatched(SynthesizeAlertTts::class, function (SynthesizeAlertTts $job) {
        return $job->targetSlugs === null;
    });
});

test('TtsAudioReady broadcastWith payload exposes target_overlay_slugs', function () {
    $event = new TtsAudioReady(
        alertId: 'alert-abc-123',
        broadcasterId: '12345',
        audioUrl: 'https://example.com/tts/abc.mp3',
        targetSlugs: ['my-static-overlay'],
    );

    $payload = $event->broadcastWith();

    expect($payload['alert_id'])->toBe('alert-abc-123')
        ->and($payload['audio_url'])->toBe('https://example.com/tts/abc.mp3')
        ->and($payload['target_overlay_slugs'])->toBe(['my-static-overlay']);
});

test('TtsAudioReady ships null target_overlay_slugs when the alert targets all overlays', function () {
    $event = new TtsAudioReady(
        alertId: 'alert-xyz-789',
        broadcasterId: '12345',
        audioUrl: 'https://example.com/tts/xyz.mp3',
    );

    $payload = $event->broadcastWith();

    expect($payload['target_overlay_slugs'])->toBeNull();
});

test('alert renderer fills an absent value with its default, and a present value ignores it', function () {
    $user = User::factory()->create();
    $renderer = app(AlertExpressionRenderer::class);
    $expr = 'thanks [[[event.user_name ?? a kind stranger]]]';

    // Absent -> default renders; present -> default ignored (absence backstop).
    expect($renderer->renderMessage($user, $expr, []))
        ->toBe('thanks a kind stranger')
        ->and($renderer->renderMessage($user, $expr, ['event.user_name' => 'Alice']))
        ->toBe('thanks Alice');
});
