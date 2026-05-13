<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A per-user install of a Recipe. Owns the option_sets, pickers, and
 * overlay_controls rows materialised at install time. Cascade-delete
 * cleans up the whole graph on uninstall.
 *
 * @property int $id
 * @property int $recipe_id
 * @property int $user_id
 * @property string $instance_slug
 * @property string|null $label
 * @property array{option_sets?: array<string, int>, pickers?: array<string, int>} $primitive_map
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Recipe|null $recipe
 * @property-read User|null $user
 */
class RecipeInstance extends Model
{
    use HasFactory;

    public const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    protected $fillable = [
        'recipe_id',
        'user_id',
        'instance_slug',
        'label',
        'primitive_map',
    ];

    protected $casts = [
        'primitive_map' => 'array',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function optionSets(): HasMany
    {
        return $this->hasMany(OptionSet::class);
    }

    public function pickers(): HasMany
    {
        return $this->hasMany(Picker::class);
    }

    public function overlayControls(): HasMany
    {
        return $this->hasMany(OverlayControl::class);
    }
}
