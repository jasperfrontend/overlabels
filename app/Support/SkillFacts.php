<?php

namespace App\Support;

use App\Models\ListAppender;
use App\Models\ListMetaCommand;
use App\Models\OptionSet;
use App\Models\User;

/**
 * The single query pass behind /skills.
 *
 * Returns one boolean per skill key in SkillCatalog, so evaluating a skillset
 * is a lookup rather than a branch. Every check is an `exists()`, so this is
 * a handful of cheap index hits rather than hydrated rows - the page only ever
 * needs to know whether a thing is there, never what it contains.
 *
 * Deliberately NOT cached. The whole reason a skill is a query is that it
 * cannot go stale; putting a TTL in front of it would reintroduce exactly the
 * drift that storing completion rows would have.
 */
final class SkillFacts
{
    /**
     * @return array<string, bool>
     */
    public static function for(User $user): array
    {
        // An empty list is a legitimate setup - a raffle list starts empty and
        // fills from chat - so the check is that a list exists, never that it
        // has items in it.
        $hasList = OptionSet::where('user_id', $user->id)->exists();

        // Scoped through target_list_id as well as user_id: an appender whose
        // list has been deleted is not a working append path, and saying it is
        // would be the exact false-positive this page exists to prevent.
        $hasAppender = ListAppender::where('user_id', $user->id)
            ->where('enabled', true)
            ->whereIn('target_list_id', OptionSet::where('user_id', $user->id)->select('id'))
            ->exists();

        // One per user, and the command name is configurable, so its presence
        // is the signal rather than any particular command string.
        $hasReader = ListMetaCommand::where('user_id', $user->id)
            ->where('enabled', true)
            ->exists();

        return [
            'lists.has_list' => $hasList,
            'lists.has_appender' => $hasAppender,
            'lists.has_reader' => $hasReader,
            'bot.in_chat' => (bool) $user->bot_enabled,
        ];
    }
}
