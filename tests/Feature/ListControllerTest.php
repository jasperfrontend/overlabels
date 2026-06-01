<?php

use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Index
// ──────────────────────────────────────────────────────────────────────────────

it('index returns user-owned lists with the [[[c:list:<slug>]]] tag baked in', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'pizza',
        'label' => 'Pizza',
        'items' => ['Pepperoni', 'Mushroom'],
        'min_items' => 0,
        'max_items' => null,
        'user_editable' => true,
    ]);

    $this->actingAs($user)->get('/dashboard/lists')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/lists/index')
            ->has('lists', 1)
            ->where('lists.0.slug', 'pizza')
            ->where('lists.0.tag', '[[[c:list:pizza]]]')
            ->where('lists.0.items', ['Pepperoni', 'Mushroom'])
        );
});

it('does not leak lists owned by another user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    OptionSet::create([
        'user_id' => $other->id,
        'slug' => 'someone_elses',
        'items' => ['x'],
    ]);

    $this->actingAs($me)->get('/dashboard/lists')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('lists', 0));
});

// ──────────────────────────────────────────────────────────────────────────────
// Store
// ──────────────────────────────────────────────────────────────────────────────

it('store creates a user-authored list preserving exactly what was sent', function () {
    Event::fake([ListUpdated::class]);

    $user = User::factory()->create(['twitch_id' => '12345']);

    $this->actingAs($user)->post('/dashboard/lists', [
        'slug' => 'pizza',
        'label' => 'Pizza toppings',
        'items' => ['Pepperoni', 'Mushroom', '', 'Mushroom', ' '],
    ])->assertRedirect(route('lists.show', 'pizza'));

    $list = OptionSet::where('user_id', $user->id)->where('slug', 'pizza')->first();
    expect($list)->not->toBeNull()
        // Lists are lists: empties, duplicates, and whitespace-only entries
        // are intentional content. The controller does NOT dedupe or strip.
        ->and(ListItems::values($list->items))->toBe(['Pepperoni', 'Mushroom', '', 'Mushroom', ' '])
        ->and($list->recipe_instance_id)->toBeNull()
        ->and($list->user_editable)->toBeTrue();

    Event::assertDispatched(ListUpdated::class, fn (ListUpdated $e) => $e->slug === 'pizza' && ListItems::values($e->items) === ['Pepperoni', 'Mushroom', '', 'Mushroom', ' ']);
});

it('store rejects a slug that already exists for this user', function () {
    $user = User::factory()->create();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'pizza', 'items' => []]);

    $this->actingAs($user)->post('/dashboard/lists', [
        'slug' => 'pizza',
        'items' => [],
    ])->assertSessionHasErrors('slug');
});

it('store rejects malformed slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/dashboard/lists', [
        'slug' => 'Pizza-Capital',
        'items' => [],
    ])->assertSessionHasErrors('slug');
});

it('store strips NUL bytes but preserves everything else verbatim', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);

    $this->actingAs($user)->post('/dashboard/lists', [
        'slug' => 'weird',
        'items' => ['clean', "with\0null", '  spaces  ', '🌮'],
    ]);

    $list = OptionSet::where('user_id', $user->id)->where('slug', 'weird')->first();
    expect(ListItems::values($list->items))->toBe(['clean', 'withnull', '  spaces  ', '🌮']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Update
// ──────────────────────────────────────────────────────────────────────────────

it('update accepts chat_permissions PATCH and drops default-level entries', function () {
    $user = User::factory()->create(['twitch_id' => '888']);
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'q', 'items' => []]);

    // Mix: count -> override to 'everyone', clear -> matches default 'moderator'
    // (should be dropped at save time so the stored JSON stays minimal),
    // search -> override to 'vip'.
    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'chat_permissions' => [
            'count' => 'everyone',
            'clear' => 'moderator',
            'search' => 'vip',
        ],
    ])->assertRedirect();

    $list->refresh();
    expect($list->chat_permissions)->toBe(['count' => 'everyone', 'search' => 'vip']);
});

it('update accepts a fully-default chat_permissions PATCH and persists null', function () {
    $user = User::factory()->create(['twitch_id' => '887']);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'q',
        'items' => [],
        'chat_permissions' => ['count' => 'everyone'],
    ]);

    // All-defaults submission -> stored as NULL (no overrides).
    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'chat_permissions' => [
            'count' => 'moderator',
        ],
    ])->assertRedirect();

    $list->refresh();
    expect($list->chat_permissions)->toBeNull();
});

it('update rejects unknown permission levels in chat_permissions PATCH', function () {
    $user = User::factory()->create(['twitch_id' => '886']);
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'q', 'items' => []]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'chat_permissions' => ['count' => 'gooseberry'],
    ])->assertSessionHasErrors('chat_permissions.count');
});

it('update replaces items wholesale and broadcasts ListUpdated', function () {
    Event::fake([ListUpdated::class]);
    $user = User::factory()->create(['twitch_id' => '999']);
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'pizza', 'items' => ['a']]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'label' => 'new label',
        'items' => ['b', 'c'],
    ])->assertRedirect();

    $list->refresh();
    expect(ListItems::values($list->items))->toBe(['b', 'c'])
        ->and($list->label)->toBe('new label');

    Event::assertDispatched(ListUpdated::class, fn (ListUpdated $e) => $e->slug === 'pizza' && ListItems::values($e->items) === ['b', 'c']);
});

it('update refuses recipe-locked lists', function () {
    $user = User::factory()->create();
    $recipe = Recipe::create([
        'slug' => 'test_rec',
        'version' => 1,
        'name' => 'Test',
        'description' => 'x',
        'author_name' => 'x',
        'manifest' => [],
    ]);
    $instance = RecipeInstance::create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'instance_slug' => 'main',
        'primitive_map' => [],
    ]);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'recipe_instance_id' => $instance->id,
        'slug' => 'recipe_list',
        'items' => ['a'],
        'user_editable' => false, // locked
    ]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'items' => ['b'],
    ])->assertForbidden();

    $list->refresh();
    expect($list->items)->toBe(['a']);
});

it('update allows recipe-installed lists when user_editable is true', function () {
    $user = User::factory()->create(['twitch_id' => '999']);
    $recipe = Recipe::create([
        'slug' => 'test_rec',
        'version' => 1,
        'name' => 'Test',
        'description' => 'x',
        'author_name' => 'x',
        'manifest' => [],
    ]);
    $instance = RecipeInstance::create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'instance_slug' => 'main',
        'primitive_map' => [],
    ]);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'recipe_instance_id' => $instance->id,
        'slug' => 'editable_recipe_list',
        'items' => ['a'],
        'user_editable' => true,
    ]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'items' => ['b', 'c'],
    ])->assertRedirect();

    expect(ListItems::values($list->fresh()->items))->toBe(['b', 'c']);
});

it('update enforces min/max bounds when set', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'bounded',
        'items' => ['a', 'b'],
        'min_items' => 2,
        'max_items' => 4,
        'user_editable' => true,
    ]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'items' => ['only_one'],
    ])->assertStatus(422);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'items' => ['a', 'b', 'c', 'd', 'e'],
    ])->assertStatus(422);

    expect($list->fresh()->items)->toBe(['a', 'b']);
});

it('update refuses to act on another users list (404)', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $list = OptionSet::create(['user_id' => $owner->id, 'slug' => 'private_list', 'items' => ['x']]);

    $this->actingAs($intruder)->put("/dashboard/lists/{$list->id}", [
        'items' => ['changed'],
    ])->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Destroy
// ──────────────────────────────────────────────────────────────────────────────

it('destroy removes user-authored lists and broadcasts a delete', function () {
    Event::fake([ListUpdated::class]);
    $user = User::factory()->create(['twitch_id' => '999']);
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'gone', 'items' => ['x']]);

    $this->actingAs($user)->delete("/dashboard/lists/{$list->id}")
        ->assertRedirect();

    expect(OptionSet::find($list->id))->toBeNull();
    Event::assertDispatched(ListUpdated::class, fn (ListUpdated $e) => $e->slug === 'gone' && $e->items === null);
});

it('destroy refuses recipe-installed lists', function () {
    $user = User::factory()->create();
    $recipe = Recipe::create([
        'slug' => 'test_rec',
        'version' => 1,
        'name' => 'Test',
        'description' => 'x',
        'author_name' => 'x',
        'manifest' => [],
    ]);
    $instance = RecipeInstance::create([
        'recipe_id' => $recipe->id,
        'user_id' => $user->id,
        'instance_slug' => 'main',
        'primitive_map' => [],
    ]);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'recipe_instance_id' => $instance->id,
        'slug' => 'recipe_list',
        'items' => ['a'],
        'user_editable' => true,
    ]);

    $this->actingAs($user)->delete("/dashboard/lists/{$list->id}")
        ->assertForbidden();

    expect(OptionSet::find($list->id))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Broadcast payload shape
// ──────────────────────────────────────────────────────────────────────────────

it('ListUpdated broadcasts on the alerts.{twitch_id} channel as list.updated', function () {
    $event = new ListUpdated('99988877', 'pizza', ['a', 'b'], 1234567890);

    expect($event->broadcastOn()[0]->name)->toBe('private-alerts.99988877')
        ->and($event->broadcastAs())->toBe('list.updated')
        ->and($event->broadcastWith())->toBe([
            'slug' => 'pizza',
            'items' => ['a', 'b'],
            'updated_at' => 1234567890,
            'expires_at' => null,
            'disabled_at' => null,
        ]);
});

it('ListUpdated with null items broadcasts as list.deleted', function () {
    $event = new ListUpdated('99988877', 'pizza', null, null);
    expect($event->broadcastAs())->toBe('list.deleted');
});
