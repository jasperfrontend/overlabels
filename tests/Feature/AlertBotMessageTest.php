<?php

use App\Models\BotChatOutbox;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeUserWithBotAlert(?string $botCommand = null, bool $botEnabled = true): array
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'bot_enabled' => $botEnabled,
    ]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'chat_message' => $botCommand,
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

function makeBotKofiEvent(User $user, array $tags): ExternalEvent
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

test('alert queues a bot chat message with resolved tags when bot is enabled', function () {
    [$user] = makeUserWithBotAlert('[[[event.from_name]]] just tipped [[[event.amount|currency:USD]]]!');

    $event = makeBotKofiEvent($user, [
        'event.from_name' => 'Frank',
        'event.amount' => '5.00',
    ]);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    $row = BotChatOutbox::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->message)->toContain('Frank')
        ->and($row->message)->toContain('5.00');
});

test('alert does NOT queue a bot chat message when bot is disabled', function () {
    [$user] = makeUserWithBotAlert('Hello [[[event.from_name]]]', botEnabled: false);

    $event = makeBotKofiEvent($user, ['event.from_name' => 'Gina']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    expect(BotChatOutbox::where('user_id', $user->id)->exists())->toBeFalse();
});

test('alert does NOT queue a bot chat message when chat_message is null', function () {
    [$user] = makeUserWithBotAlert(null);

    $event = makeBotKofiEvent($user, ['event.from_name' => 'Henry']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    expect(BotChatOutbox::where('user_id', $user->id)->exists())->toBeFalse();
});

test('alert does NOT queue a bot chat message when chat_message renders to empty', function () {
    [$user] = makeUserWithBotAlert('[[[event.nonexistent_tag]]]');

    $event = makeBotKofiEvent($user, ['event.from_name' => 'Iris']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    expect(BotChatOutbox::where('user_id', $user->id)->exists())->toBeFalse();
});

test('the tts mute control does NOT suppress the bot chat message', function () {
    [$user] = makeUserWithBotAlert('Hello [[[event.from_name]]]');

    // A tts gate that's off mutes TTS, but bot chat messages are gated only by
    // bot_enabled - they should still post.
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'tts',
        'label' => 'TTS',
        'type' => 'boolean',
        'value' => '0',
        'sort_order' => 0,
    ]);

    $event = makeBotKofiEvent($user, ['event.from_name' => 'Jules']);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    $row = BotChatOutbox::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->message)->toBe('Hello Jules');
});

test('a chat message longer than Twitch allows is queued as several messages, in order', function () {
    // The source is short; the tag value is what makes the output long.
    [$user] = makeUserWithBotAlert('Thanks [[[event.from_name]]]! Note: [[[event.message]]] END');

    $note = trim(str_repeat('lorem ipsum dolor ', 40)); // ~720 chars
    $event = makeBotKofiEvent($user, ['event.from_name' => 'Kim', 'event.message' => $note]);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    $messages = BotChatOutbox::where('user_id', $user->id)->orderBy('id')->pluck('message')->all();

    expect($messages)->toHaveCount(2)
        ->and(mb_strlen($messages[0]))->toBeLessThanOrEqual(500)
        ->and($messages[0])->toStartWith('Thanks Kim! Note: lorem')
        ->and($messages[1])->toEndWith('END')
        // Split on a word boundary: rejoining with one space restores the text.
        ->and(implode(' ', $messages))->toBe("Thanks Kim! Note: {$note} END");
});

test('a chat message source longer than 500 characters can be saved', function () {
    [$user, $alert] = makeUserWithBotAlert('short');
    $long = trim(str_repeat('[[[event.from_name]]] ', 50)); // 1099 chars

    $this->actingAs($user)
        ->put(route('templates.update', $alert), ['chat_message' => $long])
        ->assertSessionHasNoErrors();

    expect($alert->fresh()->chat_message)->toBe($long);
});
