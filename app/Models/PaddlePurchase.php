<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line item of a completed Paddle transaction. Written only by the Paddle
 * webhook, upserted on (transaction_id, price_id).
 *
 * @property int $id
 * @property string $transaction_id
 * @property string $price_id
 * @property string $product_id
 * @property string|null $customer_id
 * @property int|null $user_id
 * @property string $status
 * @property string|null $amount
 * @property string|null $currency
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $paddle_occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 */
class PaddlePurchase extends Model
{
    protected $fillable = [
        'transaction_id', 'price_id', 'product_id', 'customer_id', 'user_id',
        'status', 'amount', 'currency', 'paid_at', 'refunded_at', 'paddle_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'paddle_occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Purchases that currently grant the product: paid and not refunded.
     * A one-off purchase has no subscription lifecycle; the only thing that
     * takes it back is an approved full refund (see the adjustment handler).
     *
     * @param  Builder<PaddlePurchase>  $query
     */
    public function scopeGrantingAccess(Builder $query): void
    {
        $query->where('status', 'completed')->whereNull('refunded_at');
    }

    /** Whether the user currently owns this Paddle product (pro_ id). */
    public static function userOwns(User $user, string $productId): bool
    {
        return static::query()
            ->grantingAccess()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();
    }
}
