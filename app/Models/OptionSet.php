<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, reusable list of values owned by a user. Pickers consume an
 * OptionSet to produce results. This is a Recipes-layer primitive; the
 * Recipe install flow creates these on behalf of an installer, but power
 * users can also create them directly via tinker / future DSL.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string|null $label
 * @property array<int, string> $items
 * @property int $min_items
 * @property int|null $max_items
 * @property bool $user_editable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OptionSet extends Model
{
    use HasFactory;

    public const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    protected $fillable = [
        'user_id',
        'slug',
        'label',
        'items',
        'min_items',
        'max_items',
        'user_editable',
    ];

    protected $casts = [
        'items' => 'array',
        'min_items' => 'integer',
        'max_items' => 'integer',
        'user_editable' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickers(): HasMany
    {
        return $this->hasMany(Picker::class);
    }
}
