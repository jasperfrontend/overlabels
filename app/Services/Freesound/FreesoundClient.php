<?php

namespace App\Services\Freesound;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client over the public Freesound v2 API.
 *
 * Two endpoints only: text search + sound info. Both accept the static
 * `Authorization: Token <api_key>` header - no OAuth needed because we never
 * download the original file (we hotlink the public `preview-hq-mp3` URL
 * instead, which keeps us on the right side of Freesound's API ToS).
 *
 * License filter is enforced server-side: all queries get
 * `license:"Creative Commons 0" OR license:"Attribution"` appended so NC /
 * Sampling+ sounds never reach the user's modal. This is a product decision -
 * Overlabels is commercial-friendly and we never want a streamer accidentally
 * shipping a NonCommercial-licensed sound.
 */
class FreesoundClient
{
    private const BASE_URL = 'https://freesound.org/apiv2';

    /**
     * Whitelisted licenses (Freesound's `license` field is a name string, not a URL).
     * Kept in the Solr filter syntax expected by `/search/text/`.
     */
    private const COMMERCIAL_SAFE_LICENSE_FILTER = 'license:"Creative Commons 0" OR license:"Attribution"';

    /**
     * Fields requested on every search hit. Keep this list tight -
     * unused fields cost bandwidth and add JSON noise.
     */
    private const SEARCH_FIELDS = 'id,name,username,license,duration,previews,url,tags';

    /**
     * Sort options accepted by Freesound's `sort` query parameter.
     * The Solr index keys these strings exactly.
     */
    public const ALLOWED_SORTS = [
        'score',
        'duration_asc',
        'duration_desc',
        'created_desc',
        'downloads_desc',
        'rating_desc',
    ];

    /**
     * Read the static API key from config at call time rather than holding it
     * in a constructor property. Sidesteps Laravel's container not being able
     * to auto-wire a scalar arg, and lets tests `config()->set(...)` to mock.
     */
    private function apiKey(): ?string
    {
        return config('services.freesound.api_key');
    }

    /**
     * Text search. Returns the raw decoded JSON shape so the controller can
     * forward it (post-shaping) to the frontend.
     *
     * @param  string  $query  Free-text query - the streamer's input. Passed through.
     * @param  int  $page  1-indexed page number.
     * @param  int  $pageSize  Max 150, default 15.
     * @param  string  $sort  One of ALLOWED_SORTS; falls back to 'score' if anything else.
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the API key is missing or the upstream call fails.
     */
    public function search(string $query, int $page = 1, int $pageSize = 15, string $sort = 'score'): array
    {
        $this->assertConfigured();

        $response = $this->http()->get(self::BASE_URL.'/search/text/', [
            'query' => $query,
            'filter' => self::COMMERCIAL_SAFE_LICENSE_FILTER,
            'page' => max(1, $page),
            'page_size' => max(1, min(150, $pageSize)),
            'fields' => self::SEARCH_FIELDS,
            'sort' => in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'score',
        ]);

        if (! $response->successful()) {
            Log::warning('freesound.search.failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Freesound search failed with status '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch the metadata for a single sound (used when the user picks one from
     * search results - we re-fetch to get the canonical record at save time).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the API key is missing or the upstream call fails.
     */
    public function getSound(int $soundId): array
    {
        $this->assertConfigured();

        $response = $this->http()->get(self::BASE_URL.'/sounds/'.$soundId.'/', [
            'fields' => self::SEARCH_FIELDS,
        ]);

        if (! $response->successful()) {
            Log::warning('freesound.get_sound.failed', [
                'sound_id' => $soundId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Freesound sound fetch failed with status '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @throws ConnectionException
     */
    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Token '.$this->apiKey(),
        ])->acceptJson()->timeout(10);
    }

    private function assertConfigured(): void
    {
        if (! $this->apiKey()) {
            throw new RuntimeException(
                'Freesound API key is not configured. Set FREESOUND_API_KEY in your .env file.'
            );
        }
    }
}
