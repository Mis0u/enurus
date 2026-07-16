<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;

/**
 * Responsabilité unique : résoudre, pour une liste d'exercices, les IDs de groupes musculaires
 * primaires consommés par le JS du sélecteur d'exercices (silhouette au survol/sélection).
 */
final class ExercisePrimaryMuscleIdsResolver
{
    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs de muscles primaires séparés par virgule
     */
    public function resolve(array $exercises): array
    {
        $result = [];

        foreach ($exercises as $exercise) {
            $primaryIds = [];

            foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
                if (MuscleTypeEnum::PRIMARY === $exerciseMuscle->type) {
                    $primaryIds[] = (string) $exerciseMuscle->muscleGroup->id;
                }
            }

            $result[(string) $exercise->id] = implode(',', $primaryIds);
        }

        return $result;
    }
}
