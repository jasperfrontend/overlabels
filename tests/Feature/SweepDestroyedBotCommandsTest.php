<?php

use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function sweepUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

it('deletes commands whose destroy_at has elapsed', function () {
    $user = sweepUser();
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'expired',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'bye',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->subMinute(),
    ]);

    $this->artisan('bot:sweep-destroyed')->assertExitCode(0);

    expect(BotCommand::where('user_id', $user->id)->where('command', 'expired')->exists())->toBeFalse();
});

it('leaves commands whose timer has not elapsed or is unset', function () {
    $user = sweepUser();
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'future',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'stay',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->addHour(),
    ]);
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'permanent',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'stay',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => null,
    ]);

    $this->artisan('bot:sweep-destroyed')->assertExitCode(0);

    expect(BotCommand::where('user_id', $user->id)->where('command', 'future')->exists())->toBeTrue();
    expect(BotCommand::where('user_id', $user->id)->where('command', 'permanent')->exists())->toBeTrue();
});

it('deletes dependent aliases but leaves unrelated ones intact', function () {
    $user = sweepUser();
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'doomed',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'bye',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->subMinute(),
    ]);

    // Forwards to !doomed (case-insensitive, with args) - should be removed.
    BotAlias::create([
        'user_id' => $user->id,
        'command' => 'd',
        'target_template' => 'Doomed extra args',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    // Forwards elsewhere - should survive.
    BotAlias::create([
        'user_id' => $user->id,
        'command' => 'w',
        'target_template' => 'increment wins {1}',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    $this->artisan('bot:sweep-destroyed')->assertExitCode(0);

    expect(BotCommand::where('user_id', $user->id)->where('command', 'doomed')->exists())->toBeFalse();
    expect(BotAlias::where('user_id', $user->id)->where('command', 'd')->exists())->toBeFalse();
    expect(BotAlias::where('user_id', $user->id)->where('command', 'w')->exists())->toBeTrue();
});

it('does not touch another users dependent aliases', function () {
    $owner = sweepUser();
    $other = sweepUser();

    BotCommand::create([
        'user_id' => $owner->id,
        'command' => 'doomed',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'reply' => 'bye',
        'enabled' => true,
        'hidden' => false,
        'destroy_at' => now()->subMinute(),
    ]);

    // Same alias target name but a different user - must be left alone.
    BotAlias::create([
        'user_id' => $other->id,
        'command' => 'd',
        'target_template' => 'doomed',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden' => false,
    ]);

    $this->artisan('bot:sweep-destroyed')->assertExitCode(0);

    expect(BotAlias::where('user_id', $other->id)->where('command', 'd')->exists())->toBeTrue();
});
