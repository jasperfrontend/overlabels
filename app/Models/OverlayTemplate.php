<?php

namespace App\Models;

use App\Services\FunSlugGenerationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OverlayTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'owner_id',
        'name',
        'description',
        'html',
        'css',
        'js',
        'is_public',
        'version',
        'fork_of_id',
        'template_tags',
        'metadata',
        'view_count',
        'fork_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'template_tags' => 'array',
        'metadata' => 'array',
        'version' => 'integer',
        'view_count' => 'integer',
        'fork_count' => 'integer',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (!$template->slug) {
                $template->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
            }
        });
    }

    /**
     * Get public URL
     */
    public function getPublicUrl(): string
    {
        return config('app.url') . "/overlay/{$this->slug}/public";
    }

    /**
     * Get authenticated URL (for display purposes)
     */
    public function getAuthUrl(): string
    {
        return config('app.url') . "/overlay/{$this->slug}#YOUR_TOKEN_HERE";
    }

    /**
     * Extract template tags from HTML/CSS
     */
    public function extractTemplateTags(): array
    {
        $tags = [];
        // Updated pattern to match [[[tag_name]]] syntax
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)(?:\|[a-zA-Z0-9_]+)?\]\]\]/';

        // Extract from HTML
        preg_match_all($pattern, $this->html, $htmlMatches);
        $tags = array_merge($tags, $htmlMatches[1] ?? []);

        // Extract from CSS
        preg_match_all($pattern, $this->css, $cssMatches);
        $tags = array_merge($tags, $cssMatches[1] ?? []);

        // Remove transformation suffixes and return unique tags
        return array_unique(array_map(function($tag) {
            return explode('|', $tag)[0];
        }, $tags));
    }

    /**
     * Fork this template
     */
    public function fork(User $user): self
    {
        $fork = $this->replicate();
        $fork->owner_id = $user->id;
        $fork->fork_of_id = $this->id;
        $fork->version = 1;
        $fork->view_count = 0;
        $fork->fork_count = 0;
        $fork->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
        $fork->save();

        $this->increment('fork_count');

        return $fork;
    }

    /**
     * Increment view count
     */
    public function recordView(): void
    {
        $this->increment('view_count');
    }

    /**
     * Relationships
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function forkParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fork_of_id');
    }

    public function forks(): HasMany
    {
        return $this->hasMany(self::class, 'fork_of_id');
    }
}
