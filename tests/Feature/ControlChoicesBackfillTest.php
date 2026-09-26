<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\OverlayMarkdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The 2026-09-27 backfill: installs of Twitch Chat and Chat Emote Bubbles
 * that predate `config.choices` get the vocabulary their recipe now declares,
 * and nothing else is touched.
 */
function backfillMigration(): Migration
{
    return require base_path('database/migrations/2026_09_27_120000_declare_vocabularies_on_installed_product_text_controls.php');
}

function backfillUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'backfill'.fake()->unique()->randomNumber(5)],
    ]);
}

function backfillInstall(User $user, string $slug): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find($slug));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom($slug));

    return OverlayTemplate::findOrFail(collect($instance->primitive_map['overlays'])->first());
}

function declaredChoices(string $slug, string $file, string $key): array
{
    $doc = OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor($slug).DIRECTORY_SEPARATOR.$file));

    return collect($doc['controls'])->keyBy('key')[$key]['config']['choices'];
}

/** What an install from before the recipe declared its vocabularies looks like. */
function stripChoices(OverlayTemplate $template, array $keys): void
{
    foreach ($template->controls()->whereIn('key', $keys)->get() as $control) {
        $config = $control->config ?? [];
        unset($config['choices']);
        $control->update(['config' => $config === [] ? null : $config]);
        expect($control->fresh()->config['choices'] ?? null)->toBeNull();
    }
}

it('puts the declared vocabulary on installed product rows that lack one', function () {
    $user = backfillUser();
    $chat = backfillInstall($user, 'twitch-chat-overlay');
    $bubbles = backfillInstall($user, 'chat-emote-bubbles');

    stripChoices($chat, ['layout', 'background']);
    stripChoices($bubbles, ['look', 'direction', 'spawn']);

    // A row that carries other config keeps them; a value stays what it was.
    $bubbles->controls()->where('key', 'look')->update(['value' => 'heart']);

    backfillMigration()->up();

    expect($chat->controls()->where('key', 'layout')->firstOrFail()->config['choices'])->toBe(declaredChoices('twitch-chat-overlay', 'chat.md', 'layout'))
        ->and($chat->controls()->where('key', 'background')->firstOrFail()->config['choices'])->toBe(declaredChoices('twitch-chat-overlay', 'chat.md', 'background'))
        ->and($bubbles->controls()->where('key', 'look')->firstOrFail()->config['choices'])->toBe(declaredChoices('chat-emote-bubbles', 'bubbles.md', 'look'))
        ->and($bubbles->controls()->where('key', 'look')->firstOrFail()->value)->toBe('heart')
        ->and($bubbles->controls()->where('key', 'direction')->firstOrFail()->config['choices'])->toBe(declaredChoices('chat-emote-bubbles', 'bubbles.md', 'direction'))
        ->and($bubbles->controls()->where('key', 'spawn')->firstOrFail()->config['choices'])->toBe(declaredChoices('chat-emote-bubbles', 'bubbles.md', 'spawn'));
});

it('leaves a hand-made control with the same key, a row that already has a vocabulary, and the number controls alone', function () {
    $user = backfillUser();
    $bubbles = backfillInstall($user, 'chat-emote-bubbles');

    $own = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'static']);
    $hand = OverlayControl::createForTemplate($own, $user, ['key' => 'look', 'type' => 'text', 'value' => 'anything']);

    $custom = [['value' => 'bubble', 'label' => 'Only bubbles', 'hint' => '']];
    $bubbles->controls()->where('key', 'spawn')->firstOrFail()->update(['config' => ['choices' => $custom]]);

    $sizeBefore = $bubbles->controls()->where('key', 'bubble_size')->firstOrFail()->config;

    backfillMigration()->up();

    expect($hand->fresh()->config)->toBeNull()
        ->and($bubbles->controls()->where('key', 'spawn')->firstOrFail()->config['choices'])->toBe($custom)
        ->and($bubbles->controls()->where('key', 'bubble_size')->firstOrFail()->config)->toBe($sizeBefore);
});
