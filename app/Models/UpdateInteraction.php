<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one user has done with one update on the What's New card.
 *
 * `visited_at` and `dismissed_at` are independent. Visiting greys a row out
 * but leaves it on the card, because "I have seen where this goes" is not the
 * same claim as "stop showing me this". Dismissing removes it.
 *
 * @property Carbon|null $visited_at
 * @property Carbon|null $dismissed_at
 */
class UpdateInteraction extends Model
{
    protected $fillable = [
        'user_id',
        'update_id',
        'visited_at',
        'dismissed_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
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
