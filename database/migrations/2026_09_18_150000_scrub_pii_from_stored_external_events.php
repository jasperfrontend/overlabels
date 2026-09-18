<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes personal data from external_events rows that were stored before the
 * drivers began scrubbing it.
 *
 * Until today Ko-fi, Fourthwall, Streamlabs and Throne payloads were written to
 * raw_payload exactly as received. Confirmed on production on 2026-09-18: every
 * stored Ko-fi event carried the donor's email address, several carried their
 * Discord identity, and all of them carried our own Ko-fi verification token -
 * the same secret the integration row goes to the trouble of encrypting.
 *
 * Fixing the drivers stops new rows. This fixes the ones already written, which
 * would otherwise sit in a readable table, and in every nightly backup, until
 * the 90-day prune caught up with them.
 *
 * The key list is frozen here as a literal rather than read from
 * PayloadScrubber::DENIED_KEYS. A migration is dated; a constant is not, and a
 * migration dated in September must not start scrubbing whatever that list has
 * grown into by March. Same reason nothing here touches an Eloquent model.
 *
 * GPS rows are left alone: GpsSessionAggregator reads raw_payload->>'lat' and
 * friends back out, and there is no PII of this kind in a location ping.
 */
return new class extends Migration
{
    private const DENIED_KEYS = [
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
        'shipping',
        'shipping_address',
        'billing_address',
        'delivery_address',
        'address',
        'discord_userid',
        'discord_username',
        'verification_token',
    ];

    private const SERVICES = ['kofi', 'fourthwall', 'streamlabs', 'bmac', 'throne'];

    public function up(): void
    {
        $denied = array_flip(self::DENIED_KEYS);

        DB::table('external_events')
            ->whereIn('service', self::SERVICES)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($denied) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->raw_payload, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $clean = $this->scrub($payload, $denied);

                    if ($clean === $payload) {
                        continue;
                    }

                    DB::table('external_events')
                        ->where('id', $row->id)
                        ->update(['raw_payload' => json_encode($clean)]);
                }
            });
    }

    /**
     * Not reversible. The whole point is that the removed values are gone.
     */
    public function down(): void {}

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, int>  $denied
     * @return array<array-key, mixed>
     */
    private function scrub(array $payload, array $denied): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && isset($denied[strtolower($key)])) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value, $denied) : $value;
        }

        return $clean;
    }
};
