<?php

namespace App\Support;

/**
 * The one and only declaration of what a working feature requires.
 *
 * A skill is a QUERY, never a record. Nothing here is stored per user, so
 * there is no table to seed, no backfill, no invalidation, and no way for the
 * page to claim someone has something they deleted five minutes ago. The
 * answer is recomputed from the account's own state on every request, which
 * is the same shape as TAG_CATALOG feeding /tags.
 *
 * Because of that, this class holds no logic at all. Every skill key is also
 * the key of a boolean that SkillFacts produces, so evaluating a skill is a
 * lookup rather than a branch. SkillCatalogTest pins the two together, so a
 * skill declared here without a matching fact fails the suite instead of
 * silently reading as unsatisfied forever.
 */
final class SkillCatalog
{
    /**
     * One entry per prerequisite.
     *
     * `summary` says what the thing does, in the user's terms. `missing` is
     * what to say when they do not have it yet, written as the consequence
     * rather than the omission - "chat cannot add to it" beats "no appender".
     *
     * @var array<string, array{label: string, summary: string, missing: string, route: string, cta: string}>
     */
    public const SKILLS = [
        'lists.has_list' => [
            'label' => 'A list exists',
            'summary' => 'Somewhere for entries to go.',
            'missing' => 'There is nothing for chat to write to or read back yet.',
            'route' => 'lists.index',
            'cta' => 'Make a list',
        ],
        'lists.has_appender' => [
            'label' => 'Chat can add to it',
            'summary' => 'An append command, so viewers can put entries in the list themselves.',
            'missing' => 'Your list can only be edited from the dashboard - chat cannot add to it.',
            'route' => 'lists.index',
            'cta' => 'Add an append command',
        ],
        'lists.has_reader' => [
            'label' => 'Chat can read it back',
            'summary' => 'The list command, so the list can be shown, drawn from or cleared in chat.',
            'missing' => 'Entries go in, but nobody in chat can see what is in the list.',
            'route' => 'lists.index',
            'cta' => 'Set up the list command',
        ],
        'bot.in_chat' => [
            'label' => 'The bot is in your chat',
            'summary' => 'Every chat command runs through the Overlabels bot.',
            'missing' => 'Your commands are configured but nothing is listening for them in chat.',
            'route' => 'settings.bot.commands.index',
            'cta' => 'Add the bot',
        ],
    ];

    /**
     * A skillset is the bundle that produces one working outcome.
     *
     * Skills are referenced by key rather than nested, so a prerequisite
     * shared by several outcomes - the bot being in chat is the obvious one -
     * stays a single declaration.
     *
     * @var array<string, array{label: string, outcome: string, skills: list<string>}>
     */
    public const SKILLSETS = [
        'lists' => [
            'label' => 'Lists in chat',
            'outcome' => 'Viewers can add themselves to a list and see it, without you touching the dashboard.',
            'skills' => [
                'lists.has_list',
                'lists.has_appender',
                'lists.has_reader',
                'bot.in_chat',
            ],
        ],
    ];

    /**
     * @return array{label: string, summary: string, missing: string, route: string, cta: string}
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
