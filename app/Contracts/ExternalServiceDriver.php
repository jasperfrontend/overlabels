<?php

namespace App\Contracts;

use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;

interface ExternalServiceDriver
{
    /**
     * The unique service key (e.g. 'kofi', 'throne').
     */
    public function getServiceKey(): string;

    /**
     * Verify the incoming request is authentic.
     * Should return true if valid, false (or throw) if not.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool;

    /**
     * Determine the normalized event type from the raw payload.
     * Returns null if the event type is unknown/unsupported.
     */
    public function parseEventType(array $payload): ?string;

    /**
     * Transform the raw payload into a NormalizedExternalEvent.
     */
    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent;

    /**
     * List of event types supported by this service.
     * Used for UI display and mapping.
     */
    public function getSupportedEventTypes(): array;

    /**
     * Return control definitions to auto-provision when a user connects this service.
     * Each entry: ['key' => ..., 'type' => ..., 'label' => ...]
     */
    public function getAutoProvisionedControls(): array;

    /**
     * Return the control update map for a given normalized event.
     * Keys are control keys (e.g. 'kofis_received'), values are:
     *   - string: new absolute value
     *   - ['action' => 'increment']: increment by step
     *   - ['action' => 'add', 'amount' => n]: add n to current value
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array;
}
