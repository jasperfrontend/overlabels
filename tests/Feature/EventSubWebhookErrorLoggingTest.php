<?php

use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * handleTwitchEvent() had `catch (Exception)` that logged, followed by an
 * empty `catch (Throwable) {}`. A PHP Error (TypeError, a ShouldBroadcastNow
 * event throwing inside a handler, ...) is a Throwable but not an Exception,
 * so it landed in the empty branch: no log, no row, no broadcast, and Twitch
 * got a 200 so it never retried. This pins that an Error is at least logged.
 */
function postTwitchNotification(array $subscription, array $event): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode(['subscription' => $subscription, 'event' => $event]);
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

test('a PHP Error thrown while handling a notification is logged, not swallowed', function () {
    $user = User::factory()->create(['access_token' => 'token']);

    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')
            ->once()
            ->andThrow(new TypeError('enrichment blew up'));
    });

    Log::spy();

    postTwitchNotification(
        ['id' => 'sub-1', 'type' => 'channel.follow', 'version' => '2'],
        ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '123', 'user_name' => 'someone'],
    )->assertOk();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'enrichment blew up')
            && ($context['event_type'] ?? null) === 'channel.follow')
        ->once();

    expect(TwitchEvent::count())->toBe(0);
});
