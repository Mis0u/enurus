<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;

final readonly class YearInReviewTopExercisesCalculator
{
    private const int PODIUM_SIZE = 3;

    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
    ) {
    }

    /**
     * @return list<YearInReviewExercise>
     */
    public function calculate(User $user, DashboardPeriod $period): array
    {
        return array_map(
            static fn (array $row): YearInReviewExercise => new YearInReviewExercise($row['name'], $row['isPublic'], $row['workoutCount']),
            $this->workoutStatsRepository->findMostFrequentExercisesInRange($user, $period->start, $period->end, self::PODIUM_SIZE),
        );
    }
}
