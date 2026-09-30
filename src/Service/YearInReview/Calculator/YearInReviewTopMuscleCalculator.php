<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Snapshot\YearInReviewMuscle;

/**
 * Classement sur les séries où le muscle est **primaire** : les épaules, secondaires de presque
 * tous les mouvements de poussée, gagneraient sinon chez tout le monde.
 */
final readonly class YearInReviewTopMuscleCalculator
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private WorkoutMuscleRepository $workoutMuscleRepository,
    ) {
    }

    public function calculate(User $user, DashboardPeriod $period): ?YearInReviewMuscle
    {
        $workoutIds = $this->workoutStatsRepository->findIdsByUserAndDateRange($user, $period->start, $period->end);
        $topMuscle = null;

        foreach ($this->workoutMuscleRepository->findMuscleGroupSetCountsByWorkoutIds($workoutIds) as $muscle) {
            if ($muscle['primarySets'] > ($topMuscle->setCount ?? 0)) {
                $topMuscle = new YearInReviewMuscle($muscle['id'], $muscle['primarySets']);
            }
        }

        return $topMuscle;
    }
}
