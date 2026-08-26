<?php

use App\Broadcasting\MeteredBroadcaster;
use App\Services\BroadcastMeter;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Pusher\ApiErrorException;
use Pusher\Pusher;

/**
 * Reverb answers a trigger that asks for `info=subscription_count` with the
 * number of connections on each channel at the moment it accepted the event.
 * The metered broadcaster is the one chokepoint every broadcast passes, so
 * it is where that answer is read back and kept as the account's last
 * delivery. Proof of delivery to N sockets, never proof of paint.
 */
function reverbTriggerResponse(array $counts): object
{
    $channels = [];
    foreach ($counts as $name => $count) {
        $channels[$name] = (object) ['subscription_count' => $count];
    }

    return (object) ['channels' => (object) $channels];
}

beforeEach(function () {
    // Keep the usage meter off so nothing touches Redis; delivery goes through Cache.
    config()->set('metering.enabled', false);
    Cache::flush();
});

test('asks reverb for the subscription count and records the delivery', function () {
    Carbon::setTestNow('2026-08-26 21:00:00');

    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')
        ->once()
        ->withArgs(fn ($channels, $event, $data, $params) => $channels === ['private-alerts.123']
            && $event === 'control.updated'
            && $params === ['info' => 'subscription_count'])
        ->andReturn(reverbTriggerResponse(['private-alerts.123' => 2]));

    $meter = new BroadcastMeter;
    $decorated = new MeteredBroadcaster(new PusherBroadcaster($pusher), $meter);

    $decorated->broadcast(['private-alerts.123'], 'control.updated', ['key' => 'x']);

    expect($meter->lastDeliveryFor('123'))->toBe([
        'at' => Carbon::parse('2026-08-26 21:00:00')->timestamp,
        'connections' => 2,
        'event' => 'control.updated',
    ]);
});

test('a broadcast nobody was listening to is recorded as zero connections', function () {
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andReturn(reverbTriggerResponse(['private-alerts.123' => 0]));

    $meter = new BroadcastMeter;
    (new MeteredBroadcaster(new PusherBroadcaster($pusher), $meter))
        ->broadcast(['private-alerts.123'], 'alert.triggered', []);

    expect($meter->lastDeliveryFor('123')['connections'])->toBe(0);
});

test('a broadcast spanning several of the owner\'s channels records the highest count', function () {
    // ListUpdated fires on alerts.{id} and lists.{id}.{slug}; the alerts channel
    // is the one every overlay holds, so it is the honest number of listeners.
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andReturn(reverbTriggerResponse([
        'private-alerts.123' => 3,
        'private-lists.123.my-list' => 1,
    ]));

    $meter = new BroadcastMeter;
    (new MeteredBroadcaster(new PusherBroadcaster($pusher), $meter))
        ->broadcast(['private-alerts.123', 'private-lists.123.my-list'], 'list.updated', []);

    expect($meter->lastDeliveryFor('123')['connections'])->toBe(3);
});

test('an unmetered channel records nothing', function () {
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andReturn(reverbTriggerResponse(['map.aB3xQ' => 5]));

    $meter = new BroadcastMeter;
    (new MeteredBroadcaster(new PusherBroadcaster($pusher), $meter))
        ->broadcast(['map.aB3xQ'], 'map.position', []);

    expect(Cache::get($meter->deliveryKey('aB3xQ')))->toBeNull();
});

test('the socket exclusion is passed through and stripped from the payload', function () {
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')
        ->once()
        ->withArgs(fn ($channels, $event, $data, $params) => $data === ['key' => 'x']
            && $params === ['socket_id' => 'sock-1', 'info' => 'subscription_count'])
        ->andReturn(reverbTriggerResponse(['private-alerts.123' => 1]));

    (new MeteredBroadcaster(new PusherBroadcaster($pusher), new BroadcastMeter))
        ->broadcast(['private-alerts.123'], 'evt', ['key' => 'x', 'socket' => 'sock-1']);
});

test('a reverb API error still surfaces as a BroadcastException', function () {
    // This is what lands a queued broadcast in failed_jobs. It must keep doing so.
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andThrow(new ApiErrorException('Payload too large', 413));

    $decorated = new MeteredBroadcaster(new PusherBroadcaster($pusher), new BroadcastMeter);

    expect(fn () => $decorated->broadcast(['private-alerts.123'], 'evt', []))
        ->toThrow(BroadcastException::class, 'Pusher error: Payload too large.');
});

test('subscriptionCounts reads the reverb response shape and ignores anything else', function () {
    expect(MeteredBroadcaster::subscriptionCounts(reverbTriggerResponse(['private-alerts.1' => 4])))
        ->toBe(['private-alerts.1' => 4])
        ->and(MeteredBroadcaster::subscriptionCounts((object) []))->toBe([])
        ->and(MeteredBroadcaster::subscriptionCounts(null))->toBe([])
        ->and(MeteredBroadcaster::subscriptionCounts(['channels' => ['a' => ['occupied' => true]]]))->toBe([]);
});

test('lastDeliveryFor is null for an account that has never been broadcast to', function () {
    expect((new BroadcastMeter)->lastDeliveryFor('999'))->toBeNull();
});
