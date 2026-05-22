<?php

namespace App\Http\Controllers;

use App\Models\UserFreesoundSound;
use App\Services\Freesound\FreesoundClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Search and library endpoints for the Freesound integration.
 *
 * Pure proxy on the read side: the controller adds the user's auth context
 * and the licence-safe filter, hits Freesound via FreesoundClient, returns
 * the shape the frontend modal expects. Mutating endpoints write to
 * user_freesound_sounds (metadata only - no audio bytes).
 *
 * Hard cap of 100 saved sounds per user. Over the cap, save() returns 422.
 * The cap exists so a future paid tier has somewhere to grow.
 */
class FreesoundController extends Controller
{
    private const int LIBRARY_CAP = 100;

    public function __construct(private readonly FreesoundClient $client) {}

    /**
     * Proxy a search to Freesound. The license filter is appended server-side
     * by FreesoundClient (CC0 + Attribution only), so the frontend cannot
     * accidentally surface NonCommercial-licensed sounds.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:200',
            'page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|string|in:'.implode(',', FreesoundClient::ALLOWED_SORTS),
        ]);

        try {
            $results = $this->client->search(
                query: $validated['q'],
                page: (int) ($validated['page'] ?? 1),
                sort: $validated['sort'] ?? 'score',
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // Re-shape: we only forward fields the modal needs, and we don't echo
        // the raw `next`/`previous` URLs (they're Freesound-internal). Frontend
        // uses page numbers via our endpoint.
        $hits = array_map(static function (array $hit): array {
            return [
                'id' => $hit['id'] ?? null,
                'name' => $hit['name'] ?? '',
                'author' => $hit['username'] ?? '',
                'license' => $hit['license'] ?? '',
                'duration' => $hit['duration'] ?? null,
                'preview_url' => $hit['previews']['preview-hq-mp3'] ?? null,
                'freesound_url' => $hit['url'] ?? null,
                'tags' => is_array($hit['tags'] ?? null) ? array_values($hit['tags']) : [],
            ];
        }, $results['results'] ?? []);

        return response()->json([
            'count' => $results['count'] ?? 0,
            'results' => $hits,
        ]);
    }

    /**
     * Save a sound to the user's library. Re-fetches the canonical record
     * from Freesound to populate fields rather than trusting client input -
     * the only thing the client controls is the sound ID.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'freesound_id' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        // Cap check - count BEFORE upsert. An idempotent re-save of an already-
        // saved sound is fine (unique key absorbs it), but a NEW sound past
        // the cap is rejected.
        $existing = UserFreesoundSound::query()
            ->where('user_id', $user->id)
            ->where('freesound_id', $validated['freesound_id'])
            ->first();

        if (! $existing) {
            $libraryCount = UserFreesoundSound::query()
                ->where('user_id', $user->id)
                ->count();
            if ($libraryCount >= self::LIBRARY_CAP) {
                return response()->json([
                    'message' => 'Your sound library is full ('.self::LIBRARY_CAP.' sounds). Remove one before adding another.',
                ], 422);
            }
        }

        try {
            $sound = $this->client->getSound((int) $validated['freesound_id']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $previewUrl = $sound['previews']['preview-hq-mp3'] ?? null;
        if (! $previewUrl) {
            return response()->json(['message' => 'Freesound did not return a preview URL for this sound.'], 422);
        }

        // Defence in depth: reject NC / Sampling+ even though the search
        // filter already excludes them. A direct save() bypassing the search
        // shouldn't be able to smuggle them through.
        $license = $sound['license'] ?? '';
        if (! $this->isCommercialSafeLicense($license)) {
            return response()->json([
                'message' => 'Sound has a non-commercial license and cannot be saved. Licence: '.$license,
            ], 422);
        }

        $row = UserFreesoundSound::updateOrCreate(
            [
                'user_id' => $user->id,
                'freesound_id' => (int) $validated['freesound_id'],
            ],
            [
                'name' => $sound['name'] ?? '',
                'author' => $sound['username'] ?? '',
                'license' => $license,
                'preview_url' => $previewUrl,
                'duration' => $sound['duration'] ?? null,
                'freesound_url' => $sound['url'] ?? null,
            ]
        );

        return response()->json(['sound' => $row]);
    }

    /**
     * Remove a sound from the user's library.
     */
    public function destroy(Request $request, UserFreesoundSound $sound): JsonResponse
    {
        if ($sound->user_id !== $request->user()->id) {
            abort(404);
        }

        $sound->delete();

        return response()->json(['ok' => true]);
    }

    private function isCommercialSafeLicense(string $license): bool
    {
        // Freesound's API actually returns license URLs, not name strings -
        // e.g. "http://creativecommons.org/publicdomain/zero/1.0/" for CC0 or
        // "https://creativecommons.org/licenses/by/4.0/" for CC-BY. We also
        // tolerate name-string variants in case the API surface ever changes.
        $normalised = strtolower(trim($license));

        // Hard-reject any NC, ND, SA, or Sampling+ modifier first so a URL
        // like "/licenses/by-nc/" can never sneak through the by-check below.
        if (str_contains($normalised, '/by-nc')
            || str_contains($normalised, '/by-nd')
            || str_contains($normalised, '/by-sa')
            || str_contains($normalised, 'noncommercial')
            || str_contains($normalised, 'sampling')) {
            return false;
        }

        // CC0 (URL and name forms)
        if (str_contains($normalised, 'publicdomain/zero')
            || str_contains($normalised, 'creative commons 0')
            || str_contains($normalised, 'cc0')) {
            return true;
        }

        // CC-BY (URL and name forms)
        if (str_contains($normalised, '/licenses/by/')
            || $normalised === 'attribution') {
            return true;
        }

        return false;
    }
}
