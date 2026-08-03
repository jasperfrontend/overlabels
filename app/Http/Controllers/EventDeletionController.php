<?php

namespace App\Http\Controllers;

use App\Services\UnifiedEventFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bulk removal of rows from the unified recent-events feed.
 *
 * Deliberately narrow: this cleans up the list and nothing else. Deleting a
 * donation event does not roll back the counters it incremented, because those
 * controls are running totals rather than a projection of the event tables.
 * Users reset them with the per-integration seed actions.
 *
 * Session-authed only. The token-authed /events/feed shares the same table
 * component but does not get this - overlay tokens live in an OBS browser
 * source URL, and a destructive third ability on them is a bad trade.
 */
class EventDeletionController extends Controller
{
    public function __construct(
        private readonly UnifiedEventFeedService $eventFeed,
    ) {}

    /**
     * POST /events/bulk-delete
     *
     * Two modes:
     *  - `all: true` deletes everything matching the filters on the query
     *    string (the same ones the feed was rendered with).
     *  - otherwise `events` carries the explicitly picked {source, id} pairs.
     *
     * Ids collide between twitch_events and external_events, so a pair without
     * its source is ambiguous and would delete the wrong row.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'events' => ['sometimes', 'array', 'max:500'],
            'events.*.source' => ['required_with:events', 'string', 'max:50'],
            'events.*.id' => ['required_with:events', 'integer', 'min:1'],
        ]);

        $userId = $request->user()->id;

        if ($validated['all'] ?? false) {
            // Filters are re-read from the request rather than accepted as a
            // client-supplied id list, so "delete all matching" can never widen
            // beyond what the same filters would have displayed.
            $filters = $this->eventFeed->normalizeFilters($request);
            $deleted = $this->eventFeed->deleteMatching($userId, $filters);
        } else {
            $pairs = $validated['events'] ?? [];

            if (empty($pairs)) {
                return back()->with('message', 'No events were selected.')->with('type', 'warning');
            }

            $twitchIds = [];
            $externalIds = [];
            foreach ($pairs as $pair) {
                if ($pair['source'] === 'twitch') {
                    $twitchIds[] = (int) $pair['id'];
                } else {
                    $externalIds[] = (int) $pair['id'];
                }
            }

            $deleted = $this->eventFeed->deleteByIds($userId, $twitchIds, $externalIds);
        }

        if ($deleted === 0) {
            return back()->with('message', 'Nothing was deleted.')->with('type', 'warning');
        }

        return back()
            ->with('message', $deleted === 1 ? 'Deleted 1 event.' : "Deleted {$deleted} events.")
            ->with('type', 'success');
    }
}
