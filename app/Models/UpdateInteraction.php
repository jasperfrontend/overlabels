<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one user has done with one update on the What's New card.
 *
 * One fact: `dismissed_at`, the moment the post stopped being news to this
 * account. Opening the post sets it, so does the row's dismiss button, so
 * does "Mark all as seen". Undo nulls it and leaves the row.
 *
 * It is a timestamp rather than a boolean because "Mark all as seen" stamps
 * every row it touches with the same instant, and that shared value is what
 * Undo reverses as one batch.
 *
 * @property Carbon|null $dismissed_at
 */
class UpdateInteraction extends Model
{
    protected $fillable = [
        'user_id',
        'update_id',
        'dismissed_at',
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Named `post`, not `update`, because Eloquent's own Model::update() lives
     * on that name - defining a relation there would break every write.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Update::class);
    }
}
