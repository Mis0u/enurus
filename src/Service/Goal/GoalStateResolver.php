<?php

declare(strict_types=1);

namespace App\Service\Goal;

use App\Entity\ExerciseGoal;
use App\Entity\User;

/**
 * Sépare un ensemble d'`ExerciseGoal` (potentiellement plusieurs par exercice, cf.
 * `ExerciseGoal` — l'historique des objectifs atteints n'est jamais purgé) en objectifs en
 * cours / atteints, et détermine l'objectif actif d'un exercice donné. Partagé entre
 * `DashboardGoalService` (tous exercices confondus) et `ExerciseHistoryController` /
 * `ExerciseGoalSaveController` (un seul exercice) pour ne pas dupliquer cette logique.
 */
final readonly class GoalStateResolver
{
    public function __construct(
        private GoalProgressResolver $goalProgressResolver,
    ) {
    }

    /**
     * @param array<ExerciseGoal> $goals
     */
    public function resolveState(User $user, array $goals): GoalState
    {
        $current = [];
        $achieved = [];

        foreach ($goals as $goal) {
            $progress = $this->goalProgressResolver->resolve($user, $goal);

            if ($progress->achieved) {
                $achieved[] = $progress;
            } else {
                $current[] = $progress;
            }
        }

        usort($achieved, static fn (GoalProgress $a, GoalProgress $b): int => self::sortKey($b) <=> self::sortKey($a));
        usort($current, static fn (GoalProgress $a, GoalProgress $b): int => $b->goal->createdAt->getTimestamp() <=> $a->goal->createdAt->getTimestamp());

        return new GoalState($current, $achieved);
    }

    /**
     * L'objectif actif d'un exercice est le plus récent non atteint — au plus un seul existe
     * normalement par construction applicative (voir `ExerciseGoalSaveController`), jamais
     * garanti par une contrainte de base.
     *
     * @param array<ExerciseGoal> $goals objectifs d'un seul et même exercice
     */
    public function findActiveGoal(User $user, array $goals): ?ExerciseGoal
    {
        return $this->resolveState($user, $goals)->current[0]->goal ?? null;
    }

    private static function sortKey(GoalProgress $progress): int
    {
        return ($progress->achievedAt ?? $progress->goal->createdAt)->getTimestamp();
    }
}
