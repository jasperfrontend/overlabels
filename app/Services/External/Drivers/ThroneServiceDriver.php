<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Services\External\NormalizedExternalEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ThroneServiceDriver implements ExternalServiceDriver
{
    public function getServiceKey(): string
    {
        return 'throne';
    }

    /**
     * Throne signs every inbound webhook with Ed25519. Unlike Ko-fi (token in the
     * body) or Fourthwall (HMAC), the signature is detached and carried in headers:
     *   - X-Signature-Ed25519    hex-encoded 64-byte signature (128 hex chars)
     *   - X-Signature-Timestamp  Unix seconds, also folded into the signed message
     *
     * The signed message is "{timestamp}.{rawBody}". We MUST verify against the raw
     * request body ($request->getContent()), never a re-encoded parse of it - any
     * re-serialization reorders/respaces keys and the signature fails. Verified
     * against Throne's global public key (config('services.throne.public_key')).
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        $timestamp = $request->header('X-Signature-Timestamp');
        $signatureHex = $request->header('X-Signature-Ed25519');

        // Reject a missing or non-numeric timestamp (Throne "additional checks").
        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return false;
        }

        // An Ed25519 signature is exactly 64 bytes / 128 hex chars. Validating the
        // hex shape up front keeps hex2bin() from emitting a warning on bad input.
        if (! is_string($signatureHex) || strlen($signatureHex) !== 128 || ! ctype_xdigit($signatureHex)) {
            return false;
        }

        $signature = hex2bin($signatureHex);
        if ($signature === false || strlen($signature) !== 64) {
            return false;
        }

        $publicKey = $this->publicKeyRaw();
        if ($publicKey === null) {
            Log::error('Throne signature verification: public key not configured or malformed');

            return false;
        }

        $message = $timestamp.'.'.$request->getContent();

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /**
     * Extract the raw 32-byte Ed25519 key from the configured PEM.
     *
     * libsodium's verify wants the raw key, not the PEM. A PEM-encoded Ed25519
     * public key is a 44-byte SubjectPublicKeyInfo DER whose final 32 bytes are the
     * key itself, so we strip the armor, base64-decode, and take the last 32 bytes.
     */
    private function publicKeyRaw(): ?string
    {
        $pem = config('services.throne.public_key');
        if (! is_string($pem) || $pem === '') {
            return null;
        }

        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        if ($der === false || strlen($der) < 32) {
            return null;
        }

        return substr($der, -32);
    }

    /**
     * All three Throne event types are one-shot purchases, so they map to the same
     * normalized `donation` type the other donation drivers emit - that keeps
     * `[[[if:event.type = donation]]]` alert templates uniform across services. The
     * original Throne type is preserved on the payload for getControlUpdates().
     */
    public function parseEventType(array $payload): ?string
    {
        return match ($payload['event_type'] ?? '') {
            'gift_purchased', 'contribution_purchased', 'gift_crowdfunded' => 'donation',
            default => null,
        };
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $data = $payload['data'] ?? [];
        $throneType = (string) ($payload['event_type'] ?? '');

        // Throne's envelope carries a UUID `event_id` purpose-built for dedup.
        $messageId = $payload['event_id'] ?? (string) Str::uuid();

        // gift_crowdfunded carries neither a gifter nor a message.
        $fromName = $data['gifter_username'] ?? null;
        $message = $data['message'] ?? null;

        // contribution_purchased uses `amount`; gifts use `price`. Both are integer
        // minor units (e.g. 10000 = 100.00 USD), so divide by 100 for display.
        $rawAmount = $data['amount'] ?? $data['price'] ?? null;
        $amount = $rawAmount !== null ? $this->minorToDecimal($rawAmount) : null;
        $currency = $data['currency'] ?? null;

        $itemName = $data['item_name'] ?? null;
        $itemThumbnailUrl = $data['item_thumbnail_url'] ?? null;
        $isSurpriseGift = ! empty($data['is_surprise_gift']);

        $tags = [
            'event.from_name' => (string) ($fromName ?? ''),
            'event.message' => (string) ($message ?? ''),
            'event.amount' => $amount !== null ? (string) $amount : '',
            'event.currency' => (string) ($currency ?? ''),
            'event.type' => $eventType,
            'event.source' => 'Throne',
            'event.item_name' => (string) ($itemName ?? ''),
            'event.item_thumbnail_url' => (string) ($itemThumbnailUrl ?? ''),
            'event.is_surprise_gift' => $isSurpriseGift ? '1' : '0',
            'event.throne_event_type' => $throneType,
            'event.transaction_id' => (string) $messageId,
        ];

        return new NormalizedExternalEvent(
            service: 'throne',
            eventType: $eventType,
            messageId: (string) $messageId,
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
     * The first six mirror the shared donation-family controls (Ko-fi, Fourthwall,
     * etc.) so the standard donation alert template works unchanged. The last three
     * are Throne-unique - Throne gifts are real products, so they carry an item
     * name, a product thumbnail, and a surprise-gift flag the donation shape has no
     * slot for.
     */
    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'donations_received', 'type' => 'counter', 'label' => 'Throne Gifts Received', 'value' => '0'],
            ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Gifter Name', 'value' => ''],
            ['key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Gift Amount', 'value' => '0'],
            ['key' => 'latest_donation_message', 'type' => 'text', 'label' => 'Latest Gift Message', 'value' => ''],
            ['key' => 'latest_donation_currency', 'type' => 'text', 'label' => 'Latest Currency', 'value' => ''],
            ['key' => 'total_received', 'type' => 'number', 'label' => 'Total Throne Amount (session)', 'value' => '0'],
            ['key' => 'latest_item_name', 'type' => 'text', 'label' => 'Latest Item Name', 'value' => ''],
            ['key' => 'latest_item_thumbnail_url', 'type' => 'text', 'label' => 'Latest Item Thumbnail URL', 'value' => ''],
            ['key' => 'latest_is_surprise_gift', 'type' => 'text', 'label' => 'Latest Is Surprise Gift', 'value' => '0'],
        ];
    }

    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        if ($event->getEventType() !== 'donation') {
            return [];
        }

        $raw = $event->getRaw();
        $data = $raw['data'] ?? [];
        $throneType = $raw['event_type'] ?? '';

        $updates = [
            'donations_received' => ['action' => 'increment'],
            'latest_donation_currency' => $event->getCurrency() ?? '',
        ];

        // gift_crowdfunded has no gifter and no message - bump the counters but do
        // NOT write the "latest" donor/message, or a crowdfund completion would
        // blank out whoever last gifted.
        if ($throneType !== 'gift_crowdfunded') {
            $updates['latest_donor_name'] = $event->getFromName() ?? '';
            $updates['latest_donation_message'] = $event->getMessage() ?? '';
        }

        if ($event->getAmount() !== null) {
            $updates['latest_donation_amount'] = $event->getAmount();
            $updates['total_received'] = ['action' => 'add', 'amount' => (float) $event->getAmount()];
        }

        // Throne-unique fields. item_name/thumbnail are present on all three event
        // types; is_surprise_gift defaults to off when absent.
        if (isset($data['item_name'])) {
            $updates['latest_item_name'] = (string) $data['item_name'];
        }
        if (isset($data['item_thumbnail_url'])) {
            $updates['latest_item_thumbnail_url'] = (string) $data['item_thumbnail_url'];
        }
        $updates['latest_is_surprise_gift'] = ! empty($data['is_surprise_gift']) ? '1' : '0';

        return $updates;
    }

    /**
     * Throne sends amounts as integer minor units (their docs phrase it as "smallest
     * currency unit x 100"; empirically a $100 gift arrives as price=10000, i.e.
     * cents). Currency-naive by design - we divide by 100 and present two decimals.
     */
    private function minorToDecimal(int|float|string $minor): string
    {
        return number_format(((float) $minor) / 100, 2, '.', '');
    }
}
