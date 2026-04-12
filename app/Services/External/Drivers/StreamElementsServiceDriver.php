<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamElementsServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'streamelements';
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
     * Map StreamElements event type to normalized type.
     * Only `tip` is supported.
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['type'] ?? '') {
            'tip' => 'donation',
            default => null,
        };
    }

    /**
     * Transform the raw StreamElements tip payload into a NormalizedExternalEvent.
     *
     * Payload shape:
     * { "_id": ..., "type": "tip", "data": { "username", "displayName", "amount", "message", "currency", "tipId" } }
     */
    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $data = $payload['data'] ?? [];
        $fromName = $data['displayName'] ?? $data['username'] ?? null;
        $message = $data['message'] ?? null;
        $amount = isset($data['amount']) ? (string) $data['amount'] : null;
        $currency = $data['currency'] ?? null;

        $messageId = $payload['_id']
            ?? $data['tipId']
            ?? ('se_'.Str::uuid());

        $tags = [
            'event.from_name' => (string) ($fromName ?? ''),
            'event.message' => (string) ($message ?? ''),
            'event.amount' => $amount ?? '',
            'event.currency' => (string) ($currency ?? ''),
            'event.type' => $eventType,
            'event.source' => 'StreamElements',
            'event.transaction_id' => (string) $messageId,
        ];

        return new NormalizedExternalEvent(
            service: 'streamelements',
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
     * Controls to auto-provision when a user connects StreamElements.
     */
    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'donations_received', 'type' => 'counter', 'label' => 'StreamElements Donations Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Donation Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Donation Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total StreamElements Amount (session)', 'value' => '0'],
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
