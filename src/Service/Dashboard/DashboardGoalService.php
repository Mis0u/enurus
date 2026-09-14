<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\ExerciseGoalRepository;
use App\Service\Goal\GoalState;
use App\Service\Goal\GoalStateResolver;

final readonly class DashboardGoalService
{
    public function __construct(
        private ExerciseGoalRepository $exerciseGoalRepository,
        private GoalStateResolver $goalStateResolver,
    ) {
    }

    public function getStateForUser(User $user): GoalState
    {
        return $this->goalStateResolver->resolveState($user, $this->exerciseGoalRepository->findByOwner($user));
    }
}
