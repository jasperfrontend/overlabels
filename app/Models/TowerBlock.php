<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One block of a channel's current Chat Tower. See the tower_blocks migration
 * for what the rows mean and why a topple deletes them.
 */
class TowerBlock extends Model
{
    /**
     * How many blocks the overlay's `tower.*` iterable carries: the TOP of
     * the tower, since the camera follows the top. A taller tower only loses
     * blocks nobody can see. Fixed at the platform ceiling rather than a
     * per-user foreach cap because there is nothing to choose: fewer blocks
     * would cut a visible tower short, more would breach the ceiling.
     */
    public const int WINDOW = User::FOREACH_CAP_MAX;

    protected $fillable = [
        'user_id',
        'position',
        'chatter_twitch_id',
        'chatter_login',
        'chatter_display_name',
        'color',
        'offset',
        'x',
        'record',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'offset' => 'float',
            'x' => 'float',
            'record' => 'boolean',
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The current height of the tower: how many blocks stand. */
    public static function heightFor(User $user): int
    {
        return static::where('user_id', $user->id)->count();
    }

    /** The top block, whose `x` is the tower's lean. Null for an empty tower. */
    public static function topFor(User $user): ?self
    {
        return static::where('user_id', $user->id)->orderByDesc('position')->first();
    }

    /**
     * The blocks an overlay should show, bottom to top, capped to the TOP
     * `$cap` blocks. Index 0 is the lowest block in the window, which is the
     * base only while the tower fits in the window.
     *
     * @return Collection<int, self>
     */
    public static function windowFor(User $user, int $cap): Collection
    {
        return static::where('user_id', $user->id)
            ->orderByDesc('position')
            ->limit($cap)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * The flat block shape shared by the tower.updated broadcast and the
     * overlay render payload. All values are strings; empty string means
     * absent (the null-over-placeholder rule is the renderer's). `offset`
     * and `x` are in block-width units, signed, negative is left.
     *
     * @return array<string, string>
     */
    public function toBlockArray(): array
    {
        return [
            'name' => $this->chatter_display_name,
            'login' => $this->chatter_login,
            'color' => (string) ($this->color ?? ''),
            'position' => (string) $this->position,
            'offset' => (string) $this->offset,
            'x' => (string) $this->x,
            // '1' when this block took the tower past the record, else ''.
            'record' => $this->record ? '1' : '',
            'at' => (string) $this->placed_at->getTimestamp(),
        ];
    }
}
