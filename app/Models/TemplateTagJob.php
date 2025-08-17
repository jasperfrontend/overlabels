<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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