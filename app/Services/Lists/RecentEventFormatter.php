<?php

namespace App\Services\Lists;

use App\Models\EventTemplateMapping;

/**
 * Turns one stored event (Twitch or external) into a single human-readable
 * line for a recent-events feed List - e.g. "Alice followed", "Bob cheered
 * 100 bits", "Dana Streamlabs tip 5 USD".
 *
 * This is the PHP port of the who/label/details logic in the dashboard's
 * EventsTable.vue, so a list-fed widget reads the same as the on-site recent
 * activity view. It is a pure formatter - no database, no broadcasting - so
 * it can be unit-tested in isolation and reused by both the live appender
 * (EventFeedAppender) and the on-enable seeder (EventFeedService::seed).
 *
 * "String now, structured later": today this emits a flat `value` string and
 * the List item's reserved label/weight/color stay null. When we want a
 * styled, per-type widget the formatter can grow a sibling method returning
 * {value,label,color} - the item schema already carries those fields, so no
 * migration is needed to light them up.
 */
class RecentEventFormatter
{
    /**
     * Twitch event_type -> verb phrase. Mirrors twitchEventLabels in
     * EventsTable.vue. Hype-train lines are computed dynamically below.
     */
    private const array TWITCH_LABELS = [
        'channel.follow' => 'followed',
        'channel.subscribe' => 'subscribed',
        'channel.subscription.message' => 'resubscribed',
        'channel.subscription.gift' => 'gifted subs',
        'channel.cheer' => 'cheered',
        'channel.raid' => 'raided',
        'channel.channel_points_custom_reward_redemption.add' => 'redeemed',
        'channel.channel_points_custom_reward_redemption.update' => 'redemption updated',
        'stream.online' => 'went live',
        'stream.offline' => 'ended the stream',
        'channel.poll.begin' => 'Poll started',
        'channel.poll.progress' => 'Poll updated',
        'channel.poll.end' => 'Poll ended',
        'channel.goal.begin' => 'Goal started',
        'channel.goal.progress' => 'Goal progressed',
        'channel.goal.end' => 'Goal ended',
    ];

    /**
     * service -> event_type -> label. Mirrors externalEventLabels in
     * EventsTable.vue.
     */
    private const array EXTERNAL_LABELS = [
        'kofi' => [
            'donation' => 'Ko-fi tip',
            'subscription' => 'Ko-fi subscription',
            'shop_order' => 'Ko-fi shop order',
            'commission' => 'Ko-fi commission',
        ],
        'streamlabs' => [
            'donation' => 'Streamlabs tip',
            'subscription' => 'Streamlabs subscription',
            'shop_order' => 'Streamlabs shop order',
            'commission' => 'Streamlabs commission',
        ],
        'bmac' => [
            'donation' => 'BMAC tip',
            'recurring' => 'BMAC subscription',
            'extra' => 'BMAC shop extra',
            'membership' => 'BMAC commission',
            'wishlist' => 'BMAC wishlist',
            'commission' => 'BMAC commission',
        ],
        'fourthwall' => [
            'donation' => 'Fourthwall tip',
            'subscription' => 'Fourthwall subscription',
            'shop_order' => 'Fourthwall shop order',
            'commission' => 'Fourthwall commission',
        ],
        'streamelements' => [
            'donation' => 'StreamElements tip',
        ],
    ];

    /**
     * Build the feed line. `$eventData` is the Twitch event payload (for
     * source 'twitch'); `$normalizedPayload` is the flattened template-tag
     * map for external services. Returns a trimmed non-empty string, falling
     * back to the event-type display name so a matched event never produces
     * a blank item.
     *
     * @param  array<string, mixed>|null  $eventData
     * @param  array<string, mixed>|null  $normalizedPayload
     */
    public function format(string $source, string $eventType, ?array $eventData, ?array $normalizedPayload): string
    {
        $parts = $source === 'twitch'
            ? [$this->twitchWho($eventType, $eventData ?? []), $this->twitchLabel($eventType, $eventData ?? []), $this->twitchDetails($eventType, $eventData ?? [])]
            : [$this->externalWho($normalizedPayload ?? []), $this->externalLabel($source, $eventType), $this->externalDetails($normalizedPayload ?? [])];

        $line = trim(implode(' ', array_filter($parts, static fn ($p) => is_string($p) && $p !== '')));

        if ($line !== '') {
            return $line;
        }

        // Nothing renderable (e.g. an event type with no who/label/details
        // mapping). Fall back to the same human label the recents list uses.
        return EventTemplateMapping::EVENT_TYPES[$eventType] ?? $eventType;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function twitchWho(string $eventType, array $d): ?string
    {
        if ($eventType === 'channel.raid') {
            return $this->str($d['from_broadcaster_user_name'] ?? null);
        }
        if ($eventType === 'stream.online' || $eventType === 'stream.offline') {
            return null;
        }

        return $this->str($d['user_name'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function twitchLabel(string $eventType, array $d): string
    {
        if (str_starts_with($eventType, 'channel.hype_train.')) {
            return $this->hypeTrainLabel($eventType, $d) ?: $eventType;
        }

        return self::TWITCH_LABELS[$eventType]
            ?? EventTemplateMapping::EVENT_TYPES[$eventType]
            ?? $eventType;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function twitchDetails(string $eventType, array $d): ?string
    {
        return match ($eventType) {
            'channel.subscribe', 'channel.subscription.message' => isset($d['tier'])
                ? 'Tier '.str_replace(['1000', '2000', '3000'], ['1', '2', '3'], (string) $d['tier'])
                : null,
            'channel.subscription.gift' => isset($d['total']) ? $d['total'].' gifts' : null,
            'channel.cheer' => isset($d['bits']) ? $d['bits'].' bits' : null,
            'channel.raid' => isset($d['viewers']) ? $d['viewers'].' viewers' : null,
            'channel.channel_points_custom_reward_redemption.add',
            'channel.channel_points_custom_reward_redemption.update' => $this->str(
                is_array($d['reward'] ?? null) ? ($d['reward']['title'] ?? null) : null
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function hypeTrainLabel(string $eventType, array $d): string
    {
        $level = $d['level'] ?? null;
        $progress = $d['progress'] ?? null;
        $goal = $d['goal'] ?? null;
        $total = $d['total'] ?? null;

        return match ($eventType) {
            'channel.hype_train.begin' => "Hype Train started at level {$level}: {$progress} of {$goal}",
            'channel.hype_train.progress' => "Hype Train progressed to level {$level}: {$progress} of {$goal}",
            'channel.hype_train.end' => "Hype Train ended at level {$level}: {$total} contributions",
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function externalWho(array $p): ?string
    {
        return $this->str($p['event.from_name'] ?? null);
    }

    private function externalLabel(string $source, string $eventType): string
    {
        return self::EXTERNAL_LABELS[$source][$eventType] ?? "{$source}: {$eventType}";
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function externalDetails(array $p): ?string
    {
        $amount = $this->str($p['event.amount'] ?? null);
        if ($amount !== null) {
            $currency = $this->str($p['event.currency'] ?? null);

            return $currency !== null ? "{$amount} {$currency}" : $amount;
        }

        return $this->str($p['event.tier_name'] ?? null);
    }

    /**
     * Coerce a payload value to a non-empty trimmed string, or null. Keeps
     * the formatter from emitting "0"-ish or whitespace-only fragments that
     * would clutter a line.
     */
    private function str(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
