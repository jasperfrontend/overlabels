<?php

use App\Services\EventMeter;

test('key is namespaced per user and month', function () {
    $meter = new EventMeter;

    expect($meter->key(42, '2026-06'))->toBe('metering:events:42:2026-06');
    expect($meter->key(42))->toBe('metering:events:42:'.now()->format('Y-m'));
});

test('freeLimit is null in observe-only mode', function () {
    config()->set('metering.free_monthly_events', null);

    expect((new EventMeter)->freeLimit())->toBeNull();
});

test('freeLimit returns the configured ceiling as an int', function () {
    config()->set('metering.free_monthly_events', '10000');

    expect((new EventMeter)->freeLimit())->toBe(10000);
});
