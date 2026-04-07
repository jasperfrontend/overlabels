<?php

namespace App\Services;

use App\Events\StreamStatusChanged;
use App\Jobs\VerifyStreamState;
use App\Models\ExternalEvent;
use App\Models\StreamSession;
use App\Models\StreamState;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StreamStateMachineService
{
    public function __construct(
        private TwitchApiService $twitchApi,
        private TwitchEventSubService $eventSubService,
        private StreamSessionService $sessionService,
    ) {}

    /**
     * Handle an EventSub stream.online event.
     * Transitions to "starting" and dispatches Helix verification.
     */
    public function handleEventSubOnline(User $user, array $eventData): void
    {
        DB::transaction(function () use ($user) {
            $state = $this->lockState($user);

            if ($state->state === StreamState::STATE_OFFLINE || $state->state === StreamState::STATE_ENDING) {
                // If ending within grace period, keep current_session_id for stitching
                $keepSession = $state->state === StreamState::STATE_ENDING
                    && $state->current_session_id !== null;

                $state->state = StreamState::STATE_STARTING;
                $state->confidence = StreamState::CONFIDENCE_INCREMENT;
                $state->last_event_at = now();

                if (! $keepSession) {
                    $state->current_session_id = null;
                }

                $state->grace_period_until = null;
                $state->save();

                Log::info('Stream state: EventSub online received, transitioning to starting', [
                    'user_id' => $user->id,
                    'session_stitching' => $keepSession,
                ]);
            } elseif ($state->state === StreamState::STATE_STARTING || $state->state === StreamState::STATE_LIVE) {
                $state->confidence = min(1.0, $state->confidence + StreamState::CONFIDENCE_INCREMENT);
                $state->last_event_at = now();
                $state->save();

                Log::info('Stream state: EventSub online received, bumping confidence', [
                    'user_id' => $user->id,
                    'state' => $state->state,
                    'confidence' => $state->confidence,
                ]);
            }
        });

        VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
    }

    /**
     * Handle an EventSub stream.offline event.
     * Transitions to "ending" with grace period and dispatches Helix verification.
     */
    public function handleEventSubOffline(User $user, array $eventData): void
    {
        DB::transaction(function () use ($user) {
            $state = $this->lockState($user);

            if ($state->state === StreamState::STATE_LIVE || $state->state === StreamState::STATE_STARTING) {
                $state->state = StreamState::STATE_ENDING;
                $state->confidence = StreamState::CONFIDENCE_INCREMENT;
                $state->last_event_at = now();
                $state->grace_period_until = now()->addSeconds(StreamState::GRACE_PERIOD_SECONDS);
                $state->save();

                Log::info('Stream state: EventSub offline received, transitioning to ending', [
                    'user_id' => $user->id,
                    'grace_period_until' => $state->grace_period_until,
                ]);
            } elseif ($state->state === StreamState::STATE_ENDING) {
                $state->confidence = min(1.0, $state->confidence + StreamState::CONFIDENCE_INCREMENT);
                $state->last_event_at = now();
                $state->save();
            } elseif ($state->state === StreamState::STATE_OFFLINE) {
                // Offline event without prior online - could be a missed online event.
                // Set to ending with higher confidence (Twitch said offline, probably true)
                // and let verification sort it out.
                $state->state = StreamState::STATE_ENDING;
                $state->confidence = 0.50;
                $state->last_event_at = now();
                $state->grace_period_until = now()->addSeconds(StreamState::GRACE_PERIOD_SECONDS);
                $state->save();

                Log::warning('Stream state: offline event without prior online, verifying', [
                    'user_id' => $user->id,
                ]);
            }
        });

        VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
    }

    /**
     * Verify the current stream state against Twitch Helix API.
     * Called by the VerifyStreamState job.
     */
    public function verify(User $user): void
    {
        $state = StreamState::forUser($user);

        if ($state->state === StreamState::STATE_OFFLINE) {
            return;
        }

        $appToken = $this->eventSubService->getAppAccessToken();
        if (! $appToken) {
            Log::warning('Stream state: could not get app token for verification', [
                'user_id' => $user->id,
            ]);
            // Retry in 30 seconds
            VerifyStreamState::dispatch($user)->delay(now()->addSeconds(30));

            return;
        }

        $helixResult = $this->twitchApi->getStreamStatus($appToken, $user->twitch_id);
        if ($helixResult === null) {
            Log::warning('Stream state: Helix API call failed, retrying', [
                'user_id' => $user->id,
            ]);
            VerifyStreamState::dispatch($user)->delay(now()->addSeconds(30));

            return;
        }

        $helixIsLive = $helixResult['is_live'];
        $helixStream = $helixResult['stream'];

        DB::transaction(function () use ($user, $state, $helixIsLive, $helixStream) {
            // Re-fetch with lock inside transaction
            $state = $this->lockState($user);

            // Bail if state changed to offline while we were checking
            if ($state->state === StreamState::STATE_OFFLINE) {
                return;
            }

            $state->last_verified_at = now();

            match ($state->state) {
                StreamState::STATE_STARTING => $this->verifyStarting($user, $state, $helixIsLive, $helixStream),
                StreamState::STATE_LIVE => $this->verifyLive($user, $state, $helixIsLive, $helixStream),
                StreamState::STATE_ENDING => $this->verifyEnding($user, $state, $helixIsLive, $helixStream),
                default => null,
            };
        });
    }

    /**
     * Verify logic for the "starting" state.
     */
    private function verifyStarting(User $user, StreamState $state, bool $helixIsLive, ?array $helixStream): void
    {
        if ($helixIsLive) {
            $state->confidence += StreamState::CONFIDENCE_INCREMENT;
            $state->clampConfidence();
            $state->save();

            if ($state->confidence >= StreamState::CONFIDENCE_THRESHOLD) {
                $this->transitionToLive($user, $state, $helixStream);
            } else {
                VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
            }
        } else {
            $state->confidence -= StreamState::CONFIDENCE_INCREMENT;
            $state->clampConfidence();
            $state->save();

            if ($state->confidence <= 0) {
                // False alarm - revert to offline
                $state->state = StreamState::STATE_OFFLINE;
                $state->confidence = 0.0;
                $state->helix_stream_id = null;
                $state->current_session_id = null;
                $state->save();

                Log::info('Stream state: starting reverted to offline (false alarm)', [
                    'user_id' => $user->id,
                ]);
            } else {
                VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
            }
        }
    }

    /**
     * Verify logic for the "live" state (heartbeat).
     */
    private function verifyLive(User $user, StreamState $state, bool $helixIsLive, ?array $helixStream): void
    {
        if ($helixIsLive) {
            // Stream confirmed live - full confidence, schedule next heartbeat
            $state->confidence = 1.0;
            $state->helix_stream_id = $helixStream['id'] ?? $state->helix_stream_id;
            $state->save();

            VerifyStreamState::dispatch($user)->delay(now()->addSeconds(60));
        } else {
            // Stream went offline but we didn't get an EventSub event (missed offline)
            $state->state = StreamState::STATE_ENDING;
            $state->confidence = 0.50;
            $state->grace_period_until = now()->addSeconds(StreamState::GRACE_PERIOD_SECONDS);
            $state->save();

            Log::warning('Stream state: heartbeat detected offline (missed EventSub offline)', [
                'user_id' => $user->id,
            ]);

            VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
        }
    }

    /**
     * Verify logic for the "ending" state.
     */
    private function verifyEnding(User $user, StreamState $state, bool $helixIsLive, ?array $helixStream): void
    {
        // Check grace period expiry first
        if ($state->grace_period_until && now()->greaterThan($state->grace_period_until)) {
            Log::info('Stream state: grace period expired, forcing transition to offline', [
                'user_id' => $user->id,
            ]);
            $this->transitionToOffline($user, $state);

            return;
        }

        if (! $helixIsLive) {
            // Helix confirms offline
            $state->confidence += StreamState::CONFIDENCE_INCREMENT;
            $state->clampConfidence();
            $state->save();

            if ($state->confidence >= StreamState::CONFIDENCE_THRESHOLD) {
                $this->transitionToOffline($user, $state);
            } else {
                VerifyStreamState::dispatch($user)->delay(now()->addSeconds(10));
            }
        } else {
            // Stream is back! (OBS restart, connection recovery)
            Log::info('Stream state: ending reversed - stream back online (OBS restart?)', [
                'user_id' => $user->id,
            ]);

            $state->state = StreamState::STATE_LIVE;
            $state->confidence = StreamState::CONFIDENCE_THRESHOLD;
            $state->grace_period_until = null;
            $state->helix_stream_id = $helixStream['id'] ?? $state->helix_stream_id;
            $state->save();

            // Session stitching: if we have a session, reopen it
            if ($state->current_session_id) {
                $session = StreamSession::find($state->current_session_id);
                if ($session && $session->ended_at !== null) {
                    $session->update(['ended_at' => null]);
                    Log::info('Stream state: stitched session back open', [
                        'session_id' => $session->id,
                    ]);
                }
            }

            $this->broadcastState($user, $state, true);

            VerifyStreamState::dispatch($user)->delay(now()->addSeconds(60));
        }
    }

    /**
     * Transition to the "live" state - create/reuse session, reset controls, broadcast.
     */
    private function transitionToLive(User $user, StreamState $state, ?array $helixStream): void
    {
        $session = null;

        // Session stitching: check if we can reuse a recently-closed session
        if ($state->current_session_id) {
            $session = StreamSession::find($state->current_session_id);
            if ($session && $session->ended_at !== null && $session->ended_at->diffInMinutes(now()) <= 5) {
                // Reopen the session (stitch)
                $session->update(['ended_at' => null]);
                Log::info('Stream state: stitched session on live transition', [
                    'session_id' => $session->id,
                ]);
            } else {
                $session = null;
            }
        }

        if (! $session) {
            // Create new session via the existing service (handles closing lingering sessions + control reset)
            $session = $this->sessionService->openSession($user);
        }

        // Retroactive repair: align started_at with Helix truth
        if ($helixStream && isset($helixStream['started_at'])) {
            $helixStartedAt = Carbon::parse($helixStream['started_at']);
            if ($session->started_at->diffInSeconds($helixStartedAt) > 5) {
                $session->update(['started_at' => $helixStartedAt]);
            }
        }

        // Store Helix stream ID
        if ($helixStream && isset($helixStream['id'])) {
            $session->update(['helix_stream_id' => $helixStream['id']]);
            $state->helix_stream_id = $helixStream['id'];
        }

        $state->state = StreamState::STATE_LIVE;
        $state->confidence = StreamState::CONFIDENCE_THRESHOLD;
        $state->current_session_id = $session->id;
        $state->save();

        // Stamp events that arrived during the starting phase
        $this->stampEventsWithSession($user->id, $session->id, $session->started_at);

        // Broadcast enriched status
        $this->broadcastState($user, $state, true);

        Log::info('Stream state: transitioned to live', [
            'user_id' => $user->id,
            'session_id' => $session->id,
            'helix_stream_id' => $state->helix_stream_id,
        ]);

        // Start heartbeat
        VerifyStreamState::dispatch($user)->delay(now()->addSeconds(60));
    }

    /**
     * Transition to the "offline" state - close session, broadcast.
     */
    private function transitionToOffline(User $user, StreamState $state): void
    {
        // Close the session via the existing service
        $this->sessionService->closeSession($user);

        $state->state = StreamState::STATE_OFFLINE;
        $state->confidence = 0.0;
        $state->helix_stream_id = null;
        $state->current_session_id = null;
        $state->grace_period_until = null;
        $state->save();

        $this->broadcastState($user, $state, false);

        Log::info('Stream state: transitioned to offline', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Stamp unstamped events with a session ID for future event grouping.
     */
    private function stampEventsWithSession(int $userId, int $sessionId, Carbon $since): void
    {
        $twitchCount = TwitchEvent::where('user_id', $userId)
            ->whereNull('stream_session_id')
            ->where('created_at', '>=', $since)
            ->update(['stream_session_id' => $sessionId]);

        $externalCount = ExternalEvent::where('user_id', $userId)
            ->whereNull('stream_session_id')
            ->where('created_at', '>=', $since)
            ->update(['stream_session_id' => $sessionId]);

        if ($twitchCount > 0 || $externalCount > 0) {
            Log::info('Stream state: stamped events with session', [
                'session_id' => $sessionId,
                'twitch_events' => $twitchCount,
                'external_events' => $externalCount,
            ]);
        }
    }

    /**
     * Broadcast an enriched stream status event.
     */
    private function broadcastState(User $user, StreamState $state, bool $live): void
    {
        $startedAt = null;
        if ($live && $state->current_session_id) {
            $session = StreamSession::find($state->current_session_id);
            $startedAt = $session?->started_at?->toISOString();
        }

        StreamStatusChanged::dispatch(
            $user->twitch_id,
            $live,
            $state->state,
            $state->confidence,
            $startedAt,
        );
    }

    /**
     * Get the stream state for a user (read-only helper for other services/middleware).
     */
    public function getStateForUser(User $user): StreamState
    {
        return StreamState::forUser($user);
    }

    /**
     * Lock the stream state row for update within a transaction.
     */
    private function lockState(User $user): StreamState
    {
        $state = StreamState::where('user_id', $user->id)->lockForUpdate()->first();

        if (! $state) {
            $state = StreamState::create([
                'user_id' => $user->id,
                'state' => StreamState::STATE_OFFLINE,
                'confidence' => 0.0,
            ]);
            // Re-fetch with lock
            $state = StreamState::where('user_id', $user->id)->lockForUpdate()->first();
        }

        return $state;
    }
}
