<?php

namespace App\Support;

use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;

/**
 * "This person is mid-install of product X." One fact on the user, in the
 * preferences column, set by the install button and cleared when the product
 * page sees nothing left to do or the person says not now. While it is set,
 * every app page carries a banner that says what is left and reels them back
 * to the product page; the product page itself never shows it.
 *
 * The steps are the product's own wiring subject, so the banner and the page
 * can never disagree about what is left.
 */
final class ProductSetup
{
    public const PREFERENCE = 'product_setup';

    public static function start(User $user, string $slug): void
    {
        $user->setPreference(self::PREFERENCE, ['slug' => $slug, 'started_at' => now()->timestamp])->save();
    }

    public static function end(User $user): void
    {
        if (self::activeSlug($user) === null) {
            return;
        }

        $user->setPreference(self::PREFERENCE, null)->save();
    }

    public static function activeSlug(User $user): ?string
    {
        $slug = $user->preference(self::PREFERENCE.'.slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * What the banner shows, or null when no flow is active or the product
     * is no longer installed (an uninstall ends the flow, but a deleted
     * instance from anywhere else must not leave a banner about nothing).
     *
     * @return array{slug: string, name: string, url: string, remaining: int, next: ?array{label: string, message: string}, ready: bool}|null
     */
    public static function banner(User $user, RecipeCatalog $catalog): ?array
    {
        $slug = self::activeSlug($user);
        if ($slug === null) {
            return null;
        }

        $manifest = $catalog->find($slug);
        $instance = self::instanceFor($user, $slug);
        if ($manifest === null || $instance === null) {
            return null;
        }

        $steps = self::steps($instance);
        $missing = array_values(array_filter($steps, fn (array $wire) => $wire['state'] === WiringCatalog::MISSING));
        $next = $missing[0] ?? null;

        return [
            'slug' => $slug,
            'name' => $manifest['name'],
            'url' => route('products.show', $slug),
            'remaining' => count($missing),
            'next' => $next ? ['label' => $next['label'], 'message' => $next['message']] : null,
            'ready' => $missing === [],
        ];
    }

    /**
     * The product's wires in checklist order, with their copy, minus the ones
     * that do not apply. Same list the product page renders.
     *
     * @return list<array<string, mixed>>
     */
    public static function steps(RecipeInstance $instance): array
    {
        $report = WiringReport::build(['products' => [WiringFacts::productSubject($instance)]]);
        $circuit = collect($report)->firstWhere('key', 'products');
        $wires = $circuit['subjects'][0]['wires'] ?? [];

        return array_values(array_filter($wires, fn (array $wire) => $wire['state'] !== WiringCatalog::NOT_APPLICABLE));
    }

    public static function instanceFor(User $user, string $slug): ?RecipeInstance
    {
        return RecipeInstance::with('recipe')
            ->where('user_id', $user->id)
            ->whereIn('recipe_id', Recipe::where('slug', $slug)->select('id'))
            ->orderByDesc('created_at')
            ->first();
    }
}
