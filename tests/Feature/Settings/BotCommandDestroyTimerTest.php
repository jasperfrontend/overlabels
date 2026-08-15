<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function timerUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function commandPayload(array $overrides = []): array
{
    return array_merge([
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'Hi [[[bot:from_user]]]!',
        'enabled' => true,
        'hidden' => false,
    ], $overrides);
}

test('creating with destroy_hours schedules a self-destruct from now', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/commands', commandPayload(['destroy_hours' => 12]))
        ->assertRedirect('/settings/bot/commands');

    $command = BotCommand::where('user_id', $user->id)->where('command', 'temp')->firstOrFail();

    expect($command->destroy_at)->not->toBeNull();
    expect($command->destroy_at->diffInHours(now()->addHours(12)))->toBeLessThanOrEqual(1);
});

test('creating without destroy_hours leaves no timer', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/commands', commandPayload(['command' => 'forever']))
        ->assertRedirect('/settings/bot/commands');

    $command = BotCommand::where('user_id', $user->id)->where('command', 'forever')->firstOrFail();

    expect($command->destroy_at)->toBeNull();
});

test('updating with destroy_hours 0 cancels a pending timer', function () {
    $user = timerUser();
    $command = BotCommand::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'hi',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->addHours(5),
    ]);

    $this->actingAs($user)
        ->patch("/settings/bot/commands/{$command->id}", commandPayload(['destroy_hours' => 0]))
        ->assertRedirect('/settings/bot/commands');

    expect($command->fresh()->destroy_at)->toBeNull();
});

test('destroy_hours over the one-year cap is rejected', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/commands', commandPayload(['destroy_hours' => 9000]))
        ->assertSessionHasErrors('destroy_hours');

    expect(BotCommand::where('user_id', $user->id)->count())->toBe(0);
});

test('a reply starting with a slash command is rejected', function () {
    $user = timerUser();

    $this->actingAs($user)
        ->post('/settings/bot/commands', commandPayload([
            'command' => 'vanish',
            'reply' => '/timeout [[[bot:from_user]]] 1',
        ]))
        ->assertSessionHasErrors('reply');

    expect(BotCommand::where('user_id', $user->id)->count())->toBe(0);
});

test('the edit page serializes destroy_at into the payload', function () {
    $user = timerUser();
    $command = BotCommand::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'hi',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->addHours(3),
    ]);

    $this->actingAs($user)
        ->get("/settings/bot/commands/{$command->id}/edit")
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('settings/bot/commands/Edit')
            ->where('command.destroy_at', fn ($v) => $v !== null)
        );
});
