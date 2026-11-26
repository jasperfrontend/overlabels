<?php

namespace App\Models;

use App\Services\MapSlugService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Overlabels Overload Report (#345): the timeline of one busy stretch on
 * stream. Not the abuse report on an overlay - that is OverlayReport.
 *
 * `timeline` is a list of dots ({kind, first, last, count, ...amounts}),
 * `totals` the stretch's sum per kind. Aggregates only, never a viewer.
 * See App\Services\OverloadReports\BusyStretch.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property CarbonInterface $started_at
 * @property CarbonInterface $ended_at
 * @property int $event_count
 * @property array<string, int> $totals
 * @property list<array<string, mixed>> $timeline
 * @property CarbonInterface $created_at
 */
class OverloadReport extends Model
{
    /** How long a report is kept, and shown as its expiry date. */
    public const int KEEP_DAYS = 90;

    protected $fillable = ['user_id', 'code', 'started_at', 'ended_at', 'event_count', 'totals', 'timeline'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'totals' => 'array',
            'timeline' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Reports still within their 90 days. */
    public function scopeKept(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(self::KEEP_DAYS));
    }

    public function expiresAt(): CarbonInterface
    {
        return $this->created_at->copy()->addDays(self::KEEP_DAYS);
    }

    /**
     * The public link. The streamer part is the same opaque slug the public
     * map uses (MapSlugService); the 8 random digits are what make it
     * unguessable.
     */
    public function url(): string
    {
        return route('overload-report.show', [
            'slug' => app(MapSlugService::class)->encode($this->user->twitch_id),
            'code' => $this->code,
        ]);
    }
}
