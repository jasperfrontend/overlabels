<?php

namespace App\Services;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
     * Control keys reset to their reset_value at the start of every stream.
     *
     * Scoped by key rather than by source on purpose. The `latest_cheer*` presets
     * also carry source='twitch', but they are most-recent values, not per-stream
     * tallies, and every equivalent on the five donation services persists across
     * streams. Resetting them was collateral from this filter predating them, and
     * it broke the bits/donation parity those presets were added to provide.
     *
     * A key belongs here only if its label promises per-stream scope.
     */
    public const array PER_STREAM_CONTROL_KEYS = [
        'follows_this_stream',
        'subs_this_stream',
        'gift_subs_this_stream',
        'resubs_this_stream',
        'raids_this_stream',
        'redemptions_this_stream',
        'cheers_this_stream',
        'bits_this_stream',
        'chat_messages_this_stream',
        'unique_chatters_this_stream',
    ];

    /**
     * How long the per-stream unique-chatter set survives without a write.
     * Longer than any plausible stream, because the authoritative clear is the
     * go-live reset in resetControls(), not expiry.
     */
    private const int CHATTER_SET_TTL = 43200; // 12 hours

    /**
     * Ceiling on logins tracked for unique_chatters_this_stream. A real 86k-viewer
     * stream produced ~134 messages/minute, so this is unreachable in practice; it
     * exists so the cached set cannot grow without bound.
     */
    private const int MAX_TRACKED_CHATTERS = 50000;

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
        // The all-time pair, deliberately mirroring donations_received /
        // total_received on every donation driver. Not on
        // PER_STREAM_CONTROL_KEYS, and not gated on stream state - see
        // handleEvent(). Pairing one of these with its This Stream twin is what
        // makes "bits tonight vs bits ever" a progress bar.
        ['key' => 'cheers_received', 'type' => 'counter', 'label' => 'Cheers Received (all time)', 'value' => '0'],
        ['key' => 'bits_received', 'type' => 'number', 'label' => 'Bits Received (all time)', 'value' => '0'],
        ['key' => 'latest_cheerer_name', 'type' => 'text', 'label' => 'Latest Cheerer Name', 'value' => ''],
        ['key' => 'latest_cheer_amount', 'type' => 'number', 'label' => 'Latest Cheer Amount (bits)', 'value' => '0'],
        ['key' => 'latest_cheer_message', 'type' => 'text', 'label' => 'Latest Cheer Message', 'value' => ''],
        ['key' => 'chat_messages_this_stream', 'type' => 'counter', 'label' => 'Chat Messages This Stream', 'value' => '0'],
        ['key' => 'unique_chatters_this_stream', 'type' => 'counter', 'label' => 'Unique Chatters This Stream', 'value' => '0'],
        ['key' => 'latest_chatter_name', 'type' => 'text', 'label' => 'Latest Chatter Name', 'value' => ''],
        ['key' => 'latest_chat_message', 'type' => 'text', 'label' => 'Latest Chat Message', 'value' => ''],
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
     *
     * Two gates, not one. The all-time totals and the latest_cheer* trio are
     * applied whether or not the channel is live, matching how every donation
     * service behaves. Only the `*_this_stream` keys require a confident live
     * state.
     */
    public function handleEvent(User $user, string $eventType, array $event = []): void
    {
        $controlKey = self::EVENT_CONTROL_MAP[$eventType] ?? null;
        $isCheer = $eventType === 'channel.cheer';

        if (! $controlKey && ! $isCheer) {
            return;
        }

        $bits = $isCheer ? (int) ($event['bits'] ?? 0) : 0;

        // Everything that is not per-stream is applied BEFORE the live gate.
        //
        // A viewer can cheer in offline chat, and no external donation driver
        // consults stream state before recording a tip. The all-time totals are
        // the Twitch equivalent of donations_received / total_received, and the
        // latest_cheer* trio is the equivalent of the latest_donor_* values on
        // those same services - none of which go quiet when you stop streaming.
        // A cheer that arrives offline is still the latest cheer.
        //
        // Everything past the gate below is per-stream and stays strictly
        // live-only. That is the whole point of the separation: one set answers
        // "ever", the other answers "tonight", and comparing them is only
        // meaningful while they keep meaning different things.
        if ($isCheer) {
            $cheererName = ($event['is_anonymous'] ?? false)
                ? 'Anonymous'
                : ($event['user_name'] ?? 'Anonymous');
            $message = (string) ($event['message'] ?? '');

            $this->applyTwitchControl($user, 'cheers_received', function (OverlayControl $control) {
                $step = (float) ($control->config['step'] ?? 1);

                return (string) ((float) ($control->value ?? 0) + $step);
            });
            $this->applyTwitchControl($user, 'bits_received', function (OverlayControl $control) use ($bits) {
                return (string) ((float) ($control->value ?? 0) + $bits);
            });
            $this->applyTwitchControl($user, 'latest_cheerer_name', fn () => $cheererName);
            $this->applyTwitchControl($user, 'latest_cheer_amount', fn () => (string) $bits);
            $this->applyTwitchControl($user, 'latest_cheer_message', fn () => $message);
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
            $this->applyTwitchControl($user, 'bits_this_stream', function (OverlayControl $control) use ($bits) {
                $current = (float) ($control->value ?? 0);

                return (string) ($current + $bits);
            });
        }
    }

    /**
     * Apply one aggregated chat summary from the bot.
     *
     * The bot buffers chat in memory per channel and POSTs ONE summary every
     * 30-60s rather than one request per message, so Laravel sees ~60-120
     * requests per hour per channel instead of thousands.
     *
     * Counts are NATIVE-ONLY by contract: the bot excludes Shared Chat messages
     * duplicated in from another channel, so a collab cannot inflate your
     * numbers. The feed therefore shows more messages than the counter counts.
     *
     * The overlay's own IRC connection is display-only and never feeds these -
     * overlays do not phone home.
     *
     * @param  list<string>  $chatters  Distinct logins seen in this window.
     * @return array{applied: bool, unique_chatters: int|null}
     */
    public function applyChatSummary(
        User $user,
        int $messageCount,
        array $chatters = [],
        ?string $latestChatterName = null,
        ?string $latestChatMessage = null,
    ): array {
        // "This stream" is meaningless while offline, so the whole summary is
        // dropped rather than gated per key. This does NOT mirror the cheer
        // handling above, which stopped gating its latest_* trio: a cheer is a
        // discrete event Twitch delivers whenever it happens, whereas this is a
        // windowed count that only means anything between a stream.online and a
        // stream.offline. Returning applied:false rather than erroring is what
        // stops the bot retrying a summary there is nothing to do with.
        if (! StreamState::forUser($user)->isConfidentlyLive()) {
            return ['applied' => false, 'unique_chatters' => null];
        }

        if ($messageCount > 0) {
            $this->applyTwitchControl(
                $user,
                'chat_messages_this_stream',
                fn (OverlayControl $control) => (string) ((float) ($control->value ?? 0) + $messageCount),
            );
        }

        $uniqueCount = $this->mergeChatters($user, $chatters);

        if ($uniqueCount !== null) {
            $this->applyTwitchControl(
                $user,
                'unique_chatters_this_stream',
                // Only ever moves upward. The chatter set lives in the cache, so
                // a cache flush mid-stream restarts it from empty; without this
                // guard the counter would visibly count DOWN on stream. It
                // resumes climbing once the fresh set overtakes the stored peak.
                fn (OverlayControl $control) => (string) max((float) ($control->value ?? 0), $uniqueCount),
            );
        }

        // Stored raw, exactly like latest_cheer_message and the donation message
        // controls. strip_tags() is deliberately NOT used: it eats from `<` to
        // the next `>` or end of string, so "i <3 you" would be truncated to
        // "i ". The render pass is what makes this safe - control values are
        // substituted once and entity-encoded, and defuseBrackets() stops a
        // chatter's literal [[[c:...]]] from being resolved.
        if ($latestChatterName !== null && $latestChatterName !== '') {
            $this->applyTwitchControl($user, 'latest_chatter_name', fn () => $latestChatterName);
        }

        if ($latestChatMessage !== null && $latestChatMessage !== '') {
            $this->applyTwitchControl($user, 'latest_chat_message', fn () => $latestChatMessage);
        }

        return ['applied' => true, 'unique_chatters' => $uniqueCount];
    }

    /**
     * Fold this window's logins into the per-stream unique-chatter set.
     *
     * Returns the new set size, or null when the window added nobody new - the
     * caller skips the control write entirely in that case, so a quiet window
     * costs zero writes and zero broadcasts.
     *
     * Uniqueness is decided HERE rather than in the bot: the bot is a thin relay
     * and has no notion of where a stream starts, and it would lose the set on
     * every restart.
     *
     * @param  list<string>  $chatters
     */
    private function mergeChatters(User $user, array $chatters): ?int
    {
        $incoming = collect($chatters)
            ->map(fn ($login) => strtolower(trim((string) $login)))
            ->filter()
            ->unique();

        $key = self::chatterSetCacheKey($user);
        $known = collect(Cache::get($key, []));
        $before = $known->count();

        $merged = $known->merge($incoming)->unique()->values();

        if ($merged->count() > self::MAX_TRACKED_CHATTERS) {
            Log::warning("Unique-chatter set capped for user {$user->id}", [
                'seen' => $merged->count(),
                'cap' => self::MAX_TRACKED_CHATTERS,
            ]);
            $merged = $merged->take(self::MAX_TRACKED_CHATTERS)->values();
        }

        if ($merged->count() === $before) {
            return null;
        }

        Cache::put($key, $merged->all(), self::CHATTER_SET_TTL);

        return $merged->count();
    }

    /**
     * Cache key holding the current stream's set of distinct chatter logins.
     */
    private static function chatterSetCacheKey(User $user): string
    {
        return "chat:chatters:{$user->id}";
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
            $control->writeValue($newValue);

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
     * Reset the per-stream twitch controls to their reset_value (or 0) and broadcast each.
     */
    private function resetControls(User $user): void
    {
        // unique_chatters_this_stream is backed by a cached set of logins, so
        // zeroing the control alone would leave the counter pinned at last
        // stream's total (applyChatSummary only ever moves it upward).
        Cache::forget(self::chatterSetCacheKey($user));

        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'twitch')
            ->whereIn('key', self::PER_STREAM_CONTROL_KEYS)
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $resetValue = (string) ($control->config['reset_value'] ?? 0);
            $control->writeValue($resetValue);

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

        Log::info("Reset per-stream twitch controls for user {$user->id}", [
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
