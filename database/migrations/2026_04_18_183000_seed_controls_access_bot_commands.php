<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed !enablecontrols and !disablecontrols (broadcaster-only) for every
 * opted-in streamer so they can toggle chat control access without a web
 * visit. Default for the flag itself is "disabled" - see the User model
 * accessors; this just makes the commands exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    foreach (['enablecontrols', 'disablecontrols'] as $command) {
                        BotCommand::firstOrCreate(
                            ['user_id' => $user->id, 'command' => $command],
                            ['permission_level' => 'broadcaster', 'enabled' => true],
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        BotCommand::whereIn('command', ['enablecontrols', 'disablecontrols'])->delete();
    }
};
