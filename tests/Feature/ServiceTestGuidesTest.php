<?php

use App\Services\External\ExternalServiceRegistry;
use App\Support\ServiceTestGuides;

/**
 * The per-service "send yourself a test tip" guides a product shows after
 * install. Each is a page to open and a few things to press, walked by hand
 * on 2026-09-14. The tests pin the shape, not the prose.
 */
it('has a guide for every donation service and none for an Overlabels channel', function () {
    foreach (['kofi', 'streamlabs', 'bmac', 'fourthwall', 'throne'] as $service) {
        expect(ServiceTestGuides::for($service))->not->toBeNull($service);
    }

    foreach (['checkin', 'tower', 'gps', 'nope'] as $service) {
        expect(ServiceTestGuides::for($service))->toBeNull($service);
    }
});

it('gives every guide a page to open, a button label, steps, and the way to test mode', function () {
    foreach (['kofi', 'streamlabs', 'bmac', 'fourthwall', 'throne'] as $service) {
        $guide = ServiceTestGuides::for($service);

        expect($guide['service'])->toBe($service)
            ->and($guide['service_label'])->toBe(ExternalServiceRegistry::displayName($service))
            ->and($guide['url'])->toStartWith('https://')
            ->and($guide['label'])->not->toBe('')
            ->and($guide['steps'])->not->toBe([])
            ->and($guide['settings_url'])->toBe(route("settings.integrations.{$service}.show"));

        foreach ($guide['steps'] as $step) {
            expect($step)->not->toContain('{webhook_prefix}');
        }
    }
});

it('names this install\'s own webhook URL where a dashboard lists several', function () {
    foreach (['bmac', 'throne'] as $service) {
        $steps = implode("\n", ServiceTestGuides::for($service)['steps']);

        expect($steps)->toContain(url('/api/webhooks/'.$service).'/');
    }
});

it('picks the first service in a list that has a guide', function () {
    expect(ServiceTestGuides::firstFor(['checkin', 'throne', 'kofi'])['service'])->toBe('throne')
        ->and(ServiceTestGuides::firstFor(['checkin', 'tower']))->toBeNull()
        ->and(ServiceTestGuides::firstFor([]))->toBeNull();
});
