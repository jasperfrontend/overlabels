<?php

namespace App\Models;

use App\Services\FunSlugGenerationService;
use App\Services\TemplateDataMapperService;
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
 *
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
 *
 * @mixin Eloquent
 * @mixin IdeHelperOverlayTemplate
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
        'compiled_css',
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
     * Extract template tags from HTML/CSS.
     *
     * @param  array<string,int>|null  $caps  Per-user foreach caps keyed by
     *                                        preference key (subscribers, goals, followers, followed). When
     *                                        null, the owner's foreachCaps() are used; falls back to defaults
     *                                        if no owner is loaded.
     */
    public function extractTemplateTags(?array $caps = null): array
    {
        $caps = $caps ?? $this->owner?->foreachCaps() ?? [];
        $tags = [];

        // Pattern to match [[[tag_name]]] and [[[tag_name|formatter:args]]] syntax
        // Pipe args allow word chars, dots, colons, hyphens, and spaces (for patterns like date:dd-MM-yyyy HH:mm)
        $pattern = '/\[\[\[([a-zA-Z0-9_.][a-zA-Z0-9_.:\-]*?)(?:\|[a-zA-Z0-9_.:% -]+)?]]]/';

        // Extract from HTML
        preg_match_all($pattern, $this->html ?? '', $htmlMatches);
        $tags = array_merge($tags, $htmlMatches[1] ?? []);

        // Extract from CSS
        preg_match_all($pattern, $this->css ?? '', $cssMatches);
        $tags = array_merge($tags, $cssMatches[1] ?? []);

        // Extract tags from conditional statements
        $conditionalTags = $this->extractConditionalTags($this->html ?? '');
        $tags = array_merge($tags, $conditionalTags);

        $conditionalTags = $this->extractConditionalTags($this->css ?? '');
        $tags = array_merge($tags, $conditionalTags);

        // Foreach loops reference scoped aliases inside the body
        // (e.g. `[[[choice.title]]]`) rather than the real flat keys
        // (`event.choices.0.title`). Expand each loop to the concrete indexed
        // data keys so the template-data mapper includes them in the response.
        $foreachAliases = [];
        $effectiveCaps = $this->buildEffectiveForeachCaps($caps);
        foreach ([$this->html ?? '', $this->css ?? ''] as $source) {
            [$expandedTags, $aliasesInSource] = $this->extractForeachTags($source, $effectiveCaps);
            $tags = array_merge($tags, $expandedTags);
            $foreachAliases = array_merge($foreachAliases, $aliasesInSource);
        }
        $foreachAliases = array_unique($foreachAliases);

        // Remove transformation suffixes
        $tags = array_map(function ($tag) {
            return explode('|', $tag)[0];
        }, $tags);

        // Drop scope-local tokens (alias.*, loop.*) - they never correspond to
        // real data keys, so they'd otherwise pollute the template_tags list.
        $tags = array_filter($tags, function (string $tag) use ($foreachAliases) {
            if ($tag === 'loop' || str_starts_with($tag, 'loop.')) {
                return false;
            }
            foreach ($foreachAliases as $alias) {
                if ($tag === $alias || str_starts_with($tag, $alias.'.')) {
                    return false;
                }
            }

            return true;
        });

        // Re-index the array to ensure it's saved as a JSON array, not object
        return array_values(array_unique($tags));
    }

    /**
     * Hard caps for Twitch event list fields - these match Twitch's own limits
     * and the INDEXED_LIST_FIELDS in TemplateDataMapperService. Not user-configurable.
     */
    private const FOREACH_EVENT_CAPS = [
        'choices' => 5,
        'winners' => 5,
        'outcomes' => 10,
        'top_contributions' => 3,
    ];

    private const FOREACH_DEFAULT_CAP = 10;

    /**
     * Build a lookup keyed by the final dotted segment of the iterable
     * (e.g. `event.choices` -> `choices`, `subscribers` -> `subscribers`) to
     * the cap that should apply. Merges event caps (hardcoded) with user-scope
     * caps from preferences so one lookup covers both.
     *
     * @param  array<string,int>  $userCaps  Keyed by user preference key
     *                                       (subscribers, goals, followers, followed).
     */
    private function buildEffectiveForeachCaps(array $userCaps): array
    {
        $effective = self::FOREACH_EVENT_CAPS;

        foreach (TemplateDataMapperService::userScopeIterables() as $prefKey => $spec) {
            $cap = (int) ($userCaps[$prefKey] ?? $spec['default_cap']);
            $alias = $spec['alias'];
            // The last segment of the alias is what extractForeachTags keys on
            // (e.g. `channel_followers` -> `channel_followers`).
            $segments = explode('.', $alias);
            $effective[end($segments)] = $cap;
        }

        return $effective;
    }

    /**
     * Expand each `[[[foreach:X as Y]]] ... [[[endforeach]]]` block into the
     * concrete data keys its body references. Returns [$expandedTags, $aliases]
     * so the caller can also strip the scope-local aliases from the final list.
     *
     * @param  array<string,int>  $effectiveCaps  Result of buildEffectiveForeachCaps.
     * @return array{0: string[], 1: string[]}
     */
    private function extractForeachTags(string $content, array $effectiveCaps): array
    {
        $expanded = [];
        $aliases = [];

        // Non-nested match is fine here - we only need to discover the
        // (iterable, alias, body) tuple. The frontend handles actual nesting.
        $pattern = '/\[\[\[foreach:\s*([a-zA-Z0-9_.:\-]+)\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*]]](.*?)\[\[\[endforeach]]]/s';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return [$expanded, $aliases];
        }

        foreach ($matches as $m) {
            $iterable = $m[1];
            $alias = $m[2];
            $body = $m[3];
            $aliases[] = $alias;

            // `iterable.count` is emitted by the data mapper for INDEXED_LIST_FIELDS
            $expanded[] = $iterable.'.count';

            $subkeys = [];

            // Body tokens: [[[alias]]] or [[[alias.sub.path]]] (with optional pipe)
            $bodyPattern = '/\[\[\['.preg_quote($alias, '/').'(?:\.([a-zA-Z0-9_.:\-]+))?(?:\|[^]]+)?]]]/';
            if (preg_match_all($bodyPattern, $body, $bodyMatches, PREG_SET_ORDER)) {
                foreach ($bodyMatches as $bm) {
                    if (! empty($bm[1])) {
                        $subkeys[] = $bm[1];
                    }
                }
            }

            // Conditionals referencing the alias: [[[if:alias.sub = ...]]]
            $condPattern = '/\[\[\[(?:if|elseif):\s*'.preg_quote($alias, '/').'\.([a-zA-Z0-9_.]+)/';
            if (preg_match_all($condPattern, $body, $condMatches, PREG_SET_ORDER)) {
                foreach ($condMatches as $cm) {
                    $subkeys[] = $cm[1];
                }
            }

            $subkeys = array_unique($subkeys);

            // Determine cap for this iterable. Use the last dotted segment
            // (e.g. `event.choices` -> `choices`) to key into effective caps.
            $segments = explode('.', $iterable);
            $last = end($segments);
            $cap = $effectiveCaps[$last] ?? self::FOREACH_DEFAULT_CAP;

            for ($i = 0; $i < $cap; $i++) {
                if (empty($subkeys)) {
                    // Scalar iterable (uncommon but possible)
                    $expanded[] = $iterable.'.'.$i;

                    continue;
                }
                foreach ($subkeys as $sk) {
                    $expanded[] = $iterable.'.'.$i.'.'.$sk;
                }
            }
        }

        return [$expanded, $aliases];
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
     * Detect which external services are referenced in the template HTML/CSS
     * by scanning for [[[c:service:key]]] patterns.
     *
     * @return string[] List of unique service keys (e.g. ['kofi', 'streamlabs'])
     */
    public function detectRequiredServices(): array
    {
        $services = [];
        $pattern = '/\[\[\[c:([a-zA-Z0-9_]+):[a-zA-Z0-9_]+(?:\|[a-zA-Z0-9_.:% -]+)?]]]/';

        foreach (['html', 'css', 'head'] as $field) {
            preg_match_all($pattern, $this->{$field} ?? '', $matches);
            $services = array_merge($services, $matches[1] ?? []);
        }

        return array_values(array_unique($services));
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
        $copiedName = 'Copy: '.$this->owner->name.' - '.$this->name;
        if (strlen($copiedName) > 70) {
            // If it's too long, truncate and add ellipsis
            $copiedName = substr($copiedName, 0, 97).'...';
        }
        $fork->name = $copiedName;

        $fork->version = 1;
        $fork->view_count = 0;
        $fork->fork_count = 0;
        $fork->slug = app(FunSlugGenerationService::class)->generateUniqueSlug();
        $fork->save();

        $this->increment('fork_count');

        // Attach source controls metadata for the fork wizard (not persisted to DB)
        $sourceControls = $this->controls()
            ->select(['key', 'label', 'description', 'type', 'value', 'config', 'sort_order', 'source', 'source_managed'])
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        $fork->_sourceControls = $sourceControls;
        $fork->_hasControls = count($sourceControls) > 0;

        // Detect which external services are referenced in template HTML/CSS
        // by scanning for [[[c:service:key]]] patterns
        $fork->_requiredServices = $this->detectRequiredServices();

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
