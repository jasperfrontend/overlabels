<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Pile C of docs/design/event-delivery-heal-2026-08.md: things noticed during
 * the delivery audit that were real but not on the path. Pinned here so none
 * of them quietly comes back.
 */
test('eventsub:monitor is scheduled exactly once', function () {
    // It was scheduled hourly AND six-hourly with identical flags; the "deep
    // check" differed only in the log file it appended to.
    $monitors = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'eventsub:monitor'));

    expect($monitors)->toHaveCount(1);
});

test('the gps_pings table is gone', function () {
    // Created March 2026, never written or read: GPS goes through external_events.
    expect(Schema::hasTable('gps_pings'))->toBeFalse();
});

test('a logged-in owner can authorise their own lists channel and nobody else\'s', function () {
    // The test suite broadcasts with the null driver, which authorises everyone
    // at /broadcasting/auth, so the registered callback is exercised directly.
    $callback = Broadcast::driver()->getChannels()['lists.{twitchId}.{slug}'] ?? null;
    expect($callback)->not->toBeNull();

    $owner = User::factory()->create(['twitch_id' => '424242']);
    $stranger = User::factory()->create(['twitch_id' => '999999']);

    expect($callback($owner, '424242', 'my-list'))->toBeTrue()
        ->and($callback($stranger, '424242', 'my-list'))->toBeFalse();
});
