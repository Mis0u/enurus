<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Entity\User;
use App\Repository\RegularityGoalRepository;
use App\Repository\WorkoutStatsRepository;

/**
 * Séances chargées une seule fois, puis chaque objectif calculé en mémoire.
 */
final readonly class RegularityGoalOverviewBuilder
{
    public function __construct(
        private RegularityGoalRepository $regularityGoalRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
        private RegularityGoalProgressCalculator $progressCalculator,
    ) {
    }

    public function build(User $user): RegularityGoalOverview
    {
        $goals = $this->regularityGoalRepository->findByOwnerNewestFirst($user);

        if ([] === $goals) {
            return new RegularityGoalOverview(null, []);
        }

        $today = new \DateTimeImmutable('today');
        $workoutDates = $this->workoutStatsRepository->findAllPerformedDatesByUser($user);
        $progressOf = fn (RegularityGoal $goal): RegularityGoalProgress => $this->progressCalculator->calculate($goal, $workoutDates, $today);
        $current = array_find($goals, static fn (RegularityGoal $goal): bool => $goal->isOngoingOn($today));

        return new RegularityGoalOverview(
            null !== $current ? $progressOf($current) : null,
            array_values(array_map($progressOf, array_filter($goals, static fn (RegularityGoal $goal): bool => ! $goal->isOngoingOn($today)))),
        );
    }
}
