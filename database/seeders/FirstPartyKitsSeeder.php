<?php

namespace Database\Seeders;

use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Ships first-party Kits owned by the ghost user. Each kit bundles a
 * first-party recipe (via kit_recipes) and an overlay template that uses
 * the recipe's control exports. The kit is marked is_starter_kit so it
 * surfaces in the onboarding wizard.
 *
 * Runs after GhostUserSeeder (owner) and FirstPartyRecipesSeeder (recipes
 * catalogue) - see DatabaseSeeder for the order.
 *
 * Idempotent: re-running the seeder finds the same kit by slug-ish title
 * and updates its templates / recipes attach links in place.
 */
class FirstPartyKitsSeeder extends Seeder
{
    public function run(): void
    {
        $ghost = User::where('twitch_id', 'GHOST_USER')->first();
        if (! $ghost) {
            throw new RuntimeException('Ghost user not seeded yet - GhostUserSeeder must run first.');
        }

        $this->seedWheelOfFortune($ghost);
    }

    private function seedWheelOfFortune(User $ghost): void
    {
        $recipe = Recipe::where('slug', 'wheel_spin')->where('version', 1)->first();
        if (! $recipe) {
            throw new RuntimeException("wheel_spin recipe not seeded - FirstPartyRecipesSeeder must run first.");
        }

        $assetDir = base_path('resources/kits/wheel_of_fortune');
        $html = file_get_contents($assetDir.'/overlay.html');
        $css = file_get_contents($assetDir.'/overlay.css');
        $js = file_get_contents($assetDir.'/overlay.js');

        if ($html === false || $css === false || $js === false) {
            throw new RuntimeException("Wheel of Fortune overlay assets not readable at {$assetDir}.");
        }

        // Owned by the ghost user, kept under a stable slug so re-runs find
        // and update the same row rather than spawning duplicates.
        $template = OverlayTemplate::updateOrCreate(
            ['slug' => 'overlabels-wheel-of-fortune'],
            [
                'owner_id' => $ghost->id,
                'name' => 'Wheel of Fortune',
                'description' => 'A spinning wheel that lands on a random segment. Paired with the wheel_spin recipe.',
                'type' => 'static',
                'html' => $html,
                'css' => $css,
                'js' => $js,
                'is_public' => true,
                'version' => 1,
            ]
        );

        $kit = Kit::firstOrCreate(
            ['owner_id' => $ghost->id, 'title' => 'Wheel of Fortune'],
            [
                'description' => 'A spinning wheel of fortune. Type !spin in chat or click the dashboard button. Edit the segment labels in the overlay HTML and the matching values in the Wheel segments controls to customise.',
                'is_public' => true,
                'is_starter_kit' => true,
            ]
        );

        // sync() makes attach idempotent - re-runs won't duplicate the link.
        $kit->templates()->sync([$template->id]);
        $kit->recipes()->sync([
            $recipe->id => [
                'default_instance_slug' => 'main',
                'sort_order' => 0,
            ],
        ]);
    }
}
