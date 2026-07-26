<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;

/**
 * Responsabilité unique : résoudre, pour une liste d'exercices, les IDs (SVG ou UUID) des
 * groupes musculaires primaires/secondaires consommés par le JS du sélecteur d'exercices
 * (silhouette au survol/sélection, filtre par muscle).
 */
final class ExercisePrimaryMuscleIdsResolver
{
    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs SVG des muscles primaires séparés par virgule
     */
    public function resolve(array $exercises): array
    {
        return $this->resolveSvgIdsByType($exercises, MuscleTypeEnum::PRIMARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs SVG des muscles secondaires séparés par virgule
     */
    public function resolveSecondary(array $exercises): array
    {
        return $this->resolveSvgIdsByType($exercises, MuscleTypeEnum::SECONDARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs (UUID) des groupes musculaires
     *                                primaires séparés par virgule
     */
    public function resolvePrimaryMuscleGroupIds(array $exercises): array
    {
        return $this->resolveMuscleGroupIdsByType($exercises, MuscleTypeEnum::PRIMARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string> exerciseId (RFC4122) => IDs (UUID) des groupes musculaires
     *                                secondaires séparés par virgule
     */
    public function resolveSecondaryMuscleGroupIds(array $exercises): array
    {
        return $this->resolveMuscleGroupIdsByType($exercises, MuscleTypeEnum::SECONDARY);
    }

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string>
     */
    private function resolveSvgIdsByType(array $exercises, MuscleTypeEnum $type): array
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

    /**
     * @param list<Exercise> $exercises
     * @return array<string, string>
     */
    private function resolveMuscleGroupIdsByType(array $exercises, MuscleTypeEnum $type): array
    {
        $result = [];

        foreach ($exercises as $exercise) {
            $muscleGroupIds = [];

            foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
                if ($type === $exerciseMuscle->type) {
                    $muscleGroupIds[(string) $exerciseMuscle->muscleGroup->id] = true;
                }
            }

            $result[(string) $exercise->id] = implode(',', array_keys($muscleGroupIds));
        }

        return $result;
    }
}
