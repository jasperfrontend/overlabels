<?php

namespace App\Models;

use App\Services\FunSlugGenerationService;
use Database\Factories\OverlayTemplateFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property int $owner_id
 * @property string $name
 * @property string|null $description
 * @property string $html
 * @property string|null $css
 * @property string|null $js
 * @property bool $is_public
 * @property int $version
 * @property int|null $fork_of_id
 * @property array<array-key, mixed>|null $template_tags
 * @property array<array-key, mixed>|null $metadata
 * @property int $view_count
 * @property int $fork_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $type
 * @property string|null $head
 * @property string|null $screenshot_url
 * @property-read Collection<int, OverlayControl> $controls
 * @property-read int|null $controls_count
 * @property-read Collection<int, EventTemplateMapping> $eventMappings
 * @property-read int|null $event_mappings_count
 * @property-read Collection<int, ExternalEventTemplateMapping> $externalEventMappings
 * @property-read int|null $external_event_mappings_count
 * @property-read OverlayTemplate|null $forkParent
 * @property-read Collection<int, OverlayTemplate> $forks
 * @property-read int|null $forks_count
 * @property-read Collection<int, Kit> $kits
 * @property-read int|null $kits_count
 * @property-read User|null $owner
 * @property-read Collection<int, OverlayTemplate> $targetStaticOverlays
 * @property-read int|null $target_static_overlays_count
 * @method static Builder<static>|OverlayTemplate alert()
 * @method static OverlayTemplateFactory factory($count = null, $state = [])
 * @method static Builder<static>|OverlayTemplate newModelQuery()
 * @method static Builder<static>|OverlayTemplate newQuery()
 * @method static Builder<static>|OverlayTemplate query()
 * @method static Builder<static>|OverlayTemplate static()
 * @method static Builder<static>|OverlayTemplate whereCreatedAt($value)
 * @method static Builder<static>|OverlayTemplate whereCss($value)
 * @method static Builder<static>|OverlayTemplate whereDescription($value)
 * @method static Builder<static>|OverlayTemplate whereForkCount($value)
 * @method static Builder<static>|OverlayTemplate whereForkOfId($value)
 * @method static Builder<static>|OverlayTemplate whereHead($value)
 * @method static Builder<static>|OverlayTemplate whereHtml($value)
 * @method static Builder<static>|OverlayTemplate whereId($value)
 * @method static Builder<static>|OverlayTemplate whereIsPublic($value)
 * @method static Builder<static>|OverlayTemplate whereJs($value)
 * @method static Builder<static>|OverlayTemplate whereMetadata($value)
 * @method static Builder<static>|OverlayTemplate whereName($value)
 * @method static Builder<static>|OverlayTemplate whereOwnerId($value)
 * @method static Builder<static>|OverlayTemplate whereScreenshotUrl($value)
 * @method static Builder<static>|OverlayTemplate whereSlug($value)
 * @method static Builder<static>|OverlayTemplate whereTemplateTags($value)
 * @method static Builder<static>|OverlayTemplate whereType($value)
 * @method static Builder<static>|OverlayTemplate whereUpdatedAt($value)
 * @method static Builder<static>|OverlayTemplate whereVersion($value)
 * @method static Builder<static>|OverlayTemplate whereViewCount($value)
 * @mixin Eloquent
 */
class OverlayTemplate extends Model
{
    // Transient properties set after fork() for the fork wizard
    public array $_sourceControls = [];

    public bool $_hasControls = false;

    use HasFactory;

    protected $fillable = [
        'slug',
        'type',
        'owner_id',
        'name',
        'description',
        'head',
        'html',
        'css',
        'js',
        'is_public',
        'version',
        'fork_of_id',
        'template_tags',
        'metadata',
        'screenshot_url',
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
            if (! $template->slug) {
                $template->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
            }
        });

        static::deleting(function ($template) {
            // Prevent deletion if template is part of any kit
            if ($template->kits()->count() > 0) {
                throw new Exception('Cannot delete a template that is part of a kit. Remove it from all kits first.');
            }
        });
    }

    /**
     * Get authenticated URL (for display purposes)
     */
    public function getAuthUrl(): string
    {
        return config('app.url')."/overlay/$this->slug/#YOUR_TOKEN_HERE";
    }

    /**
     * Extract template tags from HTML/CSS
     */
    public function extractTemplateTags(): array
    {
        $tags = [];

        // Pattern to match [[[tag_name]]] and [[[tag_name|formatter:args]]] syntax
        // Pipe args allow word chars, dots, colons, hyphens, and spaces (for patterns like date:dd-MM-yyyy HH:mm)
        $pattern = '/\[\[\[([a-zA-Z0-9_.][a-zA-Z0-9_.:]*?)(?:\|[a-zA-Z0-9_.:% -]+)?]]]/';

        // Extract from HTML
        preg_match_all($pattern, $this->html ?? '', $htmlMatches);
        $tags = array_merge($tags, $htmlMatches[1] ?? []);

        // Extract from CSS
        preg_match_all($pattern, $this->css ?? '', $cssMatches);
        $tags = array_merge($tags, $cssMatches[1] ?? []);

        // NEW: Extract tags from conditional statements
        $conditionalTags = $this->extractConditionalTags($this->html ?? '');
        $tags = array_merge($tags, $conditionalTags);

        $conditionalTags = $this->extractConditionalTags($this->css ?? '');
        $tags = array_merge($tags, $conditionalTags);

        // Remove transformation suffixes and return unique tags with re-indexed array
        $uniqueTags = array_unique(array_map(function ($tag) {
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
        $conditionalPattern = '/\[\[\[(?:if|elseif):([a-zA-Z0-9_.][a-zA-Z0-9_.:]*?)(?:\s*(?:>=|<=|>|<|!=|=)\s*[^]]+)?]]]/';

        preg_match_all($conditionalPattern, $content, $matches);

        if (! empty($matches[1])) {
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
            $forkedName = substr($forkedName, 0, 97).'...';
        }
        $fork->name = $forkedName;

        $fork->version = 1;
        $fork->view_count = 0;
        $fork->fork_count = 0;
        $fork->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
        $fork->save();

        $this->increment('fork_count');

        // Attach source controls metadata for the fork wizard (not persisted to DB)
        $sourceControls = $this->controls()
            ->select(['key', 'label', 'type', 'config', 'sort_order'])
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $fork->_sourceControls = $sourceControls;
        $fork->_hasControls = count($sourceControls) > 0;

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

    public function externalEventMappings(): HasMany
    {
        return $this->hasMany(ExternalEventTemplateMapping::class, 'overlay_template_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(OverlayControl::class, 'overlay_template_id');
    }

    /**
     * kits relationship
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
     * Static overlays this alert template is configured to fire on.
     * Empty = fires on all static overlays (no restriction).
     */
    public function targetStaticOverlays(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'alert_template_static_overlays',
            'alert_template_id',
            'static_overlay_id'
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
