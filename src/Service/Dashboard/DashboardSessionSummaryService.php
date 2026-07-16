<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\Workout;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;

/**
 * Résumé du widget "Séance" (dernier workout) : totaux sets/reps et groupes musculaires
 * sollicités — calculé depuis les collections déjà chargées par
 * WorkoutRepository::findLatestByUser(), sans requête supplémentaire.
 */
final readonly class DashboardSessionSummaryService
{
    /**
     * @return array{totalSets: int, totalReps: int, primarySvgIds: list<string>, secondarySvgIds: list<string>}
     */
    public function summarize(Workout $workout): array
    {
        $totalSets = 0;
        $totalReps = 0;

        foreach ($workout->workoutExercises as $workoutExercise) {
            $totalSets += $workoutExercise->exerciseSets->count();

            foreach ($workoutExercise->exerciseSets as $set) {
                $totalReps += $set->reps;
            }
        }

        [$primary, $secondary] = $this->collectSvgIds($workout);

        return [
            'totalSets' => $totalSets,
            'totalReps' => $totalReps,
            'primarySvgIds' => $primary,
            'secondarySvgIds' => $secondary,
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function collectSvgIds(Workout $workout): array
    {
        $primary = [];
        $secondary = [];

        foreach ($workout->workoutExercises as $workoutExercise) {
            foreach ($workoutExercise->exercise->exerciseMuscles as $exerciseMuscle) {
                if (MuscleTypeEnum::PRIMARY === $exerciseMuscle->type) {
                    $primary = array_merge($primary, $exerciseMuscle->muscleGroup->svgIds);
                } else {
                    $secondary = array_merge($secondary, $exerciseMuscle->muscleGroup->svgIds);
                }
            }
        }

        $primary = array_values(array_unique($primary));
        $secondary = array_values(array_diff(array_unique($secondary), $primary));

        return [$primary, $secondary];
    }
}
