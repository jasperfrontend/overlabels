<?php

use App\Events\AlertTriggered;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * An alert is a cocktail: overlay HTML, a sound, TTS, a chat message - any
 * mix. The overlay only needs to hear about it when there is something for
 * the overlay to do (HTML to show, a sound to play, TTS to schedule). A
 * chat-only alert has none of those, and used to broadcast anyway: an empty
 * box on screen for duration_ms, and - before the empty-allowlist fix - the
 * whole dataset in the payload. The chat message goes through the outbox
 * regardless; that is the bot's job, not the overlay's.
 */
function chatOnlyAlertUser(array $templateAttributes): array
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'token',
        'bot_enabled' => true,
    ]);

    $alert = OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '',
        'css' => '',
        'alert_sound_url' => null,
        'tts_message' => null,
        'chat_message' => 'Thanks [[[event.from_name]]]!',
    ], $templateAttributes));

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    $event = ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'msg-'.fake()->uuid(),
        'raw_payload' => ['event.from_name' => 'Frank'],
        'normalized_payload' => ['event.from_name' => 'Frank'],
    ]);

    return [$user, $alert, $event];
}

test('a chat-only alert reaches the bot and never the overlay', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake();

    [$user, , $event] = chatOnlyAlertUser([]);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertNotDispatched(AlertTriggered::class);
    Bus::assertNotDispatched(SynthesizeAlertTts::class);
    expect(BotChatOutbox::where('user_id', $user->id)->value('message'))->toBe('Thanks Frank!');
});

test('an alert with HTML still reaches the overlay', function () {
    Event::fake([AlertTriggered::class]);

    [$user, , $event] = chatOnlyAlertUser(['html' => '<div>[[[event.from_name]]]</div>']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class);
});

test('a sound-only alert still reaches the overlay', function () {
    Event::fake([AlertTriggered::class]);

    [$user, , $event] = chatOnlyAlertUser(['alert_sound_url' => 'https://example.test/ding.mp3']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class);
});

test('a TTS-only alert still reaches the overlay, which schedules the audio', function () {
    Event::fake([AlertTriggered::class]);
    Bus::fake();

    [$user, , $event] = chatOnlyAlertUser(['tts_message' => '[[[event.from_name]]] tipped']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->ttsText === 'Frank tipped');
    Bus::assertDispatched(SynthesizeAlertTts::class);
});

test('a chat-only alert on the Twitch webhook path is also bot-only', function () {
    Event::fake([AlertTriggered::class]);

    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
    });

    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'token',
        'bot_enabled' => true,
    ]);
    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '',
        'css' => '',
        'alert_sound_url' => null,
        'tts_message' => null,
        'chat_message' => 'Welcome [[[event.user_name]]]',
        // What a real save stores since #309: message tags are on the allowlist.
        'template_tags' => ['event.user_name'],
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    // Signing helper from TwitchEventRedeliveryTest: posts a channel.follow notification.
    postSignedNotification('msg-chat-only', [
        'broadcaster_user_id' => $user->twitch_id,
        'user_id' => '123',
        'user_name' => 'someone',
    ], 'msg-chat-only')->assertOk();

    Event::assertNotDispatched(AlertTriggered::class);
    expect(BotChatOutbox::where('user_id', $user->id)->value('message'))->toBe('Welcome someone');
});
