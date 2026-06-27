<?php

use App\Models\BotExpression;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function timerUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function expressionPayload(array $overrides = []): array
{
    return array_merge([
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'Hi [[[bot:from_user]]]!',
        'enabled' => true,
        'hidden_from_commands' => false,
    ], $overrides);
}

test('creating with destroy_hours schedules a self-destruct from now', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/expressions', expressionPayload(['destroy_hours' => 12]))
        ->assertRedirect('/settings/bot/expressions');

    $expr = BotExpression::where('user_id', $user->id)->where('command', 'temp')->firstOrFail();

    expect($expr->destroy_at)->not->toBeNull();
    expect($expr->destroy_at->diffInHours(now()->addHours(12)))->toBeLessThanOrEqual(1);
});

test('creating without destroy_hours leaves no timer', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/expressions', expressionPayload(['command' => 'forever']))
        ->assertRedirect('/settings/bot/expressions');

    $expr = BotExpression::where('user_id', $user->id)->where('command', 'forever')->firstOrFail();

    expect($expr->destroy_at)->toBeNull();
});

test('updating with destroy_hours 0 cancels a pending timer', function () {
    $user = timerUser();
    $expr = BotExpression::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'hi',
        'enabled' => true,
        'hidden_from_commands' => false,
        'destroy_at' => now()->addHours(5),
    ]);

    $this->actingAs($user)
        ->patch("/settings/bot/expressions/{$expr->id}", expressionPayload(['destroy_hours' => 0]))
        ->assertRedirect('/settings/bot/expressions');

    expect($expr->fresh()->destroy_at)->toBeNull();
});

test('destroy_hours over the one-year cap is rejected', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/expressions', expressionPayload(['destroy_hours' => 9000]))
        ->assertSessionHasErrors('destroy_hours');

    expect(BotExpression::where('user_id', $user->id)->count())->toBe(0);
});

test('an expression starting with a slash command is rejected', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/expressions', expressionPayload([
            'command' => 'vanish',
            'expression' => '/timeout [[[bot:from_user]]] 1',
        ]))
        ->assertSessionHasErrors('expression');

    expect(BotExpression::where('user_id', $user->id)->count())->toBe(0);
});

test('the edit page serializes destroy_at into the payload', function () {
    $user = timerUser();
    $expr = BotExpression::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'hi',
        'enabled' => true,
        'hidden_from_commands' => false,
        'destroy_at' => now()->addHours(3),
    ]);

    $this->actingAs($user)
        ->get("/settings/bot/expressions/{$expr->id}/edit")
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('settings/bot/expressions/Edit')
            ->where('expression.destroy_at', fn ($v) => $v !== null)
        );
});
