<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;

final readonly class DashboardUnlockService
{
    // En dessous, les séances ne peuvent pas couvrir deux semaines : inutile de lire leurs dates.
    private const int MIN_WORKOUTS_TO_SPAN_TWO_WEEKS = 2;

    public function __construct(
        private WorkoutRepository $workoutRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
    ) {
    }

    public function getStateForUser(User $user): DashboardState
    {
        $workoutCount = $this->workoutRepository->countByUser($user);

        return new DashboardState(workoutCount: $workoutCount, trainedWeekCount: $this->trainedWeekCount($user, $workoutCount));
    }

    /**
     * Semaines calendaires (lundi → dimanche) différentes contenant au moins une séance. Moins de
     * deux séances : autant de semaines que de séances, sans requête.
     */
    private function trainedWeekCount(User $user, int $workoutCount): int
    {
        if (self::MIN_WORKOUTS_TO_SPAN_TWO_WEEKS > $workoutCount) {
            return $workoutCount;
        }

        $mondays = array_map(
            static fn (\DateTimeImmutable $date): string => $date->modify(\sprintf('-%d days', (int) $date->format('N') - 1))->format('Y-m-d'),
            $this->workoutStatsRepository->findAllPerformedDatesByUser($user),
        );

        return \count(array_unique($mondays));
    }
}
