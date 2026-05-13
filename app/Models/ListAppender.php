<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chat command that appends to a List (option_set) when invoked.
 *
 * @property int $id
 * @property int $user_id
 * @property int $target_list_id
 * @property string $command           Without leading "!"
 * @property string $permission_level
 * @property int $cooldown_seconds
 * @property string $value_template
 * @property string|null $args_empty_reply
 * @property string $dedup_policy      'none' | 'per_chatter' | 'per_chatter_per_stream'
 * @property int|null $max_size
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon|null $last_fired_at
 * @property-read OptionSet|null $targetList
 * @property-read User|null $user
 */
class ListAppender extends Model
{
    use HasFactory;

    public const DEDUP_NONE = 'none';

    public const DEDUP_PER_CHATTER = 'per_chatter';

    public const DEDUP_PER_CHATTER_PER_STREAM = 'per_chatter_per_stream';

    public const array DEDUP_POLICIES = [
        self::DEDUP_NONE,
        self::DEDUP_PER_CHATTER,
        self::DEDUP_PER_CHATTER_PER_STREAM,
    ];

    protected $fillable = [
        'user_id',
        'target_list_id',
        'command',
        'permission_level',
        'cooldown_seconds',
        'value_template',
        'args_empty_reply',
        'dedup_policy',
        'max_size',
        'enabled',
        'last_fired_at',
    ];

    protected $casts = [
        'cooldown_seconds' => 'integer',
        'max_size' => 'integer',
        'enabled' => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetList(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class, 'target_list_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ListAppendHistory::class);
    }
}
