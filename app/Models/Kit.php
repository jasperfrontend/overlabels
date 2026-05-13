<?php

namespace App\Models;

use App\Services\Recipes\RecipeInstaller;
use App\Models\RecipeInstance;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
 * @property-read Kit|null $forkedFrom
 * @property-read Collection<int, Kit> $forks
 * @property-read int|null $forks_count
 * @property-read string|null $thumbnail_url
 * @property-read User|null $owner
 * @property-read Collection<int, OverlayTemplate> $templates
 * @property-read int|null $templates_count
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
 * @mixin Eloquent
 * @mixin IdeHelperKit
 */
class Kit extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'thumbnail',
        'is_public',
        'is_starter_kit',
        'forked_from_id',
        'fork_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_starter_kit' => 'boolean',
        'fork_count' => 'integer',
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * Boot method
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($kit) {
            // Prevent deletion if kit has been forked
            if ($kit->fork_count > 0) {
                throw new Exception('Cannot delete a kit that has been forked.');
            }

            // Delete thumbnail if exists and is local storage (not Cloudinary URL)
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
     * Recipes bundled with this kit. When the kit is forked, each linked
     * recipe is installed for the new owner via RecipeInstaller using the
     * pivot's default_instance_slug (with a suffix on collision).
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(
            Recipe::class,
            'kit_recipes',
            'kit_id',
            'recipe_id'
        )->withPivot(['default_instance_slug', 'sort_order'])
            ->withTimestamps()
            ->orderBy('kit_recipes.sort_order');
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
     * Fork this kit for a user. Wrapped in a transaction so a partial
     * failure (e.g. a recipe-install collision) leaves no half-baked state.
     */
    public function fork(User $user): self
    {
        return DB::transaction(function () use ($user) {
            $fork = $this->replicate();
            $fork->owner_id = $user->id;
            $fork->forked_from_id = $this->id;
            $fork->title = 'Fork of '.Str::limit($this->title, 80);
            $fork->fork_count = 0;
            $fork->is_starter_kit = false;
            $fork->save();

            // Install bundled recipes FIRST so we know the actual instance
            // slugs before we fork the templates - templates can ship with
            // an __INSTANCE__ placeholder that gets substituted to the
            // chosen slug after install resolves collisions.
            $instanceSlugByRecipeSlug = $this->installBundledRecipes($user);

            $this->forkBundledTemplates($user, $fork, $instanceSlugByRecipeSlug);

            $this->increment('fork_count');

            return $fork;
        });
    }

    /**
     * Install every kit-bundled recipe for the forker. Returns a map from
     * recipe slug to the actually-used instance slug (suffixed if the
     * pivot's default collided with an existing install on this user).
     *
     * @return array<string, string>
     */
    private function installBundledRecipes(User $user): array
    {
        $installer = app(RecipeInstaller::class);
        $map = [];

        foreach ($this->recipes as $recipe) {
            $desiredSlug = $recipe->pivot->default_instance_slug;
            $chosenSlug = $this->reserveInstanceSlug($user, $recipe, $desiredSlug);

            $installer->install($recipe, $user, $chosenSlug);

            $map[$recipe->slug] = $chosenSlug;
        }

        return $map;
    }

    /**
     * Find the first instance slug not already in use for (user, recipe).
     * Starts from $desired, then $desired_2, $desired_3, ...
     */
    private function reserveInstanceSlug(User $user, Recipe $recipe, string $desired): string
    {
        $candidate = $desired;
        $suffix = 1;

        while (
            RecipeInstance::where('user_id', $user->id)
                ->where('recipe_id', $recipe->id)
                ->where('instance_slug', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = $desired.'_'.$suffix;
            if (strlen($candidate) > 50 || $suffix > 50) {
                throw new Exception("Cannot find a free instance slug for recipe '{$recipe->slug}'.");
            }
        }

        return $candidate;
    }

    /**
     * Fork every kit-attached template into a new owned copy. If the
     * recipe-instance map is non-empty, also substitutes the
     * `__INSTANCE__` placeholder inside the forked template's html/css/js
     * with each recipe's chosen instance slug.
     *
     * @param  array<string, string>  $instanceSlugByRecipeSlug
     */
    private function forkBundledTemplates(User $user, self $fork, array $instanceSlugByRecipeSlug): void
    {
        $forkedTemplateIds = [];

        foreach ($this->templates as $template) {
            $forkedTemplate = $template->fork($user);

            if ($instanceSlugByRecipeSlug !== []) {
                $this->substituteRecipePlaceholders($forkedTemplate, $instanceSlugByRecipeSlug);
            }

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

            $forkedTemplateIds[] = $forkedTemplate->id;
        }

        $fork->templates()->attach($forkedTemplateIds);
    }

    /**
     * Replace each `[[[c:<recipe_slug>:__INSTANCE__:<key>]]]`-shaped tag
     * (and the matching `__INSTANCE__` references in JS/CSS) with the
     * actually-installed instance slug. Only the recipes in the provided
     * map are substituted; unrelated `__INSTANCE__` strings stay intact.
     *
     * @param  array<string, string>  $instanceSlugByRecipeSlug
     */
    private function substituteRecipePlaceholders(OverlayTemplate $template, array $instanceSlugByRecipeSlug): void
    {
        $needles = [];
        $replacements = [];

        foreach ($instanceSlugByRecipeSlug as $recipeSlug => $instanceSlug) {
            // The fully-qualified tag form: c:<recipe>:__INSTANCE__:<key>
            $needles[] = "c:{$recipeSlug}:__INSTANCE__:";
            $replacements[] = "c:{$recipeSlug}:{$instanceSlug}:";
            // The shorter `<recipe>:__INSTANCE__` form used inside JS/data
            // attributes that compose tag prefixes dynamically.
            $needles[] = "{$recipeSlug}:__INSTANCE__";
            $replacements[] = "{$recipeSlug}:{$instanceSlug}";
        }

        $newHtml = str_replace($needles, $replacements, $template->html ?? '');
        $newCss = str_replace($needles, $replacements, $template->css ?? '');
        $newJs = str_replace($needles, $replacements, $template->js ?? '');

        // OverlayTemplate::fork() stashes transient model properties for the
        // fork wizard UI (_sourceControls, _hasControls, _requiredServices).
        // A bare $template->save() would try to flush those into columns
        // that don't exist. Use a targeted update query so we only touch
        // the three real columns we changed.
        OverlayTemplate::where('id', $template->id)->update([
            'html' => $newHtml,
            'css' => $newCss,
            'js' => $newJs,
        ]);

        $template->html = $newHtml;
        $template->css = $newCss;
        $template->js = $newJs;
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

        // If the thumbnail is already a full URL (Cloudinary), return as-is
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
