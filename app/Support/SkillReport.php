<?php

namespace App\Support;

/**
 * Turns subjects into the thing /skills renders.
 *
 * Pure: arrays in, arrays out. No models, no database, no user. That is what
 * makes the interesting part - the ranking - testable without Postgres.
 *
 * The ranking IS the feature. A checklist of everything you could set up is a
 * score; the point is loose ends, meaning things that EXIST and cannot work.
 * So a skill in NOT_APPLICABLE never counts, in either direction: it is not
 * progress and it is not a gap. Only MISSING draws attention, and MISSING is
 * only ever produced for something already built.
 */
final class SkillReport
{
    /** At least one subject has something built that cannot work. */
    public const LOOSE_END = 'loose_end';

    /** Subjects exist and nothing about them is broken. */
    public const COMPLETE = 'complete';

    /** Nothing to evaluate. Not a defect, and deliberately silent. */
    public const NOT_STARTED = 'not_started';

    /**
     * @param  array<string, list<array<string, mixed>>>  $factsBySkillset
     * @return list<array<string, mixed>>
     */
    public static function build(array $factsBySkillset): array
    {
        $sets = [];

        foreach (SkillCatalog::SKILLSETS as $key => $definition) {
            $subjects = [];
            $attention = 0;
            $applicableCount = 0;

            foreach ($factsBySkillset[$key] ?? [] as $subject) {
                $skills = [];
                $subjectMissing = 0;

                foreach ($definition['skills'] as $skillKey) {
                    $state = $subject['states'][$skillKey] ?? SkillCatalog::NOT_APPLICABLE;
                    $subjectMissing += $state === SkillCatalog::MISSING ? 1 : 0;

                    $copy = SkillCatalog::skill($skillKey);

                    $skills[] = [
                        'key' => $skillKey,
                        'state' => $state,
                        'label' => $copy['label'],
                        'message' => $copy[$state] ?? '',
                        'route' => $copy['route'],
                        'cta' => $copy['cta'],
                    ];
                }

                $attention += $subjectMissing > 0 ? 1 : 0;

                // A subject whose every skill is NOT_APPLICABLE has nothing to
                // say yet. It must not render as a tick: a green mark for
                // something the streamer never built reads as an award for
                // inaction, which is the achievement register this page avoids.
                $applicable = collect($skills)->contains(
                    fn (array $skill) => $skill['state'] !== SkillCatalog::NOT_APPLICABLE
                );

                $applicableCount += $applicable ? 1 : 0;

                $subjects[] = [
                    'key' => $subject['key'],
                    'label' => $subject['label'],
                    'context' => $subject['context'] ?? [],
                    'skills' => $skills,
                    'missing' => $subjectMissing,
                    'applicable' => $applicable,
                    'needsAttention' => $subjectMissing > 0,
                ];
            }

            // Broken subjects first so the page leads with them, then stable
            // by label so an untouched account does not reshuffle per request.
            usort($subjects, fn (array $a, array $b) => [$a['needsAttention'] ? 0 : 1, $a['label']]
                <=> [$b['needsAttention'] ? 0 : 1, $b['label']]);

            $sets[] = [
                'key' => $key,
                'label' => $definition['label'],
                'outcome' => $definition['outcome'],
                'subject' => $definition['subject'],
                'subjects' => $subjects,
                'attention' => $attention,
                'total' => count($subjects),
                'status' => self::status($applicableCount, $attention),
            ];
        }

        usort($sets, function (array $a, array $b) {
            $rank = [self::LOOSE_END => 0, self::COMPLETE => 1, self::NOT_STARTED => 2];

            return [$rank[$a['status']], -$a['attention'], $a['key']]
                <=> [$rank[$b['status']], -$b['attention'], $b['key']];
        });

        return $sets;
    }

    /**
     * Counted in APPLICABLE subjects. An account with no chat commands has one
     * subject and nothing to evaluate on it, which is not_started rather than
     * complete - otherwise doing nothing reports as a finished outcome.
     */
    public static function status(int $applicableSubjects, int $attention): string
    {
        if ($applicableSubjects === 0) {
            return self::NOT_STARTED;
        }

        return $attention > 0 ? self::LOOSE_END : self::COMPLETE;
    }
}
