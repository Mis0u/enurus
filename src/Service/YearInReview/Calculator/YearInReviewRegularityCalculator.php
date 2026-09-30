<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WeeklyStreakCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;

/**
 * Plus longue série de semaines **dans la période** (même règle que le widget Régularité, deloads
 * compris) : une série commencée fin décembre de l'année précédente n'y est comptée qu'à partir du
 * 1er janvier.
 */
final readonly class YearInReviewRegularityCalculator
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private WeeklyStreakCalculator $weeklyStreakCalculator,
    ) {
    }

    public function calculate(User $user, DashboardPeriod $period): YearInReviewRegularity
    {
        $workoutDates = $this->workoutDatesWithin($user, $period);
        $workoutCountByMonth = $this->workoutCountByMonth($workoutDates);
        $busiestMonth = $this->busiestMonth($workoutCountByMonth);

        return new YearInReviewRegularity(
            $this->weeklyStreakCalculator->longestStreak($workoutDates, $this->deloadPeriodSetService->weekKeySet($user), $period->end),
            $busiestMonth,
            $workoutCountByMonth[$busiestMonth] ?? 0,
        );
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function workoutDatesWithin(User $user, DashboardPeriod $period): array
    {
        return array_values(array_filter(
            $this->workoutStatsRepository->findAllPerformedDatesByUser($user),
            static fn (\DateTimeImmutable $performedAt): bool => $period->start <= $performedAt && $performedAt <= $period->end,
        ));
    }

    /**
     * @param list<\DateTimeImmutable> $workoutDates
     *
     * @return array<int, int> mois (1-12) => nombre de séances
     */
    private function workoutCountByMonth(array $workoutDates): array
    {
        $workoutCountByMonth = [];

        foreach ($workoutDates as $performedAt) {
            $month = (int) $performedAt->format('n');
            $workoutCountByMonth[$month] = ($workoutCountByMonth[$month] ?? 0) + 1;
        }

        return $workoutCountByMonth;
    }

    /**
     * Le premier mois de l'année l'emporte en cas d'égalité.
     *
     * @param array<int, int> $workoutCountByMonth
     *
     * @return int<1, 12>
     */
    private function busiestMonth(array $workoutCountByMonth): int
    {
        $busiestMonth = YearInReviewRegularity::FIRST_MONTH;

        foreach (range(YearInReviewRegularity::FIRST_MONTH, YearInReviewRegularity::LAST_MONTH) as $month) {
            if (($workoutCountByMonth[$month] ?? 0) > ($workoutCountByMonth[$busiestMonth] ?? 0)) {
                $busiestMonth = $month;
            }
        }

        return $busiestMonth;
    }
}
