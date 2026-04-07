<?php

namespace App\Services;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StreamSessionService
{
    /**
     * Mapping of Twitch event types to stream control keys.
     */
    public const EVENT_CONTROL_MAP = [
        'channel.follow' => 'follows_this_stream',
        'channel.subscribe' => 'subs_this_stream',
        'channel.subscription.gift' => 'gift_subs_this_stream',
        'channel.subscription.message' => 'resubs_this_stream',
        'channel.raid' => 'raids_this_stream',
        'channel.channel_points_custom_reward_redemption.add' => 'redemptions_this_stream',
    ];

    /**
     * Control presets users can add to their overlays.
     */
    public const CONTROL_PRESETS = [
        ['key' => 'follows_this_stream', 'type' => 'counter', 'label' => 'Followers This Stream', 'value' => '0'],
        ['key' => 'subs_this_stream', 'type' => 'counter', 'label' => 'Subs This Stream', 'value' => '0'],
        ['key' => 'gift_subs_this_stream', 'type' => 'counter', 'label' => 'Gift Subs This Stream', 'value' => '0'],
        ['key' => 'resubs_this_stream', 'type' => 'counter', 'label' => 'Resubs This Stream', 'value' => '0'],
        ['key' => 'raids_this_stream', 'type' => 'counter', 'label' => 'Raids This Stream', 'value' => '0'],
        ['key' => 'redemptions_this_stream', 'type' => 'counter', 'label' => 'Redemptions This Stream', 'value' => '0'],
    ];

    /**
     * Open a new stream session, reset controls.
     * Broadcasting is handled by StreamStateMachineService.
     */
    public function openSession(User $user): StreamSession
    {
        // Close any lingering open sessions (e.g. missed offline event)
        StreamSession::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $session = StreamSession::create([
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        $this->resetControls($user);

        Log::info("Stream session opened for user {$user->id}", [
            'session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Close the active stream session.
     * Broadcasting is handled by StreamStateMachineService.
     */
    public function closeSession(User $user): void
    {
        $closed = StreamSession::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        Log::info("Stream session closed for user {$user->id}", [
            'sessions_closed' => $closed,
        ]);
    }

    /**
     * Handle a countable Twitch event: increment the matching control if user has one.
     */
    public function handleEvent(User $user, string $eventType): void
    {
        $controlKey = self::EVENT_CONTROL_MAP[$eventType] ?? null;

        if (! $controlKey) {
            return;
        }

        // Only increment if user is confidently live
        $state = StreamState::forUser($user);
        if (! $state->isConfidentlyLive()) {
            return;
        }

        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'twitch')
            ->where('key', $controlKey)
            ->where('source_managed', true)
            ->with('template')
            ->get();

        if ($controls->isEmpty()) {
            return;
        }

        foreach ($controls as $control) {
            $step = (float) ($control->config['step'] ?? 1);
            $current = (float) ($control->value ?? 0);
            $newValue = (string) ($current + $step);

            $control->update(['value' => $newValue]);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                $newValue,
                $user->twitch_id,
            );

        }

        Log::info("Incremented twitch control {$controlKey} for user {$user->id}");
    }

    /**
     * Reset all twitch source controls to their reset_value (or 0) and broadcast each.
     */
    private function resetControls(User $user): void
    {
        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'twitch')
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $resetValue = (string) ($control->config['reset_value'] ?? 0);
            $control->update(['value' => $resetValue]);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                $resetValue,
                $user->twitch_id,
            );

        }

        Log::info("Reset twitch controls for user {$user->id}", [
            'count' => $controls->count(),
        ]);
    }

    /**
     * Check if a user is currently live (uses confidence-based state machine).
     */
    public static function isLive(User $user): bool
    {
        return StreamState::forUser($user)->isConfidentlyLive();
    }
}
