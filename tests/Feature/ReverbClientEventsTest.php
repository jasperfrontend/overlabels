<?php

use Laravel\Reverb\ConfigApplicationProvider;

/**
 * Overlays are read-only sinks: they never send anything back. Reverb's
 * client events ("whispers") are the one channel a connected overlay could
 * use to push a message to every other subscriber on private-alerts.{id},
 * and Reverb enables them by default when the app config says nothing.
 * This resolves the app through Reverb's own provider, so the default that
 * would re-enable them is what is under test, not a config string.
 */
test('reverb rejects client events from overlay connections', function () {
    // Credentials are env-driven and empty under test; the provider needs
    // them to be strings. Everything else comes from config/reverb.php.
    config([
        'reverb.apps.apps.0.app_id' => 'test-app',
        'reverb.apps.apps.0.key' => 'test-key',
        'reverb.apps.apps.0.secret' => 'test-secret',
    ]);

    $provider = new ConfigApplicationProvider(collect(config('reverb.apps.apps')));
    $accepts = $provider->findByKey('test-key')->acceptClientEventsFrom();

    // ClientEvent.php rejects anything outside these two values.
    expect($accepts)->not->toBeIn(['all', 'members']);
});
