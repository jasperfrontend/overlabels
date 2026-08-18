<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FunSlugGenerationService
{
    /**
     * Word pools for generating slugs.
     *
     * These are place names on purpose. A slug is the permanent, public URL of
     * someone's overlay (/overlay/{slug}/public) and there is no path to change
     * it after creation, so the pools must not be able to produce a combination
     * the owner would be embarrassed to share. Proper nouns cannot: suggestion
     * needs a verb or an intensity word and there are none here. The previous
     * pools paired a mood adjective with an -ing verb, an object noun and a
     * material, which could and did produce genuinely unfortunate URLs.
     *
     * Curation rules, if you add words:
     * - ASCII, lowercase, single token, at most 8 characters. The route
     *   constraint is [a-z0-9]+(-[a-z0-9]+)*, so "sao-paulo" would smuggle a
     *   hyphen inside one word and break the four-word shape.
     * - Nothing conflict-loaded or territorially disputed. Individually neutral
     *   names can still land badly side by side, and a random generator must not
     *   accidentally take a side on a stranger's overlay.
     * - Current names only, never superseded colonial ones (Denali, not McKinley).
     * - Read it out loud first. Several real places are excluded from these lists
     *   purely because the English reading is unfortunate.
     * - Mostly recognizable, with enough deep cuts to stay interesting.
     * - No word may appear in two pools, or slugs will stutter.
     */
    private array $peaks = [
        'alps', 'andes', 'atlas', 'etna', 'eiger', 'fuji', 'jura', 'harz', 'hekla', 'teide',
        'tatra', 'ural', 'bromo', 'shasta', 'denali', 'sierra', 'elbrus', 'taurus', 'aoraki', 'vosges',
        'olympus', 'rainier', 'cascade', 'troodos', 'whitney', 'ruapehu', 'rinjani', 'toubkal', 'pindus', 'rockies',
        'apennine', 'pyrenees', 'vesuvius', 'cotopaxi', 'kinabalu', 'dolomite', 'triglav', 'monviso',
    ];

    private array $waters = [
        'nile', 'rhine', 'seine', 'loire', 'arno', 'ebro', 'elbe', 'tyne', 'avon', 'waal',
        'tiber', 'volga', 'rhone', 'adige', 'douro', 'tagus', 'weser', 'ijssel', 'neva', 'indus',
        'danube', 'thames', 'mekong', 'ganges', 'severn', 'meuse', 'murray', 'fraser', 'hudson', 'liffey',
        'yangtze', 'zambezi', 'orinoco', 'moselle', 'garonne', 'shannon', 'vltava', 'potomac', 'colorado', 'missouri',
    ];

    private array $cities = [
        'oslo', 'riga', 'brno', 'pisa', 'nara', 'york', 'lima', 'ghent', 'delft', 'turku',
        'porto', 'kyoto', 'osaka', 'siena', 'turin', 'genoa', 'quito', 'cusco', 'busan', 'kandy',
        'lisbon', 'bruges', 'leiden', 'verona', 'bergen', 'tromso', 'aarhus', 'odense', 'gdansk', 'krakow',
        'prague', 'vienna', 'zagreb', 'dublin', 'galway', 'oxford', 'bilbao', 'toledo', 'oaxaca', 'hobart',
        'tallinn', 'vilnius', 'seville', 'granada', 'bologna', 'ravenna', 'utrecht', 'haarlem', 'coimbra', 'tampere',
        'valletta', 'kanazawa', 'sapporo', 'dunedin', 'salzburg', 'funchal', 'jaipur', 'mysore', 'ohrid', 'kotor',
    ];

    private array $isles = [
        'crete', 'corfu', 'malta', 'naxos', 'paros', 'milos', 'capri', 'elba', 'skye', 'iona',
        'mull', 'arran', 'islay', 'faroe', 'sylt', 'texel', 'aruba', 'saba', 'nevis', 'gozo',
        'hvar', 'brac', 'samos', 'chios', 'symi', 'patmos', 'ithaca', 'lemnos', 'thira', 'oland',
        'rhodes', 'ischia', 'sicily', 'azores', 'orkney', 'jersey', 'gotland', 'ameland', 'madeira', 'corsica',
        'lombok', 'flores', 'palawan', 'okinawa', 'tahiti', 'moorea', 'kauai', 'tobago', 'bonaire', 'curacao',
        'sardinia', 'shetland', 'guernsey', 'vlieland', 'zanzibar', 'langkawi', 'ikaria', 'lofoten', 'korcula', 'penang',
    ];

    /**
     * Generate a unique slug with this pattern: peak-water-city-isle
     * Example: eiger-danube-lisbon-crete
     */
    public function generateUniqueSlug(int $maxAttempts = 10): string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $slug = $this->generateRandomSlug();

            // Fast lookup: Check if the slug exists using an indexed query
            if (! $this->slugExists($slug)) {
                return $slug;
            }

            // If we're on later attempts, add some randomness
            if ($attempt > 5) {
                $slug .= '-'.rand(10, 99);
                if (! $this->slugExists($slug)) {
                    return $slug;
                }
            }
        }

        // Fallback: Use timestamp + random number (virtually guaranteed unique)
        $timestamp = substr(time(), -4); // Last 4 digits of timestamp
        $random = rand(1000, 9999);
        $baseSlug = $this->generateRandomSlug();

        return $baseSlug.'-'.$timestamp.$random;
    }

    /**
     * Generate a random slug following our pattern
     */
    private function generateRandomSlug(): string
    {
        $peak = $this->peaks[array_rand($this->peaks)];
        $water = $this->waters[array_rand($this->waters)];
        $city = $this->cities[array_rand($this->cities)];
        $isle = $this->isles[array_rand($this->isles)];

        return "$peak-$water-$city-$isle";
    }

    /**
     * Fast slug existence check with caching
     * Uses database index and Redis caching for performance
     */
    private function slugExists(string $slug): bool
    {
        // Cache key for this slug check
        $cacheKey = "slug_exists:$slug";

        // Check Laravel Cache first (Redis lookup = ~0.1ms)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 'exists';
        }

        // Database check with index (should be ~1-2 ms even with 500k+ records)
        $exists = DB::table('overlay_templates')
            ->where('slug', $slug)
            ->exists();

        // Cache the result for 1 hour
        // Cache 'not exists' for shorter time in case of race conditions
        $cacheTime = $exists ? 3600 : 300; // 1 hour if exists, 5 min if not
        Cache::put($cacheKey, $exists ? 'exists' : 'not_exists', $cacheTime);

        return $exists;
    }

    /**
     * Get total possible combinations (for monitoring collision risk)
     */
    public function getTotalPossibleCombinations(): int
    {
        return count($this->peaks) *
               count($this->waters) *
               count($this->cities) *
               count($this->isles);
    }

    /**
     * Get collision risk percentage based on the current slug count saved in the database
     */
    public function getCollisionRisk(): array
    {
        $totalPossible = $this->getTotalPossibleCombinations();
        $currentCount = DB::table('overlay_templates')->count();
        $collisionRisk = ($currentCount / $totalPossible) * 100;

        return [
            'total_possible' => $totalPossible,
            'current_count' => $currentCount,
            'collision_risk_percent' => round($collisionRisk, 2),
            'recommended_action' => $collisionRisk > 70 ? 'Add more words to pools' : 'All good!',
        ];
    }

    /**
     * Regenerate slug for an existing template (useful for conflicts)
     */
    public function regenerateSlugForOverlay(int $overlayId): string
    {
        $newSlug = $this->generateUniqueSlug();

        DB::table('overlay_templates')
            ->where('id', $overlayId)
            ->update(['slug' => $newSlug]);

        // Clear any cached existence check for the new slug
        Cache::forget("slug_exists:$newSlug");

        return $newSlug;
    }

    /**
     * Batch check if multiple slugs exist (useful for bulk operations)
     */
    public function batchCheckSlugs(array $slugs): array
    {
        $results = [];
        $uncachedSlugs = [];

        // Check the cache first for all slugs
        foreach ($slugs as $slug) {
            $cacheKey = "slug_exists:$slug";
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$slug] = $cached === 'exists';
            } else {
                $uncachedSlugs[] = $slug;
            }
        }

        // Batch database check for uncached slugs
        if (! empty($uncachedSlugs)) {
            $existingSlugs = DB::table('overlay_templates')
                ->whereIn('slug', $uncachedSlugs)
                ->pluck('slug')
                ->toArray();

            foreach ($uncachedSlugs as $slug) {
                $exists = in_array($slug, $existingSlugs);
                $results[$slug] = $exists;

                // Cache the result
                $cacheTime = $exists ? 3600 : 300;
                Cache::put("slug_exists:$slug", $exists ? 'exists' : 'not_exists', $cacheTime);
            }
        }

        return $results;
    }
}
