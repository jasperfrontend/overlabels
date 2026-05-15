<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A named, reusable list of values owned by a user. Pickers consume an
 * OptionSet to produce results. This is a Recipes-layer primitive; the
 * Recipe install flow creates these on behalf of an installer, but power
 * users can also create them directly via tinker / future DSL.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $recipe_instance_id
 * @property string $slug
 * @property string|null $label
 * @property array<int, string> $items
 * @property array<int, int> $item_added_at Parallel array to items; Unix seconds per item
 * @property int $min_items
 * @property int|null $max_items
 * @property bool $user_editable
 * @property Carbon|null $disabled_at
 * @property int|null $entry_ttl_seconds
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RecipeInstance|null $recipeInstance
 */
class OptionSet extends Model
{
    use HasFactory;

    public const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    protected $fillable = [
        'user_id',
        'recipe_instance_id',
        'slug',
        'label',
        'items',
        'item_added_at',
        'min_items',
        'max_items',
        'user_editable',
        'disabled_at',
        'entry_ttl_seconds',
        'expires_at',
        'source_control_id',
    ];

    protected $casts = [
        'items' => 'array',
        'item_added_at' => 'array',
        'min_items' => 'integer',
        'max_items' => 'integer',
        'user_editable' => 'boolean',
        'disabled_at' => 'datetime',
        'entry_ttl_seconds' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Model-level guard so source_control_id can't be set to an
        // Expression Control. Expressions evaluate in the overlay (jsep)
        // only - the server never sees the computed value at the moment
        // the source updates, so the listener would append a stale stored
        // value. Reject at write time with a clear pointer.
        static::saving(function (OptionSet $list) {
            if ($list->source_control_id === null) {
                return;
            }
            if (! $list->isDirty('source_control_id')) {
                return;
            }
            $source = OverlayControl::find($list->source_control_id);
            if (! $source) {
                return;
            }
            if ($source->isExpression()) {
                throw new InvalidArgumentException(
                    "Expression Controls can't be bound as a List source in v1. ".
                    'The expression engine (jsep) runs in the overlay, not on the server, '.
                    'so the server has no computed value to append. Bind a raw control '.
                    '(counter, number, text, boolean, or service-managed) instead.'
                );
            }
            if ($source->user_id !== $list->user_id) {
                throw new InvalidArgumentException(
                    'Source control must belong to the same user as the List.'
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickers(): HasMany
    {
        return $this->hasMany(Picker::class);
    }

    public function recipeInstance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class);
    }

    /**
     * The Control this List subscribes to, if any. Every time the source
     * fires a ControlValueUpdated event, its value gets appended here via
     * the AppendControlValueToList listener.
     */
    public function sourceControl(): BelongsTo
    {
        return $this->belongsTo(OverlayControl::class, 'source_control_id');
    }

    /**
     * Defence against a control writing into a list that one of its own
     * (transitive) dependencies reads. Today expressions evaluate only in
     * the overlay (jsep), so realistic cycles aren't possible server-side;
     * this DFS exists for the day a server-side evaluator lands and
     * expressions can transitively depend on list contents.
     *
     * Returns true if binding $sourceControlId to a list with slug
     * $targetListSlug (in the same user's scope) would close a loop.
     */
    public static function detectListBindingCycle(int $sourceControlId, string $targetListSlug, int $userId): bool
    {
        $source = OverlayControl::find($sourceControlId);
        if (! $source || $source->user_id !== $userId) {
            return false;
        }

        return self::dfsBindingCycle($source, $targetListSlug, $userId, [], 0);
    }

    /**
     * @param  array<int,int>  $visited
     */
    private static function dfsBindingCycle(OverlayControl $control, string $targetListSlug, int $userId, array $visited, int $depth): bool
    {
        if ($depth >= 5) {
            return false;
        }
        if (in_array($control->id, $visited, true)) {
            return false;
        }
        $visited[] = $control->id;

        if (! $control->isExpression()) {
            // Raw controls have no inputs; they can't transitively reach a list.
            return false;
        }

        $expression = $control->config['expression'] ?? '';
        if ($expression === '') {
            return false;
        }

        // Direct list reads: c.list.<slug> or c["list"].<slug>. These don't
        // resolve server-side today but a future evaluator would honour
        // them. If the expression mentions the target list slug, we've
        // found a cycle.
        foreach (self::extractListSlugs($expression) as $slug) {
            if ($slug === $targetListSlug) {
                return true;
            }
            // Indirect: list -> source control -> deps. Follow the bound
            // source for any list the expression already reads.
            $list = self::where('user_id', $userId)->where('slug', $slug)->first();
            if ($list && $list->source_control_id) {
                $next = OverlayControl::find($list->source_control_id);
                if ($next && self::dfsBindingCycle($next, $targetListSlug, $userId, $visited, $depth + 1)) {
                    return true;
                }
            }
        }

        // Control-to-control deps (existing expression dependency walk).
        foreach (OverlayControl::extractExpressionDependencies($expression) as $dep) {
            $colon = strpos($dep, ':');
            $source = $colon !== false ? substr($dep, 0, $colon) : null;
            $key = $colon !== false ? substr($dep, $colon + 1) : $dep;

            $query = OverlayControl::where('user_id', $userId)->where('key', $key);
            if ($source) {
                $query->where('source', $source);
            } else {
                $query->whereNull('source');
            }

            foreach ($query->get() as $depControl) {
                if (self::dfsBindingCycle($depControl, $targetListSlug, $userId, $visited, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract list-slug references from an expression. Recognises
     * c.list.<slug> and c["list"].<slug>. Companion to
     * OverlayControl::extractExpressionDependencies which returns
     * control deps only.
     *
     * @return array<int,string>
     */
    private static function extractListSlugs(string $expression): array
    {
        $slugs = [];

        if (preg_match_all('/\bc\.list\.([a-z][a-z0-9_]*)/', $expression, $dotMatches)) {
            foreach ($dotMatches[1] as $slug) {
                $slugs[] = $slug;
            }
        }

        if (preg_match_all('/\bc\[([\'"])list\1\]\.([a-z][a-z0-9_]*)/', $expression, $bracketMatches)) {
            foreach ($bracketMatches[2] as $slug) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }
}
