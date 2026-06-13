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
