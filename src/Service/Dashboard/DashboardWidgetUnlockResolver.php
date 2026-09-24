<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Repository\ExerciseGoalRepository;
use App\Repository\ProfileConnectionRepository;

/**
 * Source unique de "quels widgets sont débloqués pour cet utilisateur" — partagée entre le
 * dashboard (filtre l'affichage) et les réglages (filtre les cases à cocher proposées) pour ne
 * jamais désynchroniser les deux.
 */
final readonly class DashboardWidgetUnlockResolver
{
    public function __construct(
        private ExerciseGoalRepository $exerciseGoalRepository,
        private ProfileConnectionRepository $connectionRepository,
    ) {
    }

    /**
     * @return array<string, bool> clé = DashboardWidgetEnum::value
     */
    public function resolve(User $user, DashboardState $dashboardState): array
    {
        $hasAnyGoal = [] !== $this->exerciseGoalRepository->findByOwner($user);

        return [
            DashboardWidgetEnum::SESSION->value => $dashboardState->lastWorkoutUnlocked,
            DashboardWidgetEnum::TONNAGE->value => $dashboardState->lastWorkoutUnlocked,
            DashboardWidgetEnum::MUSCLE_DISTRIBUTION->value => $dashboardState->muscleSingleUnlocked,
            DashboardWidgetEnum::REGULARITY->value => $dashboardState->regularityUnlocked,
            DashboardWidgetEnum::GOALS->value => $hasAnyGoal,
            DashboardWidgetEnum::BADGES->value => $dashboardState->lastWorkoutUnlocked,
            DashboardWidgetEnum::HEATMAP->value => $dashboardState->lastWorkoutUnlocked,
            DashboardWidgetEnum::CONNECTIONS->value => 0 < $this->connectionRepository->countAcceptedInvolving($user),
        ];
    }
}
