<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user-submitted report about a publicly listed overlay.
 *
 * @property int $id
 * @property int|null $overlay_template_id
 * @property string $template_slug
 * @property string $template_name
 * @property int|null $reporter_user_id
 * @property string|null $reporter_email
 * @property string $reason
 * @property string $status
 * @property string|null $ip_address
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $reporter
 * @property-read User|null $reviewer
 */
class OverlayReport extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_READ = 'read';

    protected $fillable = [
        'overlay_template_id',
        'template_slug',
        'template_name',
        'reporter_user_id',
        'reporter_email',
        'reason',
        'status',
        'ip_address',
        'reviewed_at',
        'reviewed_by_id',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'overlay_template_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * How the reporter identified themselves. Logged-in reporters are their
     * Twitch username; anonymous ones are the email they typed.
     */
    public function reporterLabel(): ?string
    {
        return $this->reporter?->name ?? $this->reporter_email;
    }
}
