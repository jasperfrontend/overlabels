<?php

namespace App\Services\Geo;

use App\Models\GeoPlace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Locale;

/**
 * Resolves free-text place queries ("Rotterdam, NL", "Barcelona", "amsterdamm")
 * against the local GeoNames gazetteer. This is the whole geocoder: an exact
 * lookup on normalized names, population-ranked disambiguation ("Paris" is
 * Paris FR, not Paris TX - the same heuristic the paid APIs use), an optional
 * country hint after the last comma, and a pg_trgm fuzzy pass for typos.
 *
 * Pure lookup, no side effects, nothing leaves the building. City-level only.
 *
 * The ladder is deterministic and pinned by PlaceResolverTest:
 * 1. an explicit resolvable country hint scopes exact + fuzzy to that country
 *    and does NOT fall back to other countries ("rotterdam, us" is a miss,
 *    never Rotterdam NL);
 * 2. otherwise: exact on the full query, then exact on the part before the
 *    last comma (so "rotterdam, zuid-holland" still lands), then fuzzy.
 */
class PlaceResolverService
{
    /**
     * Top similarity below this is a miss, not a match. Calibrated against
     * the real gazetteer: junk queries land on short alternate names at
     * <= 0.5 ("gyat" -> Geita via its alias "gya"), while genuine typos score
     * >= 0.58 ("amsterdamm" 0.75, "pariss" 0.625, "barclona" 0.583). A wrong
     * city pinned on stream is worse than a friendly miss.
     */
    private const float FUZZY_SIMILARITY_MIN = 0.55;

    /** Trigram matching on very short strings is noise, not typo tolerance. */
    private const int FUZZY_MIN_LENGTH = 4;

    private const int MAX_QUERY_LENGTH = 120;

    /**
     * Colloquial country names intl does not produce. Keys are normalized.
     *
     * @var array<string, string>
     */
    private const array COUNTRY_ALIASES = [
        'usa' => 'US',
        'america' => 'US',
        'united states' => 'US',
        'uk' => 'GB',
        'great britain' => 'GB',
        'england' => 'GB',
        'scotland' => 'GB',
        'wales' => 'GB',
        'northern ireland' => 'GB',
        'holland' => 'NL',
        'the netherlands' => 'NL',
        'uae' => 'AE',
        'south korea' => 'KR',
        'north korea' => 'KP',
        'russia' => 'RU',
        'czechia' => 'CZ',
        'czech republic' => 'CZ',
        'turkey' => 'TR',
        'taiwan' => 'TW',
    ];

    /**
     * Normalized country name => ISO code, built from the codes actually in
     * the gazetteer plus intl display names. Static per-request cache.
     *
     * @var array<string, string>|null
     */
    private static ?array $countryNameMap = null;

    public function resolve(string $query): ?ResolvedPlace
    {
        $query = trim($query);

        if ($query === '' || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return null;
        }

        $full = self::normalize($query);

        if ($full === '') {
            return null;
        }

        [$head, $countryCode] = $this->splitCountryHint($full);

        if ($countryCode !== null) {
            if ($head === '') {
                return null;
            }

            $place = $this->exactMatch($head, $countryCode) ?? $this->fuzzyMatch($head, $countryCode);

            return $place ? $this->toResolvedPlace($place) : null;
        }

        $place = $this->exactMatch($full)
            ?? ($head !== $full && $head !== '' ? $this->exactMatch($head) : null)
            ?? $this->fuzzyMatch($head !== '' ? $head : $full);

        return $place ? $this->toResolvedPlace($place) : null;
    }

    /**
     * The one normalization both the importer and the resolver use. A query
     * matches a stored name only because both went through here.
     */
    public static function normalize(string $value): string
    {
        $ascii = strtolower(Str::ascii($value));
        $ascii = preg_replace('/[^\x20-\x7e]/', '', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }

    /**
     * English display name for an ISO country code, or the code itself when
     * intl is unavailable or the code is unknown.
     */
    public static function countryName(string $code): string
    {
        $code = strtoupper($code);

        if (! class_exists(Locale::class)) {
            return $code;
        }

        return Locale::getDisplayRegion('-'.$code, 'en') ?: $code;
    }

    /**
     * Split "rotterdam, nl" into ["rotterdam", "NL"]. The tail after the LAST
     * comma is treated as a country hint only when it resolves to a country
     * (2-letter ISO code, intl display name, or a colloquial alias); anything
     * else returns the head with a null code so the caller can try the full
     * string and the head as plain place names.
     *
     * @return array{string, string|null}
     */
    private function splitCountryHint(string $normalized): array
    {
        $pos = strrpos($normalized, ',');

        if ($pos === false) {
            return [$normalized, null];
        }

        $head = trim(substr($normalized, 0, $pos));
        $tail = trim(substr($normalized, $pos + 1));

        return [$head, $this->resolveCountryHint($tail)];
    }

    private function resolveCountryHint(string $tail): ?string
    {
        if ($tail === '') {
            return null;
        }

        if (preg_match('/^[a-z]{2}$/', $tail)) {
            $code = strtoupper($tail);

            // intl echoes the code back for unknown regions, so a changed
            // display name is what "this is a real country" looks like.
            if (self::countryName($code) !== $code) {
                return $code;
            }

            return null;
        }

        return self::COUNTRY_ALIASES[$tail] ?? $this->countryNameMap()[$tail] ?? null;
    }

    /**
     * The name map is cached for the process lifetime; tests seed their own
     * gazetteer fixtures inside transactions, so they flush between cases.
     */
    public static function flushCountryNameMap(): void
    {
        self::$countryNameMap = null;
    }

    /**
     * @return array<string, string>
     */
    private function countryNameMap(): array
    {
        if (self::$countryNameMap !== null) {
            return self::$countryNameMap;
        }

        $map = [];

        foreach (GeoPlace::query()->distinct()->pluck('country_code') as $code) {
            $name = self::normalize(self::countryName($code));

            if ($name !== '' && $name !== self::normalize($code)) {
                $map[$name] = $code;
            }
        }

        return self::$countryNameMap = $map;
    }

    private function exactMatch(string $name, ?string $countryCode = null): ?GeoPlace
    {
        return GeoPlace::query()
            ->join('geo_place_names as gpn', 'gpn.geo_place_id', '=', 'geo_places.id')
            ->where('gpn.name_normalized', $name)
            ->when($countryCode, fn ($q) => $q->where('geo_places.country_code', $countryCode))
            ->orderByDesc('geo_places.population')
            ->select('geo_places.*')
            ->first();
    }

    private function fuzzyMatch(string $name, ?string $countryCode = null): ?GeoPlace
    {
        if (strlen($name) < self::FUZZY_MIN_LENGTH || DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        $place = GeoPlace::query()
            ->join('geo_place_names as gpn', 'gpn.geo_place_id', '=', 'geo_places.id')
            ->whereRaw('gpn.name_normalized % ?', [$name])
            ->when($countryCode, fn ($q) => $q->where('geo_places.country_code', $countryCode))
            ->selectRaw('geo_places.*, similarity(gpn.name_normalized, ?) as similarity_score', [$name])
            ->orderByDesc('similarity_score')
            ->orderByDesc('geo_places.population')
            ->first();

        if (! $place || (float) $place->getAttribute('similarity_score') < self::FUZZY_SIMILARITY_MIN) {
            return null;
        }

        return $place;
    }

    private function toResolvedPlace(GeoPlace $place): ResolvedPlace
    {
        return new ResolvedPlace(
            geonamesId: (int) $place->geonames_id,
            name: $place->name,
            countryCode: $place->country_code,
            countryName: self::countryName($place->country_code),
            lat: $place->lat,
            lng: $place->lng,
            population: $place->population,
        );
    }
}
