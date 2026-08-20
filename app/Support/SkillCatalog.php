<?php

namespace App\Support;

/**
 * The one and only declaration of what can be WRONG with a setup.
 *
 * Two rules earn their keep here, both learned by getting the first cut of
 * this file wrong:
 *
 * 1. A skill is a QUERY, never a record. Nothing is stored per user, so there
 *    is no table to seed, no backfill, no invalidation, and no way to claim
 *    someone still has something they deleted. Same shape as TAG_CATALOG
 *    feeding /tags.
 *
 * 2. A skill only describes something that can be BROKEN, never something you
 *    could have chosen to build. The first version made "chat can add to this
 *    list" a requirement; production had two lists with no append command, and
 *    both were correct - one is written by the recent-events feed, the other
 *    is a counter. Telling either owner to add a chat command would have been
 *    wrong advice about a working setup. Anything optional is context, or it
 *    is NOT_APPLICABLE, and neither ever counts against you.
 *
 * Skills carry copy for all three states because a satisfied skill and an
 * inapplicable one say different things, and "not applicable" must never read
 * as a soft failure.
 */
final class SkillCatalog
{
    public const SATISFIED = 'satisfied';

    /** Something exists that cannot do its job. The only thing worth a warning. */
    public const MISSING = 'missing';

    /** The question does not arise for this subject. Never a defect. */
    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * @var array<string, array{label: string, satisfied: string, missing: string, not_applicable: string, route: string, cta: string}>
     */
    public const SKILLS = [
        'lists.readable' => [
            'label' => 'Something reads it back',
            'satisfied' => 'Chat or an overlay can show what is in this list.',
            'missing' => 'Nothing shows this list anywhere. Entries go in and are never seen.',
            'not_applicable' => '',
            'route' => 'lists.index',
            'cta' => 'Give it a reader',
        ],
        'bot.in_chat' => [
            'label' => 'The bot is in your chat',
            'satisfied' => 'Your chat commands have something listening for them.',
            'missing' => 'You have chat commands set up, but the bot is not in your channel, so none of them run.',
            'not_applicable' => 'You have no chat commands yet, so there is nothing for the bot to answer.',
            'route' => 'settings.bot.commands.index',
            'cta' => 'Add the bot',
        ],
    ];

    /**
     * A skillset is one working outcome. `subject` names what it is evaluated
     * against: 'account' for a single implicit subject, anything else for one
     * entry per instance. Lists are per-list because "do you have an append
     * command" answered at account level is satisfied by any appender on any
     * list, which hid a half-wired list on two of the three accounts using
     * lists in production.
     *
     * @var array<string, array{label: string, outcome: string, subject: string, skills: list<string>}>
     */
    public const SKILLSETS = [
        'bot' => [
            'label' => 'Chat commands',
            'outcome' => 'Everything you type a command for runs through the Overlabels bot.',
            'subject' => 'account',
            'skills' => ['bot.in_chat'],
        ],
        'lists' => [
            'label' => 'Lists',
            'outcome' => 'Each list is filled by something and shown somewhere.',
            'subject' => 'list',
            'skills' => ['lists.readable'],
        ],
    ];

    /**
     * @return array{label: string, satisfied: string, missing: string, not_applicable: string, route: string, cta: string}
     */
    public static function skill(string $key): array
    {
        return self::SKILLS[$key];
    }

    /** @return list<string> */
    public static function skillKeys(): array
    {
        return array_keys(self::SKILLS);
    }

    /** @return list<string> */
    public static function skillsetKeys(): array
    {
        return array_keys(self::SKILLSETS);
    }
}
