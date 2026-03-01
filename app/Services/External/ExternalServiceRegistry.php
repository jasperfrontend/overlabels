<?php

namespace App\Services\External;

use App\Contracts\ExternalServiceDriver;
use App\Services\External\Drivers\KofiServiceDriver;

class ExternalServiceRegistry
{
    /**
     * Map of service key => driver class.
     */
    private static array $drivers = [
        'kofi' => KofiServiceDriver::class,
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
     * @throws \InvalidArgumentException if service is not registered
     */
    public static function driver(string $service): ExternalServiceDriver
    {
        if (! static::has($service)) {
            throw new \InvalidArgumentException("Unknown external service: {$service}");
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
}
