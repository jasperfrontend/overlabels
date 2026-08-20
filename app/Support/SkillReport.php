<?php

namespace App\Support;

/**
 * Turns a facts bag into the thing /skills renders.
 *
 * Pure: takes an array of booleans, returns an array. No models, no database,
 * no user. That is what makes the interesting part - the ranking - testable
 * without touching Postgres.
 *
 * The ranking IS the feature. A checklist of everything you could possibly set
 * up is noise; the point is loose ends. So:
 *
 *   - none satisfied      => `not_started`. Not a loose end. It is a feature
 *                            the streamer has not chosen to use, and nagging
 *                            about it is how a useful page becomes wallpaper.
 *   - all satisfied       => `complete`. Quiet.
 *   - some but not all    => `loose_end`. Work was started and stopped one
 *                            step short, so something that looks configured
 *                            does nothing. This is the whole reason the page
 *                            exists, and it sorts to the top.
 *
 * Within loose ends, fewest-missing first: one step from working is more
 * urgent than three.
 */
final class SkillReport
{
    public const NOT_STARTED = 'not_started';

    public const LOOSE_END = 'loose_end';

    public const COMPLETE = 'complete';

    /**
     * @param  array<string, bool>  $facts
     * @return list<array<string, mixed>>
     */
    public static function build(array $facts): array
    {
        $sets = [];

        foreach (SkillCatalog::SKILLSETS as $key => $set) {
            $skills = [];
            $satisfiedCount = 0;

            foreach ($set['skills'] as $skillKey) {
                $satisfied = $facts[$skillKey] ?? false;
                $satisfiedCount += $satisfied ? 1 : 0;

                $skills[] = [
                    'key' => $skillKey,
                    'satisfied' => $satisfied,
                ] + SkillCatalog::skill($skillKey);
            }

            $total = count($set['skills']);
            $missing = $total - $satisfiedCount;

            $sets[] = [
                'key' => $key,
                'label' => $set['label'],
                'outcome' => $set['outcome'],
                'skills' => $skills,
                'satisfied' => $satisfiedCount,
                'total' => $total,
                'missing' => $missing,
                'status' => self::status($satisfiedCount, $total),
            ];
        }

        usort($sets, function (array $a, array $b) {
            $rank = [self::LOOSE_END => 0, self::NOT_STARTED => 1, self::COMPLETE => 2];

            return [$rank[$a['status']], $a['missing'], $a['key']]
                <=> [$rank[$b['status']], $b['missing'], $b['key']];
        });

        return $sets;
    }

    public static function status(int $satisfied, int $total): string
    {
        if ($satisfied === 0) {
            return self::NOT_STARTED;
        }

        return $satisfied === $total ? self::COMPLETE : self::LOOSE_END;
    }
}
