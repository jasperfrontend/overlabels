<?php

namespace App\Services\Geo;

/**
 * The answer PlaceResolverService gives for a free-text place query.
 * City-level only, by design - the gazetteer holds nothing finer.
 */
final readonly class ResolvedPlace
{
    public function __construct(
        public int $geonamesId,
        public string $name,
        public string $countryCode,
        public string $countryName,
        public float $lat,
        public float $lng,
        public int $population,
    ) {}

    /**
     * Display label for chat replies and pin labels: "Rotterdam, NL".
     */
    public function label(): string
    {
        return $this->name.', '.$this->countryCode;
    }
}
