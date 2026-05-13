<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-per-user configuration for the `!list` meta-command. The command
 * name is configurable (defaults to "list") but the action vocabulary
 * underneath is platform-fixed. Permission is hardcoded to mod+ since
 * the action vocabulary is destructive or list-emitting; this isn't an
 * "everyone" surface.
 *
 * @property int $id
 * @property int $user_id
 * @property string $command            Without leading "!"
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon|null $last_fired_at
 */
class ListMetaCommand extends Model
{
    use HasFactory;

    /** All actions require mod-level or higher. Not configurable per-user. */
    public const PERMISSION_LEVEL = 'moderator';

    protected $fillable = [
        'user_id',
        'command',
        'enabled',
        'last_fired_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
