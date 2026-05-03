<?php

namespace App\Services;

use Sqids\Sqids;

/**
 * Encodes a Twitch ID into an opaque slug for the public map page so the
 * numeric ID never appears in URLs, network requests, or WebSocket channel
 * names. Reversible, deterministic, and pure CPU - no DB or cache hit.
 *
 * The alphabet in config('services.map_slug.alphabet') is the only state
 * that matters; if it changes, every previously shared /map/{slug} URL
 * stops resolving.
 */
class MapSlugService
{
    private Sqids $sqids;

    public function __construct()
    {
        $config = config('services.map_slug');
        $this->sqids = new Sqids(
            (string) ($config['alphabet'] ?? ''),
            (int) ($config['min_length'] ?? 8),
        );
    }

    public function encode(int|string $twitchId): string
    {
        return $this->sqids->encode([(int) $twitchId]);
    }

    /**
     * Returns the original Twitch ID as a string, or null if the slug is
     * malformed or doesn't decode to exactly one number.
     */
    public function decode(string $slug): ?string
    {
        $decoded = $this->sqids->decode($slug);

        if (count($decoded) !== 1) {
            return null;
        }

        return (string) $decoded[0];
    }
}
