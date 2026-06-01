<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}> $items
 * @property int $next_item_id Per-list id counter; the id to assign to the next appended item
 * @property int $min_items
 * @property int|null $max_items
 * @property bool $user_editable
 * @property Carbon|null $disabled_at
 * @property int|null $entry_ttl_seconds
 * @property Carbon|null $expires_at
 * @property array<string, string>|null $chat_permissions Action -> permission level overrides; NULL means use defaults
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
        'next_item_id',
        'min_items',
        'max_items',
        'user_editable',
        'disabled_at',
        'entry_ttl_seconds',
        'expires_at',
        'chat_permissions',
    ];

    protected $casts = [
        'items' => 'array',
        'next_item_id' => 'integer',
        'min_items' => 'integer',
        'max_items' => 'integer',
        'user_editable' => 'boolean',
        'disabled_at' => 'datetime',
        'entry_ttl_seconds' => 'integer',
        'expires_at' => 'datetime',
        'chat_permissions' => 'array',
    ];

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
}
