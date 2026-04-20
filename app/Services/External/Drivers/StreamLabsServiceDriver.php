<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamLabsServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'streamlabs';
    }

    /**
     * Verify the request came from our trusted Node.js listener.
     * Checks X-Listener-Secret header against the per-integration secret.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $credentials = $integration->getCredentialsDecrypted();
        $stored = $credentials['listener_secret'] ?? null;

        if (empty($stored)) {
            return false;
        }

        $headerSecret = $request->header('X-Listener-Secret', '');

        return hash_equals($stored, (string) $headerSecret);
    }

    /**
     * Map StreamLabs event type to normalized type.
     * For v1, only donations are supported.
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['type'] ?? '') {
            'donation' => 'donation',
            default => null,
        };
    }

    /**
     * Transform raw StreamLabs donation payload into a NormalizedExternalEvent.
     *
     * StreamLabs wraps donations in a "message" array:
     * { "type": "donation", "message": [{ "id": ..., "name": ..., "amount": ..., ... }] }
     */
    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $msg = $payload['message'][0] ?? [];

        $fromName = $this->decodeHtml($msg['from'] ?? $msg['name'] ?? null);
        $message = $this->decodeHtml($msg['message'] ?? null);
        $amount = $msg['amount'] ?? null;
        $currency = $msg['currency'] ?? null;
        $formattedAmount = $msg['formatted_amount'] ?? $msg['formattedAmount'] ?? null;

        $messageId = $payload['event_id']
            ?? $msg['_id']
            ?? ('sl_'.($msg['id'] ?? (string) Str::uuid()));

        $tags = [
            'event.from_name' => (string) ($fromName ?? ''),
            'event.message' => (string) ($message ?? ''),
            'event.amount' => (string) ($amount ?? ''),
            'event.currency' => (string) ($currency ?? ''),
            'event.formatted_amount' => (string) ($formattedAmount ?? ''),
            'event.type' => $eventType,
            'event.source' => 'StreamLabs',
            'event.transaction_id' => (string) $messageId,
        ];

        return new NormalizedExternalEvent(
            service: 'streamlabs',
            eventType: $eventType,
            messageId: $messageId,
            fromName: $fromName,
            message: $message,
            amount: $amount,
            currency: $currency,
            templateTags: $tags,
            raw: $payload,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['donation'];
    }

    /**
     * Defensive: widget-rendered donation platforms (StreamElements, and
     * StreamLabs from the same ecosystem) sometimes emit donor names and
     * messages with pre-encoded HTML entities. Decode once at the driver
     * boundary so plain-text consumers (control values, alert broadcasts)
     * see clean UTF-8.
     */
    private function decodeHtml(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Controls to auto-provision when a user connects StreamLabs.
     */
    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'donations_received', 'type' => 'counter', 'label' => 'StreamLabs Donations Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Donation Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Donation Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total StreamLabs Amount (session)', 'value' => '0'],
        ];
    }

    /**
     * Determine which controls to update and how, based on the event.
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        if ($event->getEventType() !== 'donation') {
            return [];
        }

        $updates = [
            'donations_received' => ['action' => 'increment'],
            'latest_donor_name' => $event->getFromName() ?? '',
            'latest_donation_message' => $event->getMessage() ?? '',
            'latest_donation_currency' => $event->getCurrency() ?? '',
        ];

        if ($event->getAmount() !== null) {
            $updates['latest_donation_amount'] = $event->getAmount();
            $updates['total_received'] = ['action' => 'add', 'amount' => (float) $event->getAmount()];
        }

        return $updates;
    }
}
