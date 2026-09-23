<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Service\Workout\WeeklyStreakCalculator;

/**
 * Retrouve, dans l'historique, la séance qui a franchi un palier et à quelle date — pour les
 * badges obtenus sans séance déclenchante (rattrapage d'un compte existant, synchro au chargement
 * d'une page). Pure fonction sur une chronologie déjà chargée.
 */
final readonly class BadgeCrossingLocator
{
    private const float KG_PER_TONNE = 1000.0;

    private const int DAYS_PER_WEEK = 7;

    public function __construct(
        private WeeklyStreakCalculator $weeklyStreakCalculator,
    ) {
    }

    /**
     * @param list<WorkoutTimelineEntry> $timeline      séances triées de la plus ancienne à la plus récente
     * @param array<string, true>        $deloadWeekSet clé = lundi `Y-m-d`
     */
    public function locate(BadgeKey $key, array $timeline, array $deloadWeekSet, \DateTimeImmutable $registeredAt, \DateTimeImmutable $now): ?BadgeCrossing
    {
        return match ($key->family) {
            BadgeFamilyEnum::ASSIDUITY => $this->atWorkout($timeline[$key->family->thresholdOf($key->tier) - 1] ?? null),
            BadgeFamilyEnum::TONNAGE => $this->atWorkout($this->tonnageCrossing($timeline, $key->family->thresholdOf($key->tier))),
            BadgeFamilyEnum::REGULARITY => $this->atWorkout($this->regularityCrossing($timeline, $deloadWeekSet, $key->family->thresholdOf($key->tier), $now)),
            BadgeFamilyEnum::SENIORITY => $this->seniorityCrossing($registeredAt, $key->family->thresholdOf($key->tier), $now),
            BadgeFamilyEnum::LEGEND => $this->legendCrossing($timeline, $deloadWeekSet, $registeredAt, $now),
        };
    }

    private function atWorkout(?WorkoutTimelineEntry $entry): ?BadgeCrossing
    {
        return null !== $entry ? new BadgeCrossing($entry->performedAt, $entry->workoutId) : null;
    }

    /**
     * @param list<WorkoutTimelineEntry> $timeline
     */
    private function tonnageCrossing(array $timeline, int $thresholdTonnes): ?WorkoutTimelineEntry
    {
        $cumulativeKg = 0.0;

        foreach ($timeline as $entry) {
            $cumulativeKg += $entry->tonnageKg;

            if ($cumulativeKg >= $thresholdTonnes * self::KG_PER_TONNE) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<WorkoutTimelineEntry> $timeline
     * @param array<string, true>        $deloadWeekSet
     */
    private function regularityCrossing(array $timeline, array $deloadWeekSet, int $weeks, \DateTimeImmutable $now): ?WorkoutTimelineEntry
    {
        $dates = array_map(static fn (WorkoutTimelineEntry $entry): \DateTimeImmutable => $entry->performedAt, $timeline);
        $monday = $this->weeklyStreakCalculator->weekReaching($dates, $deloadWeekSet, $weeks, $now);

        if (null === $monday) {
            return null;
        }

        $nextMonday = $monday->modify(\sprintf('+%d days', self::DAYS_PER_WEEK));

        foreach ($timeline as $entry) {
            if ($entry->performedAt >= $monday && $entry->performedAt < $nextMonday) {
                return $entry;
            }
        }

        return null;
    }

    private function seniorityCrossing(\DateTimeImmutable $registeredAt, int $months, \DateTimeImmutable $now): ?BadgeCrossing
    {
        $anniversary = $registeredAt->modify(\sprintf('+%d months', $months));

        return $anniversary <= $now ? new BadgeCrossing($anniversary, null) : null;
    }

    /**
     * La Légende tombe au moment où le dernier des quatre rubis est décroché.
     *
     * @param list<WorkoutTimelineEntry> $timeline
     * @param array<string, true>        $deloadWeekSet
     */
    private function legendCrossing(array $timeline, array $deloadWeekSet, \DateTimeImmutable $registeredAt, \DateTimeImmutable $now): ?BadgeCrossing
    {
        $latest = null;

        foreach (BadgeFamilyEnum::milestoneFamilies() as $family) {
            $ruby = $this->locate(new BadgeKey($family, BadgeTierEnum::RUBY), $timeline, $deloadWeekSet, $registeredAt, $now);

            if (null === $ruby) {
                return null;
            }

            $latest = null === $latest || $ruby->date > $latest->date ? $ruby : $latest;
        }

        return $latest;
    }
}
