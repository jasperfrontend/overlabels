<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FourthwallServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'fourthwall';
    }

    /**
     * Every inbound Fourthwall webhook carries two HMACs:
     *   - X-Fourthwall-Hmac-Sha256        - per-webhook secret (we don't use this;
     *                                        Fourthwall's API doesn't return the secret
     *                                        on webhook registration, only shows it in
     *                                        the dashboard, which makes automated
     *                                        provisioning impossible)
     *   - X-Fourthwall-Hmac-Apps-Sha256   - app-level HMAC, shared across every webhook
     *                                        on every user in our app. This is what we
     *                                        verify against, via config('services.fourthwall.hmac').
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $secret = config('services.fourthwall.hmac');
        $provided = $request->header('X-Fourthwall-Hmac-Apps-Sha256');
        $body = $request->getContent();

        if (empty($secret)) {
            Log::error('Fourthwall HMAC verification: FW_HMAC not configured');

            return false;
        }

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($expected, $provided);
    }

    /**
     * Fourthwall's envelope uses a `type` field with UPPERCASE underscored names.
     * Phase 1 only maps DONATION; ORDER_PLACED, GIFT_PURCHASE, SUBSCRIPTION_PURCHASED
     * are intentional follow-ups.
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['type'] ?? '') {
            'DONATION' => 'donation',
            default => null,
        };
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $data = $payload['data'] ?? [];

        // Dedup on the business entity id (`data.id`), not the envelope id - retries
        // of the same donation should reuse the same key.
        $messageId = $data['id'] ?? (string) Str::uuid();
        $fromName = $data['username'] ?? null;
        $message = $data['message'] ?? null;
        $amount = $data['amounts']['total']['value'] ?? null;
        $currency = $data['amounts']['total']['currency'] ?? null;
        $status = $data['status'] ?? null;

        $tags = [
            'event.from_name' => (string) ($fromName ?? ''),
            'event.message' => (string) ($message ?? ''),
            'event.amount' => $amount !== null ? (string) $amount : '',
            'event.currency' => (string) ($currency ?? ''),
            'event.type' => $eventType,
            'event.source' => 'Fourthwall',
            'event.status' => (string) ($status ?? ''),
            'event.transaction_id' => (string) $messageId,
        ];

        return new NormalizedExternalEvent(
            service: 'fourthwall',
            eventType: $eventType,
            messageId: (string) $messageId,
            fromName: $fromName,
            message: $message,
            amount: $amount !== null ? (string) $amount : null,
            currency: $currency,
            templateTags: $tags,
            raw: $payload,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['donation'];
    }

    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'donations_received', 'type' => 'counter', 'label' => 'Fourthwall Donations Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Donation Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Donation Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total Fourthwall Amount (session)', 'value' => '0'],
        ];
    }

    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $updates = [];

        if ($event->getEventType() === 'donation') {
            $updates['donations_received'] = ['action' => 'increment'];
            $updates['latest_donor_name'] = $event->getFromName() ?? '';
            $updates['latest_donation_message'] = $event->getMessage() ?? '';
            $updates['latest_donation_currency'] = $event->getCurrency() ?? '';

            if ($event->getAmount() !== null) {
                $updates['latest_donation_amount'] = $event->getAmount();
                $updates['total_received'] = ['action' => 'add', 'amount' => (float) $event->getAmount()];
            }
        }

        return $updates;
    }
}
