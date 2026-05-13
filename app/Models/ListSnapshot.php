<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Point-in-time snapshot of a List's items array. Auto-created before
 * every destructive action (clear / draw / pop / restore) so the
 * streamer can undo a mistake within the 30-day retention window.
 *
 * Pinned snapshots survive the retention sweep.
 *
 * @property int $id
 * @property int $list_id
 * @property array<int, string> $items
 * @property string $reason             'before_clear'|'before_draw'|'before_pop'|'before_restore'|'manual'
 * @property int|null $triggered_by_user_id
 * @property bool $pinned
 * @property \Illuminate\Support\Carbon $created_at
 */
class ListSnapshot extends Model
{
    use HasFactory;

    public const REASON_BEFORE_CLEAR = 'before_clear';

    public const REASON_BEFORE_DRAW = 'before_draw';

    public const REASON_BEFORE_POP = 'before_pop';

    public const REASON_BEFORE_RESTORE = 'before_restore';

    public const REASON_MANUAL = 'manual';

    public const array REASONS = [
        self::REASON_BEFORE_CLEAR,
        self::REASON_BEFORE_DRAW,
        self::REASON_BEFORE_POP,
        self::REASON_BEFORE_RESTORE,
        self::REASON_MANUAL,
    ];

    // Append-only. fired_at-style: created_at is the canonical timestamp,
    // no updates expected (pinning is the one exception and that does
    // need updated_at if we want to track when it was pinned, but for
    // v1 we don't surface that, so leave it append-only).
    public $timestamps = false;

    protected $fillable = [
        'list_id',
        'items',
        'reason',
        'triggered_by_user_id',
        'pinned',
        'created_at',
    ];

    protected $casts = [
        'items' => 'array',
        'pinned' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class, 'list_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
