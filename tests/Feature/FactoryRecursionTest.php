<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/*
 * Self-referential factory defaults are a trap: a nested factory on a FK that
 * points back at its own model recurses without a base case. Because nested
 * attributes resolve in declaration order, every level commits its owner User
 * before recursing, so a single create() spews users and never lands a row of
 * the model you asked for. One stray call did exactly this to the dev database
 * on 2026-06-01 (19,684 orphan users in 38 minutes).
 *
 * These tests pin the base case. Any FK that can point at its own table needs
 * a null default here.
 */

test('a template factory creates exactly one template and one user', function () {
    $templatesBefore = OverlayTemplate::count();
    $usersBefore = User::count();

    OverlayTemplate::factory()->create();

    expect(OverlayTemplate::count())->toBe($templatesBefore + 1)
        ->and(User::count())->toBe($usersBefore + 1);
});

test('a template factory leaves fork_of_id null by default', function () {
    expect(OverlayTemplate::factory()->create()->fork_of_id)->toBeNull();
});

test('the forked state creates a parent, and the parent does not recurse', function () {
    $templatesBefore = OverlayTemplate::count();

    $copy = OverlayTemplate::factory()->forked()->create();

    expect($copy->fork_of_id)->not->toBeNull()
        ->and(OverlayTemplate::count())->toBe($templatesBefore + 2)
        ->and($copy->forkParent->fork_of_id)->toBeNull();
});

test('a control factory does not recurse through its template', function () {
    $templatesBefore = OverlayTemplate::count();

    OverlayControl::factory()->create();

    expect(OverlayTemplate::count())->toBe($templatesBefore + 1);
});
