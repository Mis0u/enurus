<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;

/**
 * Écrans Séances, Tonnage et Répétitions — son nombre de séances décide aussi de l'éligibilité.
 */
final readonly class YearInReviewTotalsCalculator
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private WorkoutTonnageRepository $workoutTonnageRepository,
    ) {
    }

    public function calculate(User $user, DashboardPeriod $period): YearInReviewTotals
    {
        $workoutTotals = $this->workoutStatsRepository->findSetAndRepTotalsPerWorkout($user, $period->start, $period->end);
        $tonnageRows = $this->workoutTonnageRepository->findTonnageSeriesByUser($user, $period->start, $period->end);

        return new YearInReviewTotals(
            \count($workoutTotals),
            array_sum(array_column($workoutTotals, 'sets')),
            array_sum(array_column($workoutTotals, 'reps')),
            (float) array_sum(array_column($tonnageRows, 'tonnage')),
        );
    }
}
