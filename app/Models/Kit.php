<?php

namespace App\Models;

use App\Services\Alerts\AlertEventSamples;
use Database\Factories\KitFactory;
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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $title
 * @property string|null $description
 * @property string|null $thumbnail
 * @property bool $is_public
 * @property int|null $forked_from_id
 * @property int $fork_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_starter_kit
 * @property array|null $alert_triggers
 * @property-read Kit|null $forkedFrom
 * @property-read Collection<int, Kit> $forks
 * @property-read int|null $forks_count
 * @property-read string|null $thumbnail_url
 * @property-read User|null $owner
 * @property-read Collection<int, OverlayTemplate> $templates
 * @property-read int|null $templates_count
 *
 * @method static KitFactory factory($count = null, $state = [])
 * @method static Builder<static>|Kit newModelQuery()
 * @method static Builder<static>|Kit newQuery()
 * @method static Builder<static>|Kit ownedBy($userId)
 * @method static Builder<static>|Kit public()
 * @method static Builder<static>|Kit query()
 * @method static Builder<static>|Kit whereCreatedAt($value)
 * @method static Builder<static>|Kit whereDescription($value)
 * @method static Builder<static>|Kit whereForkCount($value)
 * @method static Builder<static>|Kit whereForkedFromId($value)
 * @method static Builder<static>|Kit whereId($value)
 * @method static Builder<static>|Kit whereIsPublic($value)
 * @method static Builder<static>|Kit whereIsStarterKit($value)
 * @method static Builder<static>|Kit whereOwnerId($value)
 * @method static Builder<static>|Kit whereThumbnail($value)
 * @method static Builder<static>|Kit whereTitle($value)
 * @method static Builder<static>|Kit whereUpdatedAt($value)
 *
 * @mixin Eloquent
 * @mixin IdeHelperKit
 */
class Kit extends Model
{
    use HasFactory;

    /**
     * Most templates a kit may hold.
     *
     * This is a rate-limiting concern, not a product one. fork() loops the kit's
     * templates and forks each, so one request to kits.fork creates as many rows
     * (and burns as many slugs) as the kit holds. Without a ceiling that request
     * is an unbounded write amplifier, and a per-request throttle cannot cap
     * something whose per-request cost is unbounded.
     *
     * 50 is deliberately far above real use: the largest kit that exists holds
     * 9 templates and the heaviest user owns 27 in total.
     */
    public const MAX_TEMPLATES = 50;

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'thumbnail',
        'is_public',
        'is_starter_kit',
        'forked_from_id',
        'fork_count',
        'alert_triggers',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_starter_kit' => 'boolean',
        'fork_count' => 'integer',
        'alert_triggers' => 'array',
    ];

    /**
     * What a trigger gets when its author did not say, matching the column
     * default on both mapping tables.
     */
    public const int DEFAULT_TRIGGER_DURATION_MS = 5000;

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * Set only for the duration of an account erasure. See withoutForkGuard().
     */
    protected static bool $forkGuardDisabled = false;

    /**
     * Run a callback with the "this kit has copies" delete guard lifted.
     *
     * The guard stops someone destroying a kit other people have built on, and
     * every interactive path keeps it: KitController::destroy checks
     * canBeDeleted() and shows an error. But it must not be able to strand an
     * account that has asked to be erased, which is what it did - a single
     * copied kit made the whole deletion throw, after the user had already been
     * logged out.
     *
     * Nothing of the copier's is lost. A copy is an independent row and
     * kits.forked_from_id is ON DELETE SET NULL, so it simply stops pointing at
     * a parent that no longer exists.
     */
    public static function withoutForkGuard(callable $callback): mixed
    {
        self::$forkGuardDisabled = true;

        try {
            return $callback();
        } finally {
            self::$forkGuardDisabled = false;
        }
    }

    /**
     * Boot method
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($kit) {
            // Prevent deletion if kit has been forked
            if (! self::$forkGuardDisabled && $kit->fork_count > 0) {
                throw new Exception('Cannot delete a kit that has been forked.');
            }

            // Delete thumbnail if exists and is local storage (not a hosted URL)
            if ($kit->thumbnail && ! filter_var($kit->thumbnail, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($kit->thumbnail)) {
                Storage::disk('public')->delete($kit->thumbnail);
            }
        });
    }

    /**
     * Owner relationship
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Templates relationship
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            OverlayTemplate::class,
            'kit_templates',
            'kit_id',
            'overlay_template_id'
        )->withTimestamps();
    }

    /**
     * Fork parent relationship
     */
    public function forkedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forked_from_id');
    }

    /**
     * Forks relationship
     */
    public function forks(): HasMany
    {
        return $this->hasMany(self::class, 'forked_from_id');
    }

    /**
     * The kit's declared alert triggers, cleaned: anything malformed, naming an
     * event alerts cannot be triggered by, or pointing at a template that is
     * not an alert in this kit, is dropped rather than trusted. The column is
     * written through validation, but templates leave kits and the event list
     * changes, and a stale row must never become a mapping.
     *
     * @return list<array{template_id:int,service:string,event_type:string,duration_ms:int}>
     */
    public function alertTriggers(): array
    {
        $alertIds = $this->templates->where('type', 'alert')->pluck('id')->all();
        $out = [];
        $seen = [];

        foreach ($this->alert_triggers ?? [] as $trigger) {
            $templateId = (int) ($trigger['template_id'] ?? 0);
            $service = (string) ($trigger['service'] ?? '');
            $eventType = (string) ($trigger['event_type'] ?? '');
            if (! in_array($templateId, $alertIds, true) || ! AlertEventSamples::isKnown($service, $eventType)) {
                continue;
            }
            // One alert per event: a second claim on the same event could
            // never be wired anyway, so the first one stands.
            if (isset($seen["{$service}:{$eventType}"])) {
                continue;
            }
            $seen["{$service}:{$eventType}"] = true;
            $out[] = [
                'template_id' => $templateId,
                'service' => $service,
                'event_type' => $eventType,
                'duration_ms' => max(1000, min(60000, (int) ($trigger['duration_ms'] ?? self::DEFAULT_TRIGGER_DURATION_MS))),
            ];
        }

        return $out;
    }

    /**
     * Point the owner's events at this kit's alerts, the way the kit's author
     * declared them. Run on a kit just copied, so "copy" means "use".
     *
     * An event the owner already has an alert for, switched on or off, is left
     * exactly as it is and reported: copying a kit must never take over an
     * alert someone already relies on. Same rule as a product install, which
     * refuses outright; a kit copies anyway and wires what it can.
     *
     * @return array{wired:list<array{label:string,alert:string}>,kept:list<array{label:string,alert:string,existing:string}>}
     */
    public function wireAlertTriggers(): array
    {
        $this->loadMissing('templates');
        $names = $this->templates->pluck('name', 'id');
        $result = ['wired' => [], 'kept' => []];

        foreach ($this->alertTriggers() as $trigger) {
            $label = AlertEventSamples::label($trigger['service'], $trigger['event_type']);
            $alert = (string) ($names[$trigger['template_id']] ?? '');

            $existing = $trigger['service'] === AlertEventSamples::TWITCH
                ? EventTemplateMapping::with('template')->where('user_id', $this->owner_id)
                    ->where('event_type', $trigger['event_type'])->get()->first(fn ($m) => $m->template !== null)
                : ExternalEventTemplateMapping::with('template')->where('user_id', $this->owner_id)
                    ->where('service', $trigger['service'])->where('event_type', $trigger['event_type'])->get()->first(fn ($m) => $m->template !== null);

            if ($existing !== null) {
                $result['kept'][] = ['label' => $label, 'alert' => $alert, 'existing' => (string) $existing->template->name];

                continue;
            }

            if ($trigger['service'] === AlertEventSamples::TWITCH) {
                EventTemplateMapping::create([
                    'user_id' => $this->owner_id,
                    'event_type' => $trigger['event_type'],
                    'template_id' => $trigger['template_id'],
                    'duration_ms' => $trigger['duration_ms'],
                    'enabled' => true,
                ]);
            } else {
                ExternalEventTemplateMapping::create([
                    'user_id' => $this->owner_id,
                    'service' => $trigger['service'],
                    'event_type' => $trigger['event_type'],
                    'overlay_template_id' => $trigger['template_id'],
                    'duration_ms' => $trigger['duration_ms'],
                    'enabled' => true,
                ]);
            }
            $result['wired'][] = ['label' => $label, 'alert' => $alert];
        }

        return $result;
    }

    /**
     * Fork this kit for a user
     */
    public function fork(User $user): self
    {
        // Create the new kit
        $fork = $this->replicate();
        $fork->owner_id = $user->id;
        $fork->forked_from_id = $this->id;
        $fork->fork_count = 0;
        $fork->is_starter_kit = false;
        $fork->save();

        // Fork all templates in the kit
        $forkedTemplates = [];
        $templateMap = [];
        foreach ($this->templates as $template) {
            $forkedTemplate = $template->fork($user);

            // Copy non-service-managed controls to the forked template.
            // The OverlayTemplate::fork() method only stashes controls in a
            // transient property for the interactive fork wizard — it never
            // persists them, so kit forks (for example when onboarding) would lose them.
            $template->controls()
                ->whereNull('source')
                ->orderBy('sort_order')
                ->get()
                ->each(function (OverlayControl $control) use ($forkedTemplate, $user) {
                    OverlayControl::create([
                        'overlay_template_id' => $forkedTemplate->id,
                        'user_id' => $user->id,
                        'key' => $control->key,
                        'label' => $control->label,
                        'type' => $control->type,
                        'value' => $control->value,
                        'config' => $control->config,
                        'sort_order' => $control->sort_order,
                        'source' => null,
                        'source_managed' => false,
                    ]);
                });

            $forkedTemplates[] = $forkedTemplate->id;
            $templateMap[$template->id] = $forkedTemplate->id;
        }

        // Attach forked templates to the new kit
        $fork->templates()->attach($forkedTemplates);

        // The declared triggers travel with the copy, repointed at the copied
        // alerts, so a copy of a copy still knows what its alerts are for.
        // Wiring them onto the copier's Triggers is wireAlertTriggers(), which
        // the copy action calls; onboarding's starter-kit copy does not.
        $fork->alert_triggers = array_map(
            fn (array $t) => [...$t, 'template_id' => $templateMap[$t['template_id']]],
            $this->alertTriggers(),
        ) ?: null;
        $fork->save();

        // Increment fork count on original
        $this->increment('fork_count');

        return $fork;
    }

    /**
     * Check if the user can delete this kit
     */
    public function canBeDeleted(): bool
    {
        return $this->fork_count === 0;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }

        // If the thumbnail is already a full URL (object storage), return as-is
        if (filter_var($this->thumbnail, FILTER_VALIDATE_URL)) {
            return $this->thumbnail;
        }

        // Legacy support for local storage files
        return Storage::disk('public')->url($this->thumbnail);
    }

    /**
     * Scope for public kits
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for user's kits
     */
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }
}
