<?php

use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\GiftSubAbsorber;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * A gift bomb is one alert. Twitch sends the gifter's `channel.subscription.gift`
 * with the final `total` first, then one gifted `channel.subscribe` per
 * recipient over the following seconds. The overlay queues alerts now, so a
 * sub alert per recipient would play for minutes; the gift alert announces the
 * batch and absorbs that many recipients. See GiftSubAbsorber.
 */
function postGiftAbsorbNotification(string $type, array $event): void
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);
    $messageId = 'msg-'.fake()->uuid();
    $body = json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '1'], 'event' => $event]);
    $timestamp = now()->toIso8601String();

    test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret'),
    ], $body)->assertOk();
}

function giftAbsorbSetup(array $types): User
{
    $user = User::factory()->create(['access_token' => 'token']);
    foreach ($types as $type) {
        $alert = OverlayTemplate::factory()->create([
            'owner_id' => $user->id,
            'type' => 'alert',
            'html' => '<div>[[[event.user_name]]]</div>',
            'template_tags' => ['event.user_name'],
        ]);
        EventTemplateMapping::create([
            'user_id' => $user->id, 'event_type' => $type, 'template_id' => $alert->id, 'duration_ms' => 6000, 'enabled' => true,
        ]);
    }

    return $user;
}

function giftAbsorbGift(User $user, int $total): void
{
    postGiftAbsorbNotification('channel.subscription.gift', [
        'broadcaster_user_id' => $user->twitch_id, 'user_id' => '900', 'user_name' => 'Gifter', 'total' => $total, 'tier' => '1000', 'is_anonymous' => false,
    ]);
}

function giftAbsorbSub(User $user, bool $gifted, string $who = 'Recipient'): void
{
    postGiftAbsorbNotification('channel.subscribe', [
        'broadcaster_user_id' => $user->twitch_id, 'user_id' => (string) fake()->randomNumber(6), 'user_name' => $who, 'tier' => '1000', 'is_gift' => $gifted,
    ]);
}

/** @return list<string> the event type of every alert broadcast, in order */
function giftAbsorbAlerts(): array
{
    return Event::dispatched(AlertTriggered::class)->map(fn ($e) => $e[0]->broadcastWith()['alert']['event_type'])->values()->all();
}

beforeEach(function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldIgnoreMissing();
    });
});

test('a gift bomb of three fires one gift alert and absorbs its three recipients', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscription.gift', 'channel.subscribe']);

    giftAbsorbGift($user, 3);
    giftAbsorbSub($user, gifted: true);
    giftAbsorbSub($user, gifted: true);
    giftAbsorbSub($user, gifted: true);

    $recipients = TwitchEvent::where('event_type', 'channel.subscribe')->get();

    expect(giftAbsorbAlerts())->toBe(['channel.subscription.gift'])
        ->and($recipients)->toHaveCount(3)
        ->and($recipients->map->outcome->all())->each->toBe(DeliveryOutcome::GiftAbsorbed);
});

test('the countdown stops at the gift total, so a later gifted sub alerts again', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscription.gift', 'channel.subscribe']);

    giftAbsorbGift($user, 2);
    giftAbsorbSub($user, gifted: true);
    giftAbsorbSub($user, gifted: true);
    giftAbsorbSub($user, gifted: true);

    expect(giftAbsorbAlerts())->toBe(['channel.subscription.gift', 'channel.subscribe']);
});

test('a sub someone paid for themselves is never absorbed', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscription.gift', 'channel.subscribe']);

    giftAbsorbGift($user, 5);
    giftAbsorbSub($user, gifted: false, who: 'PaidForIt');

    expect(giftAbsorbAlerts())->toBe(['channel.subscription.gift', 'channel.subscribe']);
});

test('with no gift alert assigned, gifted subs alert as they always did', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscribe']);

    giftAbsorbGift($user, 2);
    giftAbsorbSub($user, gifted: true);
    giftAbsorbSub($user, gifted: true);

    expect(giftAbsorbAlerts())->toBe(['channel.subscribe', 'channel.subscribe']);
});

test('the countdown expires, so a gifted sub long after the bomb alerts', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscription.gift', 'channel.subscribe']);

    giftAbsorbGift($user, 5);
    $this->travel(GiftSubAbsorber::WINDOW_SECONDS + 1)->seconds();
    giftAbsorbSub($user, gifted: true);

    expect(giftAbsorbAlerts())->toBe(['channel.subscription.gift', 'channel.subscribe']);
});

test('two gift bombs at once absorb both batches', function () {
    Event::fake([AlertTriggered::class]);
    $user = giftAbsorbSetup(['channel.subscription.gift', 'channel.subscribe']);

    giftAbsorbGift($user, 2);
    giftAbsorbGift($user, 1);
    foreach (range(1, 3) as $i) {
        giftAbsorbSub($user, gifted: true);
    }

    expect(giftAbsorbAlerts())->toBe(['channel.subscription.gift', 'channel.subscription.gift']);
});
