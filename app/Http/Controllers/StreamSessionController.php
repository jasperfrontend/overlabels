<?php

namespace App\Http\Controllers;

use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StreamSessionController extends Controller
{
    /** Buffer added before the matched stream.online event (or session.started_at fallback). */
    private const int PRE_BUFFER_SECONDS = 300;

    /** Buffer added after the matched stream.offline event (or session.ended_at fallback). */
    private const int POST_BUFFER_SECONDS = 300;

    /** How far from session bounds to look for the anchor stream.online/offline events. */
    private const int ANCHOR_SEARCH_MINUTES = 30;

    /** Max sessions returned per request (most recent first). */
    private const int SESSION_LIMIT = 50;

    /** Max resub messages to surface per session (most recent first). */
    private const int RESUB_MESSAGE_LIMIT = 25;

    /**
     * @throws DateMalformedStringException
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $this->sessionsCache = $this->loadSessionsWithWindows($userId);

        if (empty($this->sessionsCache)) {
            return Inertia::render('dashboard/stream-sessions', ['sessions' => []]);
        }

        $headlines = $this->loadHeadlineAggregates($userId);
        $subTiers = $this->loadSubsByTier($userId);
        $rewards = $this->loadRedemptionsByReward($userId);
        $goals = $this->loadGoals($userId);
        $boundedLists = $this->loadBoundedListEvents($userId);
        $resubMessages = $this->loadResubMessages($userId);

        $payload = array_map(function (array $s) use ($headlines, $subTiers, $rewards, $goals, $boundedLists, $resubMessages) {
            $sid = $s['session_id'];
            $h = $headlines[$sid] ?? $this->emptyHeadline();

            return [
                'session_id' => $sid,
                'started_at' => $s['started_at'],
                'ended_at' => $s['ended_at'],
                'completed' => $s['ended_at'] !== null,
                'duration_seconds' => $s['duration_seconds'],
                'helix_stream_id' => $s['helix_stream_id'],
                'window' => [
                    'start' => $s['window_start'],
                    'end' => $s['window_end'],
                    'pre_buffer_seconds' => self::PRE_BUFFER_SECONDS,
                    'post_buffer_seconds' => self::POST_BUFFER_SECONDS,
                    'anchored_on_eventsub' => [
                        'online' => $s['online_anchor'] !== null,
                        'offline' => $s['offline_anchor'] !== null,
                    ],
                ],
                'anchors' => [
                    'stream_online_at' => $s['online_anchor'],
                    'stream_offline_at' => $s['offline_anchor'],
                ],
                'event_counts' => $h['event_counts'],
                'stats' => [
                    'follows' => ['count' => (int) $h['follows']],
                    'new_subscribers' => [
                        'count' => (int) $h['new_subs'],
                        'by_tier' => $subTiers[$sid] ?? ['1000' => 0, '2000' => 0, '3000' => 0],
                    ],
                    'resubs' => [
                        'count' => (int) $h['resubs'],
                        'total_cumulative_months' => (int) $h['resub_cumulative_months'],
                        'recent_messages' => $resubMessages[$sid] ?? [],
                    ],
                    'gift_subs' => [
                        'count' => (int) $h['gift_events'],
                        'total_subs_gifted' => (int) $h['gift_subs_total'],
                    ],
                    'raids_received' => [
                        'count' => (int) $h['raids'],
                        'total_viewers' => (int) $h['raid_viewers'],
                        'raids' => $boundedLists[$sid]['raids'] ?? [],
                    ],
                    'cheers' => [
                        'count' => (int) $h['cheers'],
                        'total_bits' => (int) $h['cheer_bits'],
                    ],
                    'channel_point_redemptions' => [
                        'count' => (int) $h['redemptions'],
                        'total_cost' => (int) $h['redemption_cost'],
                        'by_reward' => $rewards[$sid] ?? [],
                    ],
                    'polls' => $boundedLists[$sid]['polls'] ?? [],
                    'hype_trains' => $boundedLists[$sid]['hype_trains'] ?? [],
                    'goals' => $goals[$sid] ?? [],
                    'title_history' => $boundedLists[$sid]['title_history'] ?? [],
                ],
            ];
        }, array_values($this->sessionsCache));

        return Inertia::render('dashboard/stream-sessions', [
            'sessions' => $payload,
        ]);
    }

    /**
     * Pull the recent sessions with their EventSub-anchored windows resolved in SQL.
     * Falls back to (started_at, ended_at|now()) when no anchor event exists.
     *
     * @return array<int, array<string, mixed>> keyed by session_id
     * @throws DateMalformedStringException
     */
    private function loadSessionsWithWindows(int $userId): array
    {
        $rows = DB::select("
            WITH base AS (
                SELECT
                    s.id            AS session_id,
                    s.user_id,
                    s.started_at,
                    s.ended_at,
                    s.helix_stream_id,
                    COALESCE(s.ended_at, NOW()) AS effective_end
                FROM stream_sessions s
                WHERE s.user_id = ?
                ORDER BY s.started_at DESC
                LIMIT ?
            )
            SELECT
                b.session_id,
                b.started_at,
                b.ended_at,
                b.helix_stream_id,
                (
                    SELECT MIN(e.twitch_timestamp)
                    FROM twitch_events e
                    WHERE e.user_id = b.user_id
                      AND e.event_type = 'stream.online'
                      AND e.twitch_timestamp BETWEEN
                            b.started_at - (INTERVAL '1 minute' * ?)
                        AND b.started_at + (INTERVAL '1 minute' * ?)
                ) AS online_anchor,
                (
                    SELECT MIN(e.twitch_timestamp)
                    FROM twitch_events e
                    WHERE e.user_id = b.user_id
                      AND e.event_type = 'stream.offline'
                      AND e.twitch_timestamp BETWEEN
                            b.effective_end - (INTERVAL '1 minute' * ?)
                        AND b.effective_end + (INTERVAL '1 minute' * ?)
                ) AS offline_anchor,
                b.effective_end
            FROM base b
        ", [
            $userId,
            self::SESSION_LIMIT,
            self::ANCHOR_SEARCH_MINUTES, self::ANCHOR_SEARCH_MINUTES,
            self::ANCHOR_SEARCH_MINUTES, self::ANCHOR_SEARCH_MINUTES,
        ]);

        $out = [];
        foreach ($rows as $r) {
            $startedAt = $r->started_at;
            $endedAt = $r->ended_at;
            $effectiveEnd = $r->effective_end;
            $onlineAnchor = $r->online_anchor;
            $offlineAnchor = $r->offline_anchor;

            $anchorStart = $onlineAnchor ?? $startedAt;
            $anchorEnd = $offlineAnchor ?? $effectiveEnd;

            $windowStart = new DateTimeImmutable($anchorStart)
                ->modify('-'.self::PRE_BUFFER_SECONDS.' seconds')
                ->format('Y-m-d H:i:s');
            $windowEnd = new DateTimeImmutable($anchorEnd)
                ->modify('+'.self::POST_BUFFER_SECONDS.' seconds')
                ->format('Y-m-d H:i:s');

            $duration = null;
            if ($endedAt !== null) {
                $duration = new DateTimeImmutable($endedAt)->getTimestamp()
                    - new DateTimeImmutable($startedAt)->getTimestamp();
            }

            $out[(int) $r->session_id] = [
                'session_id' => (int) $r->session_id,
                'started_at' => $this->iso($startedAt),
                'ended_at' => $endedAt !== null ? $this->iso($endedAt) : null,
                'duration_seconds' => $duration,
                'helix_stream_id' => $r->helix_stream_id,
                'online_anchor' => $onlineAnchor !== null ? $this->iso($onlineAnchor) : null,
                'offline_anchor' => $offlineAnchor !== null ? $this->iso($offlineAnchor) : null,
                'window_start' => $this->iso($windowStart),
                'window_end' => $this->iso($windowEnd),
                '_window_start_raw' => $windowStart,
                '_window_end_raw' => $windowEnd,
            ];
        }

        return $out;
    }

    /**
     * Build a VALUES-list of (session_id, window_start, window_end) so that every
     * subsequent aggregate query can join against per-session windows in SQL.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @return array{0: string, 1: array<int, mixed>}  [sql_fragment, bindings]
     */
    private function buildWindowsCte(array $sessions): array
    {
        $placeholders = [];
        $bindings = [];
        foreach ($sessions as $s) {
            $placeholders[] = '(?::bigint, ?::timestamp, ?::timestamp)';
            $bindings[] = $s['session_id'];
            $bindings[] = $s['_window_start_raw'];
            $bindings[] = $s['_window_end_raw'];
        }

        $sql = 'windows(session_id, window_start, window_end) AS (VALUES '.implode(',', $placeholders).')';

        return [$sql, $bindings];
    }

    /**
     * One row per session: counts + sums for every headline stat, plus a JSON
     * map of event_type -> count. All aggregation happens server-side.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadHeadlineAggregates(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        // event_counts must be computed in its own CTE: joining it as LATERAL
        // alongside the per-event row stream multiplies every COUNT FILTER by
        // the number of distinct event_types in the window.
        $sql = "
            WITH $cte,
            ec AS (
                SELECT
                    w.session_id,
                    COALESCE(jsonb_object_agg(et.event_type, et.cnt) FILTER (WHERE et.event_type IS NOT NULL), '{}'::jsonb) AS event_counts
                FROM windows w
                LEFT JOIN LATERAL (
                    -- channel.poll.end fires twice per poll (status=completed|terminated, then status=archived).
                    -- Dedupe by poll id so the count reflects unique polls ended, not raw event firings.
                    SELECT event_type,
                           CASE WHEN event_type = 'channel.poll.end'
                                THEN COUNT(DISTINCT event_data->>'id')::int
                                ELSE COUNT(*)::int
                           END AS cnt
                    FROM twitch_events
                    WHERE user_id = ?
                      AND twitch_timestamp >= w.window_start
                      AND twitch_timestamp <= w.window_end
                    GROUP BY event_type
                ) et ON TRUE
                GROUP BY w.session_id
            )
            SELECT
                w.session_id,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.follow') AS follows,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.subscribe'
                                   AND COALESCE((e.event_data->>'is_gift')::bool, false) = false) AS new_subs,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.subscription.message') AS resubs,
                COALESCE(SUM((e.event_data->>'cumulative_months')::int)
                    FILTER (WHERE e.event_type = 'channel.subscription.message'), 0) AS resub_cumulative_months,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.subscription.gift') AS gift_events,
                COALESCE(SUM((e.event_data->>'total')::int)
                    FILTER (WHERE e.event_type = 'channel.subscription.gift'), 0) AS gift_subs_total,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.raid') AS raids,
                COALESCE(SUM((e.event_data->>'viewers')::int)
                    FILTER (WHERE e.event_type = 'channel.raid'), 0) AS raid_viewers,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.cheer') AS cheers,
                COALESCE(SUM((e.event_data->>'bits')::int)
                    FILTER (WHERE e.event_type = 'channel.cheer'), 0) AS cheer_bits,
                COUNT(*) FILTER (WHERE e.event_type = 'channel.channel_points_custom_reward_redemption.add') AS redemptions,
                COALESCE(SUM((e.event_data->'reward'->>'cost')::int)
                    FILTER (WHERE e.event_type = 'channel.channel_points_custom_reward_redemption.add'), 0) AS redemption_cost,
                COALESCE(ec.event_counts, '{}'::jsonb) AS event_counts
            FROM windows w
            LEFT JOIN twitch_events e
                ON e.user_id = ?
                AND e.twitch_timestamp >= w.window_start
                AND e.twitch_timestamp <= w.window_end
            LEFT JOIN ec ON ec.session_id = w.session_id
            GROUP BY w.session_id, ec.event_counts
        ";

        $bindings[] = $userId;
        $bindings[] = $userId;

        $rows = DB::select($sql, $bindings);
        $out = [];
        foreach ($rows as $r) {
            $counts = is_string($r->event_counts) ? json_decode($r->event_counts, true) : $r->event_counts;
            arsort($counts);
            $out[(int) $r->session_id] = [
                'follows' => $r->follows,
                'new_subs' => $r->new_subs,
                'resubs' => $r->resubs,
                'resub_cumulative_months' => $r->resub_cumulative_months,
                'gift_events' => $r->gift_events,
                'gift_subs_total' => $r->gift_subs_total,
                'raids' => $r->raids,
                'raid_viewers' => $r->raid_viewers,
                'cheers' => $r->cheers,
                'cheer_bits' => $r->cheer_bits,
                'redemptions' => $r->redemptions,
                'redemption_cost' => $r->redemption_cost,
                'event_counts' => (object) $counts,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function loadSubsByTier(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        $sql = "
            WITH $cte
            SELECT
                w.session_id,
                COALESCE(e.event_data->>'tier', '1000') AS tier,
                COUNT(*) AS cnt
            FROM windows w
            JOIN twitch_events e
                ON e.user_id = ?
                AND e.twitch_timestamp >= w.window_start
                AND e.twitch_timestamp <= w.window_end
                AND e.event_type = 'channel.subscribe'
                AND COALESCE((e.event_data->>'is_gift')::bool, false) = false
            GROUP BY w.session_id, COALESCE(e.event_data->>'tier', '1000')
        ";
        $bindings[] = $userId;

        $rows = DB::select($sql, $bindings);
        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r->session_id;
            if (! isset($out[$sid])) {
                $out[$sid] = ['1000' => 0, '2000' => 0, '3000' => 0];
            }
            $out[$sid][(string) $r->tier] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * Bounded by the streamer's reward catalogue (typically <30 entries).
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadRedemptionsByReward(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        $sql = "
            WITH $cte
            SELECT
                w.session_id,
                e.event_data->'reward'->>'title' AS title,
                MAX((e.event_data->'reward'->>'cost')::int) AS cost_per,
                COUNT(*) AS cnt,
                SUM((e.event_data->'reward'->>'cost')::int) AS total_cost
            FROM windows w
            JOIN twitch_events e
                ON e.user_id = ?
                AND e.twitch_timestamp >= w.window_start
                AND e.twitch_timestamp <= w.window_end
                AND e.event_type = 'channel.channel_points_custom_reward_redemption.add'
            GROUP BY w.session_id, e.event_data->'reward'->>'title'
            ORDER BY w.session_id, cnt DESC
        ";
        $bindings[] = $userId;

        $rows = DB::select($sql, $bindings);
        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r->session_id;
            $out[$sid][] = [
                'title' => $r->title ?? 'Unknown',
                'count' => (int) $r->cnt,
                'cost_per' => (int) $r->cost_per,
                'total_cost' => (int) $r->total_cost,
            ];
        }

        return $out;
    }

    /**
     * First and last current_amount per goal type per session, derived in SQL via
     * ordered array_agg (same trick used in GpsSessionController).
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadGoals(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        $sql = "
            WITH $cte
            SELECT
                w.session_id,
                COALESCE(e.event_data->>'type', 'unknown') AS goal_type,
                (array_agg((e.event_data->>'current_amount')::int ORDER BY e.twitch_timestamp ASC))[1] AS start_amount,
                (array_agg((e.event_data->>'current_amount')::int ORDER BY e.twitch_timestamp DESC))[1] AS end_amount,
                MAX((e.event_data->>'target_amount')::int) AS target_amount,
                COUNT(*) AS updates
            FROM windows w
            JOIN twitch_events e
                ON e.user_id = ?
                AND e.twitch_timestamp >= w.window_start
                AND e.twitch_timestamp <= w.window_end
                AND e.event_type = 'channel.goal.progress'
            GROUP BY w.session_id, COALESCE(e.event_data->>'type', 'unknown')
        ";
        $bindings[] = $userId;

        $rows = DB::select($sql, $bindings);
        $out = [];
        foreach ($rows as $r) {
            $start = (int) $r->start_amount;
            $end = (int) $r->end_amount;
            $out[(int) $r->session_id][] = [
                'type' => $r->goal_type,
                'start' => $start,
                'end' => $end,
                'delta' => $end - $start,
                'target' => (int) $r->target_amount,
                'updates' => (int) $r->updates,
            ];
        }

        return $out;
    }

    /**
     * Pull the small set of bounded-list event types in one query, then partition
     * in PHP. Excludes follows, cheers, redemptions, and poll.progress on purpose.
     *
     * @return array<int, array<string, array<int, array<string, mixed>>>>
     * @throws DateMalformedStringException
     */
    private function loadBoundedListEvents(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        $sql = "
            WITH $cte
            SELECT
                w.session_id,
                e.event_type,
                e.event_data,
                e.twitch_timestamp
            FROM windows w
            JOIN twitch_events e
                ON e.user_id = ?
                AND e.twitch_timestamp >= w.window_start
                AND e.twitch_timestamp <= w.window_end
                AND e.event_type IN (
                    'channel.update',
                    'channel.raid',
                    'channel.poll.begin',
                    'channel.poll.end',
                    'channel.hype_train.end'
                )
            ORDER BY w.session_id, e.twitch_timestamp
        ";
        $bindings[] = $userId;

        $rows = DB::select($sql, $bindings);

        $out = [];
        $pollsBySession = [];
        foreach ($rows as $r) {
            $sid = (int) $r->session_id;
            $data = is_string($r->event_data) ? json_decode($r->event_data, true) : (array) $r->event_data;
            $ts = $this->iso($r->twitch_timestamp);

            if (! isset($out[$sid])) {
                $out[$sid] = ['title_history' => [], 'raids' => [], 'polls' => [], 'hype_trains' => []];
            }

            switch ($r->event_type) {
                case 'channel.update':
                    $out[$sid]['title_history'][] = [
                        'at' => $ts,
                        'title' => $data['title'] ?? null,
                        'category_name' => $data['category_name'] ?? null,
                        'category_id' => $data['category_id'] ?? null,
                        'language' => $data['language'] ?? null,
                    ];
                    break;

                case 'channel.raid':
                    $out[$sid]['raids'][] = [
                        'from' => $data['from_broadcaster_user_name'] ?? null,
                        'from_login' => $data['from_broadcaster_user_login'] ?? null,
                        'viewers' => (int) ($data['viewers'] ?? 0),
                        'at' => $ts,
                    ];
                    break;

                case 'channel.hype_train.end':
                    $out[$sid]['hype_trains'][] = [
                        'id' => $data['id'] ?? null,
                        'level' => (int) ($data['level'] ?? 0),
                        'total' => (int) ($data['total'] ?? 0),
                        'type' => $data['type'] ?? null,
                        'started_at' => $data['started_at'] ?? null,
                        'ended_at' => $data['ended_at'] ?? null,
                        'top_contributions' => $data['top_contributions'] ?? [],
                    ];
                    break;

                case 'channel.poll.begin':
                case 'channel.poll.end':
                    $pollId = $data['id'] ?? null;
                    if ($pollId === null) {
                        break;
                    }

                    $status = $data['status'] ?? null;
                    $isResolvedEnd = $r->event_type === 'channel.poll.end'
                        && in_array($status, ['completed', 'terminated'], true);
                    $isArchivedEnd = $r->event_type === 'channel.poll.end' && $status === 'archived';
                    $isBegin = $r->event_type === 'channel.poll.begin';

                    // Snapshot priority: resolved end (real votes + final status) > archived end > begin.
                    // We can't assume arrival order - archived may land before completed/terminated.
                    $newPriority = $isResolvedEnd ? 3 : ($isArchivedEnd ? 2 : 1);

                    $existing = $pollsBySession[$sid][$pollId] ?? null;
                    $currentPriority = $existing['_snapshot_priority'] ?? 0;

                    if ($newPriority >= $currentPriority) {
                        $choices = $data['choices'] ?? [];
                        $totalVotes = array_sum(array_map(fn ($c) => (int) ($c['votes'] ?? 0), $choices));
                        $pollsBySession[$sid][$pollId] = [
                            'id' => $pollId,
                            'title' => $data['title'] ?? null,
                            'started_at' => $data['started_at'] ?? null,
                            'ended_at' => $data['ended_at'] ?? null,
                            'status' => $status,
                            'choices' => $choices,
                            'winners' => $data['winners'] ?? [],
                            'total_votes' => $totalVotes,
                            '_snapshot_priority' => $newPriority,
                            '_has_begin' => $existing['_has_begin'] ?? false,
                            '_has_end_resolved' => $existing['_has_end_resolved'] ?? false,
                            '_has_end_archived' => $existing['_has_end_archived'] ?? false,
                        ];
                    }

                    // Always update the lifecycle flags, regardless of which snapshot won.
                    if ($isBegin) {
                        $pollsBySession[$sid][$pollId]['_has_begin'] = true;
                    } elseif ($isResolvedEnd) {
                        $pollsBySession[$sid][$pollId]['_has_end_resolved'] = true;
                    } elseif ($isArchivedEnd) {
                        $pollsBySession[$sid][$pollId]['_has_end_archived'] = true;
                    }
                    break;
            }
        }

        foreach ($pollsBySession as $sid => $polls) {
            $out[$sid]['polls'] = array_values(array_map(function ($p) {
                $hasResolved = $p['_has_end_resolved'];
                $hasArchived = $p['_has_end_archived'];
                $p['truly_finished'] = $hasResolved && $hasArchived;
                $p['lifecycle'] = [
                    'has_begin' => $p['_has_begin'],
                    'has_end_resolved' => $hasResolved,
                    'has_end_archived' => $hasArchived,
                ];
                unset($p['_snapshot_priority'], $p['_has_begin'], $p['_has_end_resolved'], $p['_has_end_archived']);

                return $p;
            }, $polls));
        }

        return $out;
    }

    /**
     * Bounded by RESUB_MESSAGE_LIMIT per session via window function.
     *
     * @return array<int, array<int, array<string, mixed>>>
     * @throws DateMalformedStringException
     */
    private function loadResubMessages(int $userId): array
    {
        $sessions = $this->sessionsCache;
        if (empty($sessions)) {
            return [];
        }

        [$cte, $bindings] = $this->buildWindowsCte($sessions);

        $sql = "
            WITH $cte,
            ranked AS (
                SELECT
                    w.session_id,
                    e.event_data,
                    e.twitch_timestamp,
                    ROW_NUMBER() OVER (
                        PARTITION BY w.session_id
                        ORDER BY e.twitch_timestamp DESC
                    ) AS rn
                FROM windows w
                JOIN twitch_events e
                    ON e.user_id = ?
                    AND e.twitch_timestamp >= w.window_start
                    AND e.twitch_timestamp <= w.window_end
                    AND e.event_type = 'channel.subscription.message'
            )
            SELECT session_id, event_data, twitch_timestamp
            FROM ranked
            WHERE rn <= ?
            ORDER BY session_id, twitch_timestamp DESC
        ";
        $bindings[] = $userId;
        $bindings[] = self::RESUB_MESSAGE_LIMIT;

        $rows = DB::select($sql, $bindings);

        $out = [];
        foreach ($rows as $r) {
            $data = is_string($r->event_data) ? json_decode($r->event_data, true) : (array) $r->event_data;
            $out[(int) $r->session_id][] = [
                'name' => $data['user_name'] ?? null,
                'tier' => $data['tier'] ?? null,
                'cumulative_months' => $data['cumulative_months'] ?? null,
                'streak_months' => $data['streak_months'] ?? null,
                'message' => $data['message']['text'] ?? null,
                'at' => $this->iso($r->twitch_timestamp),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyHeadline(): array
    {
        return [
            'follows' => 0, 'new_subs' => 0, 'resubs' => 0, 'resub_cumulative_months' => 0,
            'gift_events' => 0, 'gift_subs_total' => 0, 'raids' => 0, 'raid_viewers' => 0,
            'cheers' => 0, 'cheer_bits' => 0, 'redemptions' => 0, 'redemption_cost' => 0,
            'event_counts' => (object) [],
        ];
    }

    /**
     * @throws DateMalformedStringException
     */
    private function iso(string $sqlTimestamp): string
    {
        // Postgres returns 'YYYY-MM-DD HH:MM:SS' without TZ. Treat as UTC (DB convention).
        return new DateTimeImmutable($sqlTimestamp.' UTC')->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Side-channel cache so secondary aggregates can re-use the resolved windows
     * without re-running the anchor subqueries.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $sessionsCache = [];
}
