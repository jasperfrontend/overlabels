<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published Recipe in the catalogue. Each (slug, version) row is its
 * own installable artifact - new versions never overwrite old ones, per
 * the manifest design decision.
 *
 * @property int $id
 * @property string $slug
 * @property int $version
 * @property string $name
 * @property string $description
 * @property string $author_name
 * @property string|null $author_twitch_login
 * @property string|null $icon_url
 * @property string|null $changelog
 * @property int $min_overlabels_version
 * @property array<int, string> $requires_integrations
 * @property int|null $max_instances_per_user
 * @property array<string, mixed> $manifest
 * @property bool $is_first_party
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'version',
        'name',
        'description',
        'author_name',
        'author_twitch_login',
        'icon_url',
        'changelog',
        'min_overlabels_version',
        'requires_integrations',
        'max_instances_per_user',
        'manifest',
        'is_first_party',
    ];

    protected $casts = [
        'version' => 'integer',
        'min_overlabels_version' => 'integer',
        'max_instances_per_user' => 'integer',
        'requires_integrations' => 'array',
        'manifest' => 'array',
        'is_first_party' => 'boolean',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(RecipeInstance::class);
    }

    public function kits(): BelongsToMany
    {
        return $this->belongsToMany(
            Kit::class,
            'kit_recipes',
            'recipe_id',
            'kit_id'
        )->withPivot(['default_instance_slug', 'sort_order'])
            ->withTimestamps();
    }
}
