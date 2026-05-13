<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger of every successful list-append fire. Powers:
 *   - per_chatter / per_chatter_per_stream dedup checks
 *   - audit ("who joined the raffle and when, with what value")
 *   - future "see who contributed to this list" UI
 *
 * @property int $id
 * @property int $list_appender_id
 * @property int $target_list_id
 * @property string $chatter_id
 * @property string $chatter_login
 * @property string $value
 * @property int|null $stream_session_id
 * @property \Illuminate\Support\Carbon $fired_at
 */
class ListAppendHistory extends Model
{
    use HasFactory;

    protected $table = 'list_append_history';

    // Append-only - never updated. fired_at IS the timestamp; no
    // separate created_at column on the migration, so Eloquent's auto-
    // timestamps would try to write a non-existent column.
    public $timestamps = false;

    protected $fillable = [
        'list_appender_id',
        'target_list_id',
        'chatter_id',
        'chatter_login',
        'value',
        'stream_session_id',
        'fired_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
    ];

    public function appender(): BelongsTo
    {
        return $this->belongsTo(ListAppender::class, 'list_appender_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class, 'target_list_id');
    }
}
