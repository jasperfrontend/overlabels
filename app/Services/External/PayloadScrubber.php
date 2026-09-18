<?php

namespace App\Services\External;

/**
 * Removes personal data from a third-party payload before it is stored.
 *
 * `NormalizedExternalEvent::$raw` has always been documented as
 * "payload-as-stored: PII already stripped by the driver", but only BMAC
 * actually stripped anything. Ko-fi, Fourthwall, Streamlabs and Throne passed
 * the service's payload through untouched into `external_events.raw_payload`,
 * which is plain jsonb.
 *
 * Confirmed against production rows on 2026-09-18: every stored Ko-fi event
 * carried the donor's `email`, six of them carried `discord_username`, and all
 * of them carried our own `verification_token` - the same secret the
 * integration row keeps encrypted. `shipping` was present on every row and null
 * on all of them only because no shop order or commission had come in yet; a
 * commission populates it with the buyer's postal address.
 *
 * The match is by key name at any depth, rather than a list of known paths,
 * precisely because the payloads that carry an address are the ones we have not
 * seen. A service that adds a field tomorrow, or nests it somewhere new, is
 * covered without a code change.
 *
 * Nothing downstream reads these keys: the drivers normalise the fields they
 * need before scrubbing, and the only code that reads `raw_payload` back out is
 * the GPS aggregator, which is not scrubbed and has no PII of this kind.
 */
final class PayloadScrubber
{
    /**
     * Key names dropped wherever they appear, compared case-insensitively.
     */
    public const DENIED_KEYS = [
        // Contact details.
        'email',
        'supporter_email',
        'buyer_email',
        'donor_email',
        'gifter_email',
        'customer_email',
        'contact_email',
        'phone',
        'phone_number',
        'telephone',

        // Postal details. Ko-fi sends `shipping` on shop orders and
        // commissions; BMAC sends `shipping_address`.
        'shipping',
        'shipping_address',
        'billing_address',
        'delivery_address',
        'address',

        // Third-party account identity that has nothing to do with an overlay.
        'discord_userid',
        'discord_username',

        // Our own webhook secret, which Ko-fi echoes back in every payload.
        // Storing it next to the event puts a credential in a table that is
        // deliberately readable, and the encrypted copy on the integration row
        // is the only one anything reads.
        'verification_token',
    ];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public static function scrub(array $payload): array
    {
        $denied = array_flip(array_map('strtolower', self::DENIED_KEYS));
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && isset($denied[strtolower($key)])) {
                continue;
            }

            $clean[$key] = is_array($value) ? self::scrub($value) : $value;
        }

        return $clean;
    }
}
