<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use App\Entity\User;
use App\Entity\Workout;
use App\Service\Goal\GoalAchievementDetector;
use App\Service\Goal\GoalCardFormatter;

/**
 * Partagé entre `WorkoutController` (création) et `WorkoutEditController` (édition) : les deux
 * flux doivent notifier un objectif nouvellement atteint après le flush, via un flash bag lu par
 * `partials/_popup/_goal_achieved.html.twig` sur la page de destination.
 */
trait NotifiesGoalAchievementTrait
{
    private function notifyGoalAchievements(
        User $user,
        Workout $workout,
        GoalAchievementDetector $goalAchievementDetector,
        GoalCardFormatter $goalCardFormatter,
    ): void {
        foreach ($goalAchievementDetector->detectNewlyAchieved($user, $workout) as $goal) {
            $this->addFlash('goal_achieved', [
                'exerciseName' => $goalCardFormatter->exerciseName($goal->exercise),
                'targetLabel' => $goalCardFormatter->formatTarget($goal, $user),
            ]);
        }
    }
}
