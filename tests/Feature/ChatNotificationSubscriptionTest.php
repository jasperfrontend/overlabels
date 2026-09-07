<?php

use App\Events\AlertTriggered;
use App\Events\TwitchEventReceived;
use App\Http\Controllers\TwitchEventSubController;
use App\Models\EventTemplateMapping;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchEventSubService;
use App\Services\TwitchScopeService;
use App\Services\UserEventSubManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * channel.chat.notification is the USERNOTICE feed: the only EventSub payload
 * that says whether a sub is Prime, and the only one reporting a gift or Prime
 * sub converting to paid. It is subscribed with the bot as the second party
 * in the condition (its user:bot pairs with the streamer's channel:bot), and
 * on arrival it is stored and nothing else - a `sub` notice lands next to the
 * channel.subscribe for the same viewer, so running the alert path would
 * double every sub alert and counter.
 */
function postChatNotification(User $user, array $event): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode([
        'subscription' => ['id' => 'sub-chat', 'type' => 'channel.chat.notification', 'version' => '1'],
        'event' => array_merge([
            'broadcaster_user_id' => $user->twitch_id,
            'broadcaster_user_login' => 'streamer',
            'broadcaster_user_name' => 'Streamer',
            'chatter_user_id' => '424242',
            'chatter_user_login' => 'viewer',
            'chatter_user_name' => 'Viewer',
            'chatter_is_anonymous' => false,
            'color' => '#FF0000',
            'badges' => [],
            'system_message' => 'Viewer subscribed at Tier 1.',
            'message_id' => 'm-1',
            'message' => ['text' => 'hello', 'fragments' => []],
            'notice_type' => 'sub',
            'sub' => ['sub_tier' => '1000', 'is_prime' => false, 'duration_months' => 1],
        ], $event),
    ]);
    $messageId = 'msg-'.uniqid();
    $timestamp = now()->toIso8601String();
    $signature = 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret');

    return test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => $signature,
    ], $body);
}

/**
 * Run the setup loop with Twitch faked, returning every payload it sent.
 *
 * @return array<int, array>
 */
function runSetupCapturingPayloads(User $user): array
{
    $sent = [];

    test()->mock(TwitchEventSubService::class, function ($mock) use (&$sent) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->andReturnUsing(function ($token, $payload) use (&$sent) {
            $sent[] = $payload;

            return ['data' => [[
                'id' => 'sub-'.$payload['type'],
                'status' => 'enabled',
                'created_at' => now()->toIso8601String(),
            ]]];
        });
        $mock->shouldReceive('getSubscriptions')->andReturn(['data' => []]);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    return $sent;
}

test('channel.chat.notification is a supported event gated on channel:bot', function () {
    $config = UserEventSubManager::SUPPORTED_EVENTS['channel.chat.notification'];

    expect($config['version'])->toBe('1')
        ->and($config['condition_keys'])->toBe(['broadcaster_user_id', 'user_id'])
        ->and($config['required_scope'])->toBe('channel:bot')
        ->and(TwitchScopeService::EVENT_TYPE_TO_SCOPE['channel.chat.notification'])->toBe('channel:bot');
});

test('the subscription names the streamer and the bot in its condition', function () {
    config(['services.twitchbot.user_id' => '1130071166']);

    $user = User::factory()->create([
        'twitch_id' => '12345',
        'access_token' => 'user-token',
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
    ]);

    $sent = runSetupCapturingPayloads($user);
    $payload = collect($sent)->firstWhere('type', 'channel.chat.notification');

    expect($payload)->not->toBeNull()
        ->and($payload['version'])->toBe('1')
        ->and($payload['condition'])->toBe([
            'broadcaster_user_id' => '12345',
            'user_id' => '1130071166',
        ]);
});

test('an account without channel:bot skips the subscription instead of sending it', function () {
    $user = User::factory()->create([
        'twitch_id' => '12345',
        'access_token' => 'user-token',
        'twitch_scopes' => array_values(array_diff(TwitchScopeService::REQUIRED_SCOPES, ['channel:bot'])),
    ]);

    $sent = runSetupCapturingPayloads($user);

    expect(collect($sent)->firstWhere('type', 'channel.chat.notification'))->toBeNull();
});

test('an install without a bot user id fails that one subscription with a message, not a Twitch call', function () {
    config(['services.twitchbot.user_id' => null]);

    $user = User::factory()->create([
        'twitch_id' => '12345',
        'access_token' => 'user-token',
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
    ]);

    $sent = [];
    $this->mock(TwitchEventSubService::class, function ($mock) use (&$sent) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->andReturnUsing(function ($token, $payload) use (&$sent) {
            $sent[] = $payload['type'];

            return ['data' => [['id' => 'sub-'.$payload['type'], 'status' => 'enabled', 'created_at' => now()->toIso8601String()]]];
        });
        $mock->shouldReceive('getSubscriptions')->andReturn(['data' => []]);
    });

    $results = app(UserEventSubManager::class)->setupUserSubscriptions($user);

    expect($results['failed'])->toHaveKey('channel.chat.notification')
        ->and($results['failed']['channel.chat.notification'])->toContain('TWITCHBOT_USER_ID')
        ->and($sent)->not->toContain('channel.chat.notification')
        ->and(count($sent))->toBe(count(UserEventSubManager::SUPPORTED_EVENTS) - 1);
});

test('a chat notice is stored against the broadcaster and nothing else happens', function () {
    Http::fake();
    Event::fake([AlertTriggered::class, TwitchEventReceived::class]);
    Log::spy();

    $user = User::factory()->create(['access_token' => 'token']);

    postChatNotification($user, [])->assertOk();

    $row = TwitchEvent::where('event_type', 'channel.chat.notification')->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($user->id)
        ->and($row->event_data['notice_type'])->toBe('sub')
        ->and($row->event_data['sub']['is_prime'])->toBeFalse()
        ->and($row->alert_id)->toBeNull()
        ->and($row->outcome)->toBeNull();

    Event::assertNotDispatched(AlertTriggered::class);
    Event::assertNotDispatched(TwitchEventReceived::class);

    Log::shouldNotHaveReceived('warning', fn (string $message) => str_contains($message, 'No enabled template mapping'));
});

test('the store-only list is exactly the chat notice', function () {
    // Widening this list silently removes alerts and counters for whatever is
    // added. Anything else that should be store-only earns its own entry and
    // its own reason in the constant's docblock.
    expect(TwitchEventSubController::STORE_ONLY_EVENTS)->toBe(['channel.chat.notification']);
});

test('the chat notice is not offered as an alert trigger', function () {
    // A trigger on a store-only event could be wired up, saved and listed as
    // enabled, and would never fire.
    expect(EventTemplateMapping::EVENT_TYPES)->not->toHaveKey('channel.chat.notification');
});
