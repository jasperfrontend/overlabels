<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A streamer's saved Freesound sound (metadata only - we never host the audio
 * file). Powers the per-user library shown in the Sound tab on alert template
 * editors, and the attribution surface when an alert references a Freesound URL.
 */
class UserFreesoundSound extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'freesound_id',
        'name',
        'author',
        'license',
        'preview_url',
        'duration',
        'freesound_url',
    ];

    protected $casts = [
        'freesound_id' => 'integer',
        'duration' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
