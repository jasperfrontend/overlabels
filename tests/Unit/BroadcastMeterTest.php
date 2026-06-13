<?php

use App\Broadcasting\MeteredBroadcaster;
use App\Services\BroadcastMeter;
use Illuminate\Contracts\Broadcasting\Broadcaster;

// Default metered channel families (mirrors config/metering.php).
$prefixes = ['alerts', 'twitch-events', 'lists'];

// ──────────────────────────────────────────────────────────────────────────────
// ownerFromChannel - pure channel-name attribution, no Redis
// ──────────────────────────────────────────────────────────────────────────────

test('attributes the alerts channel to its owning twitch_id', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('private-alerts.123456', $prefixes))->toBe('123456');
    expect(BroadcastMeter::ownerFromChannel('alerts.123456', $prefixes))->toBe('123456');
});

test('attributes the twitch-events channel', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('private-twitch-events.999', $prefixes))->toBe('999');
});

test('attributes a per-list channel by its owner, ignoring the slug', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('private-lists.555.my-cool-list', $prefixes))->toBe('555');
});

test('ignores public and global channels', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('app-updates', $prefixes))->toBeNull();
    expect(BroadcastMeter::ownerFromChannel('bot-channels', $prefixes))->toBeNull();
    expect(BroadcastMeter::ownerFromChannel('map.aB3xQ', $prefixes))->toBeNull();
});

test('ignores the gamejam feed (not a metered overlay channel)', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('gamejam.123456', $prefixes))->toBeNull();
});

test('does not attribute a channel with a non-numeric owner segment', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('alerts.notanid', $prefixes))->toBeNull();
});

test('does not match a family name as a substring of another channel', function () use ($prefixes) {
    expect(BroadcastMeter::ownerFromChannel('alerts-archive.123', $prefixes))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// freeLimit - observe-only vs capped
// ──────────────────────────────────────────────────────────────────────────────

test('freeLimit is null when running observe-only', function () {
    config()->set('metering.free_monthly_broadcasts', null);
    expect((new BroadcastMeter)->freeLimit())->toBeNull();
});

test('freeLimit casts a configured ceiling to an int', function () {
    config()->set('metering.free_monthly_broadcasts', '50000');
    expect((new BroadcastMeter)->freeLimit())->toBe(50000);
});

// ──────────────────────────────────────────────────────────────────────────────
// MeteredBroadcaster - decoration behaviour, no Redis
// ──────────────────────────────────────────────────────────────────────────────

test('the metered broadcaster counts channels then delegates the broadcast', function () {
    $channels = ['private-alerts.123'];

    $meter = Mockery::mock(BroadcastMeter::class);
    $meter->shouldReceive('recordChannels')->once()->with($channels);

    $inner = Mockery::mock(Broadcaster::class);
    $inner->shouldReceive('broadcast')->once()->with($channels, 'evt', ['a' => 1])->andReturn('delivered');

    $decorated = new MeteredBroadcaster($inner, $meter);

    expect($decorated->broadcast($channels, 'evt', ['a' => 1]))->toBe('delivered');
});

test('the metered broadcaster forwards auth and channel registration to the inner driver', function () {
    $meter = Mockery::mock(BroadcastMeter::class);

    $inner = Mockery::mock(Broadcaster::class);
    $inner->shouldReceive('auth')->once()->with('req')->andReturn('authed');
    // channel() is not on the Broadcaster contract; it must reach the inner via __call.
    $inner->shouldReceive('channel')->once()->with('alerts.{id}', Mockery::type('callable'))->andReturn('registered');

    $decorated = new MeteredBroadcaster($inner, $meter);

    expect($decorated->auth('req'))->toBe('authed');
    expect($decorated->channel('alerts.{id}', fn () => true))->toBe('registered');
});
