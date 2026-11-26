<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A short note on the dashboard about a feature worth knowing, with one link
 * into the app. Written at /admin/highlights. Every highlight looks the same
 * on the dashboard: prose and a link, nothing per-highlight to style.
 *
 * @property int $id
 * @property string $body
 * @property string $link_url
 * @property string $link_label
 * @property int $sort_order
 * @property bool $enabled
 */
class DashboardHighlight extends Model
{
    protected $fillable = ['body', 'link_url', 'link_label', 'sort_order', 'enabled'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /** What the dashboard shows: enabled ones, in order. */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('sort_order')->orderBy('id');
    }
}
