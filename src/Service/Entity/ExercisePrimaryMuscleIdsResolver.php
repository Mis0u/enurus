<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;

/**
 * Responsabilité unique : résoudre, pour une liste d'exercices, les IDs SVG des groupes
 * musculaires (primaires et secondaires) consommés par le JS du sélecteur d'exercices
 * (silhouette au survol/sélection).
 */
final class ExercisePrimaryMuscleIdsResolver
{
    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs SVG des muscles primaires séparés par virgule
     */
    public function resolve(array $exercises): array
    {
        return $this->resolveByType($exercises, MuscleTypeEnum::PRIMARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs SVG des muscles secondaires séparés par virgule
     */
    public function resolveSecondary(array $exercises): array
    {
        return $this->resolveByType($exercises, MuscleTypeEnum::SECONDARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string>
     */
    private function resolveByType(array $exercises, MuscleTypeEnum $type): array
    {
        $result = [];

        foreach ($exercises as $exercise) {
            $svgIds = [];

            foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
                if ($type === $exerciseMuscle->type) {
                    array_push($svgIds, ...$exerciseMuscle->muscleGroup->svgIds);
                }
            }

            $result[(string) $exercise->id] = implode(',', $svgIds);
        }

        return $result;
    }
}
