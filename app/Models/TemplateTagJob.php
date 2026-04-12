<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $job_type
 * @property string $status
 * @property string|null $job_id
 * @property array<array-key, mixed>|null $progress
 * @property array<array-key, mixed>|null $result
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static Builder<static>|TemplateTagJob newModelQuery()
 * @method static Builder<static>|TemplateTagJob newQuery()
 * @method static Builder<static>|TemplateTagJob query()
 * @method static Builder<static>|TemplateTagJob whereCompletedAt($value)
 * @method static Builder<static>|TemplateTagJob whereCreatedAt($value)
 * @method static Builder<static>|TemplateTagJob whereErrorMessage($value)
 * @method static Builder<static>|TemplateTagJob whereId($value)
 * @method static Builder<static>|TemplateTagJob whereJobId($value)
 * @method static Builder<static>|TemplateTagJob whereJobType($value)
 * @method static Builder<static>|TemplateTagJob whereProgress($value)
 * @method static Builder<static>|TemplateTagJob whereResult($value)
 * @method static Builder<static>|TemplateTagJob whereStartedAt($value)
 * @method static Builder<static>|TemplateTagJob whereStatus($value)
 * @method static Builder<static>|TemplateTagJob whereUpdatedAt($value)
 * @method static Builder<static>|TemplateTagJob whereUserId($value)
 *
 * @mixin Eloquent
 */
class TemplateTagJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_type',
        'status',
        'job_id',
        'progress',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'progress' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(array $progress): void
    {
        $this->update(['progress' => $progress]);
    }
}
