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
    public const array EVENT_CONTROL_MAP = [
        'channel.follow' => 'follows_this_stream',
        'channel.subscribe' => 'subs_this_stream',
        'channel.subscription.gift' => 'gift_subs_this_stream',
        'channel.subscription.message' => 'resubs_this_stream',
        'channel.raid' => 'raids_this_stream',
        'channel.channel_points_custom_reward_redemption.add' => 'redemptions_this_stream',
        'channel.cheer' => 'cheers_this_stream',
    ];

    /**
     * Control presets users can add to their overlays.
     */
    public const array CONTROL_PRESETS = [
        ['key' => 'follows_this_stream', 'type' => 'counter', 'label' => 'Followers This Stream', 'value' => '0'],
        ['key' => 'subs_this_stream', 'type' => 'counter', 'label' => 'Subs This Stream', 'value' => '0'],
        ['key' => 'gift_subs_this_stream', 'type' => 'counter', 'label' => 'Gift Subs This Stream', 'value' => '0'],
        ['key' => 'resubs_this_stream', 'type' => 'counter', 'label' => 'Resubs This Stream', 'value' => '0'],
        ['key' => 'raids_this_stream', 'type' => 'counter', 'label' => 'Raids This Stream', 'value' => '0'],
        ['key' => 'redemptions_this_stream', 'type' => 'counter', 'label' => 'Redemptions This Stream', 'value' => '0'],
        ['key' => 'cheers_this_stream', 'type' => 'counter', 'label' => 'Cheers This Stream', 'value' => '0'],
        ['key' => 'bits_this_stream', 'type' => 'number', 'label' => 'Bits This Stream (total)', 'value' => '0'],
        ['key' => 'latest_cheerer_name', 'type' => 'text', 'label' => 'Latest Cheerer Name', 'value' => ''],
        ['key' => 'latest_cheer_amount', 'type' => 'number', 'label' => 'Latest Cheer Amount (bits)', 'value' => '0'],
        ['key' => 'latest_cheer_message', 'type' => 'text', 'label' => 'Latest Cheer Message', 'value' => ''],
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
     * For channel.cheer, also accumulates bits and records latest-cheer details.
     */
    public function handleEvent(User $user, string $eventType, array $event = []): void
    {
        $controlKey = self::EVENT_CONTROL_MAP[$eventType] ?? null;
        $isCheer = $eventType === 'channel.cheer';

        if (! $controlKey && ! $isCheer) {
            return;
        }

        // Only apply if user is confidently live
        $state = StreamState::forUser($user);
        if (! $state->isConfidentlyLive()) {
            return;
        }

        if ($controlKey) {
            $this->applyTwitchControl($user, $controlKey, function (OverlayControl $control) {
                $step = (float) ($control->config['step'] ?? 1);
                $current = (float) ($control->value ?? 0);

                return (string) ($current + $step);
            });

            Log::info("Incremented twitch control {$controlKey} for user {$user->id}");
        }

        if ($isCheer) {
            $bits = (int) ($event['bits'] ?? 0);
            $cheererName = ($event['is_anonymous'] ?? false)
                ? 'Anonymous'
                : ($event['user_name'] ?? 'Anonymous');
            $message = (string) ($event['message'] ?? '');

            $this->applyTwitchControl($user, 'bits_this_stream', function (OverlayControl $control) use ($bits) {
                $current = (float) ($control->value ?? 0);

                return (string) ($current + $bits);
            });
            $this->applyTwitchControl($user, 'latest_cheerer_name', fn () => $cheererName);
            $this->applyTwitchControl($user, 'latest_cheer_amount', fn () => (string) $bits);
            $this->applyTwitchControl($user, 'latest_cheer_message', fn () => $message);
        }
    }

    /**
     * Update every twitch source_managed control matching this key, then broadcast each change.
     * The transformer receives the control and returns the new stringified value.
     */
    private function applyTwitchControl(User $user, string $key, callable $transform): void
    {
        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'twitch')
            ->where('key', $key)
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $newValue = (string) $transform($control);
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
