<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\Update;
use App\Services\BroadcastMeter;
use App\Services\EventMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $userAlertTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->alert()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $userStaticTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->static()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $communityTemplates = OverlayTemplate::where('owner_id', '!=', $user->id)
            ->where('is_public', true)
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $userRecentEvents = $this->mergeRecentEvents($user->id, 5);

        $recentUpdates = Update::published()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return Inertia::render('dashboard/index', [
            'userId' => $user->id,
            'userAlertTemplates' => $userAlertTemplates,
            'userStaticTemplates' => $userStaticTemplates,
            'communityTemplates' => $communityTemplates,
            'userRecentEvents' => $userRecentEvents,
            'recentUpdates' => $recentUpdates,
            'needsOnboarding' => $request->session()->pull('preview_onboarding', false) || (! $user->isOnboarded() && ! $user->hasAlertMappings()),
            'twitchId' => $user->twitch_id,
            'usage' => config('metering.meter_mode', 'both') === 'broadcast'
                ? ($user->twitch_id ? app(BroadcastMeter::class)->summaryFor((string) $user->twitch_id) : null)
                : app(EventMeter::class)->summaryFor($user->id),
        ]);
    }

    public function recentActivity(Request $request): Response
    {
        $user = $request->user();

        $recentTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->with('owner:id,name,avatar')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $filters = $this->normalizeEventFilters($request);
        $paginator = $this->paginateUnifiedEvents($user->id, $filters, 20);
        $facets = $this->eventFilterFacets($user->id);

        return Inertia::render('dashboard/recents', [
            'recentTemplates' => $recentTemplates,
            'recentEvents' => $paginator,
            'filters' => $filters,
            'facets' => $facets,
        ]);
    }

    public function recentActivityDashboard(Request $request): Response
    {
        $user = $request->user();

        $recentTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->with('owner:id,name,avatar')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $recentEvents = $this->mergeRecentEvents($user->id, 30);

        return Inertia::render('dashboard/index', [
            'recentTemplates' => $recentTemplates,
            'recentEvents' => $recentEvents,
        ]);
    }

    public function recentEvents(Request $request): Response
    {
        $user = $request->user();

        $filters = $this->normalizeEventFilters($request);
        $paginator = $this->paginateUnifiedEvents($user->id, $filters, 25);
        $facets = $this->eventFilterFacets($user->id);

        return Inertia::render('dashboard/events', [
            'events' => $paginator,
            'filters' => $filters,
            'facets' => $facets,
        ]);
    }

    /**
     * @return array{search: string, source: string, event_type: string, range: string}
     */
    private function normalizeEventFilters(Request $request): array
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
    private function paginateUnifiedEvents(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        $twitch = DB::table('twitch_events')
            ->where('user_id', $userId)
            ->selectRaw("id, 'twitch' AS source, event_type, created_at, event_data::text AS event_data_json, NULL::text AS normalized_payload_json");

        $external = DB::table('external_events')
            ->where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->selectRaw('id, service AS source, event_type, created_at, NULL::text AS event_data_json, normalized_payload::text AS normalized_payload_json');

        $this->applyEventFilters($twitch, $external, $filters);

        $combined = DB::query()
            ->fromSub($twitch->unionAll($external), 'events')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $combined->paginate($perPage)->withQueryString();

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
     * @param  array{search: string, source: string, event_type: string, range: string}  $filters
     */
    private function applyEventFilters(QueryBuilder $twitch, QueryBuilder $external, array $filters): void
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

    /**
     * Distinct sources + event types available to this user for populating filter dropdowns.
     *
     * @return array{sources: array<int, string>, event_types: array<int, string>}
     */
    private function eventFilterFacets(int $userId): array
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
     * Merge Twitch and external events into a unified shape, sorted newest-first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergeRecentEvents(int $userId, int $limit): array
    {
        $twitchEvents = TwitchEvent::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TwitchEvent $e) => [
                'id' => $e->id,
                'source' => 'twitch',
                'event_type' => $e->event_type,
                'label' => EventTemplateMapping::EVENT_TYPES[$e->event_type] ?? $e->event_type,
                'created_at' => $e->created_at->toIso8601String(),
                'event_data' => $e->event_data,
                'normalized_payload' => null,
            ]);

        $externalEvents = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ExternalEvent $e) => [
                'id' => $e->id,
                'source' => $e->service,
                'event_type' => $e->event_type,
                'created_at' => $e->created_at->toIso8601String(),
                'event_data' => null,
                'normalized_payload' => $e->normalized_payload,
            ]);

        return $twitchEvents
            ->concat($externalEvents)
            ->sortByDesc('created_at')
            ->values()
            ->take($limit)
            ->all();
    }
}
