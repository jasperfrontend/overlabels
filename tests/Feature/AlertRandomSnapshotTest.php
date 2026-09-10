<?php

use App\Events\AlertTriggered;
use App\Jobs\SynthesizeAlertTts;
use App\Models\BotChatOutbox;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

/**
 * One alert is one query. A random-mode control rolls fresh on every
 * resolveDisplayValue(), so the TTS line, the bot's chat line and the
 * overlay's own render of the alert must all be served from ONE roll.
 * The overlay ticks its random controls client-side, so the only way it
 * can agree with the server is for the broadcast payload to carry the
 * roll under the control's own `c:` key, which the alert merge prefers
 * over the live data.
 *
 * Three broadcast sites, same assertion each.
 */
uses(DatabaseTransactions::class);

const RANDOM_SNAPSHOT_TEMPLATE = '[[[event.user_name]]] Random number: [[[c:random1000]]]';

beforeEach(function () {
    $stub = new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $twitchId): array
        {
            return [];
        }
    };
    app()->instance(TwitchApiService::class, $stub);

    Event::fake([AlertTriggered::class]);
    Bus::fake([SynthesizeAlertTts::class]);
});

function randomSnapshotUser(): User
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'token',
        'bot_enabled' => true,
    ]);

    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'random1000',
        'type' => 'number',
        'value' => '0',
        'config' => ['min' => 0, 'max' => 1000000, 'random' => true],
        'source_managed' => false,
    ]);

    return $user;
}

function randomSnapshotAlert(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '<div>'.RANDOM_SNAPSHOT_TEMPLATE.'</div>',
        'tts_message' => RANDOM_SNAPSHOT_TEMPLATE,
        'chat_message' => RANDOM_SNAPSHOT_TEMPLATE,
    ]);
}

/**
 * Pull the three numbers the alert produced. Returns [tts, chat, overlay].
 *
 * @return array{0: string, 1: string, 2: string|null}
 */
function randomSnapshotNumbers(User $user): array
{
    $tts = null;
    Bus::assertDispatched(SynthesizeAlertTts::class, function (SynthesizeAlertTts $job) use (&$tts) {
        preg_match('/Random number: (\d+)/', $job->text, $m);
        $tts = $m[1] ?? 'missing';

        return true;
    });

    $chat = BotChatOutbox::where('user_id', $user->id)->latest('id')->first();
    preg_match('/Random number: (\d+)/', (string) $chat?->message, $m);
    $chatNumber = $m[1] ?? 'missing';

    $overlay = null;
    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) use (&$overlay) {
        $overlay = $event->data['c:random1000'] ?? null;

        return true;
    });

    return [$tts, $chatNumber, $overlay];
}

test('an external webhook alert speaks, chats and broadcasts one roll', function () {
    $user = randomSnapshotUser();
    $alert = randomSnapshotAlert($user);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'snap-tok'])),
    ]);

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    $payload = [
        'verification_token' => 'snap-tok',
        'kofi_transaction_id' => 'txn-'.fake()->uuid(),
        'from_name' => 'Bob',
        'message' => 'Hi!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ];

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode($payload)]);

    [$tts, $chat, $overlay] = randomSnapshotNumbers($user);

    expect($chat)->toBe($tts, "tts={$tts} chat={$chat}")
        ->and($overlay)->toBe($tts, "tts={$tts} overlay=".var_export($overlay, true));
});

test('an external event replay speaks, chats and broadcasts one roll', function () {
    $user = randomSnapshotUser();
    $alert = randomSnapshotAlert($user);

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
        'raw_payload' => ['event.user_name' => 'Bob'],
        'normalized_payload' => ['event.user_name' => 'Bob'],
    ]);

    $this->actingAs($user)->post("/external-events/{$event->id}/replay");

    [$tts, $chat, $overlay] = randomSnapshotNumbers($user);

    expect($chat)->toBe($tts, "tts={$tts} chat={$chat}")
        ->and($overlay)->toBe($tts, "tts={$tts} overlay=".var_export($overlay, true));
});

test('a twitch event replay speaks, chats and broadcasts one roll', function () {
    $user = randomSnapshotUser();
    $alert = randomSnapshotAlert($user);

    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    $twitchEvent = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => 'Bob', 'user_login' => 'bob', 'user_id' => '1'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    $this->actingAs($user)->post("/events/{$twitchEvent->id}/replay");

    [$tts, $chat, $overlay] = randomSnapshotNumbers($user);

    expect($chat)->toBe($tts, "tts={$tts} chat={$chat}")
        ->and($overlay)->toBe($tts, "tts={$tts} overlay=".var_export($overlay, true));
});
