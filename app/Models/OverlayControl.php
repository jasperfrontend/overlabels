<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\OverlayControlFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Random\RandomException;

/**
 * @property int $id
 * @property int|null $overlay_template_id
 * @property int $user_id
 * @property string $key
 * @property string|null $label
 * @property string|null $description
 * @property string $type
 * @property string|null $value
 * @property array<array-key, mixed>|null $config
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source
 * @property bool $source_managed
 * @property-read OverlayTemplate|null $template
 * @property-read User|null $user
 *
 * @method static OverlayControlFactory factory($count = null, $state = [])
 * @method static Builder<static>|OverlayControl newModelQuery()
 * @method static Builder<static>|OverlayControl newQuery()
 * @method static Builder<static>|OverlayControl query()
 * @method static Builder<static>|OverlayControl whereConfig($value)
 * @method static Builder<static>|OverlayControl whereCreatedAt($value)
 * @method static Builder<static>|OverlayControl whereId($value)
 * @method static Builder<static>|OverlayControl whereKey($value)
 * @method static Builder<static>|OverlayControl whereLabel($value)
 * @method static Builder<static>|OverlayControl whereOverlayTemplateId($value)
 * @method static Builder<static>|OverlayControl whereSortOrder($value)
 * @method static Builder<static>|OverlayControl whereSource($value)
 * @method static Builder<static>|OverlayControl whereSourceManaged($value)
 * @method static Builder<static>|OverlayControl whereType($value)
 * @method static Builder<static>|OverlayControl whereUpdatedAt($value)
 * @method static Builder<static>|OverlayControl whereUserId($value)
 * @method static Builder<static>|OverlayControl whereValue($value)
 *
 * @mixin Eloquent
 */
class OverlayControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'overlay_template_id',
        'user_id',
        'recipe_instance_id',
        'key',
        'label',
        'description',
        'type',
        'value',
        'config',
        'sort_order',
        'source',
        'source_managed',
    ];

    protected $casts = [
        'config' => 'array',
        'sort_order' => 'integer',
        'source_managed' => 'boolean',
    ];

    const array TYPES = ['text', 'number', 'counter', 'timer', 'datetime', 'boolean', 'expression', 'list_writer'];

    /** Service source names that cannot be used as control keys (to avoid namespace collisions in expressions). */
    const array RESERVED_KEYS = ['kofi', 'streamlabs', 'twitch', 'streamelements', 'gps', 'alerts', 'fourthwall', 'bmac', 'throne'];

    const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    /**
     * Sanitise a raw value for a given control type.
     */
    public static function sanitizeValue(string $type, mixed $raw): string
    {
        return match ($type) {
            'text', 'expression', 'datetime' => strip_tags((string) $raw),
            'number', 'counter' => is_numeric($raw) ? (string) $raw : '0',
            'boolean' => in_array($raw, ['1', 'true', true, 1], true) ? '1' : '0',
            default => '', // timer: value derived from config
        };
    }

    /**
     * Resolve the display value for this control.
     * For timer: compute elapsed seconds from config state.
     * For all others: return stored value.
     *
     * @throws RandomException
     */
    public function resolveDisplayValue(): string
    {
        if ($this->type === 'timer') {
            return $this->resolveTimerDisplayValue();
        }

        if ($this->isRandom()) {
            return $this->resolveRandomValue();
        }

        return $this->value ?? '';
    }

    /**
     * Check if this control is a random-mode number/counter.
     */
    public function isRandom(): bool
    {
        if (! in_array($this->type, ['number', 'counter'])) {
            return false;
        }

        return (bool) ($this->config['random'] ?? false);
    }

    /**
     * Generate a random integer between the configured min and max.
     *
     * @throws RandomException
     */
    private function resolveRandomValue(): string
    {
        $config = $this->config ?? [];
        $min = (int) ($config['min'] ?? 0);
        $max = (int) ($config['max'] ?? 100);

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        return (string) random_int($min, $max);
    }

    private function resolveTimerDisplayValue(): string
    {
        $config = $this->config ?? [];
        $mode = $config['mode'] ?? 'countup';
        $baseSeconds = (int) ($config['base_seconds'] ?? 0);
        $offsetSeconds = (int) ($config['offset_seconds'] ?? 0);
        $running = (bool) ($config['running'] ?? false);
        $startedAt = $config['started_at'] ?? null;

        if ($mode === 'countto') {
            $targetDatetime = $config['target_datetime'] ?? null;
            if (! $targetDatetime) {
                return '0';
            }
            $target = Carbon::parse($targetDatetime);
            $remaining = (int) now()->diffInSeconds($target);

            return (string) max(0, $remaining);
        }

        $elapsed = $offsetSeconds;

        if ($running && $startedAt) {
            $startTime = Carbon::parse($startedAt);
            $elapsed = $offsetSeconds + (int) $startTime->diffInSeconds(now());
        }

        if ($mode === 'countdown') {
            return (string) max(0, $baseSeconds - $elapsed);
        }

        return (string) $elapsed;
    }

    /**
     * The broadcast key used for ControlValueUpdated events.
     * For recipe-managed controls: "<recipe_slug>:<instance_slug>:<key>" (e.g. "coin_flip:lolwheel:result")
     * For service-managed controls: "<source>:<key>" (e.g. "kofi:donations_received")
     * For template controls: just the key (e.g. "goal")
     */
    public function broadcastKey(): string
    {
        if ($this->recipe_instance_id) {
            $instance = $this->recipeInstance;
            $recipe = $instance?->recipe;
            if ($instance && $recipe) {
                return "{$recipe->slug}:{$instance->instance_slug}:{$this->key}";
            }
        }

        if ($this->source) {
            return "$this->source:$this->key";
        }

        return $this->key;
    }

    /**
     * Single entry point to create a control for a template.
     * Validates key uniqueness within the template.
     */
    public static function createForTemplate(OverlayTemplate $template, User $user, array $data): self
    {
        $source = $data['source'] ?? null;

        return static::create([
            'overlay_template_id' => $template->id,
            'user_id' => $user->id,
            'key' => $data['key'],
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => isset($data['value']) ? static::sanitizeValue($data['type'], $data['value']) : null,
            'config' => $data['config'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'source' => $source,
            'source_managed' => ! empty($source) ? (bool) ($data['source_managed'] ?? true) : false,
        ]);
    }

    /**
     * Create or update a service-managed (user-scoped) control.
     * These controls have overlay_template_id = NULL.
     */
    public static function provisionServiceControl(User $user, string $source, array $data): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $user->id,
                'source' => $source,
                'key' => $data['key'],
            ],
            [
                'overlay_template_id' => null,
                'label' => $data['label'] ?? null,
                'type' => $data['type'],
                'value' => $data['value'] ?? '0',
                'config' => $data['config'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'source_managed' => true,
            ]
        );
    }

    /**
     * Check if this control is an expression type.
     */
    public function isExpression(): bool
    {
        return $this->type === 'expression';
    }

    public function isListWriter(): bool
    {
        return $this->type === 'list_writer';
    }

    /**
     * For list_writer controls, return the source control ID this writer
     * subscribes to. Stored on config so the column model stays flat.
     */
    public function listWriterSourceId(): ?int
    {
        if (! $this->isListWriter()) {
            return null;
        }
        $id = $this->config['source_control_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /**
     * For list_writer controls, return the target list ID this writer
     * appends into.
     */
    public function listWriterTargetId(): ?int
    {
        if (! $this->isListWriter()) {
            return null;
        }
        $id = $this->config['target_list_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /**
     * Get all dependency broadcast keys for an expression control.
     * Returns empty array if not an expression or has no dependencies.
     */
    public function getExpressionDependencies(): array
    {
        if (! $this->isExpression()) {
            return [];
        }

        return $this->config['dependencies'] ?? [];
    }

    /**
     * Extract control references from an expression string.
     * Parses "c.key" and "c.source.sub_key" patterns, returning broadcast keys.
     *
     * Examples:
     *   "c.wins + 1" => ["wins"]
     *   "c.kofi.donations_received + c.streamlabs.total_received" => ["kofi:donations_received", "streamlabs:total_received"]
     */
    public static function extractExpressionDependencies(string $expression): array
    {
        $deps = [];

        // Dot notation: c.key or c.source.key
        preg_match_all('/\bc\.([a-z][a-z0-9_]*)(?:\.([a-z][a-z0-9_]*))?/', $expression, $dotMatches, PREG_SET_ORDER);
        foreach ($dotMatches as $match) {
            if (! empty($match[2])) {
                // Strip _at suffix — these are virtual companion values, not real controls.
                // The base control is the actual dependency.
                $key = preg_replace('/_at$/', '', $match[2]);
                $deps[] = $match[1].':'.$key;
            } else {
                $key = preg_replace('/_at$/', '', $match[1]);
                $deps[] = $key;
            }
        }

        // Bracket notation for hyphenated service names: c["source"].key or c["source"]["key"]
        // Example: c["overlabels-mobile"].gps_lat. Single-quoted form also accepted.
        preg_match_all(
            '/\bc\[([\'"])([a-z][a-z0-9_\-]*)\1\](?:\.([a-z][a-z0-9_]*)|\[([\'"])([a-z][a-z0-9_]*)\4\])?/',
            $expression,
            $bracketMatches,
            PREG_SET_ORDER
        );
        foreach ($bracketMatches as $match) {
            $source = $match[2];
            $key = $match[3] ?? '';
            if ($key === '' && isset($match[5])) {
                $key = $match[5];
            }
            if ($key === '') {
                continue;
            }
            $key = preg_replace('/_at$/', '', $key);
            $deps[] = $source.':'.$key;
        }

        return array_values(array_unique($deps));
    }

    /**
     * Extract Twitch template-tag references from an expression string.
     * Parses `t.<name>` patterns and returns the list of tag names.
     *
     * These are not controls; they're keys resolved against the user's
     * `template_tags` table and live EventSub mutations. The renderer uses
     * this list to widen its data allowlist so an expression can read a
     * tag that isn't referenced in the HTML.
     *
     * Example:
     *   "t.followers_total + t.subscribers_total" => ["followers_total", "subscribers_total"]
     */
    public static function extractTwitchTagReferences(string $expression): array
    {
        if ($expression === '') {
            return [];
        }

        if (! preg_match_all('/\bt\.([a-z][a-z0-9_]*)/', $expression, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Get controls available as dependencies for expression controls.
     */
    public static function getAvailableControls(User $user, ?int $templateId, ?int $excludeId = null): Collection
    {
        $query = static::where('user_id', $user->id);

        if ($templateId) {
            $query->where(function ($q) use ($templateId) {
                $q->where('overlay_template_id', $templateId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('overlay_template_id')
                            ->where('source_managed', true);
                    });
            });
        } else {
            $query->whereNull('overlay_template_id');
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Detect if saving this expression would create a circular dependency.
     */
    public static function detectExpressionCycle(self $control, array $dependencies, ?int $templateId): bool
    {
        return static::dfsDetectExpressionCycle(
            $control->id,
            $control->user_id,
            $dependencies,
            $templateId,
            [],
            0
        );
    }

    private static function dfsDetectExpressionCycle(int $originId, int $userId, array $dependencies, ?int $templateId, array $visited, int $depth): bool
    {
        if ($depth >= 5) {
            return false;
        }

        foreach ($dependencies as $dep) {
            $colonIdx = strpos($dep, ':');
            if ($colonIdx !== false) {
                $depSource = substr($dep, 0, $colonIdx);
                $depKey = substr($dep, $colonIdx + 1);
            } else {
                $depSource = null;
                $depKey = $dep;
            }

            $query = static::where('user_id', $userId)->where('key', $depKey);
            if ($depSource) {
                $query->where('source', $depSource);
            } else {
                $query->whereNull('source');
            }
            if ($templateId) {
                $query->where(function ($q) use ($templateId) {
                    $q->where('overlay_template_id', $templateId)
                        ->orWhereNull('overlay_template_id');
                });
            } else {
                $query->whereNull('overlay_template_id');
            }

            foreach ($query->get() as $watched) {
                /** @var OverlayControl $watched */
                if ($watched->id === $originId) {
                    return true;
                }
                if (in_array($watched->id, $visited)) {
                    continue;
                }
                $visited[] = $watched->id;

                if ($watched->isExpression()) {
                    $watchedDeps = $watched->getExpressionDependencies();
                    if (! empty($watchedDeps) && static::dfsDetectExpressionCycle($originId, $userId, $watchedDeps, $watched->overlay_template_id ?? $templateId, $visited, $depth + 1)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Relationships
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'overlay_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipeInstance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class);
    }
}
