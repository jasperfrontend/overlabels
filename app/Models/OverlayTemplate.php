<?php

namespace App\Models;

use App\Services\FunSlugGenerationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OverlayTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'type',
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
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($template) {
            if (!$template->slug) {
                $template->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
            }
        });

        static::deleting(function ($template) {
            // Prevent deletion if template is part of any kit
            if ($template->kits()->count() > 0) {
                throw new \Exception('Cannot delete a template that is part of a kit. Remove it from all kits first.');
            }
        });
    }

    /**
     * Get authenticated URL (for display purposes)
     */
    public function getAuthUrl(): string
    {
        return config('app.url') . "/overlay/$this->slug#YOUR_TOKEN_HERE";
    }

    /**
     * Extract template tags from HTML/CSS
     */
    public function extractTemplateTags(): array
    {
        $tags = [];

        // Pattern to match [[[tag_name]]] syntax including dots for event.* tags
        $pattern = '/\[\[\[([a-zA-Z0-9_.]+)(?:\|[a-zA-Z0-9_]+)?]]]/';

        // Extract from HTML
        preg_match_all($pattern, $this->html, $htmlMatches);
        $tags = array_merge($tags, $htmlMatches[1] ?? []);

        // Extract from CSS
        preg_match_all($pattern, $this->css, $cssMatches);
        $tags = array_merge($tags, $cssMatches[1] ?? []);

        // NEW: Extract tags from conditional statements
        $conditionalTags = $this->extractConditionalTags($this->html);
        $tags = array_merge($tags, $conditionalTags);

        $conditionalTags = $this->extractConditionalTags($this->css);
        $tags = array_merge($tags, $conditionalTags);

        // Remove transformation suffixes and return unique tags with re-indexed array
        $uniqueTags = array_unique(array_map(function($tag) {
            return explode('|', $tag)[0];
        }, $tags));

        // Re-index the array to ensure it's saved as a JSON array, not object
        return array_values($uniqueTags);
    }

    /**
     * Extract tags referenced in conditional statements
     */
    private function extractConditionalTags(string $content): array
    {
        $tags = [];

        // Pattern to match conditional statements: [[[if:tag_name operator value]]]
        // Also matches: [[[elseif:tag_name operator value]]]
        // Updated to properly support dots in tag names like event.bits, event.user_name
        $conditionalPattern = '/\[\[\[(?:if|elseif):([a-zA-Z0-9_.]+)(?:\s*(?:>=|<=|>|<|!=|=)\s*[^\]]+)?]]]/';

        preg_match_all($conditionalPattern, $content, $matches);

        if (!empty($matches[1])) {
            $tags = array_merge($tags, $matches[1]);
        }

        return $tags;
    }

    /**
     * Fork this template
     */
    public function fork(User $user): self
    {
        $fork = $this->replicate();
        $fork->owner_id = $user->id;
        $fork->fork_of_id = $this->id;

        // Create the forked name and limit to 100 characters
        $forkedName = 'Forked from '.$this->name;
        if (strlen($forkedName) > 70) {
            // If it's too long, truncate and add ellipsis
            $forkedName = substr($forkedName, 0, 97) . '...';
        }
        $fork->name = $forkedName;

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

    public function eventMappings(): HasMany
    {
        return $this->hasMany(EventTemplateMapping::class, 'template_id');
    }

    /**
     * Kits relationship
     */
    public function kits(): BelongsToMany
    {
        return $this->belongsToMany(
            Kit::class,
            'kit_templates',
            'overlay_template_id',
            'kit_id'
        )->withTimestamps();
    }

    /**
     * Scope for static overlays
     */
    public function scopeStatic($query)
    {
        return $query->where('type', 'static');
    }

    /**
     * Scope for alert templates
     */
    public function scopeAlert($query)
    {
        return $query->where('type', 'alert');
    }
}
