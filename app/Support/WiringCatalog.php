<?php

namespace App\Support;

/**
 * The one and only declaration of what can be WRONG with a setup.
 *
 * Vocabulary: a WIRE is one connection that can be live or dangling; a CIRCUIT
 * is one working outcome made of wires. A missing wire is a loose end.
 *
 * Two rules earn their keep here, both learned by getting the first cut of
 * this file wrong:
 *
 * 1. A wire is a QUERY, never a record. Nothing is stored per user, so there
 *    is no table to seed, no backfill, no invalidation, and no way to claim
 *    someone still has something they deleted. Same shape as TAG_CATALOG
 *    feeding /tags.
 *
 * 2. A wire only describes something that can be BROKEN, never something you
 *    could have chosen to build. The first version made "chat can add to this
 *    list" a requirement; production had two lists with no append command, and
 *    both were correct - one is written by the recent-events feed, the other
 *    is a counter. Telling either owner to add a chat command would have been
 *    wrong advice about a working setup. Anything optional is context, or it
 *    is NOT_APPLICABLE, and neither ever counts against you.
 *
 * Wires carry copy for all three states because a satisfied wire and an
 * inapplicable one say different things, and "not applicable" must never read
 * as a soft failure.
 */
final class WiringCatalog
{
    public const SATISFIED = 'satisfied';

    /** Something exists that cannot do its job. The only thing worth a warning. */
    public const MISSING = 'missing';

    /** The question does not arise for this subject. Never a defect. */
    public const NOT_APPLICABLE = 'not_applicable';

    /**
     * @var array<string, array{label: string, satisfied: string, missing: string, not_applicable: string, route: string, cta: string}>
     */
    public const WIRES = [
        'alerts.token_valid' => [
            'label' => 'Your Twitch login is valid',
            'satisfied' => 'Alerts can be built with the Twitch login on this account.',
            'missing' => 'The Twitch login on this account has expired, so every alert fails before it reaches your overlay. Opening the dashboard renews it, or sends you through Twitch again if it cannot.',
            'not_applicable' => 'You have no alerts set up, so nothing needs the login.',
            'route' => 'dashboard.index',
            'cta' => 'Renew the login',
        ],
        'alerts.subscribed' => [
            'label' => 'Twitch is sending you events',
            'satisfied' => 'Every event your alerts listen for is subscribed and enabled.',
            'missing' => 'You have alerts set up, but Twitch is not sending you the events they listen for, so they never fire.',
            'not_applicable' => 'You have no alerts set up, so there is nothing to subscribe to.',
            'route' => 'settings.integrations.index',
            'cta' => 'Check your Twitch connection',
        ],
        'alerts.delivering' => [
            'label' => 'Alerts are getting through',
            'satisfied' => 'The most recent alert that should have reached your overlay was sent.',
            'missing' => 'The most recent alert that should have reached your overlay failed on the way. The events page has the details.',
            'not_applicable' => 'No alert has fired in the last 7 days, so there is nothing to check yet.',
            'route' => 'dashboard.events',
            'cta' => 'See recent events',
        ],
        'alerts.overlay_listening' => [
            'label' => 'An overlay is listening',
            'satisfied' => 'You are live and at least one overlay is connected to receive alerts.',
            'missing' => 'You are live, but no overlay is connected, so alerts are being sent to nobody. Add an overlay to OBS or reload the one you have.',
            'not_applicable' => 'Only checked while you are live.',
            'route' => 'templates.index',
            'cta' => 'Open your overlays',
        ],
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
     * A circuit is one working outcome. `subject` names what it is evaluated
     * against: 'account' for a single implicit subject, anything else for one
     * entry per instance. Lists are per-list because "do you have an append
     * command" answered at account level is satisfied by any appender on any
     * list, which hid a half-wired list on two of the three accounts using
     * lists in production.
     *
     * @var array<string, array{label: string, outcome: string, subject: string, wires: list<string>}>
     */
    public const CIRCUITS = [
        'alerts' => [
            'label' => 'Alerts',
            'outcome' => 'Every alert you set up reaches your overlay.',
            'subject' => 'account',
            'wires' => ['alerts.token_valid', 'alerts.subscribed', 'alerts.delivering', 'alerts.overlay_listening'],
        ],
        'bot' => [
            'label' => 'Chat commands',
            'outcome' => 'Everything you type a command for runs through the Overlabels bot.',
            'subject' => 'account',
            'wires' => ['bot.in_chat'],
        ],
        'lists' => [
            'label' => 'Lists',
            'outcome' => 'Each list is filled by something and shown somewhere.',
            'subject' => 'list',
            'wires' => ['lists.readable'],
        ],
    ];

    /**
     * @return array{label: string, satisfied: string, missing: string, not_applicable: string, route: string, cta: string}
     */
    public static function wire(string $key): array
    {
        return self::WIRES[$key];
    }

    /** @return list<string> */
    public static function wireKeys(): array
    {
        return array_keys(self::WIRES);
    }

    /** @return list<string> */
    public static function circuitKeys(): array
    {
        return array_keys(self::CIRCUITS);
    }
}
