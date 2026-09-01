<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One city from the GeoNames cities500 dump. Static shared data, written only
 * by `geo:import` - see PlaceResolverService for how it is queried.
 */
class GeoPlace extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'geonames_id',
        'name',
        'ascii_name',
        'lat',
        'lng',
        'country_code',
        'population',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'population' => 'integer',
        ];
    }

    public function names(): HasMany
    {
        return $this->hasMany(GeoPlaceName::class);
    }
}
