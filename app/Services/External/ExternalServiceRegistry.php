<?php

namespace App\Services\External;

use App\Contracts\ExternalServiceDriver;
use App\Services\External\Drivers\BMACServiceDriver;
use App\Services\External\Drivers\FourthwallServiceDriver;
use App\Services\External\Drivers\GpsServiceDriver;
use App\Services\External\Drivers\KofiServiceDriver;
use App\Services\External\Drivers\StreamElementsServiceDriver;
use App\Services\External\Drivers\StreamLabsServiceDriver;
use App\Services\External\Drivers\ThroneServiceDriver;
use InvalidArgumentException;

class ExternalServiceRegistry
{
    /**
     * Map of service key => driver class.
     */
    private static array $drivers = [
        'kofi' => KofiServiceDriver::class,
        'gps' => GpsServiceDriver::class,
        'streamlabs' => StreamLabsServiceDriver::class,
        'streamelements' => StreamElementsServiceDriver::class,
        'fourthwall' => FourthwallServiceDriver::class,
        'bmac' => BMACServiceDriver::class,
        'throne' => ThroneServiceDriver::class,
    ];

    /**
     * Check if a service key is registered.
     */
    public static function has(string $service): bool
    {
        return array_key_exists($service, static::$drivers);
    }

    /**
     * Resolve and return a driver instance for the given service key.
     *
     * @throws InvalidArgumentException if service is not registered
     */
    public static function driver(string $service): ExternalServiceDriver
    {
        if (! static::has($service)) {
            throw new InvalidArgumentException("Unknown external service: {$service}");
        }

        return app(static::$drivers[$service]);
    }

    /**
     * List all registered service keys.
     */
    public static function services(): array
    {
        return array_keys(static::$drivers);
    }

    /**
     * Human-facing name for a service key.
     *
     * Lives here rather than in a controller because the generated reference
     * pages need it too, and a second hand-maintained copy is how "Streamlabs"
     * and "StreamLabs" end up both being right somewhere. Patreon is listed
     * without a driver on purpose: it is a known service name used in settings
     * copy before any integration exists for it.
     */
    public static function displayName(string $service): string
    {
        return match ($service) {
            'kofi' => 'Ko-fi',
            'gps' => 'Overlabels GPS',
            'streamlabs' => 'Streamlabs',
            'streamelements' => 'StreamElements',
            'throne' => 'Throne',
            'patreon' => 'Patreon',
            'fourthwall' => 'Fourthwall',
            'bmac' => 'Buy Me a Coffee',
            default => ucfirst($service),
        };
    }
}
