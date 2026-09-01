<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One searchable, normalized (lowercase ASCII) name for a GeoPlace - the
 * primary name, the ASCII name, or an alternate ("den haag" for The Hague).
 */
class GeoPlaceName extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'geo_place_id',
        'name_normalized',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(GeoPlace::class, 'geo_place_id');
    }
}
