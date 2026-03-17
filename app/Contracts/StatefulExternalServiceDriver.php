<?php

namespace App\Contracts;

use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;

interface StatefulExternalServiceDriver
{
    /**
     * Called before control updates are applied.
     * Allows drivers to enrich $updates with state-dependent calculations
     * (e.g. distance accumulation from GPS coordinates).
     */
    public function beforeControlUpdates(
        ExternalIntegration $integration,
        NormalizedExternalEvent $event,
        array &$updates
    ): void;
}
