<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BMACServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'bmac';
    }

    /**
     * BMAC signs the raw request body with HMAC-SHA256(body, secret) and sends
     * the hex digest in the `x-signature-sha256` header. The shared secret is
     * what the user pastes from studio.buymeacoffee.com/webhooks/<id> into the
     * Overlabels settings page.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $credentials = $integration->getCredentialsDecrypted();
        $secret = $credentials['webhook_secret'] ?? null;

        if (empty($secret)) {
            return false;
        }

        $provided = $request->header('x-signature-sha256') ?? $request->header('X-BMC-Signature');
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, strtolower($provided));
    }

    /**
     * Map BMAC `type` (e.g. "donation.created") to a normalized internal type.
     * Returning null short-circuits the controller into a 200 + ignored response,
     * which satisfies BMAC's "always 2xx or we disable the webhook" requirement.
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['type'] ?? '') {
            'donation.created' => 'donation',
            'commission_order.created' => 'commission',
            'extra_purchase.created' => 'extra',
            'membership.started' => 'membership',
            'recurring_donation.started' => 'recurring',
            'wishlist_payment.created' => 'wishlist',
            default => null,
        };
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $rawType = (string) ($payload['type'] ?? '');
        $data = $payload['data'] ?? [];

        // Capture PII before stripping it out of $rawForStorage.
        $email = isset($data['supporter_email']) && is_string($data['supporter_email'])
            ? $data['supporter_email']
            : null;
        $emailHash = $email !== null ? hash('sha256', strtolower(trim($email))) : null;

        // Build the storage-safe payload: drop email, address, and gross-charged amount.
        $rawForStorage = $payload;
        if (isset($rawForStorage['data']) && is_array($rawForStorage['data'])) {
            unset(
                $rawForStorage['data']['supporter_email'],
                $rawForStorage['data']['shipping_address'],
                $rawForStorage['data']['total_amount_charged'],
            );
            if (isset($rawForStorage['data']['commission']) && is_array($rawForStorage['data']['commission'])) {
                unset($rawForStorage['data']['commission']['shipping_address']);
            }
        }

        $supporterName = isset($data['supporter_name']) ? (string) $data['supporter_name'] : null;
        $description = isset($data['message']) ? (string) $data['message'] : null;
        $supportNote = $this->resolveSupportNote($data);
        $amount = isset($data['amount']) ? (string) $data['amount'] : null;
        $currency = isset($data['currency']) ? (string) $data['currency'] : null;
        $supportType = isset($data['support_type']) && is_string($data['support_type']) && $data['support_type'] !== ''
            ? $data['support_type']
            : $this->humanizeSupportType($eventType);

        $messageId = $this->buildMessageId($rawType, $data);

        $tags = [
            'event.from_name' => (string) ($supporterName ?? ''),
            'event.message' => (string) ($description ?? ''),
            'event.support_note' => $supportNote,
            'event.amount' => (string) ($amount ?? ''),
            'event.currency' => (string) ($currency ?? ''),
            'event.type' => $eventType,
            'event.support_type' => $supportType,
            'event.source' => 'Buy Me a Coffee',
            'event.transaction_id' => (string) ($data['transaction_id'] ?? $data['psp_id'] ?? ''),
            'event.url' => '',
            'event.coffee_count' => isset($data['coffee_count']) ? (string) $data['coffee_count'] : '',
            'event.is_recurring' => in_array($eventType, ['recurring', 'membership'], true) ? '1' : '0',
            'event.is_membership' => $eventType === 'membership' ? '1' : '0',
            'event.commission_name' => $eventType === 'commission'
                ? (string) ($data['commission']['name'] ?? '')
                : '',
            'event.wishlist_title' => $eventType === 'wishlist'
                ? (string) ($data['wishlist']['title'] ?? '')
                : '',
            'event.extras_title' => $eventType === 'extra'
                ? (string) ($data['extras'][0]['title'] ?? '')
                : '',
            'event.live_mode' => ! empty($payload['live_mode']) ? '1' : '0',
        ];

        return new NormalizedExternalEvent(
            service: 'bmac',
            eventType: $eventType,
            messageId: $messageId,
            fromName: $supporterName,
            // The "message" exposed via getMessage() (used to populate
            // latest_donation_message) is the supporter's own note, not BMAC's
            // canned description.
            message: $supportNote,
            amount: $amount,
            currency: $currency,
            templateTags: $tags,
            raw: $rawForStorage,
            supporterEmail: $email,
            supporterEmailHash: $emailHash,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['donation', 'commission', 'extra', 'membership', 'recurring', 'wishlist'];
    }

    /**
     * BMAC follows the Ko-fi pattern: presets are added explicitly by the user
     * via ControlFormModal on a static template, not auto-provisioned on connect.
     * This list backs the frontend BMAC_PRESETS array and the help page reference.
     */
    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'donations_received', 'type' => 'counter', 'label' => 'BMAC Donations Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Donation Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Donation Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total BMAC Amount (session)', 'value' => '0'],
            ['key' => 'latest_support_type', 'type' => 'text', 'label' => 'Latest Support Type', 'value' => ''],
        ];
    }

    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $updates = [];

        if (! in_array($event->getEventType(), $this->getSupportedEventTypes(), true)) {
            return $updates;
        }

        $tags = $event->getTemplateTags();

        $updates['donations_received'] = ['action' => 'increment'];
        $updates['latest_donor_name'] = $event->getFromName() ?? '';
        $updates['latest_donation_message'] = $event->getMessage() ?? '';
        $updates['latest_donation_currency'] = $event->getCurrency() ?? '';
        $updates['latest_support_type'] = (string) ($tags['event.support_type'] ?? '');

        if ($event->getAmount() !== null && $event->getAmount() !== '') {
            $updates['latest_donation_amount'] = $event->getAmount();
            $updates['total_received'] = ['action' => 'add', 'amount' => (float) $event->getAmount()];
        }

        return $updates;
    }

    /**
     * BMAC mixes string ("true"/"false") and bool note_hidden flags across event
     * types. When the supporter chose to keep the note private to the streamer,
     * we emit an empty string everywhere downstream rather than the placeholder
     * text - that keeps overlay alerts that conditionally render the line clean.
     */
    private function resolveSupportNote(array $data): string
    {
        $hidden = filter_var($data['note_hidden'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($hidden) {
            return '';
        }

        $note = $data['support_note'] ?? null;

        return $note === null ? '' : (string) $note;
    }

    private function humanizeSupportType(string $eventType): string
    {
        return match ($eventType) {
            'donation' => 'Supporter',
            'commission' => 'Commission',
            'extra' => 'Extra',
            'membership' => 'Membership',
            'recurring' => 'Subscription',
            'wishlist' => 'Wishlist',
            default => '',
        };
    }

    /**
     * Stable, type-scoped dedup key. BMAC `data.id` is unique per event-type
     * namespace (donation 58 vs. extra 59 vs. commission 63 etc.), so we
     * prefix with the raw type to avoid cross-type collisions. Falls through
     * to transaction_id / psp_id / a UUID if the id is somehow missing.
     */
    private function buildMessageId(string $rawType, array $data): string
    {
        $id = $data['id'] ?? null;
        if ($id !== null && $id !== '') {
            $type = $rawType !== '' ? $rawType : 'unknown';

            return "bmac:{$type}:{$id}";
        }

        $fallback = $data['transaction_id'] ?? $data['psp_id'] ?? null;
        if (is_string($fallback) && $fallback !== '') {
            return "bmac:fallback:{$fallback}";
        }

        return 'bmac:uuid:'.Str::uuid()->toString();
    }
}
