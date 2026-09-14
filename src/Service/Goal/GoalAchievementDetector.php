<?php

declare(strict_types=1);

namespace App\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\ExerciseGoalRepository;

/**
 * Détecte les objectifs franchis par l'enregistrement/modification d'une séance précise —
 * comparaison "atteint sans cette séance" vs "atteint avec", pour ne déclencher la pop de
 * félicitations que sur une transition réelle, jamais à chaque simple affichage.
 */
final readonly class GoalAchievementDetector
{
    public function __construct(
        private ExerciseGoalRepository $exerciseGoalRepository,
        private GoalProgressResolver $goalProgressResolver,
    ) {
    }

    /**
     * @return array<ExerciseGoal>
     */
    public function detectNewlyAchieved(User $user, Workout $workout): array
    {
        if (null === $workout->id) {
            throw new \LogicException('Cannot detect goal achievement for a workout without a persisted id.');
        }

        $workoutId = $workout->id->toRfc4122();
        $newlyAchieved = [];

        foreach ($this->distinctExercises($workout) as $exercise) {
            foreach ($this->exerciseGoalRepository->findAllByOwnerAndExercise($user, $exercise) as $goal) {
                // Déjà atteint avant cette séance (historique) : jamais une transition à notifier.
                if ($this->goalProgressResolver->resolveExcludingWorkout($user, $goal, $workoutId)->achieved) {
                    continue;
                }

                if ($this->goalProgressResolver->resolve($user, $goal)->achieved) {
                    $newlyAchieved[] = $goal;
                }
            }
        }

        return $newlyAchieved;
    }

    /**
     * @return array<Exercise>
     */
    private function distinctExercises(Workout $workout): array
    {
        $exercises = [];

        foreach ($workout->workoutExercises as $workoutExercise) {
            $exercises[(string) $workoutExercise->exercise->id] = $workoutExercise->exercise;
        }

        return array_values($exercises);
    }
}
