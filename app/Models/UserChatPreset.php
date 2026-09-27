<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A look the streamer saved from a product's designer, under a name they
 * chose.
 *
 * `product` is the product slug the look belongs to. One table serves every
 * product's designer, and a look is only ever listed on, and applied from,
 * the designer of the product it was saved on: a chat look's keys mean
 * nothing to the bubbles overlay. Names are unique per product, so "Cozy"
 * can exist once for each.
 *
 * `values` is a bundle of the overlay's designer controls, keyed by control
 * key, in the shape a manifest's built-in presets hold theirs - so applying
 * one goes through ProductDesigner::applyValues() like the built-ins do, and
 * the designer derives "active" for both kinds with one comparison.
 *
 * Captured server-side from the overlay's controls, never posted by the page:
 * the name is the only thing the client says.
 *
 * @property int $id
 * @property int $user_id
 * @property string $product
 * @property string $name
 * @property array<string, string> $values
 */
class UserChatPreset extends Model
{
    /** Enough for every stream category a person runs, few enough for one dropdown. Per product. */
    public const MAX_PER_USER = 20;

    public const NAME_MAX = 40;

    protected $fillable = ['user_id', 'product', 'name', 'values'];

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
