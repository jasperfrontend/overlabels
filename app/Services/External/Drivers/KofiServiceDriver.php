<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KofiServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'kofi';
    }

    /**
     * Ko-fi embeds a verification_token string inside the JSON payload.
     * No HMAC — just compare the stored token to the one in the payload.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $credentials = $integration->getCredentialsDecrypted();
        $stored = $credentials['verification_token'] ?? null;

        if (empty($stored)) {
            return false;
        }

        // Ko-fi sends form-encoded body with a `data` JSON field
        $data = $request->input('data');
        if (! is_string($data)) {
            return false;
        }

        $payload = json_decode($data, true);
        if (! is_array($payload)) {
            return false;
        }

        return hash_equals($stored, (string) ($payload['verification_token'] ?? ''));
    }

    /**
     * Map Ko-fi `type` → normalized event type.
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['type'] ?? '') {
            'Donation' => 'donation',
            'Subscription' => 'subscription',
            'Shop Order' => 'shop_order',
            'Commission' => 'commission',
            default => null,
        };
    }

    /**
     * Transform raw Ko-fi payload into a NormalizedExternalEvent.
     */
    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $fromName = $payload['from_name'] ?? null;
        $message = $payload['message'] ?? null;
        $amount = $payload['amount'] ?? null;
        $currency = $payload['currency'] ?? null;
        $messageId = $payload['kofi_transaction_id'] ?? (string) Str::uuid();

        $tags = [
            'event.from_name' => (string) ($fromName ?? ''),
            'event.message' => (string) ($message ?? ''),
            'event.amount' => (string) ($amount ?? ''),
            'event.currency' => (string) ($currency ?? ''),
            'event.type' => $eventType,
            'event.source' => 'Ko-fi',
            'event.tier_name' => (string) ($payload['tier_name'] ?? ''),
            'event.is_first_sub' => ($payload['is_first_subscription_payment'] ?? false) ? '1' : '0',
            'event.is_subscription' => ($payload['is_subscription_payment'] ?? false) ? '1' : '0',
            'event.is_shop_order' => ($eventType === 'shop_order') ? '1' : '0',
            'event.url' => (string) ($payload['url'] ?? ''),
            'event.transaction_id' => (string) $messageId,
        ];

        return new NormalizedExternalEvent(
            service: 'kofi',
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
        return ['donation', 'subscription', 'shop_order', 'commission'];
    }

    /**
     * Controls to auto-provision when a user connects Ko-fi.
     */
    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'kofis_received', 'type' => 'counter', 'label' => 'Ko-fi Donations Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Donation Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Donation Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total Ko-fi Amount (session)', 'value' => '0'],
        ];
    }

    /**
     * Determine which controls to update and how, based on the event.
     */
    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $updates = [];

        if (in_array($event->getEventType(), ['donation', 'subscription'])) {
            $updates['kofis_received'] = ['action' => 'increment'];
            $updates['latest_donor_name'] = $event->getFromName() ?? '';
            $updates['latest_donation_message'] = $event->getMessage() ?? '';
            $updates['latest_donation_currency'] = $event->getCurrency() ?? '';

            if ($event->getAmount() !== null) {
                $updates['latest_donation_amount'] = $event->getAmount();
                $updates['total_received'] = ['action' => 'add', 'amount' => (float) $event->getAmount()];
            }
        } elseif ($event->getEventType() === 'shop_order') {
            $updates['kofis_received'] = ['action' => 'increment'];
            $updates['latest_donor_name'] = $event->getFromName() ?? '';
        }

        return $updates;
    }
}
