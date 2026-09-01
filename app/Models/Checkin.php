<?php

namespace App\Models;

use App\Services\Geo\PlaceResolverService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A viewer's current !checkin pin on a channel. One row per (user, chatter);
 * a re-checkin moves the pin rather than adding one. See the checkins table
 * migration for the lifetime/read-filter design.
 */
class Checkin extends Model
{
    protected $fillable = [
        'user_id',
        'chatter_twitch_id',
        'chatter_login',
        'chatter_display_name',
        'place_label',
        'country_code',
        'lat',
        'lng',
        'distance_km',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'distance_km' => 'float',
            'checked_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The pins an overlay should currently show, newest first, capped.
     *
     * per_stream mode shows pins made since the open stream session started -
     * with no open session it shows nothing, matching the counters that reset
     * at go-live. persistent mode shows everything.
     *
     * @return Collection<int, self>
     */
    public static function windowFor(User $user, string $pinLifetime, int $cap): Collection
    {
        $query = self::windowQuery($user, $pinLifetime);

        if ($query === null) {
            return new Collection;
        }

        return $query->orderByDesc('checked_in_at')->limit($cap)->get();
    }

    /**
     * Uncapped count of the same window - the authoritative `checkins.count`.
     */
    public static function windowCountFor(User $user, string $pinLifetime): int
    {
        return self::windowQuery($user, $pinLifetime)?->count() ?? 0;
    }

    private static function windowQuery(User $user, string $pinLifetime): ?Builder
    {
        $query = static::where('user_id', $user->id);

        if ($pinLifetime === 'per_stream') {
            $session = StreamSession::activeFor($user);

            if (! $session) {
                return null;
            }

            $query->where('checked_in_at', '>=', $session->started_at);
        }

        return $query;
    }

    /**
     * The flat pin shape shared by the checkins.updated broadcast and the
     * overlay render payload. All values are strings; empty string means
     * absent (the null-over-placeholder rule is the renderer's).
     *
     * @return array<string, string>
     */
    public function toPinArray(): array
    {
        return [
            'name' => $this->chatter_display_name,
            'login' => $this->chatter_login,
            'place' => $this->place_label,
            'country' => PlaceResolverService::countryName($this->country_code),
            'country_code' => $this->country_code,
            'lat' => (string) $this->lat,
            'lng' => (string) $this->lng,
            'at' => (string) $this->checked_in_at->getTimestamp(),
            // Kilometers, unit-free name: presentation belongs to the
            // |distance: pipe (km or mi), never to the field name.
            'distance' => $this->distance_km !== null ? (string) $this->distance_km : '',
        ];
    }
}
