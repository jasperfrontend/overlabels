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
        'key',
        'label',
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

    const array TYPES = ['text', 'number', 'counter', 'timer', 'datetime', 'boolean', 'expression'];

    /** Service source names that cannot be used as control keys (to avoid namespace collisions in expressions). */
    const array RESERVED_KEYS = ['kofi', 'streamlabs', 'twitch', 'gpslogger', 'streamelements'];

    const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    /**
     * Sanitize a raw value for a given control type.
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
     * For service-managed controls, includes the source namespace: "kofi:kofis_received"
     * For template controls, is just the key: "goal"
     */
    public function broadcastKey(): string
    {
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
        return static::create([
            'overlay_template_id' => $template->id,
            'user_id' => $user->id,
            'key' => $data['key'],
            'label' => $data['label'] ?? null,
            'type' => $data['type'],
            'value' => isset($data['value']) ? static::sanitizeValue($data['type'], $data['value']) : null,
            'config' => $data['config'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
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
     *   "c.deaths + 1" => ["deaths"]
     *   "c.kofi.kofis_received + c.streamlabs.total_received" => ["kofi:kofis_received", "streamlabs:total_received"]
     */
    public static function extractExpressionDependencies(string $expression): array
    {
        // Match c.identifier or c.identifier.identifier patterns
        preg_match_all('/\bc\.([a-z][a-z0-9_]*)(?:\.([a-z][a-z0-9_]*))?/', $expression, $matches, PREG_SET_ORDER);

        $deps = [];
        foreach ($matches as $match) {
            if (! empty($match[2])) {
                $key = $match[2];
                // Strip _at suffix — these are virtual companion values, not real controls.
                // The base control is the actual dependency.
                $key = preg_replace('/_at$/', '', $key);
                $deps[] = $match[1].':'.$key;
            } else {
                $key = $match[1];
                $key = preg_replace('/_at$/', '', $key);
                $deps[] = $key;
            }
        }

        return array_values(array_unique($deps));
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
}
