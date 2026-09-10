<?php

namespace App\Http\Controllers\Api\Internal;

use App\Contracts\StatefulExternalServiceDriver;
use App\Events\ExternalEventStored;
use App\Http\Controllers\Controller;
use App\Models\ExternalEvent;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\EventMeter;
use App\Services\External\ExternalAlertService;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\StreamSessionService;
use App\Services\Tower\TowerPhysics;
use App\Services\Tower\TowerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BotTowerController extends Controller
{
    /** Seconds between `!tower` replies per chatter - a read, so short. */
    private const int STATUS_COOLDOWN = 10;

    public function __construct(
        private readonly TowerService $tower,
        private readonly ExternalControlService $controlService,
        private readonly ExternalAlertService $alertService,
    ) {}

    /**
     * Handle one `!stack [left|right]` or `!tower` from chat, relayed by the
     * bot as `action` stack / status.
     *
     * The bot is a thin relay: everything happens here. The physics and the
     * block rows are TowerService; the event then runs the SAME pipeline as
     * ExternalWebhookController::handle steps 7-11: store with dedup, meter,
     * feed hook, control updates, alert dispatch, last_received_at. Chat
     * Tower has no public webhook route - the driver's verifyRequest()
     * refuses everything - so this endpoint is the single door.
     *
     * Replies follow the checkin convention: the returned `reply` string is
     * spoken inline by the bot; null means stay silent. A plain successful
     * stack is silent on purpose - a busy tower would otherwise be a wall of
     * bot lines - and speaks only on a topple, on passing the record, and at
     * every tenth block.
     */
    public function store(Request $request, string $login): JsonResponse
    {
        $data = $request->validate([
            'chatter_id' => 'required|string|max:32',
            'chatter_login' => 'required|string|max:64',
            'chatter_display_name' => 'required|string|max:64',
            'chatter_color' => 'nullable|string|max:16',
            'action' => 'nullable|string|in:stack,status',
            'args' => 'nullable|string|max:60',
        ]);

        $user = $this->resolveUser($login);

        if (! $user) {
            return response()->json(['error' => 'channel not found'], 404);
        }

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'tower')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return response()->json(['reply' => null]);
        }

        if (($data['action'] ?? 'stack') === 'status') {
            if (! Cache::add("tower:status:{$user->id}:{$data['chatter_id']}", 1, self::STATUS_COOLDOWN)) {
                return response()->json(['reply' => null]);
            }

            return response()->json(['reply' => $this->tower->status($user)]);
        }

        // Server-side cooldown backstop (the bot has its own command cooldown,
        // but this endpoint must defend itself). Silent: a cooldown reply per
        // spammed command would itself be spam.
        $settings = $integration->settings ?? [];
        $cooldown = max(5, (int) ($settings['cooldown_seconds'] ?? 30));

        if (! Cache::add("tower:cooldown:{$user->id}:{$data['chatter_id']}", 1, $cooldown)) {
            return response()->json(['reply' => null]);
        }

        // The tower only stands on a live stream: the house isConfidentlyLive
        // gate, same as checkin. Nothing lands offline - no block, no event,
        // no controls - and the reply says so, because a silently swallowed
        // command means the viewer retypes it later and hands Twitch's
        // repeated-message filter a reason to eat it. The reply sits behind
        // the cooldown above, so offline spam stays quiet.
        if (! StreamSessionService::isLive($user)) {
            return response()->json(['reply' => 'The tower only stands while the stream is live. Come back then!']);
        }

        $facts = $this->tower->stack($user, [
            'chatter_id' => $data['chatter_id'],
            'chatter_login' => $data['chatter_login'],
            'chatter_display_name' => $data['chatter_display_name'],
            'chatter_color' => self::normalizeColor($data['chatter_color'] ?? null),
        ], TowerPhysics::normalizeAim($data['args'] ?? null));

        $this->runPipeline($user, $integration, $facts);

        return response()->json(['reply' => $this->replyFor($facts)]);
    }

    /**
     * What the bot says after a stack. Null for the ordinary block.
     *
     * @param  array<string, mixed>  $facts
     */
    private function replyFor(array $facts): ?string
    {
        $name = $facts['chatter_display_name'];
        $height = (int) $facts['height'];
        $record = (int) $facts['record_height'];

        if ($facts['type'] === 'topple') {
            $tail = match (true) {
                (bool) $facts['record_tower'] => 'That tower was the record.',
                $record > 0 => "Record stands at {$record}.",
                default => 'No record yet.',
            };

            return "The tower fell at {$height}. {$name} placed the last block. {$tail}";
        }

        // The first block past a real record announces it once; the blocks
        // after it keep raising the record quietly, and the first block of
        // an account with no record yet is not news.
        if ($facts['first_record_block'] && $record > 0) {
            return "NEW RECORD! The tower is {$height} high. The old record was {$record}.";
        }

        if ($height % 10 === 0) {
            $side = match (TowerPhysics::leanSide((float) $facts['lean'])) {
                'left' => 'leaning left',
                'right' => 'leaning right',
                default => 'dead straight',
            };

            return "The tower is {$height} high, {$side}, ".TowerService::roomText((float) $facts['room']).'.';
        }

        return null;
    }

    /**
     * ExternalWebhookController::handle steps 7-11 for a controller-built
     * payload: normalize, store with dedup, meter, feed hook, controls,
     * alert, last_received_at. The stored event is passed to the alert
     * dispatch so the delivery ledger sees tower alerts, and the
     * controls_updated / alert_dispatched flags are written like the webhook
     * path writes them.
     *
     * @param  array<string, mixed>  $facts
     */
    private function runPipeline(User $user, ExternalIntegration $integration, array $facts): void
    {
        $driver = ExternalServiceRegistry::driver('tower');
        $normalizedEvent = $driver->normalizeEvent($facts, $facts['type']);

        try {
            // The nested transaction is a savepoint: on the unique violation
            // Postgres rolls back to it instead of aborting the surrounding
            // transaction (which is how this path behaves under test).
            $storedEvent = DB::transaction(fn () => ExternalEvent::create([
                'user_id' => $user->id,
                'service' => 'tower',
                'event_type' => $facts['type'],
                'raw_payload' => $normalizedEvent->getRaw(),
                'message_id' => $normalizedEvent->getMessageId(),
                'normalized_payload' => $normalizedEvent->getTemplateTags(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return;
        }

        app(EventMeter::class)->record($user->id);

        ExternalEventStored::dispatch($user->id, 'tower', $facts['type'], $normalizedEvent->getTemplateTags());

        $controlUpdates = $driver->getControlUpdates($normalizedEvent);

        if ($driver instanceof StatefulExternalServiceDriver) {
            $driver->beforeControlUpdates($integration, $normalizedEvent, $controlUpdates);
        }

        if (! empty($controlUpdates)) {
            $this->controlService->applyUpdates($user, 'tower', $controlUpdates);
            $storedEvent->update(['controls_updated' => true]);
        }

        if ($this->alertService->dispatch($normalizedEvent, $user, $storedEvent)) {
            $storedEvent->update(['alert_dispatched' => true]);
        }

        $integration->update(['last_received_at' => now()]);
    }

    /**
     * A Twitch chat colour is `#RRGGBB` or empty (the viewer never picked one).
     * Anything else is dropped rather than stored: the value ends up in an
     * overlay's style attribute.
     */
    private static function normalizeColor(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? strtoupper($raw) : null;
    }

    private function resolveUser(string $login): ?User
    {
        $login = strtolower($login);

        return User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->first(fn (User $u) => strtolower($u->twitch_data['login'] ?? '') === $login);
    }
}
