<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A look the streamer saved from the chat designer, under a name they chose.
 *
 * `values` is a bundle of the chat overlay's look controls, keyed by control
 * key, in the shape ChatPresets::PRESETS holds its built-in bundles - so
 * applying one goes through ChatPresets::applyValues() like the built-ins do,
 * and the designer derives "active" for both kinds with one comparison.
 *
 * Captured server-side from the overlay's controls, never posted by the page:
 * the name is the only thing the client says.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, string> $values
 */
class UserChatPreset extends Model
{
    /** Enough for every stream category a person runs, few enough for one dropdown. */
    public const MAX_PER_USER = 20;

    public const NAME_MAX = 40;

    protected $fillable = ['user_id', 'name', 'values'];

    protected $casts = ['values' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What the designer renders for one row.
     *
     * @return array{id: int, name: string, values: array<string, string>}
     */
    public function toDesigner(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'values' => $this->values];
    }
}
