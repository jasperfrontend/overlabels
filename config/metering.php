<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcast metering
    |--------------------------------------------------------------------------
    |
    | Reverb fan-out is the one defacto resource limit in Overlabels: everything
    | else is free, but every real-time push to a user's overlays costs CPU and
    | bandwidth on the WebSocket server. We meter outbound broadcasts per user so
    | a free tier can be capped and heavy users nudged toward a paid plan.
    |
    | This is OBSERVE-ONLY today: we count and display usage, but never suppress
    | a broadcast. Set a number on `free_monthly_broadcasts` once real per-user
    | data exists; leaving it null means "no limit shown, just the running count".
    |
    */

    // Master switch. When false the metered broadcaster still broadcasts, it
    // just skips counting. Flip BROADCAST_CONNECTION back to `reverb` for a
    // full kill-switch that bypasses the decorator entirely.
    'enabled' => env('METERING_ENABLED', true),

    // Which meter feeds the displayed usage and which counters keep writing.
    // 'both' (default): write BOTH the legacy broadcast counter (kept as an
    // internal verification signal while we collapse the GPS fan-out) AND the
    // input-event counter, and DISPLAY the input count. 'input': stop writing
    // the broadcast counter. 'broadcast': legacy output-only behaviour.
    // Inputs are immune to broadcast fan-out, so pricing is set against them.
    'meter_mode' => env('METERING_MODE', 'both'),

    // Free-tier monthly ceiling in INPUT EVENTS (pings / donations / twitch
    // events). null = observe-only. This is the number to set pricing against,
    // since one inbound event is one inbound event regardless of overlay count.
    'free_monthly_events' => env('METERING_FREE_MONTHLY_EVENTS'),

    // Redis connection backing the input-event counters. Falls back to
    // redis_connection when unset.
    'event_redis_connection' => env('METERING_EVENT_REDIS_CONNECTION'),

    // Free-tier monthly ceiling, in broadcasts. null = observe-only (no cap,
    // no percentage, just the running total). A number turns on the progress
    // UI. Enforcement (suppressing over-limit broadcasts) is a later phase.
    'free_monthly_broadcasts' => env('METERING_FREE_MONTHLY_BROADCASTS'),

    // Which per-user channel families count toward a user's quota. Each of
    // these embeds the owner's twitch_id as the first segment, which is how we
    // attribute a broadcast back to a user. Public/global channels
    // (app-updates, bot-channels, map.{slug}) and the niche gamejam.{id} feed
    // are intentionally excluded - they are not "overlay updates".
    'channels' => ['alerts', 'twitch-events', 'lists'],

    // Redis connection backing the counters. Counters are month-keyed and
    // expire on their own (see ttl_days), so no scheduled reset is needed.
    'redis_connection' => env('METERING_REDIS_CONNECTION', 'default'),

    // How long a month's counter lives after last write. Comfortably past the
    // month boundary so the Usage page can still show recent history.
    'ttl_days' => (int) env('METERING_TTL_DAYS', 70),

];
