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
     * @return array{search: string, source: string, event_type: string, range: string}
     */
    public function normalizeFilters(Request $request): array
    {
        $allowedRanges = ['all', 'hour', '24h', '7d', '30d'];
        $range = (string) $request->query('range', 'all');
        if (! in_array($range, $allowedRanges, true)) {
            $range = 'all';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'source' => trim((string) $request->query('source', '')),
            'event_type' => trim((string) $request->query('event_type', '')),
            'range' => $range,
        ];
    }

    /**
     * @param  array{search: string, source: string, event_type: string, range: string}  $filters
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
     * @param  array{search: string, source: string, event_type: string, range: string}  $filters
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
            $twitch->whereRaw('event_data::text ILIKE ?', [$like]);
            $external->whereRaw('normalized_payload::text ILIKE ?', [$like]);
        }
    }
}
