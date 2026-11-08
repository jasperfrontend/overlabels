<?php

namespace App\Services\Paddle;

/**
 * Verifies the Paddle-Signature header, per Paddle's manual verification spec:
 * the header is `ts=<unix>;h1=<hex>` (more than one h1 while a secret
 * rotates), and h1 is HMAC-SHA256 of "<ts>:<raw body>" keyed with the
 * notification destination's secret.
 *
 * The body must be the raw bytes as received. A decoded and re-encoded body
 * does not hash the same.
 */
class PaddleSignature
{
    /**
     * Paddle's SDKs default to 5 seconds. This is wider because every handler
     * is an idempotent upsert, so a replay inside the window changes nothing,
     * while a few seconds of clock skew would otherwise fail every delivery.
     */
    public const TOLERANCE_SECONDS = 300;

    public static function isValid(string $rawBody, string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 'ts' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'h1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$rawBody, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
