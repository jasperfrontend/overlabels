<?php

namespace App\Observers;

use App\Models\BotCommand;
use App\Models\User;

class UserObserver
{
    /**
     * Seed default bot commands the first time a user opts into the bot.
     * Fires on bot_enabled transitioning from false to true (including on create).
     * BotCommand::seedDefaults() is itself idempotent.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('bot_enabled') && $user->bot_enabled) {
            BotCommand::seedDefaults($user);
        }
    }

    public function created(User $user): void
    {
        if ($user->bot_enabled) {
            BotCommand::seedDefaults($user);
        }
    }
}
