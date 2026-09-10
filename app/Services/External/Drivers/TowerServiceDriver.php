<?php

namespace App\Services\External\Drivers;

use App\Contracts\ExternalServiceDriver;
use App\Contracts\StatefulExternalServiceDriver;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\NormalizedExternalEvent;
use App\Services\Tower\TowerPhysics;
use Illuminate\Http\Request;

/**
 * First-party Chat Tower integration: chat stacks one shared tower of named
 * blocks with `!stack`, and it falls when the lean crosses the fall line.
 * Events never arrive over the public webhook route - the bot relays
 * `!stack` and `!tower` to the internal BotTowerController, which runs the
 * physics (TowerService), persists the block, and runs this driver through
 * the same pipeline every webhook service uses.
 *
 * The payload this driver normalizes is therefore controller-built, already
 * carrying every derived fact (height, lean, room, record). The driver maps
 * facts onto controls; it never re-derives them.
 */
class TowerServiceDriver implements ExternalServiceDriver, StatefulExternalServiceDriver
{
    /**
     * Per-stream keys reset at go-live by StreamSessionService::resetControls.
     * A key belongs here only if its label promises per-stream scope - the
     * standing tower (tower_height / tower_lean / tower_room) is NOT here:
     * whether it survives a stream ending is the tower_lifetime setting,
     * applied by TowerService::clear(), and the all-time record and the
     * last_* controls persist by the same rule as latest_cheer*.
     */
    public const array PER_STREAM_CONTROL_KEYS = [
        'blocks_stacked_this_stream',
        'tallest_tower_this_stream',
        'topples_this_stream',
    ];

    public function getServiceKey(): string
    {
        return 'tower';
    }

    /**
     * Chat Tower has no public webhook: the only entry point is the internal
     * bot endpoint behind X-Internal-Secret. A POST to
     * /api/webhooks/tower/{token} must always fail verification.
     */
    public function verifyRequest(Request $request, ExternalIntegration $integration): bool
    {
        return false;
    }

    public function parseEventType(array $payload): ?string
    {
        $type = $payload['type'] ?? null;

        return in_array($type, $this->getSupportedEventTypes(), true) ? $type : null;
    }

    public function normalizeEvent(array $payload, string $eventType): NormalizedExternalEvent
    {
        $tags = [
            'event.user_name' => (string) ($payload['chatter_display_name'] ?? ''),
            'event.user_login' => (string) ($payload['chatter_login'] ?? ''),
            'event.color' => (string) ($payload['chatter_color'] ?? ''),
            'event.aim' => (string) ($payload['aim'] ?? ''),
            'event.height' => (string) ($payload['height'] ?? ''),
            'event.lean' => (string) ($payload['lean'] ?? ''),
            'event.room' => (string) ($payload['room'] ?? ''),
            'event.record' => ! empty($payload['new_record']) || ! empty($payload['record_tower']) ? '1' : '',
        ];

        return new NormalizedExternalEvent(
            service: 'tower',
            eventType: $eventType,
            messageId: 'tower_'.($payload['chatter_id'] ?? '0').'_'.($payload['at'] ?? now()->timestamp).'_'.($payload['position'] ?? '0'),
            fromName: $payload['chatter_display_name'] ?? null,
            message: $eventType === 'topple'
                ? 'toppled at '.($payload['height'] ?? '?')
                : 'stacked block '.($payload['height'] ?? '?'),
            amount: null,
            currency: null,
            templateTags: $tags,
            raw: $payload,
        );
    }

    public function getSupportedEventTypes(): array
    {
        return ['stack', 'topple'];
    }

    public function getAutoProvisionedControls(): array
    {
        return [
            ['key' => 'tower_height', 'type' => 'number', 'label' => 'Tower Height', 'value' => '0'],
            ['key' => 'tower_lean', 'type' => 'number', 'label' => 'Tower Lean', 'value' => '0'],
            ['key' => 'tower_room', 'type' => 'number', 'label' => 'Tower Room To Fall', 'value' => (string) TowerPhysics::FALL_LINE],
            ['key' => 'last_stacker_name', 'type' => 'text', 'label' => 'Last Stacker Name', 'value' => ''],
            ['key' => 'blocks_stacked_this_stream', 'type' => 'counter', 'label' => 'Blocks Stacked This Stream', 'value' => '0'],
            ['key' => 'tallest_tower_this_stream', 'type' => 'number', 'label' => 'Tallest Tower This Stream', 'value' => '0'],
            ['key' => 'topples_this_stream', 'type' => 'counter', 'label' => 'Topples This Stream', 'value' => '0'],
            ['key' => 'last_topple_by', 'type' => 'text', 'label' => 'Last Topple By', 'value' => ''],
            ['key' => 'last_topple_height', 'type' => 'number', 'label' => 'Last Topple Height', 'value' => '0'],
            ['key' => 'tallest_tower_record', 'type' => 'number', 'label' => 'Tallest Tower Record (all time)', 'value' => '0'],
            ['key' => 'blocks_stacked_total', 'type' => 'number', 'label' => 'Blocks Stacked Total (all time)', 'value' => '0'],
        ];
    }

    public function getControlUpdates(NormalizedExternalEvent $event): array
    {
        $raw = $event->getRaw();
        $name = (string) ($raw['chatter_display_name'] ?? '');

        // Every placed block counts as stacked, the one that brought the
        // tower down included - it was a block somebody placed.
        $updates = [
            'last_stacker_name' => $name,
            'blocks_stacked_this_stream' => ['action' => 'increment'],
            'blocks_stacked_total' => ['action' => 'increment'],
        ];

        if ($event->getEventType() === 'topple') {
            $updates['tower_height'] = '0';
            $updates['tower_lean'] = '0';
            $updates['tower_room'] = (string) TowerPhysics::FALL_LINE;
            $updates['last_topple_by'] = $name;
            $updates['last_topple_height'] = (string) (int) ($raw['height'] ?? 0);
            $updates['topples_this_stream'] = ['action' => 'increment'];

            return $updates;
        }

        $updates['tower_height'] = (string) (int) ($raw['height'] ?? 0);
        $updates['tower_lean'] = (string) ($raw['lean'] ?? '0');
        $updates['tower_room'] = (string) ($raw['room'] ?? TowerPhysics::FALL_LINE);

        return $updates;
    }

    /**
     * The two upward-only aggregates. Written only on a STRICT beat, so a
     * replayed event can never make a record count down and `_at` on these
     * controls answers "when did the record last move", not "when was the
     * last block". Only a standing tower counts: a topple event never
     * reaches here, so the block that brings the tower down sets no record.
     */
    public function beforeControlUpdates(
        ExternalIntegration $integration,
        NormalizedExternalEvent $event,
        array &$updates
    ): void {
        if ($event->getEventType() !== 'stack') {
            return;
        }

        $user = $integration->user;

        if (! $user) {
            return;
        }

        $height = (int) ($event->getRaw()['height'] ?? 0);

        foreach (['tallest_tower_this_stream', 'tallest_tower_record'] as $key) {
            if ($height > (int) $this->currentControlValue($user->id, $key)) {
                $updates[$key] = (string) $height;
            }
        }
    }

    private function currentControlValue(int $userId, string $key): string
    {
        return (string) (OverlayControl::where('user_id', $userId)
            ->where('source', 'tower')
            ->where('key', $key)
            ->where('source_managed', true)
            ->value('value') ?? '0');
    }
}
