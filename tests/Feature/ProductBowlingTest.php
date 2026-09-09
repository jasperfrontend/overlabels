<?php

use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Follower Bowling is the second product and the first to install a List
 * and a chat command. Its overlay carries twenty expression controls and a
 * gobowl toggle; the lane list and the !bowl appender are what the deep
 * dive told a streamer to make by hand.
 */
function bowlingUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'bowlingtester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function bowlingRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('follower_bowling'));
}

it('is listed with its hero image', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.1.slug', 'follower_bowling')
            ->where('products.1.hero', '/products/follower-bowling-hero.svg')
            ->where('products.0.hero', '/products/chat-checkin-hero.svg')
        );

    expect(is_file(public_path('products/follower-bowling-hero.svg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/follower-bowling-hero.svg')))->not->toContain('c2pa');
});

it('shows the list and the command it will create', function () {
    $this->get('/products/follower_bowling')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.lists.0.slug', 'lane')
            ->where('product.commands.0.command', '!bowl')
            ->where('product.commands.0.kind', 'appender')
            ->where('product.commands.1.command', '!fbfirst')
            ->where('product.commands.1.kind', 'alias')
            ->where('product.commands.2.command', '!fbdraw')
            ->where('product.overlays.0.name', 'Follower bowling lane')
        );
});

it('installs the lane overlay with all 21 controls, the lane list and the !bowl appender', function () {
    $user = bowlingUser();

    $instance = app(RecipeInstaller::class)->install(bowlingRecipe(), $user, 'follower_bowling');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['lane']);
    expect($template)->not->toBeNull()
        ->and($template->html)->toContain('[[[foreach:c:list:lane as p]]]')
        ->and($template->html)->not->toContain('DO NOT DELETE');
    expect(OverlayControl::where('overlay_template_id', $template->id)->count())->toBe(21)
        ->and(OverlayControl::where('overlay_template_id', $template->id)->where('type', 'expression')->count())->toBe(20);

    $list = OptionSet::find($instance->primitive_map['lists']['lane']);
    expect($list)->not->toBeNull()
        ->and($list->user_id)->toBe($user->id)
        ->and($list->slug)->toBe('lane')
        ->and($list->recipe_instance_id)->toBe($instance->id)
        ->and($list->items)->toBe([]);

    $appender = ListAppender::find($instance->primitive_map['list_appenders']['bowl']);
    expect($appender)->not->toBeNull()
        ->and($appender->command)->toBe('bowl')
        ->and($appender->target_list_id)->toBe($list->id)
        ->and($appender->value_template)->toBe('[[[bot:from_user]]]')
        ->and($appender->dedup_policy)->toBe('per_chatter')
        ->and($appender->enabled)->toBeTrue();
});

it('refuses to install over an existing list with the slug lane', function () {
    $user = bowlingUser();
    OptionSet::create([
        'user_id' => $user->id, 'slug' => 'lane', 'label' => 'Mine', 'items' => [], 'next_item_id' => 1,
        'min_items' => 0, 'max_items' => null, 'user_editable' => true,
    ]);

    expect(fn () => app(RecipeInstaller::class)->install(bowlingRecipe(), $user, 'follower_bowling'))
        ->toThrow(RuntimeException::class, "slug 'lane'");

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0)
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(0);
});

it('refuses to install over an existing !bowl command before creating anything', function () {
    $user = bowlingUser();
    BotCommand::create(['user_id' => $user->id, 'command' => 'bowl', 'reply' => 'strike', 'enabled' => true]);

    expect(fn () => app(RecipeInstaller::class)->install(bowlingRecipe(), $user, 'follower_bowling'))
        ->toThrow(RuntimeException::class, '!bowl');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0);
});

it('surfaces the refusal on the product page instead of a 500', function () {
    $user = bowlingUser();
    ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => OptionSet::create([
            'user_id' => $user->id, 'slug' => 'other', 'items' => [], 'next_item_id' => 1,
            'min_items' => 0, 'max_items' => null, 'user_editable' => true,
        ])->id,
        'command' => 'bowl',
        'value_template' => '[[[bot:from_user]]]',
    ]);

    $this->actingAs($user)
        ->post('/products/follower_bowling/install')
        ->assertRedirect('/products/follower_bowling')
        ->assertSessionHasErrors('install');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0);
});

it('reports the list and command wires and leaves the integration wire out', function () {
    $user = bowlingUser(['bot_enabled' => true]);
    $instance = app(RecipeInstaller::class)->install(bowlingRecipe(), $user, 'follower_bowling');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.command'])->toBe(WiringCatalog::SATISFIED);

    ListAppender::find($instance->primitive_map['list_appenders']['bowl'])->update(['enabled' => false]);
    expect(WiringFacts::productSubject($instance->fresh())['states']['product.command'])->toBe(WiringCatalog::MISSING);

    OptionSet::find($instance->primitive_map['lists']['lane'])->delete();
    expect(WiringFacts::productSubject($instance->fresh())['states']['product.list'])->toBe(WiringCatalog::MISSING);
});

it('keeps the checkin product free of list and command wires', function () {
    $user = bowlingUser();
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat_checkin')), $user, 'chat_checkin');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE);
});
