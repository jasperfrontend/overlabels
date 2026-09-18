<?php

use App\Models\Kit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// Kit::boot()'s deleting hook refuses to delete a kit that has been copied, so
// that nobody destroys something other people built on. That guard is right for
// the kits page and wrong for account erasure: a single copied kit threw out of
// the UserDeletionService transaction and aborted the whole delete. Because the
// controller logged the user out first, the result was a logged-out user, an
// error page, and an account that still existed.
//
// Both halves are pinned here: the erasure completes, and a failure cannot
// strand the session.

it('deletes an account whose kit has been copied by someone else', function () {
    $user = User::factory()->create();

    Kit::factory()->create([
        'owner_id' => $user->id,
        'fork_count' => 3,
    ]);

    $this->actingAs($user)
        ->from(route('settings.account'))
        ->delete(route('settings.account.destroy'), ['confirmation' => 'DELETE ACCOUNT']);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('kits', ['owner_id' => $user->id]);
    $this->assertGuest();
});

it('leaves a copy standing when the account it was copied from is deleted', function () {
    $author = User::factory()->create();
    $copier = User::factory()->create();

    $original = Kit::factory()->create([
        'owner_id' => $author->id,
        'fork_count' => 1,
    ]);

    $copy = Kit::factory()->create([
        'owner_id' => $copier->id,
        'forked_from_id' => $original->id,
    ]);

    $this->actingAs($author)
        ->delete(route('settings.account.destroy'), ['confirmation' => 'DELETE ACCOUNT']);

    // The copy survives and simply stops pointing at a parent that is gone.
    $this->assertDatabaseHas('kits', ['id' => $copy->id, 'owner_id' => $copier->id]);
    expect($copy->fresh()->forked_from_id)->toBeNull();
});

it('still refuses an interactive delete of a copied kit', function () {
    $user = User::factory()->create();

    $kit = Kit::factory()->create([
        'owner_id' => $user->id,
        'fork_count' => 2,
    ]);

    $this->actingAs($user)
        ->from(route('kits.index'))
        ->delete(route('kits.destroy', $kit))
        ->assertSessionHasErrors('error');

    $this->assertDatabaseHas('kits', ['id' => $kit->id]);
});

it('restores the fork guard after an erasure', function () {
    $victim = User::factory()->create();
    Kit::factory()->create(['owner_id' => $victim->id, 'fork_count' => 1]);

    $this->actingAs($victim)
        ->delete(route('settings.account.destroy'), ['confirmation' => 'DELETE ACCOUNT']);

    // A later interactive delete must not inherit the lifted guard.
    $other = User::factory()->create();
    $kit = Kit::factory()->create(['owner_id' => $other->id, 'fork_count' => 1]);

    expect(fn () => $kit->delete())->toThrow(Exception::class);
});
