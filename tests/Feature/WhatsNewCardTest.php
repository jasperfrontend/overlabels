<?php

use App\Models\Update;
use App\Models\UpdateInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Matches the local-helper style of PublicUpdatesTest - App\Models\Update has
 * no HasFactory trait, so Update::factory() would fatal.
 */
function makeCardUpdate(array $attributes = []): Update
{
    return Update::create(array_merge([
        'title' => 'Wiring status',
        'body' => 'Here is what shipped.',
        'excerpt' => 'A live view of every subscription feeding your overlays.',
        'tags' => ['whatsnew'],
        'published_at' => now()->subDay(),
    ], $attributes));
}

/**
 * The card only ever shows posts published after the account existed, so a
 * user in these tests has to predate the fixture. created_at is not fillable.
 */
function cardUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->forceFill(['created_at' => now()->subYear()])->save();

    return $user->fresh();
}

function whatsNewProp(User $user): array
{
    return test()->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->viewData('page')['props']['whatsNew'];
}

// ──────────────────────────────────────────────────────────────────────────────
// Selection
// ──────────────────────────────────────────────────────────────────────────────

it('shows a tagged, published post to a user who predates it', function () {
    $user = cardUser();
    makeCardUpdate(['title' => 'Wiring status']);

    $prop = whatsNewProp($user);

    expect($prop['items'])->toHaveCount(1)
        ->and($prop['items'][0]['title'])->toBe('Wiring status')
        ->and($prop['total'])->toBe(1)
        ->and($prop['canUndo'])->toBeFalse();
});

it('ignores a post without the whatsnew tag', function () {
    $user = cardUser();
    makeCardUpdate(['tags' => ['release']]);
    makeCardUpdate(['tags' => null, 'slug' => 'untagged']);

    expect(whatsNewProp($user)['items'])->toBeEmpty();
});

it('ignores a post published before the account existed', function () {
    // A new account is caught up by definition - you are not accountable for
    // what shipped before you existed, and this needs no registration hook.
    $user = User::factory()->create();
    makeCardUpdate(['published_at' => now()->subMonth()]);

    expect(whatsNewProp($user)['items'])->toBeEmpty();
});

it('ignores a future dated post', function () {
    $user = cardUser();
    makeCardUpdate(['published_at' => now()->addWeek()]);

    expect(whatsNewProp($user)['items'])->toBeEmpty();
});

it('caps the rendered rows but still counts the rest', function () {
    $user = cardUser();
    foreach (range(1, 7) as $i) {
        makeCardUpdate(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
    }

    $prop = whatsNewProp($user);

    expect($prop['items'])->toHaveCount(5)
        ->and($prop['total'])->toBe(7);
});

it('orders newest first', function () {
    $user = cardUser();
    makeCardUpdate(['title' => 'Older', 'slug' => 'older', 'published_at' => now()->subDays(5)]);
    makeCardUpdate(['title' => 'Newer', 'slug' => 'newer', 'published_at' => now()->subHour()]);

    expect(whatsNewProp($user)['items'][0]['title'])->toBe('Newer');
});

// ──────────────────────────────────────────────────────────────────────────────
// Mark all as seen, and Undo
// ──────────────────────────────────────────────────────────────────────────────

it('marks every unseen post as seen and then shows the caught up bar', function () {
    $user = cardUser();
    makeCardUpdate(['slug' => 'one']);
    makeCardUpdate(['slug' => 'two']);

    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();

    expect(UpdateInteraction::where('user_id', $user->id)->count())->toBe(2);

    $prop = whatsNewProp($user);
    expect($prop['items'])->toBeEmpty()
        ->and($prop['canUndo'])->toBeTrue();
});

it('is idempotent when marked seen twice', function () {
    $user = cardUser();
    makeCardUpdate();

    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();
    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();

    expect(UpdateInteraction::where('user_id', $user->id)->count())->toBe(1);
});

it('undoes only the most recent batch', function () {
    $user = cardUser();
    $first = makeCardUpdate(['slug' => 'first']);
    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();

    // Backdate the first batch so the second is unambiguously newer.
    UpdateInteraction::where('user_id', $user->id)
        ->update(['dismissed_at' => now()->subDays(3)]);

    $second = makeCardUpdate(['slug' => 'second']);
    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();

    $this->actingAs($user)->delete(route('dashboard.whats-new.undo'))->assertRedirect();

    // The older press stands; only the post cleared by the latest one returns.
    expect(UpdateInteraction::where('user_id', $user->id)->whereNotNull('dismissed_at')->pluck('update_id')->all())
        ->toBe([$first->id])
        ->and(collect(whatsNewProp($user)['items'])->pluck('id')->all())
        ->toBe([$second->id]);
});

it('never touches another account', function () {
    $mine = cardUser();
    $theirs = cardUser();
    makeCardUpdate();

    $this->actingAs($mine)->post(route('dashboard.whats-new.seen'))->assertRedirect();

    expect(UpdateInteraction::where('user_id', $theirs->id)->count())->toBe(0)
        ->and(whatsNewProp($theirs)['items'])->toHaveCount(1);

    $this->actingAs($theirs)->delete(route('dashboard.whats-new.undo'))->assertRedirect();

    expect(UpdateInteraction::where('user_id', $mine->id)->whereNotNull('dismissed_at')->count())->toBe(1);
});

it('requires a logged in user for both writes', function () {
    $this->post(route('dashboard.whats-new.seen'))->assertRedirect();
    $this->delete(route('dashboard.whats-new.undo'))->assertRedirect();

    expect(UpdateInteraction::count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// The per-row CTA, declared in body frontmatter
// ──────────────────────────────────────────────────────────────────────────────

it('resolves a route name and params into a link', function () {
    $user = cardUser();
    makeCardUpdate([
        'body' => "---\nroute: templates.index\nparams: filter=mine&type=static\nlabel: Check your overlays\n---\n\nReal body.\n",
    ]);

    $cta = whatsNewProp($user)['items'][0]['cta'];

    expect($cta['label'])->toBe('Check your overlays')
        ->and($cta['href'])->toContain('filter=mine')
        ->and($cta['href'])->toContain('type=static');
});

it('takes a url verbatim', function () {
    $user = cardUser();
    makeCardUpdate(['body' => "---\nurl: https://example.com/x?a=1\nlabel: Read more\n---\n\nBody.\n"]);

    expect(whatsNewProp($user)['items'][0]['cta'])
        ->toBe(['label' => 'Read more', 'href' => 'https://example.com/x?a=1']);
});

it('drops the link rather than throwing when the route has since been renamed', function () {
    // The deliberate asymmetry: a bad route name is loud at save time and quiet
    // at render time, because a stale CTA is worth less than a dashboard that
    // still loads.
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: route.deleted.last.year\nlabel: Go\n---\n\nBody.\n"]);

    $prop = whatsNewProp($user);

    expect($prop['items'])->toHaveCount(1)
        ->and($prop['items'][0]['cta'])->toBeNull();
});

it('has no cta when the author declared none', function () {
    $user = cardUser();
    makeCardUpdate(['body' => "Just a normal post.\n"]);

    expect(whatsNewProp($user)['items'][0]['cta'])->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Opening the post is reading it
// ──────────────────────────────────────────────────────────────────────────────

it('marks a post seen when a logged in reader opens it', function () {
    $user = cardUser();
    $update = makeCardUpdate();

    expect(whatsNewProp($user)['items'])->toHaveCount(1);

    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();

    $prop = whatsNewProp($user);

    expect($prop['items'])->toBeEmpty()
        ->and($prop['total'])->toBe(0)
        ->and($prop['canUndo'])->toBeTrue()
        ->and(UpdateInteraction::where('user_id', $user->id)->first()->dismissed_at)->not->toBeNull();
});

it('leaves the other posts on the card', function () {
    $user = cardUser();
    $one = makeCardUpdate(['slug' => 'one', 'title' => 'One']);
    makeCardUpdate(['slug' => 'two', 'title' => 'Two']);

    $this->actingAs($user)->get(route('updates.show', $one->slug))->assertOk();

    expect(collect(whatsNewProp($user)['items'])->pluck('title')->all())->toBe(['Two']);
});

it('writes nothing when a guest opens a post', function () {
    $update = makeCardUpdate();

    $this->get(route('updates.show', $update->slug))->assertOk();

    expect(UpdateInteraction::count())->toBe(0);
});

it('writes nothing for a post that was never on the reader\'s card', function () {
    // A post older than the account is not news to it. Writing a row anyway
    // would set canUndo and light the caught-up bar for someone who has
    // never cleared anything.
    $user = User::factory()->create();
    $update = makeCardUpdate(['published_at' => now()->subMonth()]);

    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();

    expect(UpdateInteraction::count())->toBe(0)
        ->and(whatsNewProp($user)['canUndo'])->toBeFalse();
});

it('writes nothing for a post without the card tag', function () {
    $user = cardUser();
    $update = makeCardUpdate(['tags' => ['release']]);

    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();

    expect(UpdateInteraction::count())->toBe(0);
});

it('does not move the batch when a post is opened again', function () {
    $user = cardUser();
    $update = makeCardUpdate();

    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();
    $first = UpdateInteraction::where('user_id', $user->id)->first()->dismissed_at;

    $this->travel(5)->minutes();
    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();

    expect(UpdateInteraction::where('user_id', $user->id)->count())->toBe(1)
        ->and(UpdateInteraction::where('user_id', $user->id)->first()->dismissed_at->timestamp)
        ->toBe($first->timestamp);
});

it('brings an opened post back with undo', function () {
    $user = cardUser();
    $update = makeCardUpdate();

    $this->actingAs($user)->get(route('updates.show', $update->slug))->assertOk();
    $this->actingAs($user)->delete(route('dashboard.whats-new.undo'))->assertRedirect();

    expect(collect(whatsNewProp($user)['items'])->pluck('id')->all())->toBe([$update->id]);
});

it('does not mark a post seen for a different account', function () {
    $mine = cardUser();
    $theirs = cardUser();
    $update = makeCardUpdate();

    $this->actingAs($mine)->get(route('updates.show', $update->slug))->assertOk();

    expect(whatsNewProp($theirs)['items'])->toHaveCount(1);
});

it('has no visited route and no visit middleware', function () {
    // The old layer: a middleware that greyed a row when the reader landed on
    // the page its CTA pointed at, and a route the card posted to for external
    // links. Both are gone; landing on a CTA target is not reading the post.
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();

    expect(UpdateInteraction::count())->toBe(0)
        ->and(whatsNewProp($user)['items'])->toHaveCount(1)
        ->and(Route::has('dashboard.whats-new.visited'))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// Dismissing one row
// ──────────────────────────────────────────────────────────────────────────────

it('dismisses a single entry and leaves the rest', function () {
    $user = cardUser();
    $one = makeCardUpdate(['slug' => 'one', 'title' => 'One']);
    makeCardUpdate(['slug' => 'two', 'title' => 'Two']);

    $this->actingAs($user)->delete(route('dashboard.whats-new.dismiss', $one))->assertRedirect();

    $prop = whatsNewProp($user);

    expect(collect($prop['items'])->pluck('title')->all())->toBe(['Two'])
        ->and($prop['total'])->toBe(1)
        ->and($prop['canUndo'])->toBeTrue();
});

it('refuses to dismiss on behalf of another account', function () {
    $mine = cardUser();
    $theirs = cardUser();
    $update = makeCardUpdate();

    $this->actingAs($mine)->delete(route('dashboard.whats-new.dismiss', $update))->assertRedirect();

    expect(whatsNewProp($theirs)['items'])->toHaveCount(1)
        ->and(UpdateInteraction::where('user_id', $theirs->id)->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// The CTA is projected onto columns so a visit can be detected in SQL
// ──────────────────────────────────────────────────────────────────────────────

it('projects frontmatter onto the cta columns on save', function () {
    $update = makeCardUpdate([
        'body' => "---\nroute: templates.index\nparams: filter=mine\nlabel: Check your overlays\n---\n\nBody.\n",
    ]);

    expect($update->cta_route)->toBe('templates.index')
        ->and($update->cta_params)->toBe('filter=mine')
        ->and($update->cta_label)->toBe('Check your overlays')
        ->and($update->cta_url)->toBeNull();
});

it('clears the columns when the frontmatter is removed', function () {
    $update = makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);
    expect($update->fresh()->cta_route)->toBe('dashboard.recents');

    $update->update(['body' => "Just prose now.\n"]);

    expect($update->fresh()->cta_route)->toBeNull()
        ->and($update->fresh()->cta_label)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Frontmatter never reaches the public post page
// ──────────────────────────────────────────────────────────────────────────────

it('strips the frontmatter block off the public post body', function () {
    $update = makeCardUpdate([
        'slug' => 'wiring-status',
        'body' => "---\nroute: dashboard.index\nlabel: Go\n---\n\nReal body copy.\n",
    ]);

    $body = $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->viewData('page')['props']['update']['body'];

    expect($body)->toBe("Real body copy.\n")
        ->and($body)->not->toContain('route:')
        ->and($body)->not->toContain('label:');
});

it('leaves a body that opens with a horizontal rule completely alone', function () {
    // Without the required-key guard this loses the intro paragraph and turns
    // a "Note:" line into a phantom metadata key.
    $raw = "---\n\nNote: this paragraph matters.\n\n---\n\nRest of the post.\n";
    $update = makeCardUpdate(['slug' => 'rule-first', 'body' => $raw]);

    $body = $this->get("/updates/{$update->slug}")
        ->assertOk()
        ->viewData('page')['props']['update']['body'];

    expect($body)->toBe($raw)
        ->and($body)->toContain('this paragraph matters');
});

// ──────────────────────────────────────────────────────────────────────────────
// Authoring gates
// ──────────────────────────────────────────────────────────────────────────────

it('refuses to save an update with no excerpt', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.updates.store'), [
            'title' => 'No excerpt',
            'body' => 'Body copy.',
        ])
        ->assertSessionHasErrors('excerpt');

    expect(Update::where('title', 'No excerpt')->exists())->toBeFalse();
});

it('refuses frontmatter naming a route that does not exist', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.updates.store'), [
            'title' => 'Bad route',
            'excerpt' => 'Short line.',
            'body' => "---\nroute: nope.not.a.route\nlabel: Go\n---\n\nBody.\n",
        ])
        ->assertSessionHasErrors('body');
});

it('refuses frontmatter carrying both a route and a url', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.updates.store'), [
            'title' => 'Both',
            'excerpt' => 'Short line.',
            'body' => "---\nroute: dashboard.index\nurl: https://example.com\nlabel: Go\n---\n\nBody.\n",
        ])
        ->assertSessionHasErrors('body');
});

it('refuses frontmatter with a link but no label', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.updates.store'), [
            'title' => 'No label',
            'excerpt' => 'Short line.',
            'body' => "---\nroute: dashboard.index\n---\n\nBody.\n",
        ])
        ->assertSessionHasErrors('body');
});

it('accepts a well formed post', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.updates.store'), [
            'title' => 'Good one',
            'excerpt' => 'Short line.',
            'tags' => ['whatsnew'],
            'body' => "---\nroute: dashboard.index\nlabel: Go\n---\n\nBody.\n",
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Update::where('title', 'Good one')->exists())->toBeTrue();
});
