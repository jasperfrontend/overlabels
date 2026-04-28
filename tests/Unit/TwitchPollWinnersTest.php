<?php

use App\Http\Controllers\TwitchEventSubController;

function callComputePollWinners(array $choices): array
{
    $controller = app(TwitchEventSubController::class);
    $method = new ReflectionMethod($controller, 'computePollWinners');
    $method->setAccessible(true);

    return $method->invoke($controller, $choices);
}

test('computePollWinners returns the single max-vote choice', function () {
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 21],
        ['id' => 'c2', 'title' => 'Blue', 'votes' => 18],
        ['id' => 'c3', 'title' => 'Green', 'votes' => 9],
    ];

    $winners = callComputePollWinners($choices);

    expect($winners)->toHaveCount(1);
    expect($winners[0]['id'])->toBe('c1');
});

test('computePollWinners returns every choice tied at the max', function () {
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 5],
        ['id' => 'c2', 'title' => 'Blue', 'votes' => 5],
        ['id' => 'c3', 'title' => 'Green', 'votes' => 3],
        ['id' => 'c4', 'title' => 'Yellow', 'votes' => 1],
    ];

    $winners = callComputePollWinners($choices);

    expect($winners)->toHaveCount(2);
    expect(array_column($winners, 'id'))->toBe(['c1', 'c2']);
});

test('computePollWinners returns all choices when every choice has 0 votes', function () {
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 0],
        ['id' => 'c2', 'title' => 'Blue', 'votes' => 0],
        ['id' => 'c3', 'title' => 'Green', 'votes' => 0],
    ];

    $winners = callComputePollWinners($choices);

    expect($winners)->toHaveCount(3);
});

test('computePollWinners returns empty array for empty choices', function () {
    expect(callComputePollWinners([]))->toBe([]);
});

test('computePollWinners ignores bits_votes and channel_points_votes', function () {
    // c1 has high bits/cp votes but the lowest `votes`; c2 is the true winner.
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 5, 'bits_votes' => 100, 'channel_points_votes' => 100],
        ['id' => 'c2', 'title' => 'Blue', 'votes' => 10, 'bits_votes' => 0, 'channel_points_votes' => 0],
    ];

    $winners = callComputePollWinners($choices);

    expect($winners)->toHaveCount(1);
    expect($winners[0]['id'])->toBe('c2');
});

test('computePollWinners returns the full choice object, not just id/title', function () {
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 10, 'bits_votes' => 6, 'channel_points_votes' => 9],
    ];

    $winners = callComputePollWinners($choices);

    expect($winners[0])->toHaveKeys(['id', 'title', 'votes', 'bits_votes', 'channel_points_votes']);
    expect($winners[0]['bits_votes'])->toBe(6);
    expect($winners[0]['channel_points_votes'])->toBe(9);
});

test('computePollWinners returns a JSON-array-shaped list (sequential 0-indexed keys)', function () {
    // If choice index 0 loses and choices 1+2 tie, the result must be re-keyed
    // from 0 - otherwise array_is_list() in the mapper fails and the indexed
    // flatten branch never fires.
    $choices = [
        ['id' => 'c1', 'title' => 'Red', 'votes' => 1],
        ['id' => 'c2', 'title' => 'Blue', 'votes' => 5],
        ['id' => 'c3', 'title' => 'Green', 'votes' => 5],
    ];

    $winners = callComputePollWinners($choices);

    expect(array_keys($winners))->toBe([0, 1]);
    expect(array_is_list($winners))->toBeTrue();
});
