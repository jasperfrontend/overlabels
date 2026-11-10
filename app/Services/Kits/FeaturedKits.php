<?php

namespace App\Services\Kits;

use App\Models\Kit;

/**
 * The official ready-made kits, as cards: what the kit picker a new account
 * lands on offers (/start), and what leads the Alerts shelf on /products.
 *
 * A kit is featured when `kits.featured_at` is set and it is public. The flag
 * is set by hand; a copy never inherits it (Kit::fork). Oldest feature first,
 * so the order on the page is the order they were featured in.
 */
class FeaturedKits
{
    /**
     * @return list<array{id: int, title: string, description: ?string, thumbnail: ?string, covers: list<string>, alerts: int}>
     */
    public function cards(): array
    {
        return Kit::query()
            ->whereNotNull('featured_at')
            ->where('is_public', true)
            ->orderBy('featured_at')
            ->with('templates:id,type')
            ->get()
            ->map(fn (Kit $kit) => [
                'id' => $kit->id,
                'title' => $kit->title,
                'description' => $kit->description,
                'thumbnail' => $kit->thumbnail_url,
                // The services its declared triggers listen to ('twitch' for
                // Twitch events), in first-declared order.
                'covers' => array_values(array_unique(array_column($kit->alertTriggers(), 'service'))),
                'alerts' => $kit->templates->where('type', 'alert')->count(),
            ])
            ->values()
            ->all();
    }
}
