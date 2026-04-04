<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $job_type
 * @property string $status
 * @property string|null $job_id
 * @property array<array-key, mixed>|null $progress
 * @property array<array-key, mixed>|null $result
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereJobType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereProgress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TemplateTagJob whereUserId($value)
 * @mixin \Eloquent
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
