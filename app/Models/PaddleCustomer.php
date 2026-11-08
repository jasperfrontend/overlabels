<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Paddle customer (ctm_) and the Overlabels user it belongs to. Written only
 * by the Paddle webhook. Holds no email or address on purpose.
 *
 * @property string $customer_id
 * @property int|null $user_id
 * @property Carbon|null $paddle_occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 */
class PaddleCustomer extends Model
{
    protected $primaryKey = 'customer_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['customer_id', 'user_id', 'paddle_occurred_at'];

    protected function casts(): array
    {
        return ['paddle_occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
