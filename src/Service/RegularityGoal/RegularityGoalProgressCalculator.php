<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;

/**
 * Découpe un objectif de régularité en périodes (jours ou semaines) et juge chacune : quota atteint,
 * manqué, en cours ou à venir. Deux séances le même jour comptent pour deux ; chaque période compte,
 * aucune n'est neutralisée (un objectif ne chevauche jamais un deload, cf. `NoDeloadOverlap`). Pur
 * calcul en mémoire, sans requête : les séances sont fournies.
 */
final readonly class RegularityGoalProgressCalculator
{
    /**
     * @param array<\DateTimeImmutable> $workoutDates
     */
    public function calculate(RegularityGoal $goal, array $workoutDates, \DateTimeImmutable $today): RegularityGoalProgress
    {
        $today = $today->setTime(0, 0, 0);
        $periods = array_map(
            fn (array $bounds): RegularityGoalPeriodProgress => $this->judgePeriod($goal, $bounds, $workoutDates, $today),
            $this->periodBounds($goal),
        );

        return $this->summarize($goal, $periods, $today);
    }

    /**
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable}> premier et dernier jour de chaque période
     */
    private function periodBounds(RegularityGoal $goal): array
    {
        $daysPerPeriod = RegularityGoalPeriodEnum::DAY === $goal->period ? 1 : 7;
        $bounds = [];

        for ($start = $goal->firstDay(); $start <= $goal->lastDay(); $start = $start->modify(\sprintf('+%d days', $daysPerPeriod))) {
            $bounds[] = [$start, $start->modify(\sprintf('+%d days', $daysPerPeriod - 1))];
        }

        return $bounds;
    }

    /**
     * @param array{\DateTimeImmutable, \DateTimeImmutable} $bounds
     * @param array<\DateTimeImmutable>                      $workoutDates
     */
    private function judgePeriod(RegularityGoal $goal, array $bounds, array $workoutDates, \DateTimeImmutable $today): RegularityGoalPeriodProgress
    {
        [$start, $end] = $bounds;
        $sessionCount = \count(array_filter(
            $workoutDates,
            static fn (\DateTimeImmutable $date): bool => $date >= $start && $date < $end->modify('+1 day'),
        ));

        return new RegularityGoalPeriodProgress($start, $end, $sessionCount, $this->statusOf($goal, $bounds, $sessionCount, $today));
    }

    /**
     * @param array{\DateTimeImmutable, \DateTimeImmutable} $bounds
     */
    private function statusOf(RegularityGoal $goal, array $bounds, int $sessionCount, \DateTimeImmutable $today): RegularityGoalPeriodStatusEnum
    {
        [$start, $end] = $bounds;

        return match (true) {
            $sessionCount >= $goal->sessionsPerPeriod => RegularityGoalPeriodStatusEnum::MET,
            $end < $today => RegularityGoalPeriodStatusEnum::MISSED,
            $start > $today => RegularityGoalPeriodStatusEnum::UPCOMING,
            default => RegularityGoalPeriodStatusEnum::IN_PROGRESS,
        };
    }

    /**
     * @param list<RegularityGoalPeriodProgress> $periods
     */
    private function summarize(RegularityGoal $goal, array $periods, \DateTimeImmutable $today): RegularityGoalProgress
    {
        $metCount = \count(array_filter(
            $periods,
            static fn (RegularityGoalPeriodProgress $period): bool => RegularityGoalPeriodStatusEnum::MET === $period->status,
        ));

        return new RegularityGoalProgress(
            $goal,
            $periods,
            $metCount,
            \count($periods),
            $goal->lastDay() < $today,
            $metCount === \count($periods),
        );
    }
}
