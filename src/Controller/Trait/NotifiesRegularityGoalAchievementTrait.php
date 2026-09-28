<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use App\Entity\User;
use App\Service\RegularityGoal\RegularityGoalAchievementDetector;

/**
 * Partagé entre `WorkoutController` (création) et `WorkoutEditController` (édition) : après le flush,
 * un objectif de régularité que la séance vient de compléter est célébré via le flash bag, lu par
 * `partials/_popup/_regularity_goal_achieved.html.twig` sur la page de destination — même mécanisme
 * que `NotifiesGoalAchievementTrait`. Les paramètres bruts sont transmis, la vue les traduit.
 */
trait NotifiesRegularityGoalAchievementTrait
{
    private function notifyRegularityGoalAchievements(User $user, RegularityGoalAchievementDetector $detector): void
    {
        foreach ($detector->detectNewlyAchieved($user) as $goal) {
            $this->addFlash('regularity_goal_achieved', [
                'sessions' => $goal->sessionsPerPeriod,
                'period' => $goal->period->value,
                'weeks' => $goal->duration->value,
            ]);
        }
    }
}
