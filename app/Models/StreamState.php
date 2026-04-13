<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperStreamState
 */
class StreamState extends Model
{
    public const string STATE_OFFLINE = 'offline';

    public const string STATE_STARTING = 'starting';

    public const string STATE_LIVE = 'live';

    public const string STATE_ENDING = 'ending';

    public const array VALID_STATES = [
        self::STATE_OFFLINE,
        self::STATE_STARTING,
        self::STATE_LIVE,
        self::STATE_ENDING,
    ];

    public const float CONFIDENCE_THRESHOLD = 0.75;

    public const float CONFIDENCE_INCREMENT = 0.25;

    public const int GRACE_PERIOD_SECONDS = 30;

    protected $fillable = [
        'user_id',
        'state',
        'confidence',
        'last_event_at',
        'last_verified_at',
        'helix_stream_id',
        'current_session_id',
        'grace_period_until',
    ];

    protected $casts = [
        'confidence' => 'float',
        'last_event_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'grace_period_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentSession(): BelongsTo
    {
        return $this->belongsTo(StreamSession::class, 'current_session_id');
    }

    /**
     * Get or create the stream state record for a user.
     */
    public static function forUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            ['state' => self::STATE_OFFLINE, 'confidence' => 0.0]
        );
    }

    /**
     * Whether the user is confidently live (state is live and confidence meets threshold).
     */
    public function isConfidentlyLive(): bool
    {
        return $this->state === self::STATE_LIVE && $this->confidence >= self::CONFIDENCE_THRESHOLD;
    }

    /**
     * Clamp confidence between 0.0 and 1.0.
     */
    public function clampConfidence(): void
    {
        $this->confidence = min(1.0, max(0.0, $this->confidence));
    }
}
