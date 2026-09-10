<?php

use App\Services\Tower\TowerPhysics;

/**
 * The fall rule, pinned. Every number in the Chat Tower demo that got the
 * product green-lit lives in TowerPhysics; these assert the shape of the
 * rule (aimed beats unaimed, sway grows with height, symmetric fall lines)
 * rather than the exact constants, so retuning is a constant change and not
 * a test rewrite - except where the constant IS the promise.
 */
test('the aim word reduces to left, right or nothing, never an error', function () {
    expect(TowerPhysics::normalizeAim('left'))->toBe('left')
        ->and(TowerPhysics::normalizeAim(' L '))->toBe('left')
        ->and(TowerPhysics::normalizeAim('Right'))->toBe('right')
        ->and(TowerPhysics::normalizeAim('r'))->toBe('right')
        ->and(TowerPhysics::normalizeAim('sideways'))->toBeNull()
        ->and(TowerPhysics::normalizeAim(''))->toBeNull()
        ->and(TowerPhysics::normalizeAim(null))->toBeNull();
});

test('an unaimed block lands within a quarter block of centre, either side', function () {
    expect(TowerPhysics::offsetFor(null, 0.0))->toBe(-1.0)
        ->and(TowerPhysics::offsetFor(null, 0.5))->toBe(0.0)
        ->and(TowerPhysics::offsetFor(null, 0.999999))->toBe(1.0);

    for ($i = 0; $i < 200; $i++) {
        expect(abs(TowerPhysics::offsetFor(null)))->toBeLessThanOrEqual(TowerPhysics::UNAIMED_MAX);
    }
});

test('an aimed block lands on its side and further out than an unaimed block ever goes', function () {
    expect(TowerPhysics::offsetFor('left', 0.0))->toBe(-TowerPhysics::AIMED_MIN)
        ->and(TowerPhysics::offsetFor('left', 0.999999))->toBe(-TowerPhysics::AIMED_MAX)
        ->and(TowerPhysics::offsetFor('right', 0.0))->toBe(TowerPhysics::AIMED_MIN)
        ->and(TowerPhysics::offsetFor('right', 0.999999))->toBe(TowerPhysics::AIMED_MAX)
        ->and(TowerPhysics::AIMED_MAX)->toBeGreaterThan(TowerPhysics::UNAIMED_MAX);

    for ($i = 0; $i < 200; $i++) {
        $left = TowerPhysics::offsetFor('left');
        $right = TowerPhysics::offsetFor('right');

        expect($left)->toBeLessThanOrEqual(-TowerPhysics::AIMED_MIN)
            ->and($left)->toBeGreaterThanOrEqual(-TowerPhysics::AIMED_MAX)
            ->and($right)->toBeGreaterThanOrEqual(TowerPhysics::AIMED_MIN)
            ->and($right)->toBeLessThanOrEqual(TowerPhysics::AIMED_MAX);
    }
});

test('sway grows with height, so the safe zone shrinks', function () {
    expect(TowerPhysics::swayAmplitude(0))->toBe(0.0)
        ->and(TowerPhysics::swayAmplitude(20))->toBe(1.1)
        ->and(TowerPhysics::swayAmplitude(40))->toBe(2.2)
        ->and(TowerPhysics::room(0.0, 1))->toBeGreaterThan(TowerPhysics::room(0.0, 30));
});

test('the tower falls when lean plus sway crosses the fall line, on either side', function () {
    // One block, lean 3.9: sway 0.055, top of sway 3.955, still inside.
    expect(TowerPhysics::room(3.9, 1))->toBe(0.045)
        ->and(TowerPhysics::topples(3.9, 1))->toBeFalse()
        // Lean 3.95: top of sway 4.005, past the line.
        ->and(TowerPhysics::topples(3.95, 1))->toBeTrue()
        ->and(TowerPhysics::topples(-3.95, 1))->toBeTrue()
        // A dead-straight tower falls from sway alone once it is tall enough.
        ->and(TowerPhysics::topples(0.0, 72))->toBeFalse()
        ->and(TowerPhysics::topples(0.0, 73))->toBeTrue();
});

test('the lean side is the word the bot uses', function () {
    expect(TowerPhysics::leanSide(0.2))->toBe('straight')
        ->and(TowerPhysics::leanSide(0.31))->toBe('right')
        ->and(TowerPhysics::leanSide(-0.31))->toBe('left');
});
