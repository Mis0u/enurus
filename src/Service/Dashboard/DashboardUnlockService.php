<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;

final readonly class DashboardUnlockService
{
    public function __construct(
        private WorkoutRepository $workoutRepository,
    ) {
    }

    public function getStateForUser(User $user): DashboardState
    {
        $workoutCount = $this->workoutRepository->countByUser($user);

        return new DashboardState(workoutCount: $workoutCount);
    }
}
