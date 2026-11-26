<?php

namespace App\Services\OverloadReports;

/**
 * What an Overload Report counts and how it words it (#345). Pure functions,
 * no state: BusyStretch feeds events through apply(), the page, the Markdown
 * export and the chat line read the result through title(), line() and
 * summary().
 *
 * Numbers and timestamps only. Nothing here ever keeps a viewer's name, id or
 * message: a dot is a kind, two timestamps and counts.
 *
 * One dot is a RUN of one kind (Jasper, 2026-11-26): follows in a row merge
 * until another kind cuts in. A raid, a gift bomb and a hype train are always
 * their own dot, being one moment each.
 */
class ReportKinds
{
    /**
     * Every kind a streamer can tick, in settings order. `default` is the
     * decided default (#345): channel points and charity off, the rest on.
     */
    public const array KINDS = [
        'follows' => ['label' => 'Follows', 'title' => 'Follows!', 'default' => true, 'merges' => true],
        'subs' => ['label' => 'Subs and resubs', 'title' => 'Subs!', 'default' => true, 'merges' => true],
        'gift_subs' => ['label' => 'Gift subs', 'title' => 'Gift subs!', 'default' => true, 'merges' => false],
        'bits' => ['label' => 'Bits', 'title' => 'Bits!', 'default' => true, 'merges' => true],
        'raids' => ['label' => 'Raids', 'title' => 'Raid!', 'default' => true, 'merges' => false],
        'hype_trains' => ['label' => 'Hype trains', 'title' => 'Hype train!', 'default' => true, 'merges' => false],
        'channel_points' => ['label' => 'Channel point redemptions', 'title' => 'Channel points!', 'default' => false, 'merges' => true],
        'charity' => ['label' => 'Charity donations', 'title' => 'Charity!', 'default' => false, 'merges' => true],
    ];

    /** A stretch keeps at most this many dots; past it, totals still count. */
    public const int MAX_DOTS = 200;

    /** The kind an event belongs to, or null when a report never counts it. */
    public static function kindOf(string $eventType, array $event): ?string
    {
        return match ($eventType) {
            'channel.follow' => 'follows',
            // A gifted sub is counted once, by its gift's total, never again
            // per recipient.
            'channel.subscribe' => ($event['is_gift'] ?? false) === true ? null : 'subs',
            'channel.subscription.message' => 'subs',
            'channel.subscription.gift' => 'gift_subs',
            'channel.cheer' => 'bits',
            'channel.raid' => 'raids',
            'channel.hype_train.begin', 'channel.hype_train.progress', 'channel.hype_train.end' => 'hype_trains',
            'channel.channel_points_custom_reward_redemption.add' => 'channel_points',
            'channel.charity_campaign.donate' => 'charity',
            default => null,
        };
    }

    /**
     * How many events this is toward the threshold. A gift bomb is its
     * total; a hype train is one, at its start - the subs and cheers inside it
     * count as themselves, so its updates count as nothing.
     */
    public static function weight(string $eventType, array $event, int $times = 1): int
    {
        return match ($eventType) {
            'channel.subscription.gift' => max(1, (int) ($event['total'] ?? 1)),
            'channel.hype_train.progress', 'channel.hype_train.end' => 0,
            default => $times,
        };
    }

    /** Whether this event may open a stretch (a train's update never does). */
    public static function opens(string $eventType): bool
    {
        return ! in_array($eventType, ['channel.hype_train.progress', 'channel.hype_train.end'], true);
    }

    /**
     * The stretch's state with one more event in it. $times is how many
     * identical events this is (a flood tick's count). Returns [timeline, totals].
     *
     * @param  list<array<string, mixed>>  $timeline
     * @param  array<string, int>  $totals
     * @return array{0: list<array<string, mixed>>, 1: array<string, int>}
     */
    public static function apply(array $timeline, array $totals, string $kind, string $eventType, array $event, int $times, string $at): array
    {
        if ($kind === 'hype_trains' && ! self::opens($eventType)) {
            for ($i = count($timeline) - 1; $i >= 0; $i--) {
                if ($timeline[$i]['kind'] === 'hype_trains' && empty($timeline[$i]['ended'])) {
                    $timeline[$i]['level'] = max((int) $timeline[$i]['level'], (int) ($event['level'] ?? 1));
                    $timeline[$i]['last'] = $at;
                    if ($eventType === 'channel.hype_train.end') {
                        $timeline[$i]['ended'] = true;
                    }
                    break;
                }
            }

            return [$timeline, $totals];
        }

        $weight = self::weight($eventType, $event, $times);
        $totals[$kind] = ($totals[$kind] ?? 0) + $weight;
        $bits = $kind === 'bits' ? max(0, (int) ($event['bits'] ?? 0)) * $times : 0;
        if ($kind === 'bits') {
            $totals['bits_total'] = ($totals['bits_total'] ?? 0) + $bits;
        }

        $lastIndex = count($timeline) - 1;
        if (self::KINDS[$kind]['merges'] && $lastIndex >= 0 && $timeline[$lastIndex]['kind'] === $kind) {
            $timeline[$lastIndex]['count'] += $weight;
            $timeline[$lastIndex]['last'] = $at;
            if ($kind === 'bits') {
                $timeline[$lastIndex]['bits'] += $bits;
            }

            return [$timeline, $totals];
        }

        if (count($timeline) >= self::MAX_DOTS) {
            return [$timeline, $totals];
        }

        $timeline[] = array_filter([
            'kind' => $kind,
            'first' => $at,
            'last' => $at,
            'count' => $weight,
            'bits' => $kind === 'bits' ? $bits : null,
            'tier' => $kind === 'gift_subs' ? self::tier($event['tier'] ?? null) : null,
            'viewers' => $kind === 'raids' ? max(0, (int) ($event['viewers'] ?? 0)) : null,
            'level' => $kind === 'hype_trains' ? max(1, (int) ($event['level'] ?? 1)) : null,
        ], fn ($value) => $value !== null);

        return [$timeline, $totals];
    }

    public static function title(array $dot): string
    {
        return self::KINDS[$dot['kind']]['title'] ?? 'Events!';
    }

    /** The one line under a dot's title. */
    public static function line(array $dot): string
    {
        $n = (int) ($dot['count'] ?? 0);
        $num = number_format($n);

        return match ($dot['kind']) {
            'follows' => $n === 1 ? '1 new follower.' : "{$num} new followers.",
            'subs' => $n === 1 ? '1 new sub.' : "{$num} new subs.",
            'gift_subs' => "{$num}x Tier ".($dot['tier'] ?? 1).' gift sub received.',
            'bits' => number_format((int) ($dot['bits'] ?? 0)).' bits in '.($n === 1 ? '1 cheer.' : "{$num} cheers."),
            'raids' => ((int) ($dot['viewers'] ?? 0) === 1 ? '1 viewer.' : number_format((int) ($dot['viewers'] ?? 0)).' viewers.'),
            'hype_trains' => 'Level '.((int) ($dot['level'] ?? 1)).' reached.',
            'channel_points' => $n === 1 ? '1 redemption.' : "{$num} redemptions.",
            'charity' => $n === 1 ? '1 donation to charity.' : "{$num} donations to charity.",
            default => "{$num} events.",
        };
    }

    /**
     * "34 new subs and 412 follows in 3 minutes": the two biggest kinds and
     * the length. The chat line and the dashboard use it.
     *
     * @param  array<string, int>  $totals
     */
    public static function summary(array $totals, int $seconds): string
    {
        $counts = array_filter(
            array_intersect_key($totals, self::KINDS),
            fn ($count) => $count > 0,
        );
        arsort($counts);

        $parts = [];
        foreach (array_slice(array_keys($counts), 0, 2) as $kind) {
            $parts[] = self::phrase($kind, $kind === 'bits' ? (int) ($totals['bits_total'] ?? 0) : (int) $counts[$kind]);
        }

        $minutes = max(1, (int) round($seconds / 60));

        return implode(' and ', $parts).' in '.($minutes === 1 ? '1 minute' : "{$minutes} minutes");
    }

    private static function phrase(string $kind, int $n): string
    {
        $num = number_format($n);

        return match ($kind) {
            'follows' => $n === 1 ? '1 follow' : "{$num} follows",
            'subs' => $n === 1 ? '1 new sub' : "{$num} new subs",
            'gift_subs' => $n === 1 ? '1 gift sub' : "{$num} gift subs",
            'bits' => "{$num} bits",
            'raids' => $n === 1 ? 'a raid' : "{$num} raids",
            'hype_trains' => $n === 1 ? 'a hype train' : "{$num} hype trains",
            'channel_points' => $n === 1 ? '1 redemption' : "{$num} redemptions",
            'charity' => $n === 1 ? '1 charity donation' : "{$num} charity donations",
            default => "{$num} events",
        };
    }

    private static function tier(mixed $tier): int
    {
        return match ((string) $tier) {
            '2000' => 2,
            '3000' => 3,
            default => 1,
        };
    }
}
