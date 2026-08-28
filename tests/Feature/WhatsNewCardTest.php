<?php

use App\Models\Update;
use App\Models\UpdateInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->toBe(['label' => 'Read more', 'href' => 'https://example.com/x?a=1', 'external' => true]);
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
// Going stale on a visit
// ──────────────────────────────────────────────────────────────────────────────

it('goes stale when the reader lands on the route it points at', function () {
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: See recent events\n---\n\nBody.\n"]);

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeFalse();

    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();

    $prop = whatsNewProp($user);

    // Still on the card - visiting is not dismissing.
    expect($prop['items'])->toHaveCount(1)
        ->and($prop['items'][0]['stale'])->toBeTrue()
        ->and($prop['total'])->toBe(1);
});

it('goes stale on arrival however the reader got there', function () {
    // The whole point: the sidebar, a bookmark or a typed URL all count. The
    // card never sees the click, so it cannot be what triggers this.
    $user = cardUser();
    makeCardUpdate(['body' => "---\nurl: /dashboard/recents\nlabel: See recent events\n---\n\nBody.\n"]);

    $this->actingAs($user)->get('/dashboard/recents')->assertOk();

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeTrue();
});

it('ignores a route no entry points at', function () {
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($user)->get(route('updates.index'))->assertOk();

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeFalse()
        ->and(UpdateInteraction::count())->toBe(0);
});

it('matches on the route name regardless of its parameters', function () {
    // A CTA aimed at a filtered view is satisfied by arriving at the page.
    // Demanding an exact query string leaves rows stuck teal for readers who
    // did exactly what was asked.
    $user = cardUser();
    makeCardUpdate([
        'body' => "---\nroute: templates.index\nparams: filter=community&type=static\nlabel: Browse\n---\n\nBody.\n",
    ]);

    $this->actingAs($user)->get('/templates?filter=mine&type=alert')->assertOk();

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeTrue();
});

it('does not write again once an entry is already stale', function () {
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();
    $first = UpdateInteraction::where('user_id', $user->id)->first()->visited_at;

    $this->travel(5)->minutes();
    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();

    expect(UpdateInteraction::where('user_id', $user->id)->count())->toBe(1)
        ->and(UpdateInteraction::where('user_id', $user->id)->first()->visited_at->timestamp)
        ->toBe($first->timestamp);
});

it('does not go stale for a different account', function () {
    $mine = cardUser();
    $theirs = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($mine)->get(route('dashboard.recents'))->assertOk();

    expect(whatsNewProp($theirs)['items'][0]['stale'])->toBeFalse();
});

it('marks an external link visited from the browser', function () {
    $user = cardUser();
    $update = makeCardUpdate(['body' => "---\nurl: https://example.com/x\nlabel: Read more\n---\n\nBody.\n"]);

    expect(whatsNewProp($user)['items'][0]['cta']['external'])->toBeTrue();

    $this->actingAs($user)->post(route('dashboard.whats-new.visited', $update))->assertRedirect();

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeTrue();
});

it('survives a stale entry through an undo', function () {
    // Undo nulls dismissed_at and leaves the row, so an entry that was grey
    // before it was cleared comes back grey rather than shouting again.
    $user = cardUser();
    makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();
    $this->actingAs($user)->post(route('dashboard.whats-new.seen'))->assertRedirect();
    $this->actingAs($user)->delete(route('dashboard.whats-new.undo'))->assertRedirect();

    expect(whatsNewProp($user)['items'][0]['stale'])->toBeTrue();
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

it('dismisses a stale entry without losing that it was visited', function () {
    $user = cardUser();
    $update = makeCardUpdate(['body' => "---\nroute: dashboard.recents\nlabel: Go\n---\n\nBody.\n"]);

    $this->actingAs($user)->get(route('dashboard.recents'))->assertOk();
    $this->actingAs($user)->delete(route('dashboard.whats-new.dismiss', $update))->assertRedirect();

    $row = UpdateInteraction::where('user_id', $user->id)->first();

    expect(whatsNewProp($user)['items'])->toBeEmpty()
        ->and($row->visited_at)->not->toBeNull()
        ->and($row->dismissed_at)->not->toBeNull();
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
