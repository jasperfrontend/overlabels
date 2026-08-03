<?php

namespace App\Services;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\TwitchEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Unified twitch_events + external_events feed: filter normalization, facets
 * and pagination. Extracted from DashboardController so the session dashboard
 * pages and the token-authed /api/events feed serve identical data from one
 * query path, keyed by an explicit user id.
 */
class UnifiedEventFeedService
{
    /**
     * @return array{search: string, source: string, event_type: string, hidden_types: array<int, string>, range: string}
     */
    public function normalizeFilters(Request $request): array
    {
        $allowedRanges = ['all', 'hour', '24h', '7d', '30d'];
        $range = (string) $request->query('range', 'all');
        if (! in_array($range, $allowedRanges, true)) {
            $range = 'all';
        }

        // Subtractive event-type filter: a comma-separated list of event types
        // the viewer has chosen to hide. Everything not listed stays visible,
        // so event types Twitch adds later show by default. Capped so a crafted
        // query string can't balloon the WHERE NOT IN clause.
        $hiddenRaw = (string) $request->query('hidden_types', '');
        $hiddenTypes = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $hiddenRaw)),
            fn (string $t): bool => $t !== '',
        )));
        $hiddenTypes = array_slice($hiddenTypes, 0, 100);

        return [
            'search' => trim((string) $request->query('search', '')),
            'source' => trim((string) $request->query('source', '')),
            'event_type' => trim((string) $request->query('event_type', '')),
            'hidden_types' => $hiddenTypes,
            'range' => $range,
        ];
    }

    /**
     * @param  array{search: string, source: string, event_type: string, hidden_types: array<int, string>, range: string}  $filters
     */
    public function paginate(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $twitch = DB::table('twitch_events')
            ->where('user_id', $userId)
            ->selectRaw("id, 'twitch' AS source, event_type, created_at, event_data::text AS event_data_json, NULL::text AS normalized_payload_json");

        $external = DB::table('external_events')
            ->where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->selectRaw('id, service AS source, event_type, created_at, NULL::text AS event_data_json, normalized_payload::text AS normalized_payload_json');

        $this->applyFilters($twitch, $external, $filters);

        $combined = DB::query()
            ->fromSub($twitch->unionAll($external), 'events')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $combined->paginate($perPage);

        $paginator->getCollection()->transform(function (object $row): array {
            return [
                'id' => (int) $row->id,
                'source' => $row->source,
                'event_type' => $row->event_type,
                'label' => $row->source === 'twitch'
                    ? (EventTemplateMapping::EVENT_TYPES[$row->event_type] ?? $row->event_type)
                    : $row->event_type,
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
                'event_data' => $row->event_data_json ? json_decode($row->event_data_json, true) : null,
                'normalized_payload' => $row->normalized_payload_json ? json_decode($row->normalized_payload_json, true) : null,
            ];
        });

        return $paginator;
    }

    /**
     * Distinct sources + event types available to this user for populating filter dropdowns.
     *
     * @return array{sources: array<int, string>, event_types: array<int, string>}
     */
    public function facets(int $userId): array
    {
        $externalSources = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->distinct()
            ->pluck('service')
            ->all();

        $hasTwitch = TwitchEvent::where('user_id', $userId)->exists();

        $sources = $externalSources;
        if ($hasTwitch) {
            array_unshift($sources, 'twitch');
        }
        $sources = array_values(array_unique($sources));
        sort($sources);

        $twitchTypes = TwitchEvent::where('user_id', $userId)->distinct()->pluck('event_type')->all();
        $externalTypes = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->distinct()
            ->pluck('event_type')
            ->all();
        $eventTypes = array_values(array_unique(array_merge($twitchTypes, $externalTypes)));
        sort($eventTypes);

        return [
            'sources' => $sources,
            'event_types' => $eventTypes,
        ];
    }

    /**
     * Delete explicitly picked rows. Ids are per-table and collide across the
     * two sources, so callers pass them pre-split - `source === 'twitch'` picks
     * twitch_events, every other source is a service name in external_events.
     *
     * The user_id scope is the authorization boundary, not a nicety:
     * twitch_events.user_id is nullable (events for unknown broadcasters), so a
     * bare whereIn would reach rows that belong to nobody.
     *
     * @param  array<int, int>  $twitchIds
     * @param  array<int, int>  $externalIds
     * @return int rows deleted
     */
    public function deleteByIds(int $userId, array $twitchIds, array $externalIds): int
    {
        return DB::transaction(function () use ($userId, $twitchIds, $externalIds): int {
            $deleted = 0;

            if (! empty($twitchIds)) {
                $deleted += DB::table('twitch_events')
                    ->where('user_id', $userId)
                    ->whereIn('id', $twitchIds)
                    ->delete();
            }

            if (! empty($externalIds)) {
                $deleted += DB::table('external_events')
                    ->where('user_id', $userId)
                    // GPS rows never surface in this feed, so they can never be
                    // picked from it either - guards a spoofed source value.
                    ->where('service', '!=', 'gps')
                    ->whereIn('id', $externalIds)
                    ->delete();
            }

            return $deleted;
        });
    }

    /**
     * Delete every row matching the current feed filters, re-derived server-side
     * rather than trusting a client-supplied id list. This is what backs
     * "select all N matching these filters" - the filter UI doubles as the
     * selector, so the user decides what counts as junk per cleanup.
     *
     * @param  array{search: string, source: string, event_type: string, hidden_types: array<int, string>, range: string}  $filters
     * @return int rows deleted
     */
    public function deleteMatching(int $userId, array $filters): int
    {
        return DB::transaction(function () use ($userId, $filters): int {
            $twitch = DB::table('twitch_events')->where('user_id', $userId);
            $external = DB::table('external_events')
                ->where('user_id', $userId)
                ->where('service', '!=', 'gps');

            // Same filter pass the read query uses, so what gets deleted is
            // exactly what the page was showing. A source filter turns the other
            // builder into `1 = 0`, which deletes nothing.
            $this->applyFilters($twitch, $external, $filters);

            return $twitch->delete() + $external->delete();
        });
    }

    /**
     * @param  array{search: string, source: string, event_type: string, hidden_types: array<int, string>, range: string}  $filters
     */
    private function applyFilters(QueryBuilder $twitch, QueryBuilder $external, array $filters): void
    {
        if ($filters['source'] !== '') {
            if ($filters['source'] === 'twitch') {
                $external->whereRaw('1 = 0');
            } else {
                $twitch->whereRaw('1 = 0');
                $external->where('service', $filters['source']);
            }
        }

        if ($filters['event_type'] !== '') {
            $twitch->where('event_type', $filters['event_type']);
            $external->where('event_type', $filters['event_type']);
        }

        // Hide the viewer's opted-out event types from both sources. A hidden
        // Twitch type simply never matches external rows and vice versa, so
        // applying the same exclusion to both subqueries is safe.
        if (! empty($filters['hidden_types'])) {
            $twitch->whereNotIn('event_type', $filters['hidden_types']);
            $external->whereNotIn('event_type', $filters['hidden_types']);
        }

        $since = match ($filters['range']) {
            'hour' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            default => null,
        };
        if ($since !== null) {
            $twitch->where('created_at', '>=', $since);
            $external->where('created_at', '>=', $since);
        }

        if ($filters['search'] !== '') {
            $like = '%'.addcslashes($filters['search'], '%_\\').'%';

            // The event type is searched alongside the payload, because the
            // words people read in the feed often live only in the type. A poll
            // payload never contains the string "poll" - so searching "poll"
            // used to find nothing, while "po" found polls by accident, via the
            // "po" in the payload's `channel_points_voting` key.
            //
            // Both branches must stay grouped: an ungrouped orWhere would climb
            // out past the user_id scope and the gps exclusion, and this same
            // method backs deleteMatching().
            $twitch->where(function (QueryBuilder $q) use ($like): void {
                $q->whereRaw('event_data::text ILIKE ?', [$like])
                    ->orWhere('event_type', 'ilike', $like);
            });
            $external->where(function (QueryBuilder $q) use ($like): void {
                $q->whereRaw('normalized_payload::text ILIKE ?', [$like])
                    ->orWhere('event_type', 'ilike', $like);
            });
        }
    }
}
