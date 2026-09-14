<?php

declare(strict_types=1);

namespace App\Service\Goal;

use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Repository\ExerciseSetRepository;

/**
 * Calcule l'état d'un objectif à la demande, jamais stocké : si l'utilisateur modifie ou
 * supprime la séance qui faisait atteindre la cible, un nouvel appel recalcule automatiquement
 * un objectif "en cours" — aucune synchronisation à gérer côté écriture.
 */
final readonly class GoalProgressResolver
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
    ) {
    }

    public function resolve(User $user, ExerciseGoal $goal): GoalProgress
    {
        return new GoalProgress(
            $goal,
            $this->exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $goal->exercise),
        );
    }

    /**
     * Même calcul que `resolve()` en excluant une séance précise — sert à déterminer si cette
     * séance est à l'origine d'un franchissement de seuil (voir `GoalAchievementDetector`).
     */
    public function resolveExcludingWorkout(User $user, ExerciseGoal $goal, string $excludedWorkoutId): GoalProgress
    {
        $rows = array_values(array_filter(
            $this->exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $goal->exercise),
            static fn (array $row): bool => $row['workoutId'] !== $excludedWorkoutId,
        ));

        return new GoalProgress($goal, $rows);
    }
}
