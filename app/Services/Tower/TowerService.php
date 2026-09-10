<?php

namespace App\Services\Tower;

use App\Events\ControlValueUpdated;
use App\Events\ListUpdated;
use App\Events\TowerUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\TowerBlock;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Support\Facades\Cache;

/**
 * Chat Tower's one writer. A `!stack` lands here, the physics decides whether
 * the tower stands or falls, the block rows move, the overlay hears about
 * it. Everything the driver later maps onto controls is computed here, once,
 * and handed back as facts.
 *
 * The all-time record roster lives in the `tower_record` List when the
 * account has one (the Chat Tower product installs it): every time a standing
 * tower passes the record, the list is rewritten with everyone who has a
 * block in it, bottom to top. The record HEIGHT is the `tallest_tower_record`
 * control, raised by the driver through the ordinary pipeline; this service
 * only reads it.
 */
class TowerService
{
    public const string RECORD_LIST_SLUG = 'tower_record';

    /**
     * The controls that describe the standing tower. First-party clears
     * (go-live in per_stream mode, the settings-page reset) put these back
     * without moving `_at`; a topple, which is chat's doing, writes them
     * through the driver like any other event.
     */
    private const array STANDING_CONTROLS = [
        'tower_height' => '0',
        'tower_lean' => '0',
        'tower_room' => '4',
    ];

    /**
     * Place one block. Serialized per channel so two `!stack`s arriving in
     * the same instant cannot both read the same top block.
     *
     * @param  array{chatter_id: string, chatter_login: string, chatter_display_name: string, chatter_color: ?string}  $chatter
     * @param  float|null  $u  Deterministic landing draw in [0, 1); tests only.
     * @return array<string, mixed> The facts the driver maps onto controls.
     */
    public function stack(User $user, array $chatter, ?string $aim, ?float $u = null): array
    {
        return Cache::lock("tower:stack:{$user->id}", 5)->block(3, function () use ($user, $chatter, $aim, $u) {
            $top = TowerBlock::topFor($user);
            $position = ($top?->position ?? 0) + 1;
            $offset = TowerPhysics::offsetFor($aim, $u);
            $x = round(($top?->x ?? 0.0) + $offset, 3);
            $recordHeight = $this->currentRecord($user);
            $topples = TowerPhysics::topples($x, $position);
            // Only a STANDING tower counts for the record, so the block that
            // brings the tower down sets none.
            $newRecord = ! $topples && $position > $recordHeight;
            $now = now();

            $block = [
                'name' => $chatter['chatter_display_name'],
                'login' => strtolower($chatter['chatter_login']),
                'color' => (string) ($chatter['chatter_color'] ?? ''),
                'position' => (string) $position,
                'offset' => (string) $offset,
                'x' => (string) $x,
                'record' => $newRecord ? '1' : '',
                'at' => (string) $now->getTimestamp(),
            ];

            $facts = [
                'chatter_id' => $chatter['chatter_id'],
                'chatter_login' => strtolower($chatter['chatter_login']),
                'chatter_display_name' => $chatter['chatter_display_name'],
                'chatter_color' => (string) ($chatter['chatter_color'] ?? ''),
                'aim' => $aim ?? '',
                'position' => $position,
                'offset' => $offset,
                'lean' => $x,
                'room' => TowerPhysics::room($x, $position),
                'at' => $now->getTimestamp(),
                'record_height' => $recordHeight,
                'block' => $block,
            ];

            if ($topples) {
                // The tower fell at the height of the block that brought it
                // down. `record_tower` says whether the tower it took down
                // was the record holder: the block under it was a record
                // block, so that tower set the record as it stood.
                $recordTower = (bool) ($top?->record ?? false);

                TowerBlock::where('user_id', $user->id)->delete();

                $facts += [
                    'type' => 'topple',
                    'height' => $position,
                    'new_record' => false,
                    'first_record_block' => false,
                    'record_tower' => $recordTower,
                ];

                TowerUpdated::dispatch($user->twitch_id, $block, 0, true, [
                    'by' => $chatter['chatter_display_name'],
                    'login' => strtolower($chatter['chatter_login']),
                    'height' => $position,
                    'record_tower' => $facts['record_tower'],
                ]);

                return $facts;
            }

            TowerBlock::create([
                'user_id' => $user->id,
                'position' => $position,
                'chatter_twitch_id' => $chatter['chatter_id'],
                'chatter_login' => strtolower($chatter['chatter_login']),
                'chatter_display_name' => $chatter['chatter_display_name'],
                'color' => $chatter['chatter_color'] ?: null,
                'offset' => $offset,
                'x' => $x,
                'record' => $newRecord,
                'placed_at' => $now,
            ]);

            if ($newRecord) {
                $this->writeRecordRoster($user);
            }

            $facts += [
                'type' => 'stack',
                'height' => $position,
                'new_record' => $newRecord,
                // The first block of THIS tower past the record: the block
                // below it was not a record block. The blocks after it keep
                // raising the record without being news again.
                'first_record_block' => $newRecord && ! ($top?->record ?? false),
                'record_tower' => false,
            ];

            TowerUpdated::dispatch($user->twitch_id, $block, $position);

            return $facts;
        });
    }

    /**
     * The `!tower` reply: height, lean, room, record. Works offline too - it
     * reads, it does not stack.
     */
    public function status(User $user): string
    {
        $height = TowerBlock::heightFor($user);
        $record = $this->currentRecord($user);
        $recordText = $record > 0 ? "Record {$record}." : 'No record yet.';

        if ($height === 0) {
            return "No tower right now. Type !stack to lay the first block. {$recordText}";
        }

        $top = TowerBlock::topFor($user);
        $room = TowerPhysics::room((float) $top->x, $height);
        $side = match (TowerPhysics::leanSide((float) $top->x)) {
            'left' => 'leaning left',
            'right' => 'leaning right',
            default => 'dead straight',
        };

        return "Tower is {$height} high, {$side}, ".self::roomText($room).". {$recordText}";
    }

    /**
     * A first-party clear: go-live in per_stream mode, or the settings-page
     * reset. The blocks go, the standing controls return to their rest values
     * WITHOUT moving `_at` (this is us, not chat - the resetValue contract),
     * and connected overlays drop their blocks.
     */
    public function clear(User $user): void
    {
        TowerBlock::where('user_id', $user->id)->delete();

        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', 'tower')
            ->whereIn('key', array_keys(self::STANDING_CONTROLS))
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $resetValue = self::STANDING_CONTROLS[$control->key];
            $preservedAt = $control->resetValue($resetValue);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                $resetValue,
                $user->twitch_id,
                null,
                null,
                null,
                false,
                $preservedAt,
            );
        }

        TowerUpdated::dispatch($user->twitch_id, null, 0, true);
    }

    /** The all-time record height, as the control holds it. */
    public function currentRecord(User $user): int
    {
        return (int) (OverlayControl::where('user_id', $user->id)
            ->where('source', 'tower')
            ->where('key', 'tallest_tower_record')
            ->where('source_managed', true)
            ->value('value') ?? 0);
    }

    /** "1.2 from falling" or "plenty of room" - the bot's phrase for the room left. */
    public static function roomText(float $room): string
    {
        if ($room > 2) {
            return 'plenty of room';
        }

        $number = rtrim(rtrim(number_format(max($room, 0), 1), '0'), '.');

        return "{$number} from falling";
    }

    /**
     * Rewrite the record roster: everyone with a block in the standing tower,
     * bottom to top, one entry per viewer (a second block by the same viewer
     * adds nothing). No list means the account did not install the product;
     * the record height still moves, only the names have nowhere to go.
     */
    private function writeRecordRoster(User $user): void
    {
        $list = OptionSet::where('user_id', $user->id)
            ->where('slug', self::RECORD_LIST_SLUG)
            ->first();

        if (! $list) {
            return;
        }

        $names = [];

        foreach (TowerBlock::where('user_id', $user->id)->orderBy('position')->get() as $block) {
            $names[$block->chatter_login] ??= $block->chatter_display_name;
        }

        $fresh = ListItems::freshFromValues(array_values($names), (int) ($list->next_item_id ?? 1));

        $list->update([
            'items' => $fresh['items'],
            'next_item_id' => $fresh['next_id'],
        ]);

        ListUpdated::dispatchFor((string) $user->twitch_id, $list->fresh());
    }
}
