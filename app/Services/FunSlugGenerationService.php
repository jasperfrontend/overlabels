<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FunSlugGenerationService
{
    // Word pools for generating fun slugs
    private array $adjectives1 = [
        'happy', 'bright', 'quick', 'calm', 'bold', 'cool', 'warm', 'fast', 'slow', 'big',
        'tiny', 'loud', 'quiet', 'smooth', 'rough', 'soft', 'hard', 'light', 'dark', 'fresh',
        'old', 'new', 'wild', 'tame', 'free', 'brave', 'shy', 'smart', 'fun', 'silly',
        'wise', 'magic', 'super', 'mega', 'ultra', 'epic', 'rare', 'common', 'special', 'plain',
        'fancy', 'simple', 'complex', 'easy', 'tough', 'gentle', 'fierce', 'kind', 'mean', 'nice',
        'oddly', 'strange', 'weird', 'crazy', 'scary', 'scarier', 'scariest',
    ];

    private array $adjectives2 = [
        'dancing', 'flying', 'running', 'jumping', 'swimming', 'climbing', 'rolling', 'spinning', 'bouncing', 'sliding',
        'glowing', 'shining', 'sparkling', 'twinkling', 'blazing', 'floating', 'drifting', 'rushing', 'crawling', 'racing',
        'singing', 'humming', 'whistling', 'laughing', 'smiling', 'giggling', 'cheering', 'celebrating', 'playing', 'working',
        'sleeping', 'dreaming', 'thinking', 'wondering', 'exploring', 'discovering', 'creating', 'building', 'making', 'crafting',
        'painting', 'drawing', 'writing', 'reading', 'learning', 'teaching', 'helping', 'caring', 'loving', 'sharing',
        'healing', 'doing', 'dabbling', 'tinkering', 'architecting', 'engineering', 'designing', 'planning', 'preparing',

    ];

    private array $nouns = [
        'star', 'moon', 'sun', 'cloud', 'wave', 'rock', 'tree', 'leaf', 'flower', 'grass',
        'mountain', 'valley', 'river', 'lake', 'ocean', 'beach', 'island', 'forest', 'desert', 'field',
        'bridge', 'tower', 'castle', 'house', 'garden', 'path', 'road', 'trail', 'door', 'window',
        'book', 'song', 'dance', 'game', 'toy', 'ball', 'box', 'key', 'coin', 'gem',
        'fire', 'ice', 'wind', 'earth', 'water', 'thunder', 'lightning', 'rainbow', 'prism', 'crystal'
    ];

    private array $adjectives3 = [
        'golden', 'silver', 'bronze', 'crystal', 'diamond', 'emerald', 'ruby', 'sapphire', 'pearl', 'copper',
        'wooden', 'stone', 'metal', 'glass', 'plastic', 'fabric', 'paper', 'leather', 'silk', 'wool',
        'striped', 'spotted', 'dotted', 'lined', 'curved', 'straight', 'round', 'square', 'triangle', 'spiral',
        'frozen', 'melted', 'heated', 'cooled', 'twisted', 'bent', 'broken', 'fixed', 'lost', 'found',
        'hidden', 'visible', 'secret', 'open', 'closed', 'locked', 'unlocked', 'empty', 'full', 'half'
    ];

    private array $animals = [
        'cat', 'dog', 'fox', 'wolf', 'bear', 'lion', 'tiger', 'leopard', 'cheetah', 'panda',
        'rabbit', 'hare', 'deer', 'elk', 'moose', 'horse', 'zebra', 'giraffe', 'elephant', 'rhino',
        'bird', 'eagle', 'hawk', 'owl', 'raven', 'swan', 'duck', 'goose', 'penguin', 'flamingo',
        'fish', 'shark', 'whale', 'dolphin', 'seal', 'otter', 'crab', 'lobster', 'octopus', 'squid',
        'butterfly', 'bee', 'ant', 'spider', 'dragonfly', 'beetle', 'moth', 'cricket', 'grasshopper', 'firefly'
    ];

    /**
     * Generate a fun, unique slug with this pattern: adjective-adjective-noun-adjective-animal
     * Example: bright-dancing-star-golden-fox
     */
    public function generateUniqueSlug(int $maxAttempts = 10): string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $slug = $this->generateRandomSlug();

            // Fast lookup: Check if the slug exists using an indexed query
            if (!$this->slugExists($slug)) {
                return $slug;
            }

            // If we're on later attempts, add some randomness
            if ($attempt > 5) {
                $slug .= '-' . rand(10, 99);
                if (!$this->slugExists($slug)) {
                    return $slug;
                }
            }
        }

        // Fallback: Use timestamp + random number (virtually guaranteed unique)
        $timestamp = substr(time(), -4); // Last 4 digits of timestamp
        $random = rand(1000, 9999);
        $baseSlug = $this->generateRandomSlug();

        return $baseSlug . '-' . $timestamp . $random;
    }

    /**
     * Generate a random slug following our pattern
     */
    private function generateRandomSlug(): string
    {
        $adj1 = $this->adjectives1[array_rand($this->adjectives1)];
        $adj2 = $this->adjectives2[array_rand($this->adjectives2)];
        $noun = $this->nouns[array_rand($this->nouns)];
        $adj3 = $this->adjectives3[array_rand($this->adjectives3)];
        $animal = $this->animals[array_rand($this->animals)];

        return "$adj1-$adj2-$noun-$adj3-$animal";
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
        $exists = DB::table('overlay_hashes')
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
        return count($this->adjectives1) *
               count($this->adjectives2) *
               count($this->nouns) *
               count($this->adjectives3) *
               count($this->animals);
    }

    /**
     * Get collision risk percentage based on the current slug count saved in the database
     */
    public function getCollisionRisk(): array
    {
        $totalPossible = $this->getTotalPossibleCombinations();
        $currentCount = DB::table('overlay_hashes')->count();
        $collisionRisk = ($currentCount / $totalPossible) * 100;

        return [
            'total_possible' => $totalPossible,
            'current_count' => $currentCount,
            'collision_risk_percent' => round($collisionRisk, 2),
            'recommended_action' => $collisionRisk > 70 ? 'Add more words to pools' : 'All good!'
        ];
    }

    /**
     * Regenerate slug for existing overlay (useful for conflicts)
     */
    public function regenerateSlugForOverlay(int $overlayId): string
    {
        $newSlug = $this->generateUniqueSlug();

        DB::table('overlay_hashes')
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
        if (!empty($uncachedSlugs)) {
            $existingSlugs = DB::table('overlay_hashes')
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
