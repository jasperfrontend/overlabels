<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Per-user alert/control/stream/template fanout. Authorize subscription only
 * for the channel's owning streamer (logged-in dashboard). Overlays without a
 * session use the dedicated `POST /api/overlay/broadcasting/auth` endpoint,
 * which validates an OverlayAccessToken and signs the auth response directly.
 */
Broadcast::channel('alerts.{twitchId}', function ($user, $twitchId) {
    return (string) $user->twitch_id === (string) $twitchId;
});

/**
 * Per-user Twitch EventSub fanout. Replaces the old global `twitch-events`
 * channel which leaked every streamer's events to anyone connected to Reverb.
 */
Broadcast::channel('twitch-events.{twitchId}', function ($user, $twitchId) {
    return (string) $user->twitch_id === (string) $twitchId;
});
