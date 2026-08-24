<?php

/**
 * Serves the static template tag catalogue.
 *
 * Until Aug 2026 every user owned a private copy of this list: 1155 rows in
 * production expressing 82 distinct names across 19 accounts, none of them ever
 * edited, plus a queued job to generate them, a jobs table to track it, a
 * cleanup job, deletion bookkeeping and an admin CRUD screen. The copies existed
 * because the old generator derived tag names by walking each user's Twitch
 * payload, so its output was per-user by construction and the storage followed.
 *
 * The catalogue in TemplateDataMapperService replaced that. Every account gets
 * the same tags, so there is nothing to store and nothing to generate - this
 * controller reads the constant and fills in the caller's own live values.
 */

namespace App\Http\Controllers;

use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TemplateTagController extends Controller
{
    public function __construct(
        protected TemplateDataMapperService $mapper,
        protected TwitchApiService $twitch,
    ) {}

    /**
     * Browse every template tag, showing this account's current values.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user || ! $user->access_token) {
            abort(403, 'User not authenticated with Twitch');
        }

        $twitchData = $this->twitchDataFor($request);

        return Inertia::render('TemplateTagGenerator', [
            'tags' => $this->mapper->tagBrowser($twitchData),
            'liveValues' => $twitchData !== [],
        ]);
    }

    /**
     * Same catalogue as JSON, for the tag list in the template editor.
     */
    public function getAllTags(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        return response()->json([
            'success' => true,
            'tags' => $this->mapper->tagBrowser($this->twitchDataFor($request)),
            // event.* has no catalogue entry (it comes from the EventSub payload
            // at render time), so the editor's autocomplete gets it separately.
            'event_tags' => $this->mapper->getTagCategories()['event']['tags'] ?? [],
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * The account's Twitch snapshot, or an empty array when it cannot be
     * fetched. Callers degrade to the catalogue's static samples rather than
     * failing - the tag list itself does not depend on this data, only the
     * example values beside each tag do.
     */
    private function twitchDataFor(Request $request): array
    {
        $user = $request->user();

        if (! $user?->access_token) {
            return [];
        }

        try {
            return $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::warning('Tag browser could not load Twitch data; falling back to catalogue samples', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
